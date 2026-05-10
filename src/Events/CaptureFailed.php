<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Events;

use Illuminate\Foundation\Events\Dispatchable;
use MikeHins\Ecoute\Models\EcouteCapture;
use Throwable;

final class CaptureFailed
{
    use Dispatchable;

    public function __construct(
        public readonly EcouteCapture $capture,
        public readonly Throwable $exception,
    ) {}
}
