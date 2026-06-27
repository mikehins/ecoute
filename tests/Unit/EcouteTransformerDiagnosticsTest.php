<?php

declare(strict_types=1);

use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\CodeResolver;
use MikeHins\Ecoute\Services\EcouteTransformer;

function makeDiagnosticsCapture(array $overrides = []): EcouteCapture
{
    $capture = new EcouteCapture;

    $data = array_merge([
        'element_selector' => 'div.broken',
        'element_html' => '<div>test</div>',
        'nearby_text' => [],
        'user_prompt' => 'Something is broken',
        'interaction' => [
            'page_title' => 'Test',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'deduplication_hash' => hash('sha256', 'test|diagnostics|https://example.com'),
    ], $overrides);

    foreach ($data as $key => $value) {
        $capture->{$key} = $value;
    }

    return $capture;
}

function makeDiagnosticsTransformer(): array
{
    $mockProvider = Mockery::mock(AIProviderInterface::class);
    $transformer = new EcouteTransformer($mockProvider, new CodeResolver);

    return [$transformer, $mockProvider];
}

test('injects console diagnostics into the AI context when enabled', function () {
    config(['ecoute.diagnostics.enabled' => true]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'diagnostics' => [
            'console' => [
                ['level' => 'error', 'args' => ['Cannot read property x of undefined'], 'timestamp' => '2024-01-01T00:00:00Z'],
                ['level' => 'warn', 'args' => ['Deprecated API call'], 'timestamp' => '2024-01-01T00:00:01Z'],
            ],
            'network' => [],
        ],
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => str_contains($prompt, '### Console Logs')
            && str_contains($prompt, '[error] Cannot read property x of undefined')
            && str_contains($prompt, '[warn] Deprecated API call'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix bug',
                'description' => 'Bug description',
                'type' => 'bug',
                'suggested_fix' => 'Fix it',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $result = $transformer->transform($capture);

    expect($result['response'])->toHaveKey('title');
});

test('renders network requests as a markdown table in context', function () {
    config(['ecoute.diagnostics.enabled' => true]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'element_html' => '<button>Click</button>',
        'user_prompt' => 'Button not working',
        'diagnostics' => [
            'console' => [],
            'network' => [
                ['url' => '/api/users', 'method' => 'GET', 'status' => 500, 'duration' => 42],
                ['url' => '/api/login', 'method' => 'POST', 'status' => 200, 'duration' => 120],
            ],
        ],
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => str_contains($prompt, '### Network Requests')
            && str_contains($prompt, '| Method | URL | Status | Duration |')
            && str_contains($prompt, '| GET |')
            && str_contains($prompt, '| 500 | 42ms')
            && str_contains($prompt, '| POST |')
            && str_contains($prompt, '| 200 | 120ms'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix API',
                'description' => 'API returning 500',
                'type' => 'bug',
                'suggested_fix' => 'Check logs',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $result = $transformer->transform($capture);

    expect($result['response']['title'])->toBe('Fix API');
});

test('skips diagnostics section when disabled', function () {
    config(['ecoute.diagnostics.enabled' => false]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'diagnostics' => [
            'console' => [['level' => 'error', 'args' => ['err'], 'timestamp' => '']],
            'network' => [['url' => '/api', 'method' => 'GET', 'status' => 500, 'duration' => 10]],
        ],
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => ! str_contains($prompt, '### Browser Diagnostics')
            && ! str_contains($prompt, '### Console Logs')
            && ! str_contains($prompt, '### Network Requests'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix',
                'description' => 'Desc',
                'type' => 'bug',
                'suggested_fix' => 'Fix',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $result = $transformer->transform($capture);

    expect($result['response']['title'])->toBe('Fix');
});

test('handles empty diagnostics gracefully', function () {
    config(['ecoute.diagnostics.enabled' => true]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'user_prompt' => 'test',
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => ! str_contains($prompt, '### Browser Diagnostics'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'T',
                'description' => 'D',
                'type' => 'bug',
                'suggested_fix' => 'F',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $result = $transformer->transform($capture);

    expect($result['response'])->toHaveKey('title');
});

test('masks PII in console log args within diagnostics', function () {
    config(['ecoute.diagnostics.enabled' => true]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'diagnostics' => [
            'console' => [
                ['level' => 'error', 'args' => ['user@example.com sent payment'], 'timestamp' => ''],
            ],
            'network' => [],
        ],
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => str_contains($prompt, '[REDACTED]')
            && ! str_contains($prompt, 'user@example.com'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix',
                'description' => 'Fix description',
                'type' => 'bug',
                'suggested_fix' => 'Fix',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $transformer->transform($capture);

    expect(true)->toBeTrue();
});

test('masks PII in network request URLs within diagnostics', function () {
    config(['ecoute.diagnostics.enabled' => true]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'diagnostics' => [
            'console' => [],
            'network' => [
                ['url' => '/api/user?email=test@example.com', 'method' => 'GET', 'status' => 200, 'duration' => 10],
            ],
        ],
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => str_contains($prompt, '[REDACTED]')
            && ! str_contains($prompt, 'test@example.com'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix',
                'description' => 'Fix description',
                'type' => 'bug',
                'suggested_fix' => 'Fix',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $transformer->transform($capture);

    expect(true)->toBeTrue();
});

test('console diagnostics without level key defaults to log', function () {
    config(['ecoute.diagnostics.enabled' => true]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'diagnostics' => [
            'console' => [
                ['args' => ['a plain info message']],
            ],
            'network' => [],
        ],
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => str_contains($prompt, '[log] a plain info message'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix',
                'description' => 'Fix description',
                'type' => 'bug',
                'suggested_fix' => 'Fix',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $transformer->transform($capture);

    expect(true)->toBeTrue();
});

test('network diagnostics round duration to nearest millisecond', function () {
    config(['ecoute.diagnostics.enabled' => true]);

    [$transformer, $mockProvider] = makeDiagnosticsTransformer();
    $capture = makeDiagnosticsCapture([
        'diagnostics' => [
            'console' => [],
            'network' => [
                ['url' => '/api', 'method' => 'POST', 'status' => 201, 'duration' => 42.7],
            ],
        ],
    ]);

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => str_contains($prompt, '43ms'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix',
                'description' => 'Fix description',
                'type' => 'bug',
                'suggested_fix' => 'Fix',
                'code_suggestion' => null,
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $transformer->transform($capture);

    expect(true)->toBeTrue();
});
