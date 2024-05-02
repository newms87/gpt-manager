<?php

namespace Database\Seeders;

use App\Models\Agent\Agent;
use App\Models\Agent\Thread;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::firstWhere('name', 'Team Dan');
        if (!$team) {
            $team = Team::factory()->create([
                'name' => 'Team Dan',
            ]);
        }

        if (User::where('email', 'dan@sagesweeper.com')->doesntExist()) {
            User::factory()->create([
                'name'    => 'Daniel Newman',
                'email'   => 'dan@sagesweeper.com',
                'team_id' => $team,
            ]);
        }

        $threads = Thread::factory()->forTeam($team)->count(3);
        Agent::factory()->has($threads)->recycle($team)->count(20)->create();
    }
}
