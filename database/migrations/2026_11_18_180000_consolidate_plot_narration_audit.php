<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eliminate a redundant plot-only audit table by copying existing evidence
 * into the application's shared voucher edit snapshots. Fail closed on any
 * row that cannot be preserved. No financial source/posting rows are changed.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('finance_plot_voucher_edit_audits')) return;
        if (!Schema::hasTable('daily_voucher_edit_snapshots')) {
            throw new \RuntimeException('Shared voucher edit snapshots missing: plot audit table preserved.');
        }
        $rows=DB::table('finance_plot_voucher_edit_audits')->orderBy('id')->get();
        foreach ($rows as $row) {
            if (!in_array($row->source_kind,['plot_receipt','plot_purchase'],true)
                || !(int)$row->edited_by || !(int)$row->project_id || !(int)$row->source_id) {
                throw new \RuntimeException('Plot audit migration blocked: incomplete edit evidence #'.$row->id);
            }
        }

        // Keep the copy and the source table if a row cannot be represented.
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $before=json_encode([
                    'source_kind'=>$row->source_kind,'source_id'=>(int)$row->source_id,
                    'narration'=>(string)$row->previous_narration,
                ],JSON_THROW_ON_ERROR);
                $after=json_encode([
                    'source_kind'=>$row->source_kind,'source_id'=>(int)$row->source_id,
                    'narration'=>(string)$row->updated_narration,
                ],JSON_THROW_ON_ERROR);
                $reason='Plot narration legacy audit #'.(int)$row->id;
                $alreadyCopied=DB::table('daily_voucher_edit_snapshots')
                    ->where('project_id',$row->project_id)->where('source_id',$row->source_id)
                    ->where('status','plot_note')->where('reason',$reason)->exists();
                if ($alreadyCopied) continue;
                DB::table('daily_voucher_edit_snapshots')->insert([
                    'project_id'=>$row->project_id,'source_id'=>$row->source_id,
                    'approval_id'=>null,'status'=>'plot_note',
                    'before_snapshot'=>$before,'proposed_snapshot'=>$after,
                    'reason'=>$reason,
                    'submitted_by'=>$row->edited_by,'decided_by'=>$row->edited_by,
                    'created_at'=>$row->created_at,'updated_at'=>$row->updated_at,
                ]);
            }
        });
        // FK and audit history are preserved in the shared table. The old
        // source is only dropped after the entire copy finishes successfully.
        Schema::drop('finance_plot_voucher_edit_audits');
    }
    public function down(): void
    {
        // Do not recreate the redundant table or delete migrated audit records.
    }
};
