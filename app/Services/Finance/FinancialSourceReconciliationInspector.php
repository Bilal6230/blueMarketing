<?php
namespace App\Services\Finance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only, bounded, project-scoped audit of explicit typed provenance. */
final class FinancialSourceReconciliationInspector
{
    public function inspect(int $projectId, int $limit = 100): array
    {
        if ($projectId < 1 || $limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Positive project and limit 1-1000 required.');
        }
        if (!DB::table('projects')->where('id', $projectId)->exists()) {
            throw new \DomainException('Project does not exist.');
        }
        if (!Schema::hasTable('ledger_source_attributions')) {
            return ['status'=>'schema_missing','project_id'=>$projectId,'scanned'=>0,'available'=>0,'issues'=>[],'incomplete'=>true];
        }
        $query = DB::table('ledger_source_attributions')->where('project_id', $projectId);
        $available = (clone $query)->count();
        $issues = [];
        $scanned = 0;
        foreach ((clone $query)->orderBy('id')->limit($limit)->get() as $a) {
            $scanned++;
            $codes = [];
            if (!in_array($a->source_kind, ['journal_voucher','commission_voucher'], true) ||
                !in_array($a->evidence_kind, ['new_write_transaction','verified_external_reference','manual_review'], true)) {
                $codes[] = 'invalid_source_or_evidence';
            }
            $ledger = DB::table('ledgers')->where('id', $a->ledger_id)->first();
            if (!$ledger) {
                $codes[] = 'missing_ledger';
            } else {
                if (!DB::table('project_head_subheads')->where('id', $ledger->project_head_subheads_id)
                    ->where('project_id', $projectId)->exists()) {
                    $codes[] = 'ledger_project_mismatch';
                }
                if ($ledger->type !== 'JV' || (int) $ledger->type_id !== (int) $a->source_id) {
                    $codes[] = 'ledger_source_mismatch';
                }
            }
            $table = $a->source_kind === 'journal_voucher' ? 'journal_vouchers' :
                ($a->source_kind === 'commission_voucher' ? 'commision_vouchers' : null);
            if (!$table || !Schema::hasTable($table)) {
                $codes[] = 'unknown_source_header';
            } else {
                $header = DB::table($table)->where('id', $a->source_id)->where('project_id', $projectId);
                if ($a->source_kind === 'journal_voucher') {
                    $header->whereNull('deleted_at');
                }
                if (!$header->exists()) {
                    $codes[] = 'missing_or_deleted_header';
                }
            }
            if ($codes) {
                $issues[] = ['attribution_id'=>(int)$a->id,'ledger_id'=>(int)$a->ledger_id,'codes'=>$codes];
            }
        }
        return ['status'=>'scanned','project_id'=>$projectId,'scanned'=>$scanned,'available'=>$available,
            'issues'=>$issues,'incomplete'=>$available > $scanned];
    }
}
