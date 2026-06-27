<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use MikeHins\Ecoute\Models\EcouteCapture;

final class IssueCapturedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly EcouteCapture $capture) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return config('ecoute.notifications.channels', ['mail']);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $aiResponse = $this->capture->ai_response ?? [];
        $title = $aiResponse['title'] ?? 'New Issue Captured';
        $type = $aiResponse['type'] ?? 'unknown';
        $description = $aiResponse['description'] ?? 'No description available.';
        $suggestedFix = $aiResponse['suggested_fix'] ?? 'No suggestion provided.';

        $interaction = $this->capture->interaction ?? [];
        $url = $interaction['url'] ?? 'Unknown URL';
        $pageTitle = $interaction['page_title'] ?? 'Unknown Page';

        return (new MailMessage)
            ->subject("[Ecoute] {$title}")
            ->greeting('New Issue Captured')
            ->line("**Type:** {$type}")
            ->line("**Page:** {$pageTitle}")
            ->line("**URL:** {$url}")
            ->line('')
            ->line('**Description:**')
            ->line($description)
            ->line('')
            ->line('**Suggested Fix:**')
            ->line($suggestedFix)
            ->line('')
            ->line('**User Prompt:**')
            ->line($this->capture->user_prompt)
            ->when($this->capture->github_issue_url, fn (MailMessage $mail) => $mail
                ->line('')
                ->action('View on GitHub', $this->capture->github_issue_url)
            )
            ->when($this->capture->github_pr_url, fn (MailMessage $mail) => $mail
                ->action('View Pull Request', $this->capture->github_pr_url)
            );
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'capture_id' => $this->capture->id,
            'ai_response' => $this->capture->ai_response,
        ];
    }
}
