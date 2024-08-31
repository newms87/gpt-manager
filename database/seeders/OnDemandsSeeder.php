<?php

namespace Database\Seeders;

use App\Models\Team\Team;
use App\Models\User;
use Artisan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OnDemandsSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::updateOrCreate(['name' => 'On Demands'], ['namespace' => 'on-demands']);

        $user = User::firstOrCreate(['email' => 'dan@on-demands.com'], ['name' => 'Dan On Demands', 'password' => Hash::make('on-demands')]);
        $user->teams()->syncWithoutDetaching([$team->id]);

        // Call artisan command team:objects
        Artisan::call('team:objects', ['namespace' => $team->namespace]);
    }
}
