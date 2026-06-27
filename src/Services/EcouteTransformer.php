<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Exceptions\TransformerException;
use MikeHins\Ecoute\Models\EcouteCapture;

final class EcouteTransformer
{
    /** @var array<string, string> In-memory prompt cache keyed by version. */
    private static array $promptCache = [];

    public function __construct(
        private readonly AIProviderInterface $aiProvider,
        private readonly CodeResolver $codeResolver,
    ) {}

    /**
     * Transform a capture into a structured AI issue response.
     *
     * @param  EcouteCapture  $capture  The capture record to process.
     * @return array{
     *     response: array{title: string, description: string, type: string, suggested_fix: string, code_suggestion: string|null},
     *     prompt_version: string
     * }
     *
     * @throws TransformerException When required fields are missing or AI returns invalid JSON.
     */
    public function transform(EcouteCapture $capture): array
    {
        $this->validateCapture($capture);

        $promptVersion = 'v1';
        $cacheKey = "ecoute:ai_response:{$capture->deduplication_hash}";

        // Return cached AI response if available (24-hour TTL)
        if (config('ecoute.deduplication.enabled')) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return [
                    'response' => $cached,
                    'prompt_version' => $promptVersion,
                ];
            }
        }

        $elementHtml = $capture->element_html;
        $parentHtml = $capture->parent_html;

        $sourceFiles = $this->codeResolver->resolve($capture);
        $sourceCode = $this->codeResolver->format($sourceFiles);

        $context = $this->buildContext($capture, $elementHtml, $parentHtml);
        $prompt = $this->buildPrompt($context, $sourceCode, $promptVersion);

        $frames = $this->extractVideoFrames($capture);

        $temperature = (float) config('ecoute.ai.temperature', 0.0);
        if (empty($frames)) {
            $result = $this->aiProvider->complete($prompt, $temperature);
        } else {
            $result = $this->aiProvider->complete($prompt, $temperature, $frames);
        }

        $response = $this->parseAiResponse($result['content']);

        if (config('ecoute.deduplication.enabled')) {
            $windowHours = (int) config('ecoute.deduplication.window_hours', 24);
            Cache::put($cacheKey, $response, now()->addHours($windowHours));
        }

        return [
            'response' => $response,
            'prompt_version' => $promptVersion,
        ];
    }

    /**
     * Validate that the capture has all required fields.
     *
     * @throws TransformerException
     */
    private function validateCapture(EcouteCapture $capture): void
    {
        if (empty($capture->element_html)) {
            throw new TransformerException('Capture is missing element_html.');
        }

        if (empty($capture->user_prompt)) {
            throw new TransformerException('Capture is missing user_prompt.');
        }

        if (empty($capture->interaction)) {
            throw new TransformerException('Capture is missing interaction data.');
        }
    }

    /**
     * Build the capture context string for the AI prompt.
     *
     * @param  EcouteCapture  $capture  The capture record.
     * @param  string  $elementHtml  Sanitized element HTML.
     * @param  string|null  $parentHtml  Sanitized parent HTML.
     */
    private function buildContext(EcouteCapture $capture, string $elementHtml, ?string $parentHtml): string
    {
        $interaction = $capture->interaction ?? [];
        $nearbyText = implode("\n", array_map(
            fn (string $text): string => $this->maskPii($text),
            $capture->nearby_text ?? []
        ));
        $userPrompt = $this->maskPii($capture->user_prompt);

        $lines = [
            "Page URL: {$interaction['url']}",
            "Page Title: {$interaction['page_title']}",
            "Timestamp: {$interaction['timestamp']}",
            '',
            "Element Selector: {$capture->element_selector}",
            'Element HTML:',
            $this->maskPii($elementHtml),
        ];

        if ($parentHtml) {
            $lines[] = '';
            $lines[] = "Parent Selector: {$capture->parent_selector}";
            $lines[] = 'Parent HTML:';
            $lines[] = $this->maskPii($parentHtml);
        }

        if (! empty($capture->attributes)) {
            $lines[] = '';
            $lines[] = 'Element Data Attributes:';
            foreach ($capture->attributes as $key => $value) {
                $lines[] = '  '.$key.': '.$this->maskPii((string) $value);
            }
        }

        if ($nearbyText) {
            $lines[] = '';
            $lines[] = 'Nearby Text:';
            $lines[] = $nearbyText;
        }

        $lines[] = '';
        $lines[] = 'User Feedback:';
        $lines[] = $userPrompt;

        if (config('ecoute.diagnostics.enabled') && ! empty($capture->diagnostics)) {
            $lines[] = '';
            $lines[] = '## Browser Diagnostics';
            $lines[] = '';

            if (! empty($capture->diagnostics['console'])) {
                $lines[] = '### Console Logs';
                foreach ($capture->diagnostics['console'] as $entry) {
                    $level = $entry['level'] ?? 'log';
                    $args = isset($entry['args']) ? implode(' ', array_map(fn ($a) => is_string($a) ? $this->maskPii($a) : json_encode($a), $entry['args'])) : '';
                    $lines[] = '  ['.$level.'] '.$args;
                }
                $lines[] = '';
            }

            if (! empty($capture->diagnostics['network'])) {
                $lines[] = '### Network Requests';
                $lines[] = '';
                $lines[] = '| Method | URL | Status | Duration |';
                $lines[] = '| --- | --- | --- | --- |';
                foreach ($capture->diagnostics['network'] as $req) {
                    $url = $req['url'] ?? '';
                    $method = $req['method'] ?? 'GET';
                    $status = $req['status'] ?? 0;
                    $duration = isset($req['duration']) ? round((float) $req['duration'], 0).'ms' : '?';
                    $lines[] = '| '.$method.' | '.$this->maskPii($url).' | '.$status.' | '.$duration.' |';
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Load the prompt template for the given version and inject the context.
     *
     * @param  string  $context  The formatted capture context.
     * @param  string  $promptVersion  The prompt version to load (e.g. "v1").
     * @return string The complete prompt.
     *
     * @throws TransformerException When the prompt template file is not found.
     */
    private function buildPrompt(string $context, string $sourceCode, string $promptVersion): string
    {
        $template = $this->loadPromptTemplate($promptVersion);

        return str_replace(
            ['{{LOCALE}}', '{{CAPTURE_CONTEXT}}', '{{SOURCE_CODE}}'],
            [config('ecoute.locale', 'en'), $context, $sourceCode],
            $template
        );
    }

    /**
     * Load a prompt template from disk, using an in-memory cache per version.
     *
     * @param  string  $version  The prompt version directory (e.g. "v1").
     * @return string The raw template content.
     *
     * @throws TransformerException When the template file does not exist.
     */
    private function loadPromptTemplate(string $version): string
    {
        if (isset(self::$promptCache[$version])) {
            return self::$promptCache[$version];
        }

        $path = __DIR__."/../../resources/prompts/{$version}/issue_transformer.txt";

        if (! file_exists($path)) {
            throw new TransformerException("Prompt template not found for version: {$version}");
        }

        $template = file_get_contents($path);

        if ($template === false) {
            throw new TransformerException("Failed to read prompt template for version: {$version}");
        }

        self::$promptCache[$version] = $template;

        return $template;
    }

    /**
     * Parse and validate the AI response JSON.
     *
     * @param  string  $content  Raw AI response content.
     * @return array{title: string, description: string, type: string, suggested_fix: string, code_suggestion: string|null}
     *
     * @throws TransformerException When the AI response is not valid JSON or missing required keys.
     */
    private function parseAiResponse(string $content): array
    {
        // Strip markdown fences if the model returned them despite instructions
        $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);
        $content = mb_trim((string) $content);

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new TransformerException(
                'AI returned invalid JSON: '.mb_substr($content, 0, 200)
            );
        }

        $required = ['title', 'description', 'type', 'suggested_fix'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new TransformerException("AI response missing required key: {$key}");
            }
        }

        $codeSuggestion = isset($decoded['code_suggestion']) && is_string($decoded['code_suggestion']) && $decoded['code_suggestion'] !== ''
            ? $decoded['code_suggestion']
            : null;

        return [
            'title' => (string) $decoded['title'],
            'description' => (string) $decoded['description'],
            'type' => (string) $decoded['type'],
            'suggested_fix' => (string) $decoded['suggested_fix'],
            'code_suggestion' => $codeSuggestion,
        ];
    }

    /**
     * Redact PII patterns (email addresses, credit card numbers, SSNs) from a string.
     *
     * @param  string  $text  The input text to scan.
     * @return string The text with PII replaced by [REDACTED].
     */
    private function maskPii(string $text): string
    {
        // Email addresses
        $text = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[REDACTED]', $text);

        // Credit card numbers (13–19 digits, optionally space/dash separated)
        $text = preg_replace('/\b(?:\d[ \-]?){13,19}\b/', '[REDACTED]', (string) $text);

        // US Social Security Numbers (XXX-XX-XXXX or XXXXXXXXX)
        $text = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[REDACTED]', (string) $text);
        $text = preg_replace('/\b\d{9}\b/', '[REDACTED]', (string) $text);

        return (string) $text;
    }

    /**
     * Extract frames from a WebM video using ffmpeg and return them as base64 strings.
     *
     * @return list<string>
     */
    private function extractVideoFrames(EcouteCapture $capture): array
    {
        if (! config('ecoute.whisper.video_analysis', true)) {
            return [];
        }

        $disk = $capture->recording_disk ?? config('ecoute.screenshot.disk', 'public');
        $path = $capture->recording_path;

        if (! $path) {
            return [];
        }

        try {
            $diskInstance = Storage::disk($disk);
            if (! $diskInstance->exists($path)) {
                return [];
            }

            $localPath = null;
            $isTempFile = false;

            if (method_exists($diskInstance, 'path')) {
                try {
                    $localPath = $diskInstance->path($path);
                } catch (\Throwable $_) {
                }
            }

            if (! $localPath || ! file_exists($localPath)) {
                $tempPath = tempnam(sys_get_temp_dir(), 'ecoute_rec_');
                file_put_contents($tempPath, $diskInstance->get($path));
                $localPath = $tempPath;
                $isTempFile = true;
            }

            $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ecoute_frames_'.uniqid('', true);
            mkdir($tempDir);

            $ffmpegBinary = config('ecoute.ffmpeg_path') ?? 'ffmpeg';
            $process = Process::run([
                $ffmpegBinary,
                '-y',
                '-i', $localPath,
                '-vf', 'fps=1',
                $tempDir.DIRECTORY_SEPARATOR.'frame_%03d.png',
            ]);

            if ($isTempFile) {
                @unlink($localPath);
            }

            if (! $process->successful()) {
                Log::warning('Ecoute: ffmpeg frame extraction failed', [
                    'output' => $process->output(),
                    'error' => $process->errorOutput(),
                ]);

                return [];
            }

            $frames = glob($tempDir.DIRECTORY_SEPARATOR.'frame_*.png');
            if ($frames === false) {
                return [];
            }

            sort($frames);

            $base64Frames = [];
            $limitedFrames = array_slice($frames, 0, 10);
            foreach ($limitedFrames as $frame) {
                $content = file_get_contents($frame);
                if ($content) {
                    $base64Frames[] = 'data:image/png;base64,'.base64_encode($content);
                }
                @unlink($frame);
            }

            foreach (glob($tempDir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tempDir);

            return $base64Frames;
        } catch (\Throwable $e) {
            Log::error('Ecoute: video frame extraction error', ['error' => $e->getMessage()]);
        }

        return [];
    }
}
