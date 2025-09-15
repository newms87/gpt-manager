<?php

namespace Database\Seeders;

use App\Models\Authorization\Role;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);
        $this->createDan();

        app(OnDemandsSeeder::class)->run();
    }

    private function createDan()
    {
        $team = Team::firstOrCreate(['name' => 'Team Dan'], ['namespace' => 'team-dan']);
        $role = Role::where('name', 'dev')->first();

        $email = config('gpt-manager.email');
        if (User::where('email', $email)->doesntExist()) {
            $user = User::factory()->create([
                'name'  => 'Daniel Newman',
                'email' => $email,
            ]);

            $user->teams()->syncWithoutDetaching([$team->id]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
