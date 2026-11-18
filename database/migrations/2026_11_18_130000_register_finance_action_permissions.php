<?php

use App\Support\Finance\FinanceActionPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) return;

        $now = now();
        foreach (FinanceActionPermission::MODULES as $module) {
            foreach (FinanceActionPermission::ACTIONS as $action) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => FinanceActionPermission::name($module,$action),
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Never delete granted permission definitions on a rollback.
    }
};
