<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Contracts;

interface AIProviderInterface
{
    /**
     * Send a prompt to the AI provider and return the response with token usage.
     *
     * @param  string  $prompt  The prompt to send to the AI provider.
     * @param  float  $temperature  Sampling temperature between 0.0 and 2.0.
     * @return array{
     *     content: string,
     *     usage: array{
     *         prompt_tokens: int,
     *         completion_tokens: int,
     *         total_tokens: int
     *     }
     * }
     */
    public function complete(string $prompt, float $temperature = 0.0): array;
}
