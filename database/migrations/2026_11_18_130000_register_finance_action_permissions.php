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
        // One-time copy of existing role/user grants keeps current staff access
        // intact. Afterwards each new permission is independently configurable.
        $legacyFor = static function (string $module, string $action): string {
            $journal = in_array($module, ['jv', 'sv', 'cv'], true);
            if ($journal) {
                return match ($action) {
                    'view' => 'read jv', 'create' => 'create jv',
                    'edit' => 'edit jv', 'delete', 'reverse' => 'delete jv',
                    'print' => 'print jv', default => 'can approve',
                };
            }
            return match ($action) {
                'view', 'print' => 'read voucher',
                'create' => 'create voucher', 'edit' => 'update voucher',
                'delete', 'reverse' => 'delete voucher',
                default => 'can approve',
            };
        };
        $legacyIds = DB::table('permissions')->where('guard_name','web')
            ->pluck('id','name');
        foreach (FinanceActionPermission::MODULES as $module) {
            foreach (FinanceActionPermission::ACTIONS as $action) {
                $new = $legacyIds[FinanceActionPermission::name($module,$action)] ?? null;
                $old = $legacyIds[$legacyFor($module,$action)] ?? null;
                if (!$new || !$old) continue;
                foreach (['role_has_permissions' => ['role_id'],
                          'model_has_permissions' => ['model_id','model_type']] as $table => $columns) {
                    if (!Schema::hasTable($table)) continue;
                    $source = DB::table($table)->where('permission_id',$old);
                    if (Schema::hasColumn($table,'deleted_at')) $source->whereNull('deleted_at');
                    foreach ($source->get($columns) as $row) {
                        $target = ['permission_id'=>$new];
                        foreach ($columns as $column) $target[$column] = $row->{$column};
                        DB::table($table)->insertOrIgnore($target);
                    }
                }
            }
        }
        app(\\Spatie\\Permission\\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Never delete granted permission definitions on a rollback.
    }
};
