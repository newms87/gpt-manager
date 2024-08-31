<?php

namespace Database\Seeders;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::firstOrCreate(['name' => 'Team Dan', 'namespace' => 'team-dan']);

        $email = config('gpt-manager.email');
        if (User::where('email', $email)->doesntExist()) {
            $user = User::factory()->create([
                'name'  => 'Daniel Newman',
                'email' => $email,
            ]);

            $user->teams()->syncWithoutDetaching([$team->id]);
        }

        app(TortguardSeeder::class)->run();
        app(OnDemandsSeeder::class)->run();
    }
}
