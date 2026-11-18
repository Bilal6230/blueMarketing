<?php

namespace App\Console\Commands;

use App\Support\Finance\RolePermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only inspection of the CURRENT configured database.
 * Never mutates permissions, role assignments, users or caches.
 */
class AuditRolePermissions extends Command
{
    protected $signature = 'permissions:audit {--json : Output a machine-readable report}';
    protected $description = 'Read-only permission inventory with active role and direct-user grant counts';

    public function handle(): int
    {
        foreach (['permissions','roles','role_has_permissions','model_has_permissions'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error('Missing table: '.$table);
                return self::FAILURE;
            }
        }

        $roleCount = DB::table('role_has_permissions')
            ->select('permission_id')->selectRaw('count(*) as assignments')
            ->groupBy('permission_id')->pluck('assignments','permission_id');
        $userCount = DB::table('model_has_permissions')
            ->select('permission_id')->selectRaw('count(*) as assignments')
            ->groupBy('permission_id')->pluck('assignments','permission_id');
        $items = DB::table('permissions')->orderBy('guard_name')->orderBy('name')
            ->get(['id','name','guard_name'])->map(function ($item) use ($roleCount,$userCount) {
                $name = (string)$item->name;
                return [
                    'id'=>(int)$item->id,
                    'name'=>$name,
                    'guard'=>(string)$item->guard_name,
                    'category'=>RolePermissionCatalog::category($name),
                    'role_grants'=>(int)($roleCount[$item->id] ?? 0),
                    'direct_user_grants'=>(int)($userCount[$item->id] ?? 0),
                ];
            })->values();

        $report = [
            'database'=>DB::connection()->getDatabaseName(),
            'generated_at'=>now()->toIso8601String(),
            'read_only'=>true,
            'total'=>$items->count(),
            'legacy_voucher_names'=>RolePermissionCatalog::LEGACY_VOUCHER_NAMES,
            'counts'=>[
                'granular_finance'=>$items->where('category','finance')->count(),
                'legacy_voucher'=>$items->where('category','legacy')->count(),
                'other'=>$items->where('category','general')->count(),
                'unassigned'=>$items->filter(fn($p)=>$p['role_grants']===0 && $p['direct_user_grants']===0)->count(),
            ],
            'permissions'=>$items->all(),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Read-only permission audit: '.$report['database']);
            $this->table(['Name','Guard','Category','Role grants','Direct grants'],
                $items->map(fn($p)=>[$p['name'],$p['guard'],$p['category'],$p['role_grants'],$p['direct_user_grants']])->all());
            $this->line('Total: '.$report['total'].' | Legacy voucher: '.$report['counts']['legacy_voucher'].' | Unassigned: '.$report['counts']['unassigned']);
        }
        return self::SUCCESS;
    }
}
