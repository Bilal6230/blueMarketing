<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        Permission::findOrCreate('read attendance','web');
        Permission::findOrCreate('create attendance','web');
        foreach (Role::where('guard_name','web')->with('permissions')->get() as $role) {
            $old = $role->permissions->pluck('name');
            $grants = [];
            if ($old->contains('read labour')) $grants[]='read attendance';
            if ($old->contains('create labour')) $grants[]='create attendance';
            if ($grants) $role->givePermissionTo($grants);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Role grants are never destroyed on rollback.
    }
};
