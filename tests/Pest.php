<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use MikeHins\Ecoute\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

// Prevent background jobs from executing in tests by default. Individual tests
// may call Bus::assertDispatched() as needed; override if a test needs real dispatch.
beforeEach(function () {
    Bus::fake();
});
