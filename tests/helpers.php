<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;

if (! function_exists('createEcouteUser')) {
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
}

if (! function_exists('validPayload')) {
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
                'timestamp' => date('Y-m-d H:i:s'),
                'input_method' => 'text',
            ],
        ];
    }
}
