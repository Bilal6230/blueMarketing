<?php

use App\Support\Stock\StockAbility;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        foreach (StockAbility::MODULES as $module) {
            foreach (StockAbility::ACTIONS as $action) {
                Permission::findOrCreate(StockAbility::name($module,$action),'web');
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Never remove role-granted permissions on rollback.
    }
};
