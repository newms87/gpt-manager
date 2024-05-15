<?php

namespace App\Http\Middleware;

use App\Models\User;

class AuthMiddleware
{
    public function handle($request, $next)
    {
        // XXX: For now just always log in as the first user
        auth()->guard()->setUser(User::firstWhere('email', config('gpt-manager.email')));

        return $next($request);
    }
}
