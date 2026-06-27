<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
            $result = $transformer->transform($capture);

            $githubIssueUrl = null;
            $githubPrUrl = null;

            if (config('ecoute.github.enabled') && config('ecoute.github.token')) {
                // Defense-in-depth: re-validate the requested template against the configured whitelist.
                $whitelist = (array) config('ecoute.github.template_whitelist', []);
                $template = $this->githubTemplate;
                if (count($whitelist) > 0 && $template !== null && ! in_array($template, $whitelist, true)) {
                    Log::warning('Ecoute: requested GitHub template not in whitelist, ignoring', ['capture_id' => $this->captureId, 'template' => $template]);
                    $template = null;
                }

                $githubIssueUrl = $github->createIssue($capture, $result['response'], $template, $this->bodyOverride, $this->titleOverride);

                if (config('ecoute.github.auto_pr.enabled')) {
                    Log::info('Ecoute: auto-pr enabled, checking code_suggestion', [
                        'capture_id' => $this->captureId,
                        'has_code_suggestion' => ! empty($result['response']['code_suggestion']),
                    ]);
                    $githubPrUrl = $github->createPullRequest($capture, $result['response']);
                    if ($githubPrUrl) {
                        Log::info('Ecoute: auto-pr created', ['capture_id' => $this->captureId, 'pr_url' => $githubPrUrl]);
                    }
                }
            }

            $capture->update([
                'ai_response' => $result['response'],
                'prompt_version' => $result['prompt_version'],
                'status' => 'completed',
                'processed_at' => now(),
                'github_issue_url' => $githubIssueUrl,
                'github_pr_url' => $githubPrUrl,
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
}
