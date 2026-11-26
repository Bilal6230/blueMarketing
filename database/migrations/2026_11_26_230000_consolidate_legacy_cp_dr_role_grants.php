<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CP is the old identifier of DR. Preserve both permission records for
 * historical compatibility, but union their grants for existing roles/users.
 * No voucher, posting, approval or ledger row is modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) return;
        $permissions = DB::table('permissions')
            ->where('name', 'like', 'finance cp %')
            ->orWhere('name', 'like', 'finance dr %')
            ->get(['id', 'name', 'guard_name'])
            ->groupBy('guard_name');

        foreach ($permissions as $items) {
            $byName = $items->keyBy('name');
            foreach (['view','create','edit','delete','approve','reject','reverse','print','history','resubmit'] as $action) {
                $cp = $byName->get('finance cp '.$action);
                $dr = $byName->get('finance dr '.$action);
                if (!$cp || !$dr) continue;

                foreach (['role_has_permissions' => 'role_id',
                          'model_has_permissions' => 'model_id'] as $table => $owner) {
                    if (!Schema::hasTable($table)) continue;
                    $grants = DB::table($table)
                        ->whereIn('permission_id', [$cp->id, $dr->id])->get();
                    foreach ($grants as $grant) {
                        foreach ([$cp->id, $dr->id] as $permissionId) {
                            $row = ['permission_id' => $permissionId, $owner => $grant->{$owner}];
                            if ($table === 'model_has_permissions') {
                                $row['model_type'] = $grant->model_type;
                            }
                            DB::table($table)->insertOrIgnore($row);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: cannot distinguish original user/role
        // grants from the equivalent historical alias without revoking access.
    }
};
