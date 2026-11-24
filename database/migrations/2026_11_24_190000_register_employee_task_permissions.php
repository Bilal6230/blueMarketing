<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $actions=['read','create','update','delete'];
        foreach ($actions as $action) {
            Permission::findOrCreate($action.' employee task','web');
        }

        // Before this migration the task list was available to every
        // authenticated role. Preserve that access on transition so the
        // owner can subsequently revoke View on a per-role basis.
        foreach (Role::where('guard_name','web')->get() as $role) {
            $role->givePermissionTo('read employee task');
            if (in_array(strtolower($role->name),['admin','superadmin','super-admin'],true)) {
                $role->givePermissionTo(['create employee task','update employee task','delete employee task']);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Role-grant removal on rollback could revoke existing staff access.
    }
};
