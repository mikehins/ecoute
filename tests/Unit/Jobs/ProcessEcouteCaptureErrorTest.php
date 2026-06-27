<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Jobs\ProcessEcouteCapture;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\CodeResolver;
use MikeHins\Ecoute\Services\EcouteTransformer;
use MikeHins\Ecoute\Services\GitHubProvider;

test('job transcribes recording via Whisper API', function () {
    Gate::define('ecoute-admin', fn ($user) => true);

    Storage::fake('public');
    Storage::disk('public')->put('ecoute/recordings/test.webm', 'fake-webm-data');

    // Fake HTTP Whisper API response
    Http::fake([
        'https://api.openai.com/v1/audio/transcriptions' => Http::response([
            'text' => 'This is a voice narration of the bug.',
        ], 200),
    ]);

    config()->set('ecoute.screenshot.disk', 'public');
    config()->set('ecoute.whisper.enabled', true);
    config()->set('ecoute.providers.openai.api_key', 'test-openai-key');
    config()->set('ecoute.github.enabled', false); // Disable github issue pushing

    $user = createEcouteUser();
    $capture = EcouteCapture::create([
        'user_id' => $user->id,
        'element_selector' => 'button.submit',
        'element_html' => '<button>Submit</button>',
        'nearby_text' => ['Submit'],
        'user_prompt' => 'Bug report here.',
        'recording_path' => 'ecoute/recordings/test.webm',
        'recording_disk' => 'public',
        'interaction' => ['page_title' => 'P', 'url' => 'https://example.com', 'timestamp' => date('Y-m-d H:i:s'), 'input_method' => 'voice'],
        'deduplication_hash' => 'whisper-test',
        'status' => 'pending',
    ]);

    // Mock AIProviderInterface so we don't hit the real AI provider for the prompt completion
    $aiProvider = Mockery::mock(AIProviderInterface::class);
    $aiProvider->shouldReceive('complete')
        ->once()
        ->andReturn(['content' => '{"title": "Transcribed Bug", "description": "Details", "type": "bug", "suggested_fix": "Fix"}']);

    $transformer = new EcouteTransformer($aiProvider, new CodeResolver);

    $job = new ProcessEcouteCapture($capture->id);
    $job->handle($transformer, app(GitHubProvider::class));

    $capture->refresh();

    // Verify Whisper API was called
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/audio/transcriptions';
    });

    // Verify user prompt got transcription appended
    expect($capture->user_prompt)->toContain('Bug report here.');
    expect($capture->user_prompt)->toContain('**Voice Transcription:**');
    expect($capture->user_prompt)->toContain('This is a voice narration of the bug.');
});

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
