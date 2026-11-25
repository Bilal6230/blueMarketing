<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        foreach (['read labour', 'create labour', 'update labour', 'delete labour'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permission definitions and existing grants are retained on rollback.
    }
};
