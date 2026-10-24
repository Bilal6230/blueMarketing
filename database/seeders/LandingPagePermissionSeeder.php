<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LandingPagePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'read landing page',
            'create landing page',
            'update landing page',
            'delete landing page',
        ];

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Assign to existing administrative roles only; never grant staff automatically.
        foreach (['admin', 'superadmin'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($names);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
