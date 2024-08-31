<?php

namespace Database\Seeders;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class OnDemandsSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::updateOrCreate(['name' => 'On Demands'], ['namespace' => 'on-demands']);

        $user = User::where('email', config('gpt-manager.email'))->first();
        $user?->teams()->save($team);
    }
}
