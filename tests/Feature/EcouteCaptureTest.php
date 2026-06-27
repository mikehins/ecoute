<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use MikeHins\Ecoute\Jobs\ProcessEcouteCapture;
use MikeHins\Ecoute\Models\EcouteCapture;

beforeEach(function () {
    Gate::define('ecoute-admin', fn ($u) => true);
});

test('admin submitting valid capture returns 202 and dispatches job', function () {
    Bus::fake();

    $user = createEcouteUser();
    $response = $this->actingAs($user)->postJson('/ecoute/capture', validPayload());

    $response->assertStatus(202)
        ->assertJsonStructure(['capture_id']);

    Bus::assertDispatched(ProcessEcouteCapture::class);
});

test('duplicate submission within window returns 200 with deduplicated flag', function () {
    Bus::fake();

    $user = createEcouteUser();
    $payload = validPayload();

    $first = $this->actingAs($user)->postJson('/ecoute/capture', $payload);
    $first->assertStatus(202);

    $second = $this->actingAs($user)->postJson('/ecoute/capture', $payload);
    $second->assertStatus(200)
        ->assertJson(['deduplicated' => true])
        ->assertJsonPath('capture_id', $first->json('capture_id'));
});

test('different users do not deduplicate against each other', function () {
    Bus::fake();

    $firstUser = createEcouteUser();
    $secondUser = createEcouteUser();
    $payload = validPayload();

    $first = $this->actingAs($firstUser)->postJson('/ecoute/capture', $payload);
    $second = $this->actingAs($secondUser)->postJson('/ecoute/capture', $payload);

    $first->assertStatus(202);
    $second->assertStatus(202)
        ->assertJsonMissing(['deduplicated' => true]);

    expect($first->json('capture_id'))->not->toBe($second->json('capture_id'));
});

test('screenshot exceeding 700000 chars fails validation', function () {
    $user = createEcouteUser();

    $payload = validPayload();
    $payload['screenshot'] = str_repeat('A', 700001);

    $response = $this->actingAs($user)->postJson('/ecoute/capture', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['screenshot']);
});

test('missing required fields fail validation', function () {
    $user = createEcouteUser();

    $response = $this->actingAs($user)->postJson('/ecoute/capture', [
        'element_html' => '<div>Hello</div>',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'element_selector',
            'nearby_text',
            'user_prompt',
        ]);
});

test('capture is persisted to database', function () {
    Bus::fake();

    $user = createEcouteUser();
    $payload = validPayload();

    $this->actingAs($user)->postJson('/ecoute/capture', $payload);

    $this->assertDatabaseHas('ecoute_captures', [
        'element_selector' => 'button.submit',
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
});

test('screenshot is stored to disk and path persisted on capture', function () {
    Bus::fake();
    Storage::fake('public');

    config(['ecoute.screenshot.storage' => 'disk', 'ecoute.screenshot.disk' => 'public']);

    $user = createEcouteUser();
    $payload = validPayload();
    // 1×1 transparent GIF as base64
    $payload['screenshot'] = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    $response = $this->actingAs($user)->postJson('/ecoute/capture', $payload);

    $response->assertStatus(202);

    $capture = EcouteCapture::first();
    expect($capture->screenshot_path)->not->toBeNull();
    expect($capture->screenshot_disk)->toBe('public');
    Storage::disk('public')->assertExists($capture->screenshot_path);
});

test('capture stores deduplication hash', function () {
    Bus::fake();

    $user = createEcouteUser();
    $this->actingAs($user)->postJson('/ecoute/capture', validPayload());

    $capture = EcouteCapture::first();
    expect($capture->deduplication_hash)->not->toBeEmpty();
    expect(mb_strlen($capture->deduplication_hash))->toBe(64);
});

test('capture allows diagnostics state snapshots', function () {
    Bus::fake();

    $user = createEcouteUser();
    $payload = validPayload();
    $payload['diagnostics'] = [
        'timeline' => [
            ['type' => 'click', 'label' => 'Submit button', 'timestamp' => '12:34:56.789'],
        ],
        'state' => [
            'localStorage' => ['theme' => 'dark'],
            'sessionStorage' => ['session_id' => '123'],
            'cookies' => 'foo=bar; baz=qux',
        ],
    ];

    $response = $this->actingAs($user)->postJson('/ecoute/capture', $payload);
    $response->assertStatus(202);

    $capture = EcouteCapture::first();
    expect($capture->interaction['diagnostics']['state']['localStorage']['theme'])->toBe('dark');
});

test('show capture diagnostics dashboard returns 200', function () {
    $user = createEcouteUser();
    $capture = EcouteCapture::create([
        'user_id' => $user->id,
        'element_selector' => '#submit',
        'element_html' => '<button id="submit">Send</button>',
        'user_prompt' => 'Failed to save form.',
        'nearby_text' => ['Submit'],
        'deduplication_hash' => 'test-hash-show',
        'interaction' => [
            'url' => 'https://example.com',
            'page_title' => 'Example',
            'input_method' => 'text',
            'diagnostics' => [
                'timeline' => [
                    ['type' => 'click', 'label' => 'Submit', 'timestamp' => '12:30:00'],
                ],
                'state' => [
                    'localStorage' => ['foo' => 'bar'],
                    'cookies' => 'session=123',
                ],
            ],
        ],
        'status' => 'processing',
    ]);

    $response = $this->actingAs($user)->get("/ecoute/captures/{$capture->id}");
    $response->assertStatus(200)
        ->assertSee('Playback Timeline Logs')
        ->assertSee('LocalStorage')
        ->assertSee('session')
        ->assertSee('123');
});
