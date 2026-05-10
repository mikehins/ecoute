<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class EcouteAdminMiddleware
{
    /**
     * Handle an incoming request by verifying ecoute-admin gate access.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! Gate::forUser($request->user())->check('ecoute-admin')) {
            abort(403, 'Unauthorized. Ecoute admin access required.');
        }

        return $next($request);
    }
}
