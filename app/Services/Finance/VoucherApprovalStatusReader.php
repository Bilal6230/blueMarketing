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
            ->where('a.project_id',$projectId)->where('a.source_table',$table)
            ->whereIn('a.source_id',$ids)
            ->get(['a.source_id','a.status','u.name as approver_name','a.rejection_reason'])
            ->keyBy('source_id')->map(fn($row)=>[
                'status'=>(string)$row->status,
                'approver'=>(string)($row->approver_name??''),
                'reason'=>(string)($row->rejection_reason??'')
            ])->all();
    }
}
