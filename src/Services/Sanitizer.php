<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Services;

/**
 * Small sanitizer helpers used to redact and truncate external provider messages
 * before they are logged or persisted. Kept deliberately conservative.
 */
final class Sanitizer
{
    /**
     * Redact common token patterns and return a normalized, whitespace-collapsed,
     * and truncated excerpt suitable for logs or UI messages.
     */
    public static function sanitizeExcerpt(string $text, int $limit = 250): string
    {
        if ($text === '') {
            return '';
        }

        // Common sensitive patterns: Bearer tokens, OpenAI-style sk- keys, api_key=... etc.
        $patterns = [
            '/\bBearer\s+[A-Za-z0-9\-\._~\+\/]+=*\b/i',
            '/\bsk-[A-Za-z0-9_\-]{8,}\b/i',
            '/\b(?:api[_-]?key|secret|token)[\s:=]+[A-Za-z0-9_\-\.]{8,}\b/i',
            // Very long hex/base64-like blobs (catch accidental dumps)
            '/[A-Za-z0-9_\-]{40,}/',
        ];

        $redacted = preg_replace($patterns, '[REDACTED]', $text);

        // Remove HTML and collapse whitespace
        $clean = preg_replace('/\s+/', ' ', strip_tags((string) $redacted));

        return mb_substr((string) $clean, 0, $limit);
    }
}
