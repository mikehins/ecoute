<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use MikeHins\Ecoute\Http\Requests\StoreCaptureRequest;

function makeValidationUser(): User
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

beforeEach(function () {
    Gate::define('ecoute-admin', fn () => true);
});

test('accepts a valid recording field', function () {
    Route::post('/test-recording', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeValidationUser();

    $this->actingAs($user)->post('/test-recording', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Broken',
        'interaction' => [
            'page_title' => 'Test',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'recording' => 'data:video/webm;base64,dGVzdA==',
    ])
        ->assertSessionDoesntHaveErrors('recording');
});

test('accepts capture without recording field', function () {
    Route::post('/test-recording-null', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeValidationUser();

    $this->actingAs($user)->post('/test-recording-null', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Broken',
        'interaction' => [
            'page_title' => 'Test',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
    ])
        ->assertSessionDoesntHaveErrors('recording');
});

test('accepts voice as input method', function () {
    Route::post('/test-voice', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeValidationUser();

    $this->actingAs($user)->post('/test-voice', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Broken',
        'interaction' => [
            'page_title' => 'Test',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'voice',
        ],
    ])
        ->assertSessionDoesntHaveErrors('interaction.input_method');
});

test('rejects invalid input method', function () {
    Route::post('/test-invalid-method', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeValidationUser();

    $this->actingAs($user)->post('/test-invalid-method', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Broken',
        'interaction' => [
            'page_title' => 'Test',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'invalid',
        ],
    ])
        ->assertSessionHasErrors('interaction.input_method');
});
