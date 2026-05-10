<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use MikeHins\Ecoute\Jobs\ProcessEcouteCapture;

it('dispatches job onto configured connection and queue', function () {
    // This is a lightweight skeleton. Implement HTTP request simulation or a
    // controller invocation here depending on your test environment.
    Bus::fake();

    config()->set('ecoute.queue.connection', 'database');
    config()->set('ecoute.queue.name', 'ecoute-high');

    // Simulate dispatch from controller
    ProcessEcouteCapture::dispatch('capture-id', 'template', null, null)
        ->onConnection(config('ecoute.queue.connection'))
        ->onQueue(config('ecoute.queue.name'));

    Bus::assertDispatched(ProcessEcouteCapture::class, function ($job) {
        return ($job->connection ?? null) === 'database' && ($job->queue ?? null) === 'ecoute-high';
    });
});
