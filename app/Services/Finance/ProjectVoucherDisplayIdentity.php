<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProjectVoucherDisplayIdentity
{
    public function lookup(int $projectId, string $sourceKind, int $sourceId): ?string
    {
        if ($projectId < 1 || $sourceId < 1 || !Schema::hasTable('project_voucher_display_numbers')) return null;
        return DB::table('project_voucher_display_numbers')->where('project_id', $projectId)
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->value('display_number') ?: null;
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

        $sequence = app(ProjectVoucherFiscalSequence::class)->next($projectId, $series, $date);
        $display = app(ProjectVoucherDisplay::class)->format($projectId, $series, $sequence, $date);
        DB::table('project_voucher_display_numbers')->insert([
            'project_id' => $projectId, 'source_kind' => $sourceKind, 'source_id' => $sourceId,
            'series' => $series, 'financial_year' => (int) substr($date, 0, 4),
            'display_number' => $display, 'created_at' => now(),
        ]);
        return $display;
    }
}
