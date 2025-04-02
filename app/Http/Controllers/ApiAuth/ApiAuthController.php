<?php

namespace App\Http\Controllers\ApiAuth;

use App\Http\Controllers\Controller;
use App\Resources\Auth\TeamResource;
use App\Resources\Auth\UserResource;
use Newms87\Danx\Exceptions\ValidationError;

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
            'token'        => user()->createToken($team->uuid)->plainTextToken,
            'team'         => TeamResource::make($team),
            'user'         => UserResource::make(user()),
            'authTeamList' => TeamResource::collection(user()->teams()->get()),
        ]);
    }

    public function logInToTeam()
    {
        $teamUuid = request()->get('team_uuid');
        if (!user()->teams()->firstWhere('uuid', $teamUuid)) {
            throw new ValidationError('You do not have permission to access this team');
        }

        return response()->json([
            'token' => user()->createToken($teamUuid)->plainTextToken,
        ]);
    }

    public function logout()
    {
        user()->tokens()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
