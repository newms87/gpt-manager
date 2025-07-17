<?php

use App\Models\Team\Team;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Newms87\Danx\Jobs\Job;

if (!function_exists('team')) {
    function team(): ?Team
    {
        $user = user();

        if (!$user) {
            return null;
        }

        if (Job::$runningJob) {
            $teamId = Job::$runningJob->data['team_id'] ?? null;
            if ($teamId && $user->currentTeam?->id !== $teamId) {
                $user->currentTeam = Team::find($teamId);
                Log::debug("Job running for $user->currentTeam");
            }
        } elseif (!$user->currentTeam) {
            /** @var PersonalAccessToken $token */
            $token = $user->currentAccessToken();

            // The token name matches the name of the team the user is authorized to access
            // TransientToken (used in tests) doesn't have a name property
            if ($token && $token instanceof PersonalAccessToken) {
                $user->setCurrentTeam($token->name);
            } else {
                $user->setCurrentTeam($user->teams()->first()?->uuid);
            }
        }

        return $user->currentTeam;
    }
}

if (!function_exists('is_associative_array')) {
    function is_associative_array(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        // If keys are not sequential integers starting from 0, it's associative
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
