<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        // Create the permission but DO NOT silently grant it to existing roles.
        // Roles are assigned explicitly through the existing Role / Permission UI.
        Permission::findOrCreate('voucher approver', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Never silently revoke finance access on a rollback.
    }
};
