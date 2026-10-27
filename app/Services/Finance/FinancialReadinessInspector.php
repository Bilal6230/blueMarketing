<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only project finance deployment readiness inventory.
 * Counts legacy JV-type rows as unverified, never attributes them to a JV/CV
 * simply because a numeric header ID happens to match.
 */
final class FinancialReadinessInspector
{
    public function inspect(int $projectId, ?array $validatedSchema = null): array
    {
        if ($projectId < 1 || !DB::table('projects')->where('id', $projectId)->exists()) {
            throw new \DomainException('Valid existing project ID required.');
        }

        $attributions = Schema::hasTable('ledger_source_attributions');
        $requests = Schema::hasTable('financial_posting_requests');
        // Table existence alone cannot protect concurrent typed postings.
        // Align per-project readiness with the actual validated metadata schema.
        $schemaReport = $validatedSchema ?? app(FinanceSchemaPreflight::class)->inspect();
        $schemaReady = $schemaReport['status'] === 'schema_verified';
        $scope = DB::table('ledgers')->where('type', 'JV')
            ->whereIn('project_head_subheads_id', function ($q) use ($projectId) {
                $q->select('id')->from('project_head_subheads')->where('project_id', $projectId);
            });
        $candidateCount = (clone $scope)->count();
        $verifiedCount = $attributions
            ? (clone $scope)->whereExists(function ($q) use ($projectId) {
                $q->selectRaw('1')->from('ledger_source_attributions as a')
                    ->whereColumn('a.ledger_id', 'ledgers.id')
                    ->where('a.project_id', $projectId)
                    ->whereIn('a.source_kind', ['journal_voucher', 'commission_voucher'])
                    ->whereIn('a.evidence_kind', [
                        'new_write_transaction', 'verified_external_reference', 'manual_review',
                    ])->whereColumn('a.source_id', 'ledgers.type_id');
            })->count()
            : 0;

        $verifiedCount = min($candidateCount, $verifiedCount);
        $unverifiedCount = $candidateCount - $verifiedCount;
        $jvHeaders = DB::table('journal_vouchers')->where('project_id', $projectId)->count();
        $cvHeaders = DB::table('commision_vouchers')->where('project_id', $projectId)->count();

        $blockers = [];
        if (!$schemaReady) {
            $blockers[] = 'finance_metadata_schema_missing_or_partial';
        }
        if ($unverifiedCount > 0) {
            $blockers[] = 'unverified_legacy_jv_type_ledger_rows';
        }
        if ($candidateCount === 0 && ($jvHeaders > 0 || $cvHeaders > 0)) {
            $blockers[] = 'voucher_headers_without_candidate_jv_ledgers';
        }

        return [
            'project_id' => $projectId,
            'status' => $blockers === [] ? 'preliminary_ready' : 'blocked',
            'schema' => ['ledger_source_attributions' => $attributions,
                'financial_posting_requests' => $requests,
                'status' => $schemaReport['status'],
                'blockers' => $schemaReport['blockers']],
            'jv_headers' => $jvHeaders,
            'cv_headers' => $cvHeaders,
            'candidate_jv_type_ledgers' => $candidateCount,
            'apparently_attributed_ledgers' => $verifiedCount,
            'unverified_jv_type_ledgers' => $unverifiedCount,
            'blockers' => $blockers,
            'limitations' => [
                'Numbers are a read-only inventory, not a complete accounting reconciliation.',
                'Apparently attributed does not prove balances or entire historic source ownership.',
                'MySQL concurrent posting and production schema rollout require separate verification.',
            ],
        ];
    }
}
