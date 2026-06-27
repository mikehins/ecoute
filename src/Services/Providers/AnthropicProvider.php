<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Services\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Exceptions\AIException;
use MikeHins\Ecoute\Exceptions\RateLimitException;
use MikeHins\Ecoute\Services\Sanitizer;

final class AnthropicProvider implements AIProviderInterface
{
    private string $apiKey;

    private string $model;

    private int $maxTokens;

    /** @param array{api_key: string|null, model: string, max_tokens: int} $config */
    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'claude-sonnet-4-5';
        $this->maxTokens = $config['max_tokens'] ?? 2048;
    }

    /**
     * Send a prompt to the Anthropic Messages API.
     *
     * @param  string  $prompt  The prompt text to send.
     * @param  float  $temperature  Sampling temperature between 0.0 and 1.0.
     * @return array{
     *     content: string,
     *     usage: array{
     *         prompt_tokens: int,
     *         completion_tokens: int,
     *         total_tokens: int
     *     }
     * }
     *
     * @throws RateLimitException When the API returns a 429 status.
     * @throws AIException When the API returns any other error.
     */
    public function complete(string $prompt, float $temperature = 0.0, array $images = []): array
    {
        $userContent = $prompt;

        if (! empty($images)) {
            $userContent = [];
            $userContent[] = ['type' => 'text', 'text' => $prompt];
            foreach ($images as $img) {
                $base64 = $img;
                $mediaType = 'image/png';
                if (preg_match('/^data:(image\/[a-z]+);base64,(.*)$/', $img, $matches)) {
                    $mediaType = $matches[1];
                    $base64 = $matches[2];
                }
                $userContent[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mediaType,
                        'data' => $base64,
                    ],
                ];
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->connectTimeout(5)
            ->timeout(60)
            ->retry(2, 500, throw: false)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $temperature,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        $this->handleErrors($response);

        $body = $response->json();

        $content = '';
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'];
            }
        }

        $usage = $body['usage'] ?? [];

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $usage['input_tokens'] ?? 0,
                'completion_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * Inspect the HTTP response and throw the appropriate exception on failure.
     *
     * @throws RateLimitException
     * @throws AIException
     */
    private function handleErrors(Response $response): void
    {
        if ($response->status() === 429) {
            throw new RateLimitException('Anthropic rate limit exceeded.');
        }

        if ($response->failed()) {
            $excerpt = Sanitizer::sanitizeExcerpt((string) $response->body(), 250);
            throw new AIException(
                'Anthropic API error: '.$response->status().' — '.$excerpt
            );
        }
    }
}
