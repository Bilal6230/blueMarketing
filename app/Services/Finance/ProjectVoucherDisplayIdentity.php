<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherDisplayIdentity
{
    public function lookup(int $projectId, string $sourceKind, int $sourceId): ?string
    {
        if ($projectId < 1 || $sourceId < 1 || !Schema::hasTable('project_voucher_display_numbers')) return null;
        $identity = DB::table('project_voucher_display_numbers')->where('project_id', $projectId)
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->first(['series','display_number']);
        // For the real CR and legacy CP-as-DR voucher form, the stored source
        // type_id is the visible voucher number, not a configurable fiscal
        // prefix. Other voucher families keep their historical identity rules.
        if ($sourceKind === 'customer_ledger' && in_array((string) ($identity->series ?? ''), ['CR','DR','CP','CCR','CDR'], true)) {
            $source = DB::table('customer_ledger')->where('id',$sourceId)
                ->where('project_id',$projectId)->first(['transaction_type','type_id']);
            if ($source && (int)$source->type_id > 0) {
                $label = $source->transaction_type === 'CP' ? 'DR' : $source->transaction_type;
                return $label.'-'.$source->type_id;
            }
        }
        $number = $identity->display_number ?? null;
        // CP is the immutable legacy source type; show its user-facing label as DR.
        return $number ? preg_replace('/^CP-/', 'DR-', (string) $number) : null;
    }

    public function series(int $projectId, string $sourceKind, int $sourceId): ?string
    {
        if ($projectId < 1 || $sourceId < 1 || !Schema::hasTable('project_voucher_display_numbers')) return null;
        return DB::table('project_voucher_display_numbers')->where('project_id', $projectId)
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->value('series') ?: null;
    }

    public function assign(int $projectId, string $sourceKind, int $sourceId, string $series, string $date): ?string
    {
        if (DB::transactionLevel() < 1) throw new \DomainException('VOUCHER_DISPLAY_REQUIRES_TRANSACTION');
        if (!in_array($sourceKind, ['customer_ledger', 'journal_voucher', 'commission_voucher', 'booking_voucher', 'ledger'], true)
            || $sourceId < 1 || !in_array($series, ProjectVoucherDisplay::SERIES, true)) {
            throw new \DomainException('INVALID_VOUCHER_DISPLAY_SOURCE');
        }
        if (!Schema::hasTable('project_voucher_display_numbers')) return null;

        $existing = DB::table('project_voucher_display_numbers')->where('project_id', $projectId)
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->series !== $series) throw new \DomainException('VOUCHER_DISPLAY_SOURCE_CONFLICT');
            return (string) $existing->display_number;
        }

        if ($sourceKind === 'customer_ledger' && in_array($series, ['CR','DR','CP','CCR','CDR'], true)) {
            $source = DB::table('customer_ledger')->where('id', $sourceId)
                ->where('project_id', $projectId)->lockForUpdate()
                ->first(['transaction_type', 'type_id']);
            if (!$source || !in_array($source->transaction_type, ['CR','CP','CCR','CDR'], true) ||
                (int)$source->type_id <= 0 ||
                (($series === 'CCR' || $series === 'CDR')
                    ? $series !== $source->transaction_type
                    : (($series === 'CR') !== ($source->transaction_type === 'CR')))) {
                throw new \DomainException('VOUCHER_SOURCE_IDENTITY_MISMATCH');
            }
            $display = ($source->transaction_type === 'CP' ? 'DR' : $source->transaction_type).'-'.$source->type_id;
        } else {
            $sequence = app(ProjectVoucherFiscalSequence::class)->next($projectId, $series, $date);
            $display = app(ProjectVoucherDisplay::class)->format($projectId, $series, $sequence, $date);
        }
        DB::table('project_voucher_display_numbers')->insert([
            'project_id' => $projectId, 'source_kind' => $sourceKind, 'source_id' => $sourceId,
            'series' => $series, 'financial_year' => (int) substr($date, 0, 4),
            'display_number' => $display, 'created_at' => now(),
        ]);
        return $display;
    }
}
