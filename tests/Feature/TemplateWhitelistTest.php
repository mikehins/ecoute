<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use MikeHins\Ecoute\Jobs\ProcessEcouteCapture;

beforeEach(function () {
    Gate::define('ecoute-admin', fn ($u) => true);
});

test('non-whitelisted template is rejected when whitelist configured', function () {
    Bus::fake();

    // Configure a restrictive whitelist
    config(['ecoute.github.template_whitelist' => ['allowed.md']]);

    $user = createEcouteUser();
    $payload = validPayload();
    $payload['template'] = 'disallowed.md';

    $response = $this->actingAs($user)->postJson('/ecoute/capture', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['template']);
});

test('when whitelist is empty template is accepted', function () {
    Bus::fake();

    // No whitelist configured — permissive behaviour
    config(['ecoute.github.template_whitelist' => []]);

    $user = createEcouteUser();
    $payload = validPayload();
    $payload['template'] = 'some_template.md';

    $response = $this->actingAs($user)->postJson('/ecoute/capture', $payload);

    $response->assertStatus(202);
    Bus::assertDispatched(ProcessEcouteCapture::class);
});
