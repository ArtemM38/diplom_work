<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Если спортсмен без анкеты
            if ($user->role === 'athlete' && !$user->athlete) {
                if (!$request->is('athlete/setup*') && !$request->routeIs('logout')) {
                    return redirect()->route('athlete.create');
                }
            }

            // Если родитель без анкеты (надо будет создать GuardianController)
            if ($user->role === 'guardian' && !$user->guardian) {
                if (!$request->is('guardian/setup*') && !$request->routeIs('logout')) {
                    return redirect()->route('guardian.create');
                }
            }
        }
        return $next($request);
    }
    
}
