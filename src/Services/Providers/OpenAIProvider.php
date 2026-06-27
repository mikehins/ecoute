<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Services\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Exceptions\AIException;
use MikeHins\Ecoute\Exceptions\RateLimitException;
use MikeHins\Ecoute\Services\Sanitizer;

final class OpenAIProvider implements AIProviderInterface
{
    private string $apiKey;

    private string $model;

    private int $maxTokens;

    /** @param array{api_key: string|null, model: string, max_tokens: int} $config */
    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4o';
        $this->maxTokens = $config['max_tokens'] ?? 2048;
    }

    /**
     * Send a prompt to the OpenAI chat completions API.
     *
     * @param  string  $prompt  The prompt text to send.
     * @param  float  $temperature  Sampling temperature between 0.0 and 2.0.
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
                $url = str_starts_with($img, 'data:') ? $img : 'data:image/png;base64,'.$img;
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $url,
                        'detail' => 'low',
                    ],
                ];
            }
        }

        $response = Http::withToken($this->apiKey)
            ->connectTimeout(5)
            ->timeout(60)
            ->retry(2, 500, throw: false)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $temperature,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        $this->handleErrors($response);

        $body = $response->json();

        return [
            'content' => $body['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $body['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $body['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $body['usage']['total_tokens'] ?? 0,
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
            throw new RateLimitException('OpenAI rate limit exceeded.');
        }

        if ($response->failed()) {
            $excerpt = Sanitizer::sanitizeExcerpt((string) $response->body(), 250);
            throw new AIException(
                'OpenAI API error: '.$response->status().' — '.$excerpt
            );
        }
    }
}
