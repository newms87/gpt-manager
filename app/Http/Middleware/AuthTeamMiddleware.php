<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthTeamMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (user() && !team()) {
            return response()->json(['error' => 'User team was not resolved'], 401);
        }

        return $next($request);
    }
}
