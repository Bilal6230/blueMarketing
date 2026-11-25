<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $existing = Permission::where('name', 'assign lead')->where('guard_name', 'web')->exists();
        Permission::findOrCreate('assign lead', 'web');

        // Previously, when this permission did not exist, lead approvals
        // required both Update Lead and an admin/superadmin role. Preserve
        // that exact legacy combination on the first permission rollout.
        if (!$existing) {
            foreach (Role::where('guard_name','web')->with('permissions')->get() as $role) {
                if (in_array(strtolower($role->name), ['admin','superadmin','super-admin'],true)
                    && $role->permissions->contains('name','update lead')) {
                    $role->givePermissionTo('assign lead');
                }
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve grants that administrators may have assigned.
    }
};
