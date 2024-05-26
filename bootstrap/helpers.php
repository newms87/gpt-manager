<?php

use App\Models\Team\Team;
use Laravel\Sanctum\PersonalAccessToken;

if (!function_exists('team')) {
    function team(): ?Team
    {
        static $team;

        if (!$team) {
            /** @var PersonalAccessToken $token */
            $token = user()->currentAccessToken();

            // The token name matches the name of the team the user is authorized to access
            if ($token) {
                $team = auth()->user()->teams()->firstWhere('name', $token->name);
            }
        }

        return $team;
    }
}
