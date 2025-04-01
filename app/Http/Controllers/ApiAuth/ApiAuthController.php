<?php

namespace App\Http\Controllers\ApiAuth;

use App\Http\Controllers\Controller;
use App\Resources\Auth\TeamResource;
use App\Resources\Auth\UserResource;

class ApiAuthController extends Controller
{
    public function login()
    {
        $credentials = request(['email', 'password']);
        $teamUuid    = request()->get('team_uuid');

        if (!auth()->attempt($credentials)) {
            return response()->json(['error' => 'Invalid Credentials'], 401);
        }

        if ($teamUuid) {
            $team = user()->teams()->firstWhere('uuid', $teamUuid) ?: user()->teams()->first();
        } else {
            $team = user()->teams()->first();
        }

        if (!$team) {
            return response()->json(['error' => 'User does not have a team'], 401);
        }

        return response()->json([
            'token' => user()->createToken($team->uuid)->plainTextToken,
            'team'  => TeamResource::make($team),
            'user'  => UserResource::make(user()),
        ]);
    }

    public function logout()
    {
        user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
