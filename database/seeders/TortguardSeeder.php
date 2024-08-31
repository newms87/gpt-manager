<?php

namespace Database\Seeders;

use App\Models\Team\Team;
use App\Models\User;
use Artisan;
use Hash;
use Illuminate\Database\Seeder;

class TortguardSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::firstOrCreate(['name' => 'Tortguard'], ['namespace' => 'tortguard']);

        $user = User::firstOrCreate(['email' => 'dan@tortguard.com'], ['name' => 'Dan Tortguard', 'password' => Hash::make('tortguard')]);
        $user->teams()->syncWithoutDetaching([$team->id]);

        // Call artisan command team:objects
        Artisan::call('team:objects', ['namespace' => $team->namespace]);
    }
}
