<?php

use App\Models\Team\Team;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Newms87\Danx\Jobs\Job;

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

            if (Job::$runningJob) {
                $teamId = Job::$runningJob->data['team_id'] ?? null;
                if ($teamId) {
                    $team = Team::find($teamId);
                }

                Log::debug("Job running for $team");
            }
        }

        return $team;
    }
}
