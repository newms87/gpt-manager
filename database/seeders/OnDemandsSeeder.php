<?php

namespace Database\Seeders;

use App\Models\Authorization\Role;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OnDemandsSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::updateOrCreate(['name' => 'On Demands'], [
            'namespace' => 'on-demands',
            'logo'      => 'https://gpt-manager.s3.amazonaws.com/stored-files/On-Demands-Logo___.png___66c4e716d85d6/On-Demands-Logo___.png',
        ]);

        $user = User::firstOrCreate(['email' => 'dan@on-demands.com'], ['name' => 'Dan On Demands', 'password' => Hash::make('on-demands')]);
        $user->teams()->syncWithoutDetaching([$team->id]);
        $role = Role::where('name', 'admin')->first();
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
