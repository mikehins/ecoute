<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use MikeHins\Ecoute\Events\CaptureFailed;
use MikeHins\Ecoute\Events\CaptureProcessed;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Notifications\IssueCapturedNotification;
use MikeHins\Ecoute\Services\EcouteTransformer;
use MikeHins\Ecoute\Services\GitHubProvider;
use MikeHins\Ecoute\Services\Sanitizer;
use Throwable;

final class ProcessEcouteCapture implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum number of attempts before the job is marked as failed. */
    public int $tries;

    /** @var int Base backoff in seconds between retries (exponential). */
    public int $backoff;

    public function __construct(
        public readonly string $captureId,
        public readonly ?string $githubTemplate = null,
        public readonly ?string $bodyOverride = null,
        public readonly ?string $titleOverride = null,
    ) {
        $this->tries = (int) config('ecoute.queue.retries', 3);
        $this->backoff = (int) config('ecoute.queue.backoff', 60);
    }

    /**
     * Execute the job: transform the capture via AI and persist the result.
     *
     * @param  EcouteTransformer  $transformer  The service that calls the AI provider.
     */
    public function handle(EcouteTransformer $transformer, GitHubProvider $github): void
    {
        $capture = EcouteCapture::find($this->captureId);

        if (! $capture) {
            Log::warning('Ecoute: capture not found', ['capture_id' => $this->captureId]);

            return;
        }

        if ($capture->user === null) {
            $capture->update([
                'status' => 'failed',
                'failure_reason' => 'Submitting user is no longer available for authorization.',
            ]);

            return;
        }

        if (! Gate::forUser($capture->user)->check('ecoute-admin')) {
            $capture->update([
                'status' => 'failed',
                'failure_reason' => 'User no longer has admin privileges.',
            ]);

            return;
        }

        $capture->update(['status' => 'processing']);

        try {
            $recordingPath = $capture->recording_path;
            if ($recordingPath && config('ecoute.whisper.enabled', true)) {
                $transcription = $this->transcribeRecording($capture);
                if ($transcription) {
                    $capture->update([
                        'user_prompt' => $capture->user_prompt."\n\n**Voice Transcription:**\n".$transcription,
                    ]);
                }
            }

            $result = $transformer->transform($capture);

            $githubIssueUrl = null;

            if (config('ecoute.github.enabled') && config('ecoute.github.token')) {
                // Defense-in-depth: re-validate the requested template against the configured whitelist.
                $whitelist = (array) config('ecoute.github.template_whitelist', []);
                $template = $this->githubTemplate;
                if (count($whitelist) > 0 && $template !== null && ! in_array($template, $whitelist, true)) {
                    Log::warning('Ecoute: requested GitHub template not in whitelist, ignoring', ['capture_id' => $this->captureId, 'template' => $template]);
                    $template = null;
                }

                $githubIssueUrl = $github->createIssue($capture, $result['response'], $template, $this->bodyOverride, $this->titleOverride);
            }

            $capture->update([
                'ai_response' => $result['response'],
                'prompt_version' => $result['prompt_version'],
                'status' => 'completed',
                'processed_at' => now(),
                'github_issue_url' => $githubIssueUrl,
            ]);

            CaptureProcessed::dispatch($capture, $result['response']);

            if ($notifiable = config('ecoute.notifications.mail_to')) {
                Notification::route('mail', $notifiable)
                    ->notify(new IssueCapturedNotification($capture));
            }
        } catch (Throwable $e) {
            // Sanitize error message before logging or persisting to avoid leaking provider
            // responses or secrets. Store a truncated snippet (max 250 chars).
            $rawMessage = (string) $e->getMessage();
            $sanitized = Sanitizer::sanitizeExcerpt($rawMessage, 250);

            Log::error('Ecoute: capture processing failed', [
                'capture_id' => $this->captureId,
                'error' => $sanitized,
                'exception' => get_class($e),
            ]);

            $capture->update([
                'status' => 'failed',
                'failure_reason' => $sanitized,
            ]);

            CaptureFailed::dispatch($capture, $e);

            throw $e; // Re-throw so the queue retries
        }
    }

    /**
     * Transcribe the audio track of the recorded video using OpenAI Whisper API.
     */
    private function transcribeRecording(EcouteCapture $capture): ?string
    {
        $disk = $capture->recording_disk ?? config('ecoute.screenshot.disk', 'public');
        $path = $capture->recording_path;

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return null;
            }

            $fileContent = Storage::disk($disk)->get($path);

            // Whisper API key falls back to the configured OpenAI API key
            $apiKey = config('ecoute.whisper.api_key')
                ?? config('ecoute.providers.openai.api_key')
                ?? config('ecoute.openai.api_key'); // check multiple locations

            if (! $apiKey) {
                Log::warning('Ecoute: Whisper transcription skipped. No OpenAI API key configured.');

                return null;
            }

            $response = Http::withToken($apiKey)
                ->attach('file', $fileContent, basename($path))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                ]);

            if ($response->successful()) {
                return $response->json('text');
            }

            Log::warning('Ecoute: Whisper transcription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (Throwable $e) {
            Log::error('Ecoute: Whisper transcription exception', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
