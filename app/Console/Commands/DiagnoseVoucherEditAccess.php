<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseVoucherEditAccess extends Command
{
    protected $signature = 'finance:diagnose-edit {--role=Data Operator : Role name to inspect} {--series=CR : Voucher display series} {--project= : Project id to inspect (leave empty for role-only)} {--limit=12 : Maximum rows to inspect}';
    protected $description = 'Read-only voucher Edit diagnosis: role/user grants versus actual voucher state and ledger linkage';

    public function handle(): int
    {
        $roleName = trim((string)$this->option('role'));
        $series = strtoupper(trim((string)$this->option('series')));
        $module = \App\Support\Finance\FinanceActionPermission::moduleForSeries($series);
        if (!$module) { $this->error('Unknown finance series: '.$series); return self::FAILURE; }
        $permission = "finance {$module} edit";
        $role = Role::query()->where('name',$roleName)->where('guard_name','web')->first();
        if (!$role) { $this->error("No active web role named {$roleName}");return self::FAILURE; }

        $this->info('READ-ONLY: database='.DB::connection()->getDatabaseName());
        $this->line("Role: {$role->name} (id {$role->id}), permission: {$permission}");
        $this->line('Role grant: '.($role->hasPermissionTo($permission,'web') ? 'YES' : 'NO'));
        $users = User::role($role->name,'web')->get(['id']);
        $this->line('Users assigned to this role: '.$users->count());
        $effective = ['allowed'=>0,'denied'=>0];
        foreach ($users as $user) {
            $yes = $user->can($permission);
            ++$effective[$yes ? 'allowed' : 'denied'];
        }
        $this->line('Assigned users effective access: '.json_encode($effective));
        $this->line('Legacy generic update voucher grant: '.($role->hasPermissionTo('update voucher','web')?'YES':'NO').' (not required for CR Edit)');

        $project=(int)($this->option('project') ?: 0);
        if (!$project) {
            $this->warn('Pass --project=PROJECT_ID to inspect voucher statuses and linked ledgers. No voucher data changed.');
            return self::SUCCESS;
        }
        if (!Schema::hasTable('customer_ledger') || !Schema::hasTable('ledgers')) {
            $this->error('Voucher tables unavailable.');return self::FAILURE;
        }
        $limit=min(100,max(1,(int)$this->option('limit')));
        $sourceType=match($series){'DR'=>'CP',default=>$series};
        $query=DB::table('customer_ledger as c')
            ->where('c.project_id',$project)->where('c.transaction_type',$sourceType);
        if (Schema::hasColumn('customer_ledger','deleted_at'))$query->whereNull('c.deleted_at');
        if (Schema::hasTable('project_voucher_display_numbers')) {
            $query->leftJoin('project_voucher_display_numbers as v',function($join)use($project){
                $join->on('v.source_id','=','c.id')
                    ->where('v.source_kind','=','customer_ledger')->where('v.project_id','=',$project);
            });
        }
        $approvalCols=Schema::hasTable('daily_voucher_approvals');
        if ($approvalCols) {
            $query->leftJoin('daily_voucher_approvals as a',function($join)use($project){
                $join->on('a.source_id','=','c.id')
                    ->where('a.source_table','=','customer_ledger')->where('a.project_id','=',$project);
            });
        }
        $fields=['c.id','c.transaction_type','c.payment_type','c.is_active'];
        if (Schema::hasTable('project_voucher_display_numbers')) $fields[]='v.series as display_series';
        if ($approvalCols)$fields[]='a.status as approval_status';
        $records=$query->orderByDesc('c.id')->limit($limit)->get($fields);
        $ids=$records->pluck('id')->all();
        $ledgerCounts=DB::table('ledgers as l')
            ->join('project_head_subheads as p','p.id','=','l.project_head_subheads_id')
            ->where('p.project_id',$project)->whereIn('l.customer_ledger_id',$ids)
            ->select('l.customer_ledger_id')->selectRaw('count(*) as total')
            ->groupBy('l.customer_ledger_id')->pluck('total','l.customer_ledger_id');
        $out=$records->map(function($r)use($ledgerCounts,$series){
            $status=(string)($r->approval_status??((int)$r->is_active===1?'posted':'inactive'));
            $linked=(int)($ledgerCounts[$r->id]??0);
            $actualSeries=(string)($r->display_series??$r->transaction_type);
            $reason=match(true){
                strtoupper($actualSeries)!==$series => 'DIFFERENT DISPLAY SERIES',
                $linked!==1 => 'LEDGER LINK COUNT '.$linked.' (existing edit service expects exactly one)',
                (int)$r->payment_type!==1 => 'ONLINE/CHEQUE: separate lifecycle edit rules',
                $status==='pending' => 'PENDING: existing edit service rejects pending decision',
                $status==='rejected' => 'REJECTED: uses revision action, not normal Edit',
                (int)$r->is_active!==1 => 'INACTIVE',
                !in_array($status,['approved','posted'],true)=>'UNSUPPORTED APPROVAL STATE',
                default=>'LISTING EDIT ELIGIBLE (subject to permission and transactional checks)',
            };
            return ['source_id'=>$r->id,'series'=>$actualSeries,'payment_type'=>$r->payment_type,
                'active'=>$r->is_active,'status'=>$status,'ledger_links'=>$linked,'reason'=>$reason];
        })->all();
        $this->table(['Source ID','Display series','Payment','Active','Status','Ledger links','Reason'],
            array_map(fn($r)=>array_values($r),$out));
        $this->warn('No financial data, permission grants, or role assignments were modified.');
        return self::SUCCESS;
    }
}
