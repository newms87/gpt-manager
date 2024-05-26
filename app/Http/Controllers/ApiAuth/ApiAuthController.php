<?php

namespace App\Http\Controllers\ApiAuth;

use App\Http\Controllers\Controller;

class ApiAuthController extends Controller
{
    public function login()
    {
        $credentials = request(['email', 'password', 'team_name']);

        if (!auth()->attempt($credentials)) {
            return response()->json(['error' => 'Invalid Credentials'], 401);
        }

        if (!empty($credentials['team_name'])) {
            $team = user()->teams()->firstWhere('name', $credentials['team_name']);
            if (!$team) {
                return response()->json(['error' => 'Invalid Team'], 401);
            }
        } else {
            $team = user()->teams()->first();
        }

        if (!$team) {
            return response()->json(['error' => 'User does not have a team'], 401);
        }

        return response()->json(['token' => user()->createToken($team->name)->plainTextToken]);
    }

    public function logout()
    {
        user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
