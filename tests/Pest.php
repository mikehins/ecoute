<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Bus;
use MikeHins\Ecoute\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

// Prevent background jobs from executing in tests by default. Individual tests
// may call Bus::assertDispatched() as needed; override if a test needs real dispatch.
Bus::fake();

/**
 * Create a user for Ecoute tests.
 */
function createEcouteUser(): User
{
    $user = new User;
    $user->forceFill([
        'name' => 'Test Admin',
        'email' => 'admin'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
    ]);
    $user->save();

    return $user;
}

/**
 * Build a minimal valid capture payload for use in feature tests.
 *
 * @return array<string, mixed>
 */
function validPayload(): array
{
    return [
        'element_selector' => 'button.submit',
        'element_html' => '<button class="submit">Submit</button>',
        'nearby_text' => ['Submit form', 'Cancel'],
        'user_prompt' => 'The submit button does not respond on mobile.',
        'interaction' => [
            'page_title' => 'Checkout',
            'url' => 'https://example.com/checkout',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
    ];
}
