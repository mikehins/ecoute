<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MikeHins\Ecoute\Http\Requests\StoreCaptureRequest;
use MikeHins\Ecoute\Jobs\ProcessEcouteCapture;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\EcouteTransformer;
use MikeHins\Ecoute\Services\GitHubProvider;
use MikeHins\Ecoute\Services\HtmlSanitizer;

final class EcouteController extends Controller
{
    public function __construct(
        private readonly EcouteTransformer $transformer,
        private readonly GitHubProvider $githubProvider,
    ) {}

    /**
     * Return available GitHub issue templates for the configured repository.
     * Results are cached for one hour to avoid repeated API calls.
     */
    public function templates(): JsonResponse
    {
        if (! config('ecoute.github.enabled')) {
            return response()->json([]);
        }

        $templates = Cache::remember('ecoute:github_templates', 3600, function () {
            return $this->githubProvider->listTemplates();
        });

        return response()->json($templates);
    }

    /**
     * Run the AI transformer on an unsaved capture and return a formatted preview
     * of the GitHub issue body. No capture record is created.
     */
    public function preview(StoreCaptureRequest $request): JsonResponse
    {
        $capture = new EcouteCapture([
            'user_id' => $request->user()?->id,
            'element_selector' => $request->element_selector,
            'parent_selector' => $request->parent_selector,
            'element_html' => $request->element_html,
            'parent_html' => $request->parent_html,
            'attributes' => $request->attributes ?? [],
            'nearby_text' => $request->nearby_text,
            'user_prompt' => $request->user_prompt,
            'interaction' => $request->interaction,
            'deduplication_hash' => $request->deduplication_hash,
        ]);

        $result = $this->transformer->transform($capture);
        $aiResponse = $result['response'];

        if (config('ecoute.github.enabled') && config('ecoute.github.token')) {
            ['body' => $body, 'sections' => $sections] = $this->githubProvider->formatBody($capture, $aiResponse, $request->template);
        } else {
            $body = "**{$aiResponse['description']}**\n\n{$aiResponse['suggested_fix']}";
            $sections = [];
        }

        return response()->json([
            'title' => $aiResponse['title'],
            'body' => $body,
            'sections' => $sections,
        ]);
    }

    /**
     * Submit a new capture for asynchronous AI processing.
     *
     * @param  StoreCaptureRequest  $request  The validated capture request.
     * @param  HtmlSanitizer  $sanitizer  The service used to strip unsafe HTML.
     * @return JsonResponse Returns 202 Accepted on success, or 200 OK if deduplicated.
     */
    public function capture(StoreCaptureRequest $request, HtmlSanitizer $sanitizer): JsonResponse
    {
        $hash = $request->deduplication_hash;
        $windowHours = (int) config('ecoute.deduplication.window_hours', 24);
        $userId = $request->user()?->getAuthIdentifier();

        if (config('ecoute.deduplication.enabled')) {
            $duplicate = EcouteCapture::query()
                ->where('user_id', $userId)
                ->where('deduplication_hash', $hash)
                ->where('created_at', '>=', now()->subHours($windowHours))
                ->latest()
                ->first();

            if ($duplicate) {
                return response()->json([
                    'capture_id' => $duplicate->id,
                    'deduplicated' => true,
                    'status' => $duplicate->status,
                ]);
            }
        }

        [$screenshotPath, $screenshotDisk] = $this->storeScreenshot($request->screenshot);
        [$recordingPath, $recordingDisk] = $this->storeRecording($request->recording);

        $capture = EcouteCapture::create([
            'user_id' => $userId,
            'element_selector' => $request->element_selector,
            'parent_selector' => $request->parent_selector,
            'element_html' => $sanitizer->sanitize($request->element_html),
            'parent_html' => $request->parent_html ? $sanitizer->sanitize($request->parent_html) : null,
            'attributes' => $request->attributes,
            'nearby_text' => $request->nearby_text,
            'diagnostics' => $request->diagnostics,
            'user_prompt' => $request->user_prompt,
            'interaction' => $request->interaction,
            'deduplication_hash' => $hash,
            'screenshot_path' => $screenshotPath,
            'screenshot_disk' => $screenshotDisk,
            'recording_path' => $recordingPath,
            'recording_disk' => $recordingDisk,
            'status' => 'pending',
        ]);

        // Dispatch the processing job onto the configured connection/queue so hosts
        // can control where background work runs (sync, database, redis, etc.).
        $connection = (string) config('ecoute.queue.connection', 'default');
        $queueName = config('ecoute.queue.name');

        $dispatch = ProcessEcouteCapture::dispatch($capture->id, $request->template, $request->body_override, $request->title_override)
            ->onConnection($connection);

        if (! empty($queueName)) {
            $dispatch->onQueue((string) $queueName);
        }

        $issueUrl = config('ecoute.issue_url')
            ? str_replace('{id}', $capture->id, config('ecoute.issue_url'))
            : null;

        return response()->json([
            'capture_id' => $capture->id,
            'issue_url' => $issueUrl,
        ], 202);
    }

    /**
     * Decode and persist a base64 screenshot.
     * Always extracts the raw base64 for direct GitHub upload.
     * Also saves to disk when storage is set to 'disk'.
     * Returns [path, disk] — any element may be null.
     *
     * @return array{string|null, string|null}
     */
    private function storeScreenshot(?string $base64): array
    {
        if (! $base64) {
            return [null, null];
        }

        $rawBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);

        if (! $rawBase64) {
            return [null, null];
        }

        if (config('ecoute.screenshot.storage') !== 'none') {
            $data = base64_decode($rawBase64, true);

            if ($data !== false && $data !== '') {
                $disk = (string) config('ecoute.screenshot.disk', 'public');
                $path = 'ecoute/screenshots/'.now()->format('Y/m/d').'/'.uniqid('', true).'.jpg';
                Storage::disk($disk)->put($path, $data);

                return [$path, $disk];
            }
        }

        return [null, null];
    }

    /**
     * Decode and persist a base64 webm screen recording.
     * Returns [path, disk] — any element may be null.
     *
     * @return array{string|null, string|null}
     */
    private function storeRecording(?string $base64): array
    {
        if (! $base64 || config('ecoute.recording.storage') !== 'disk') {
            return [null, null];
        }

        if (preg_match('#^data:video/(webm);base64,(.+)$#', (string) $base64, $m)) {
            $ext = $m[1];
            $decoded = base64_decode($m[2], true);
            if ($decoded !== false) {
                $disk = (string) config('ecoute.recording.disk', 'public');
                $filename = 'ecoute/recordings/'.Str::uuid()->toString().'.'.$ext;
                Storage::disk($disk)->put($filename, $decoded, 'private');
                $recordingDisk = $disk;
                $recordingPath = $filename;

                return [$recordingPath, $recordingDisk];
            }
        }

        return [null, null];
    }
}
