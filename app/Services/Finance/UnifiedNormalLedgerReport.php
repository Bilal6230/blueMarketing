<?php

namespace App\Services\Finance;

use App\Models\Ledger;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only mixed-ledger adapter for the existing LOCAL normal ERP.
 *
 * The legacy ledgers and typed V2 lines remain in their original tables.
 * A typed line with legacy_ledger_id already has a legacy representation
 * and must never be included twice. No inference through type/type_id.
 */
final class UnifiedNormalLedgerReport
{
    public function available(): bool
    {
        return V2OriginalWorkflowAccess::mergedWriterAllowed()
            && Schema::hasTable('finance_posting_batches')
            && Schema::hasTable('finance_posting_lines')
            && Schema::hasTable('finance_posting_source_claims');
    }

    public function rows(int $projectId, ?string $from = null, ?string $through = null): array
    {
        if ($projectId < 1 || !$this->available()) {
            throw new \DomainException('UNIFIED_LEDGER_UNAVAILABLE');
        }
        foreach ([$from, $through] as $date) {
            if ($date === null) {
                continue;
            }
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('UNIFIED_LEDGER_INVALID_DATE');
            }
        }
        if ($from !== null && $through !== null && $from > $through) {
            throw new \InvalidArgumentException('UNIFIED_LEDGER_DATE_ORDER');
        }

        $legacy = Ledger::with([
            'projectHeadSubhead.project',
            'projectHeadSubhead.headAccounting',
            'projectHeadSubhead.subheadAccounting',
            'createdBy:id,name',
        ])->where('is_active', 1)
            ->whereHas('projectHeadSubhead', fn ($q) => $q->where('project_id', $projectId));
        if ($from !== null) {
            $legacy->whereDate('date', '>=', $from);
        }
        if ($through !== null) {
            $legacy->whereDate('date', '<=', $through);
        }
        $display = app(LedgerVoucherDisplayService::class);
        $rows = [];
        foreach ($legacy->get() as $ledger) {
            $account = $ledger->projectHeadSubhead;
            $rows[] = [
                'id' => $ledger->id,
                'date' => (string) $ledger->date,
                'type' => $ledger->type,
                'voucher_number' => $ledger->voucher_number,
                'display_voucher_number' => $display->display($ledger, $projectId),
                'amount_in' => (string) ($ledger->amount_in ?? 0),
                'amount_out' => (string) ($ledger->amount_out ?? 0),
                'detail' => $ledger->detail ?? '',
                'reference' => $ledger->reference ?? '',
                'project_head_subheads_id' => (int) $ledger->project_head_subheads_id,
                'head_accounting_id' => $account?->head_accounting_id,
                'subhead_accounting_id' => $account?->subhead_accounting_id,
                'acct_type' => $account?->headAccounting?->acct_type,
                'project_name' => $account?->project?->project ?? '',
                'accountent_name' => $ledger->createdBy?->name ?? '',
                'head_account_name' => $account?->headAccounting?->name ?? '',
                'subhead_account_name' => $account?->subheadAccounting?->name ?? '',
                'action' => '',
                'scope' => 'legacy',
            ];
        }

        // The original typed report verifies source claims and real FK links
        // before furnishing document numbers. Keep only lines without legacy
        // ledger attribution; these are absent from the old ledger reader.
        $postingLines = (new V2PostingGateway())->postingLines($projectId, $from, $through);
        $unlinked = [];
        foreach ($postingLines as $line) {
            if ($line['legacy_ledger_id'] === null) {
                $unlinked[(int) $line['posting_line_id']] = true;
            }
        }
        if ($unlinked) {
            foreach ((new V2OriginalLedgerReport())->rows($projectId, $from, $through) as $line) {
                if (isset($unlinked[(int) $line['posting_line_id']])) {
                    $line['scope'] = 'typed_without_legacy_link';
                    $rows[] = $line;
                }
            }
        }
        usort($rows, static fn (array $a, array $b): int =>
            strcmp((string) $a['date'], (string) $b['date'])
            ?: strcmp((string) $a['id'], (string) $b['id']));
        return $rows;
    }

    public function totals(array $rows, string $group): array
    {
        if (!in_array($group, ['project_head_subheads_id', 'head_accounting_id', 'acct_type'], true)) {
            throw new \InvalidArgumentException('UNIFIED_LEDGER_INVALID_GROUP');
        }
        $totals = [];
        foreach ($rows as $row) {
            $key = (string) ($row[$group] ?? '');
            if ($key === '') {
                throw new \DomainException('UNIFIED_LEDGER_MISSING_GROUP_ACCOUNT');
            }
            $totals[$key] ??= [
                $group => $row[$group],
                'project_head_subheads_id' => $row['project_head_subheads_id'] ?? null,
                'head_accounting_id' => $row['head_accounting_id'] ?? null,
                'acct_type' => $row['acct_type'] ?? null,
                'project_name' => $row['project_name'] ?? '',
                'head_account_name' => $row['head_account_name'] ?? '',
                'subhead_account_name' => $row['subhead_account_name'] ?? '',
                'debit_cents' => 0,
                'credit_cents' => 0,
            ];
            $totals[$key]['debit_cents'] += $this->cents($row['amount_in']);
            $totals[$key]['credit_cents'] += $this->cents($row['amount_out']);
        }
        foreach ($totals as &$entry) {
            $entry['total_amount_in'] = $this->money($entry['debit_cents']);
            $entry['total_amount_out'] = $this->money($entry['credit_cents']);
            $entry['balance_amount'] = $this->money($entry['debit_cents'] - $entry['credit_cents']);
            if ($group === 'acct_type') {
                $entry['head_account_name'] = getAccountTypeName($entry['acct_type']);
            }
            unset($entry['debit_cents'], $entry['credit_cents']);
        }
        unset($entry);
        return array_values($totals);
    }

    private function cents(mixed $value): int
    {
        $raw = (string) $value;
        if (!preg_match('/^(-?)(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $raw, $m)) {
            throw new \DomainException('UNIFIED_LEDGER_INVALID_AMOUNT');
        }
        $result = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
        return $m[1] === '-' ? -$result : $result;
    }

    private function money(int $value): string
    {
        return ($value < 0 ? '-' : '').intdiv(abs($value), 100).'.'
            .str_pad((string) (abs($value) % 100), 2, '0', STR_PAD_LEFT);
    }
}
