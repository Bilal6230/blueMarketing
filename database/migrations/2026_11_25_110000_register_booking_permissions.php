<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        foreach (['read booking','create booking','update booking','delete booking'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Upgrade old grants exactly once. Afterwards Booking rights are
        // independent: revoking Booking View cannot be bypassed by Plot View.
        foreach (Role::where('guard_name','web')->with('permissions')->get() as $role) {
            $old = $role->permissions->pluck('name');
            $additional=[];
            if ($old->contains('read plot')) $additional[]='read booking';
            if ($old->contains('create plot')) $additional[]='create booking';
            if ($old->contains('update plot') || $old->contains('update booking price')) $additional[]='update booking';
            if ($additional) $role->givePermissionTo($additional);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Do not erase role-specific grants on rollback.
    }
};
