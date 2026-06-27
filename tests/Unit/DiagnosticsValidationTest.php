<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use MikeHins\Ecoute\Http\Requests\StoreCaptureRequest;

function makeDiagValidationUser(): User
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

test('accepts valid diagnostics payload', function () {
    Route::post('/test-diag-valid', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeDiagValidationUser();

    $this->actingAs($user)->post('/test-diag-valid', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Test',
        'interaction' => [
            'page_title' => 'T',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'diagnostics' => [
            'console' => [
                ['level' => 'error', 'args' => ['msg'], 'timestamp' => '2024-01-01T00:00:00Z'],
                ['level' => 'warn', 'args' => ['msg'], 'timestamp' => '2024-01-01T00:00:01Z'],
                ['level' => 'log', 'args' => ['msg'], 'timestamp' => '2024-01-01T00:00:02Z'],
            ],
            'network' => [
                ['url' => '/api/test', 'method' => 'GET', 'status' => 200, 'duration' => 42, 'timestamp' => '2024-01-01T00:00:00Z'],
                ['url' => '/api/create', 'method' => 'POST', 'status' => 201, 'duration' => 100, 'timestamp' => '2024-01-01T00:00:01Z'],
                ['url' => '/api/update', 'method' => 'PUT', 'status' => 200, 'duration' => 80, 'timestamp' => '2024-01-01T00:00:02Z'],
                ['url' => '/api/partial', 'method' => 'PATCH', 'status' => 200, 'duration' => 60, 'timestamp' => '2024-01-01T00:00:03Z'],
                ['url' => '/api/delete', 'method' => 'DELETE', 'status' => 204, 'duration' => 30, 'timestamp' => '2024-01-01T00:00:04Z'],
                ['url' => '/api/options', 'method' => 'OPTIONS', 'status' => 200, 'duration' => 5, 'timestamp' => '2024-01-01T00:00:05Z'],
                ['url' => '/api/head', 'method' => 'HEAD', 'status' => 200, 'duration' => 5, 'timestamp' => '2024-01-01T00:00:06Z'],
            ],
        ],
    ])
        ->assertSessionDoesntHaveErrors('diagnostics');
});

test('rejects diagnostics with invalid console level', function () {
    Route::post('/test-diag-bad-level', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeDiagValidationUser();

    $this->actingAs($user)->post('/test-diag-bad-level', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Test',
        'interaction' => [
            'page_title' => 'T',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'diagnostics' => [
            'console' => [
                ['level' => 'debug', 'args' => ['msg'], 'timestamp' => ''],
            ],
        ],
    ])
        ->assertSessionHasErrors('diagnostics.console.0.level');
});

test('rejects diagnostics console entry without args', function () {
    Route::post('/test-diag-no-args', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeDiagValidationUser();

    $this->actingAs($user)->post('/test-diag-no-args', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Test',
        'interaction' => [
            'page_title' => 'T',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'diagnostics' => [
            'console' => [
                ['level' => 'error', 'timestamp' => ''],
            ],
        ],
    ])
        ->assertSessionHasErrors('diagnostics.console.0.args');
});

test('omitting diagnostics field passes validation', function () {
    Route::post('/test-diag-absent', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeDiagValidationUser();

    $this->actingAs($user)->post('/test-diag-absent', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Test',
        'interaction' => [
            'page_title' => 'T',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
    ])
        ->assertSessionDoesntHaveErrors('diagnostics');
});

test('rejects network entry without url', function () {
    Route::post('/test-net-no-url', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeDiagValidationUser();

    $this->actingAs($user)->post('/test-net-no-url', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Test',
        'interaction' => [
            'page_title' => 'T',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'diagnostics' => [
            'network' => [
                ['method' => 'GET', 'status' => 200, 'duration' => 10],
            ],
        ],
    ])
        ->assertSessionHasErrors('diagnostics.network.0.url');
});

test('rejects network entry with invalid status code', function () {
    Route::post('/test-net-bad-status', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeDiagValidationUser();

    $this->actingAs($user)->post('/test-net-bad-status', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Test',
        'interaction' => [
            'page_title' => 'T',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'diagnostics' => [
            'network' => [
                ['url' => '/api', 'method' => 'GET', 'status' => 600, 'duration' => 10],
            ],
        ],
    ])
        ->assertSessionHasErrors('diagnostics.network.0.status');
});

test('rejects network entry with negative duration', function () {
    Route::post('/test-net-neg-duration', fn (StoreCaptureRequest $request) => response()->json(['ok' => true]))
        ->middleware(['web', 'auth']);

    $user = makeDiagValidationUser();

    $this->actingAs($user)->post('/test-net-neg-duration', [
        'element_selector' => 'button',
        'element_html' => '<button>Click</button>',
        'nearby_text' => ['Click'],
        'user_prompt' => 'Test',
        'interaction' => [
            'page_title' => 'T',
            'url' => 'https://example.com',
            'timestamp' => date('Y-m-d H:i:s'),
            'input_method' => 'text',
        ],
        'diagnostics' => [
            'network' => [
                ['url' => '/api', 'method' => 'GET', 'status' => 200, 'duration' => -1],
            ],
        ],
    ])
        ->assertSessionHasErrors('diagnostics.network.0.duration');
});
