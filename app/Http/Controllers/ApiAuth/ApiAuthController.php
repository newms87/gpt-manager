<?php

namespace App\Http\Controllers\ApiAuth;

use App\Http\Controllers\Controller;
use App\Resources\Auth\UserResource;

class ApiAuthController extends Controller
{
    public function login()
    {
        $credentials = request(['email', 'password']);
        $teamName    = request()->get('team_name');

        if (!auth()->attempt($credentials)) {
            return response()->json(['error' => 'Invalid Credentials'], 401);
        }

        if ($teamName) {
            $team = user()->teams()->firstWhere('name', $teamName);
            if (!$team) {
                return response()->json(['error' => "You are not on the team $teamName"], 401);
            }
        } else {
            $team = user()->teams()->first();
        }

        if (!$team) {
            return response()->json(['error' => 'User does not have a team'], 401);
        }

        return response()->json([
            'token' => user()->createToken($team->name)->plainTextToken,
            'team'  => $team->name,
            'user'  => UserResource::make(user()),
        ]);
    }

    public function logout()
    {
        user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
