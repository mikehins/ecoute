<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MikeHins\Ecoute\Http\Controllers\EcouteController;
use MikeHins\Ecoute\Http\Middleware\EcouteAdminMiddleware;
use MikeHins\Ecoute\Http\Middleware\EnsureEcouteEnabled;

Route::post('/ecoute/preview', [EcouteController::class, 'preview'])
    ->middleware(['web', 'auth', EnsureEcouteEnabled::class, EcouteAdminMiddleware::class, 'throttle:ecoute'])
    ->name('ecoute.preview');

Route::get('/ecoute/templates', [EcouteController::class, 'templates'])
    ->middleware(['web', 'auth', EnsureEcouteEnabled::class, EcouteAdminMiddleware::class])
    ->name('ecoute.templates');

Route::post('/ecoute/capture', [EcouteController::class, 'capture'])
    ->middleware(['web', 'auth', EnsureEcouteEnabled::class, EcouteAdminMiddleware::class, 'throttle:ecoute'])
    ->name('ecoute.capture');

Route::get('/ecoute/captures/{capture}', [EcouteController::class, 'show'])
    ->middleware(['web', 'auth', EnsureEcouteEnabled::class, EcouteAdminMiddleware::class])
    ->name('ecoute.captures.show');
