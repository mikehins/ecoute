<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Jobs\ProcessEcouteCapture;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\CodeResolver;
use MikeHins\Ecoute\Services\EcouteTransformer;
use MikeHins\Ecoute\Services\GitHubProvider;

test('job stores sanitized failure_reason when transformer throws', function () {
    Gate::define('ecoute-admin', fn ($user) => true);

    $user = createEcouteUser();

    $capture = EcouteCapture::create([
        'user_id' => $user->id,
        'element_selector' => 'button.submit',
        'element_html' => '<button>Submit</button>',
        'nearby_text' => ['Submit'],
        'user_prompt' => 'Issue',
        'interaction' => ['page_title' => 'P', 'url' => 'https://example.com', 'timestamp' => date('Y-m-d H:i:s'), 'input_method' => 'text'],
        'deduplication_hash' => 'abc',
        'status' => 'pending',
    ]);

    $long = str_repeat('VERY_SECRET_KEY_', 50);

    config()->set('ecoute.deduplication.enabled', false);

    $aiProvider = Mockery::mock(AIProviderInterface::class);
    $aiProvider->shouldReceive('complete')->once()->andThrow(new RuntimeException($long));
    $transformer = new EcouteTransformer($aiProvider, new CodeResolver);

    $job = new ProcessEcouteCapture($capture->id);
    $thrown = null;

    try {
        $job->handle($transformer, app(GitHubProvider::class));
    } catch (RuntimeException $e) {
        $thrown = $e;
    }

    $capture->refresh();

    expect($thrown)->toBeInstanceOf(RuntimeException::class);
    expect($capture->status)->toBe('failed');
    expect(mb_strlen($capture->failure_reason))->toBeLessThanOrEqual(250);
    expect($capture->failure_reason)->not->toEqual($long);
});

test('job fails closed when the submitting user no longer exists', function () {
    Gate::define('ecoute-admin', fn ($user) => true);

    $user = createEcouteUser();
    $capture = EcouteCapture::create([
        'user_id' => $user->id,
        'element_selector' => 'button.submit',
        'element_html' => '<button>Submit</button>',
        'nearby_text' => ['Submit'],
        'user_prompt' => 'Issue',
        'interaction' => ['page_title' => 'P', 'url' => 'https://example.com', 'timestamp' => date('Y-m-d H:i:s'), 'input_method' => 'text'],
        'deduplication_hash' => 'missing-user',
        'status' => 'pending',
    ]);

    $user->delete();

    $job = new ProcessEcouteCapture($capture->id);
    $job->handle(app(EcouteTransformer::class), app(GitHubProvider::class));

    $capture->refresh();

    expect($capture->status)->toBe('failed');
    expect($capture->failure_reason)->toContain('no longer available');
});
