<?php

namespace Database\Seeders;

use App\Models\Authorization\Permission;
use App\Models\Authorization\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = config('permissions.permissions');
        $roles       = config('permissions.roles');

        $permissionMap = [];
        foreach ($permissions as $name => $display) {
            $permission           = Permission::updateOrCreate(['name' => $name], [
                'display_name' => $display[0],
                'description'  => $display[1] ?? '',
            ]);
            $permissionMap[$name] = $permission->id;
        }

        foreach ($roles as $name => $role) {
            $rolePermissions = $role['permissions'];
            $display         = $role['display'];
            $roleModel       = Role::updateOrCreate(['name' => $name], [
                'display_name' => $display[0],
                'description'  => $display[1] ?? '',
            ]);
            $roleModel->permissions()->sync(array_map(fn($name) => $permissionMap[$name], $rolePermissions));
        }

        // Clear all user permission caches so changes take effect immediately
        // User permissions are cached for 24 hours, so we need to flush the cache
        // after seeding to ensure users see the updated permissions
        Cache::flush();
    }
}
