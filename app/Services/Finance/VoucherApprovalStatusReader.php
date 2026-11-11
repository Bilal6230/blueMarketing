<?php
namespace App\Services\Finance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
final class VoucherApprovalStatusReader
{
    public function forSources(int $projectId, string $table, $sourceIds): array
    {
        $ids=collect($sourceIds)->map(fn($id)=>(int)$id)->filter()->unique()->values();
        if($ids->isEmpty() || !Schema::hasTable('daily_voucher_approvals')) return [];
        return DB::table('daily_voucher_approvals as a')
            ->leftJoin('users as u','u.id','=','a.assigned_approver_id')
            ->leftJoin('users as decision_user','decision_user.id','=','a.decided_by')
            ->where('a.project_id',$projectId)->where('a.source_table',$table)
            ->whereIn('a.source_id',$ids)
            ->get(['a.source_id','a.status','a.submitted_by','a.decided_by',
                'u.name as approver_name','decision_user.name as decided_by_name',
                'a.rejection_reason'])
            ->keyBy('source_id')->map(fn($row)=>[
                'status'=>(string)$row->status,
                'submitted_by'=>(int)$row->submitted_by,
                'approver'=>(string)($row->approver_name??''),
                'approved_by'=>(string)$row->status==='approved' ? (string)($row->decided_by_name??'') : '',
                'approved_by_id'=>(string)$row->status==='approved' ? (int)($row->decided_by??0) : null,
                'reason'=>(string)($row->rejection_reason??'')
            ])->all();
    }
}
