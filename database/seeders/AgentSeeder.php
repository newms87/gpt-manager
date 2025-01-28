<?php

namespace Database\Seeders;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Team\Team;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::find(1) ?? Team::factory()->create();
        for($i = 0; $i < 3; $i++) {
            $threads = AgentThread::factory()->forTeam($team)->count(fake()->numberBetween(0, 3));
            Agent::factory()->has($threads)->recycle($team)->create();
        }
    }
}
