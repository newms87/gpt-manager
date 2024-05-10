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

        for($i = 0; $i < 20; $i++) {
            $threads = Thread::factory()->forTeam($team)->count(fake()->numberBetween(0, 3));
            Agent::factory()->has($threads)->recycle($team)->create();
        }
    }
}
