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
            if ($token) {
                $user->setCurrentTeam($token->name);
            }
        }

        return $user->currentTeam;
    }
}
