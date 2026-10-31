<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Approval compatibility boundary for legacy V1 document state -> isolated
 * V2 posting eligibility. Reads source state but never approves/edits it.
 * Writer transaction MUST independently re-check source/ownership to prevent
 * TOCTOU and concurrent approval/revocation races.
 */
final class V2SourceApprovalReadiness
{
    private const TABLES = [
        'journal' => 'journal_vouchers',
        'sales' => 'journal_vouchers',
        'cash' => 'customer_ledger',
        'customer' => 'customer_ledger',
        'commission' => 'commision_vouchers',
        'booking' => 'booking_vouchers',
        'labour' => 'labour_ledgers',
        'stock_entry' => 'stock_entries',
        'stock_bill' => 'stock_bills',
    ];

    public function inspect(
        int $projectId, string $kind, int $sourceId, ?string $series = null
    ): array {
        V2IsolatedConnectionGuard::assertAllowed();
        if ($projectId < 1 || $sourceId < 1 || !isset(self::TABLES[$kind])) {
            throw new \InvalidArgumentException('V2_APPROVAL_READINESS_INPUT_INVALID');
        }
        $table = self::TABLES[$kind];
        $stock = in_array($kind, ['stock_entry', 'stock_bill'], true);
        // Never reveal whether another project's source document exists.
        $query = DB::table($table)->where('id', $sourceId);
        if (!$stock) {
            $query->where('project_id', $projectId);
        }
        $source = $stock ? null : $query->first();
        if ($stock) {
            // No project_id on stock sources: require the independently
            // authorized project/source FK BEFORE inspecting stock data.
            $approvalTable = $kind === 'stock_entry'
                ? 'finance_stock_entry_project_approvals'
                : 'finance_stock_bill_project_approvals';
            $fk = $kind === 'stock_entry' ? 'stock_entry_id' : 'stock_bill_id';
            $approved = DB::table($approvalTable)
                ->where($fk, $sourceId)->where('project_id', $projectId)
                ->first();
            $source = $approved ? $query->first() : null;
        }
        $reason = null;
        $sourceSeries = null;
        if (!$source) {
            $reason = $stock
                ? 'STOCK_PROJECT_APPROVAL_REQUIRED' : 'SOURCE_NOT_FOUND';
        } elseif (in_array($kind, ['journal', 'sales'], true)) {
            $sourceSeries = (string) $source->type;
            if ((int) $source->project_id !== $projectId
                || $source->deleted_at !== null
                || $sourceSeries !== ($kind === 'sales' ? 'SV' : 'JV')) {
                $reason = 'SOURCE_PROJECT_OR_TYPE_INVALID';
            }
        } elseif ($kind === 'commission') {
            $sourceSeries = 'CV';
            if ((int) $source->project_id !== $projectId
                || $source->deleted_at !== null
                || (string) $source->status !== 'approved') {
                $reason = 'SOURCE_NOT_APPROVED';
            }
        } elseif (in_array($kind, ['cash', 'customer'], true)) {
            $sourceSeries = (string) $source->transaction_type;
            $allowed = $kind === 'cash' ? ['CR', 'CP'] : ['PPR', 'EC', 'ECP'];
            if ((int) $source->project_id !== $projectId
                || (int) $source->is_active !== 1
                || !in_array($sourceSeries, $allowed, true)
                || ($kind === 'customer' && (
                    !in_array($series, $allowed, true)
                    || $sourceSeries !== $series
                    || (int) ($source->is_approve ?? 0) !== 1
                ))) {
                $reason = 'SOURCE_NOT_APPROVED_OR_SERIES_INVALID';
            }
        } elseif ($kind === 'booking') {
            $sourceSeries = (string) $source->voucher_series;
            if ((int) $source->project_id !== $projectId
                || (int) $source->is_active !== 1
                || (int) $source->is_approve !== 1) {
                $reason = 'SOURCE_NOT_APPROVED';
            } elseif ($source->customer_ledger_id !== null
                && !DB::table('customer_ledger')
                    ->where('id', $source->customer_ledger_id)
                    ->where('project_id', $projectId)->exists()) {
                $reason = 'LINKED_CUSTOMER_SOURCE_PROJECT_INVALID';
            }
        } elseif ($kind === 'labour') {
            $sourceSeries = 'LAB';
            if ((int) $source->project_id !== $projectId
                || !DB::table('labours')->where('id', $source->labour_id)
                    ->where('project_id', $projectId)->exists()) {
                $reason = 'LABOUR_SOURCE_PROJECT_INVALID';
            }
        } else {
            $sourceSeries = $kind === 'stock_entry' ? 'STOCK_ENTRY' : 'STOCK_BILL';
            $approvalTable = $kind === 'stock_entry'
                ? 'finance_stock_entry_project_approvals'
                : 'finance_stock_bill_project_approvals';
            $fk = $kind === 'stock_entry' ? 'stock_entry_id' : 'stock_bill_id';
            try {
                $snapshot = DB::transaction(
                    fn (): array => (new V2StockSourceSnapshot())->read($kind, $sourceId)
                );
                $approval = DB::table($approvalTable)
                    ->where($fk, $sourceId)->where('project_id', $projectId)
                    ->first();
                if (!$approval || !$approval->approved_at
                    || !hash_equals(
                        (string) $approval->source_sha256, $snapshot['fingerprint']
                    )) {
                    $reason = 'STOCK_PROJECT_APPROVAL_REQUIRED';
                }
            } catch (\DomainException $e) {
                $reason = 'STOCK_SOURCE_NOT_ELIGIBLE';
            }
        }

        return [
            'project_id' => $projectId,
            'source_kind' => $kind,
            'source_id' => $sourceId,
            'source_series' => $sourceSeries,
            'eligible' => $reason === null,
            'reason_code' => $reason,
            'scope' => 'isolated_v2_source_readiness_only',
        ];
    }
}
