<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active && !$request->routeIs('logout')) {
            return Inertia::render('Auth/InactiveAccount')->toResponse($request);
        }

        return $next($request);
    }
}
