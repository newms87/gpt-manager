<?php

namespace Database\Seeders;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TortguardSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::firstOrCreate(['name' => 'Tortguard'], ['namespace' => 'tortguard']);

        $user = User::where('email', config('gpt-manager.email'))->first();
        $user?->teams()->save($team);
    }
}
