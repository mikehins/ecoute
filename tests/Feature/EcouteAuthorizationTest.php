<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;

test('non authenticated user cannot submit capture', function () {
    $response = $this->postJson('/ecoute/capture', validPayload());

    $response->assertUnauthorized();
});

test('non admin user gets 403 on capture submission', function () {
    $user = createEcouteUser();

    Gate::define('ecoute-admin', fn ($u) => false);

    $response = $this->actingAs($user)->postJson('/ecoute/capture', validPayload());

    $response->assertForbidden();
});

test('non admin user gets 403 on template listing', function () {
    $user = createEcouteUser();

    Gate::define('ecoute-admin', fn ($u) => false);

    $response = $this->actingAs($user)->getJson('/ecoute/templates');

    $response->assertForbidden();
});

test('admin user can submit a capture', function () {
    Bus::fake();

    $user = createEcouteUser();

    Gate::define('ecoute-admin', fn ($u) => $u->id === $user->id);

    $response = $this->actingAs($user)->postJson('/ecoute/capture', validPayload());

    $response->assertStatus(202);
});

test('rate limit returns 429 after exceeding attempts', function () {
    Bus::fake();

    $user = createEcouteUser();

    Gate::define('ecoute-admin', fn ($u) => true);

    config(['ecoute.rate_limit.attempts' => 2]);

    $this->actingAs($user)->postJson('/ecoute/capture', validPayload());
    $this->actingAs($user)->postJson('/ecoute/capture', validPayload());
    $response = $this->actingAs($user)->postJson('/ecoute/capture', validPayload());

    $response->assertStatus(429);
});

test('rate limit is isolated per user', function () {

    Bus::fake();

    $user1 = createEcouteUser();
    $user2 = createEcouteUser();

    Gate::define('ecoute-admin', fn ($u) => true);

    config(['ecoute.rate_limit.attempts' => 2]);

    // User 1 exhausts their limit
    $payload1 = validPayload();
    $this->actingAs($user1)->postJson('/ecoute/capture', $payload1);
    $this->actingAs($user1)->postJson('/ecoute/capture', $payload1);
    $this->actingAs($user1)->postJson('/ecoute/capture', $payload1)->assertStatus(429);

    // User 2 should still be allowed — use a slightly different payload to avoid global deduplication
    $payload2 = validPayload();
    $payload2['user_prompt'] .= ' (user2)';
    $this->actingAs($user2)->postJson('/ecoute/capture', $payload2)->assertStatus(202);
});

test('ecoute routes return 404 when the package is disabled at runtime', function () {
    $user = createEcouteUser();

    Gate::define('ecoute-admin', fn ($u) => true);
    config()->set('ecoute.enabled', false);

    $this->actingAs($user)->postJson('/ecoute/capture', validPayload())->assertNotFound();
    $this->actingAs($user)->postJson('/ecoute/preview', validPayload())->assertNotFound();
    $this->actingAs($user)->getJson('/ecoute/templates')->assertNotFound();
});

test('ecoute routes return 404 outside allowed environments', function () {
    $user = createEcouteUser();

    Gate::define('ecoute-admin', fn ($u) => true);
    config()->set('ecoute.environments', ['production']);

    $this->actingAs($user)->postJson('/ecoute/capture', validPayload())->assertNotFound();
});
