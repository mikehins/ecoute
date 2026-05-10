<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEcouteEnabled
{
    /**
     * Deny access when Ecoute is disabled or the current environment is not allowed.
     * We intentionally return 404 so disabled installations do not advertise these routes.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedEnvironments = (array) config('ecoute.environments', []);

        if (! config('ecoute.enabled') || ! in_array(app()->environment(), $allowedEnvironments, true)) {
            abort(404);
        }

        return $next($request);
    }
}
