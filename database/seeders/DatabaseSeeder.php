<?php

namespace Database\Seeders;

use App\Models\Team\Team;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TestingSeeder::class);
        $team = Team::firstWhere('name', 'Team Dan');
    }
}
