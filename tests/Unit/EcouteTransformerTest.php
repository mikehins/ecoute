<?php

declare(strict_types=1);

use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Exceptions\TransformerException;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\CodeResolver;
use MikeHins\Ecoute\Services\EcouteTransformer;

function makeEcouteCapture(array $overrides = []): EcouteCapture
{
    $capture = new EcouteCapture;

    $data = array_merge([
        'element_selector' => 'button.submit',
        'element_html' => '<button class="submit">Submit</button>',
        'parent_html' => null,
        'attributes' => [],
        'nearby_text' => ['Submit form', 'Cancel'],
        'user_prompt' => 'The submit button does not respond on mobile.',
        'interaction' => [
            'page_title' => 'Checkout',
            'url' => 'https://example.com/checkout',
            'timestamp' => '2026-03-27 12:00:00',
            'input_method' => 'text',
        ],
        'deduplication_hash' => hash('sha256', 'test|uniqueprompt|https://example.com/checkout'),
    ], $overrides);

    foreach ($data as $key => $value) {
        $capture->{$key} = $value;
    }

    return $capture;
}

function makeTransformer(): array
{
    $mockProvider = Mockery::mock(AIProviderInterface::class);
    $transformer = new EcouteTransformer($mockProvider, new CodeResolver);

    return [$transformer, $mockProvider];
}

test('transformer throws exception when element html is missing', function () {
    [$transformer] = makeTransformer();
    $capture = makeEcouteCapture(['element_html' => '']);

    expect(fn () => $transformer->transform($capture))
        ->toThrow(TransformerException::class, 'element_html');
});

test('transformer throws exception when user prompt is missing', function () {
    [$transformer] = makeTransformer();
    $capture = makeEcouteCapture(['user_prompt' => '']);

    expect(fn () => $transformer->transform($capture))
        ->toThrow(TransformerException::class, 'user_prompt');
});

test('transformer throws exception when interaction is empty', function () {
    [$transformer] = makeTransformer();
    $capture = makeEcouteCapture(['interaction' => []]);

    expect(fn () => $transformer->transform($capture))
        ->toThrow(TransformerException::class, 'interaction');
});

test('transformer maps valid ai response to expected schema', function () {
    [$transformer, $mockProvider] = makeTransformer();
    $capture = makeEcouteCapture();

    $mockProvider->shouldReceive('complete')->once()->andReturn([
        'content' => json_encode([
            'title' => 'Fix submit button on mobile',
            'description' => 'The submit button is unresponsive on mobile devices.',
            'type' => 'bug',
            'suggested_fix' => 'Increase the touch target area to at least 44×44px.',
        ]),
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
    ]);

    $result = $transformer->transform($capture);

    expect($result['response']['title'])->toBe('Fix submit button on mobile');
    expect($result['response']['type'])->toBe('bug');
    expect($result['prompt_version'])->toBe('v1');
});

test('transformer throws exception when ai returns invalid json', function () {
    [$transformer, $mockProvider] = makeTransformer();
    $capture = makeEcouteCapture();

    $mockProvider->shouldReceive('complete')->once()->andReturn([
        'content' => 'This is not JSON at all.',
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
    ]);

    expect(fn () => $transformer->transform($capture))
        ->toThrow(TransformerException::class);
});

test('transformer throws exception when ai response missing required key', function () {
    [$transformer, $mockProvider] = makeTransformer();
    $capture = makeEcouteCapture();

    $mockProvider->shouldReceive('complete')->once()->andReturn([
        'content' => json_encode([
            'title' => 'Missing other fields',
            'description' => 'Only title and description.',
        ]),
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
    ]);

    expect(fn () => $transformer->transform($capture))
        ->toThrow(TransformerException::class, 'type');
});

test('transformer strips markdown fences from ai response', function () {
    [$transformer, $mockProvider] = makeTransformer();
    $capture = makeEcouteCapture();

    $mockProvider->shouldReceive('complete')->once()->andReturn([
        'content' => "```json\n".json_encode([
            'title' => 'Fix layout',
            'description' => 'Layout is broken.',
            'type' => 'ux',
            'suggested_fix' => 'Adjust flex container.',
        ])."\n```",
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
    ]);

    $result = $transformer->transform($capture);

    expect($result['response']['title'])->toBe('Fix layout');
});

test('transformer loads prompt template from correct path', function () {
    [$transformer, $mockProvider] = makeTransformer();
    $capture = makeEcouteCapture();

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(function (string $prompt) {
            return str_contains($prompt, 'button.submit')
                && str_contains($prompt, 'User Feedback:');
        })
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix submit',
                'description' => 'Button broken.',
                'type' => 'bug',
                'suggested_fix' => 'Check JS.',
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $transformer->transform($capture);

    expect(true)->toBeTrue(); // assertion is inside the withArgs callback
});

test('transformer does not include source code when code resolution is disabled', function () {
    config()->set('ecoute.code.enabled', false);

    $viewsPath = base_path('resources/views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0777, true);
    }

    file_put_contents($viewsPath.'/checkout.blade.php', "<button class=\"submit\">Checkout</button>\n");

    [$transformer, $mockProvider] = makeTransformer();
    $capture = makeEcouteCapture();

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => ! str_contains($prompt, 'SOURCE CODE:'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix submit',
                'description' => 'Button broken.',
                'type' => 'bug',
                'suggested_fix' => 'Check JS.',
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $transformer->transform($capture);
});

test('transformer includes source code only when code resolution is enabled', function () {
    config()->set('ecoute.code.enabled', true);

    $viewsPath = base_path('resources/views');
    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0777, true);
    }

    file_put_contents($viewsPath.'/checkout.blade.php', "<button class=\"submit\">Checkout</button>\n");

    [$transformer, $mockProvider] = makeTransformer();
    $capture = makeEcouteCapture();

    $mockProvider->shouldReceive('complete')
        ->once()
        ->withArgs(fn (string $prompt) => str_contains($prompt, 'SOURCE CODE:') && str_contains($prompt, 'resources/views/checkout.blade.php'))
        ->andReturn([
            'content' => json_encode([
                'title' => 'Fix submit',
                'description' => 'Button broken.',
                'type' => 'bug',
                'suggested_fix' => 'Check JS.',
            ]),
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]);

    $transformer->transform($capture);
});
