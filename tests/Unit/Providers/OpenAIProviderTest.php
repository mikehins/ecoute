<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MikeHins\Ecoute\Exceptions\AIException;
use MikeHins\Ecoute\Exceptions\RateLimitException;
use MikeHins\Ecoute\Services\Providers\OpenAIProvider;

function makeOpenAIProvider(): OpenAIProvider
{
    return new OpenAIProvider([
        'api_key' => 'sk-test-key',
        'model' => 'gpt-4o',
    ]);
}

test('complete returns structured response on success', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"title":"Fix","description":"Desc","type":"bug","suggested_fix":"Fix it"}']],
            ],
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'total_tokens' => 150,
            ],
        ], 200),
    ]);

    $result = makeOpenAIProvider()->complete('Test prompt', 0.0);

    expect($result)->toHaveKeys(['content', 'usage']);
    expect($result['usage']['prompt_tokens'])->toBe(100);
    expect($result['usage']['completion_tokens'])->toBe(50);
    expect($result['usage']['total_tokens'])->toBe(150);
});

test('complete throws rate limit exception on 429', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([], 429),
    ]);

    expect(fn () => makeOpenAIProvider()->complete('Test prompt'))
        ->toThrow(RateLimitException::class);
});

test('complete throws ai exception on server error', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'Internal error'], 500),
    ]);

    expect(fn () => makeOpenAIProvider()->complete('Test prompt'))
        ->toThrow(AIException::class);
});

test('complete sends correct model and temperature', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'response']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ], 200),
    ]);

    makeOpenAIProvider()->complete('Prompt', 0.7);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $body['model'] === 'gpt-4o'
            && $body['temperature'] === 0.7
            && isset($body['messages'][0]['content']);
    });
});

test('complete sends bearer authorization header', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
        ], 200),
    ]);

    makeOpenAIProvider()->complete('Prompt');

    Http::assertSent(function (Request $request) {
        return str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ');
    });
});
