<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;

final class EcouteCapture extends Model
{
    use HasUuids;

    /** @var string */
    protected $table = 'ecoute_captures';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'element_selector',
        'parent_selector',
        'fallback_selectors',
        'element_html',
        'parent_html',
        'attributes',
        'nearby_text',
        'user_prompt',
        'interaction',
        'deduplication_hash',
        'screenshot_path',
        'screenshot_disk',
        'ai_response',
        'prompt_version',
        'status',
        'failure_reason',
        'processed_at',
        'github_issue_url',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fallback_selectors' => 'array',
        'attributes' => 'array',
        'nearby_text' => 'array',
        'interaction' => 'array',
        'ai_response' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the user that submitted this capture.
     *
     * @return BelongsTo<User, EcouteCapture>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class));
    }

    /**
     * Determine whether this capture has been successfully processed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Determine whether this capture is in a failed state.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Determine whether this capture is still pending or being processed.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}
