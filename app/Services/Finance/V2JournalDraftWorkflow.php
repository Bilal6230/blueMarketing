<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Only unposted, unlinked isolated JV drafts can be corrected.
 * Never touches V1 ledgers or already approved V2 posting history.
 */
final class V2JournalDraftWorkflow
{
    public function updateUnposted(
        int $projectId, int $sourceId, string $date, array $lines
    ): void {
        V2IsolatedConnectionGuard::assertAllowed();
        $dateValue = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($projectId < 1 || $sourceId < 1 || !$dateValue
            || $dateValue->format('Y-m-d') !== $date || count($lines) < 2
            || count($lines) > 100) {
            throw new \DomainException('V2_JV_DRAFT_INVALID_INPUT');
        }
        DB::transaction(function () use ($projectId, $sourceId, $date, $lines): void {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_JV_PROJECT_MISSING');
            }
            $header = DB::table('journal_vouchers')->where('id', $sourceId)
                ->where('project_id', $projectId)->lockForUpdate()->first();
            if (!$header || $header->deleted_at !== null || $header->type !== 'JV'
                || DB::table('finance_posting_journal_sources')
                    ->where('journal_voucher_id', $sourceId)->exists()
                || (Schema::hasTable('ledgers') && DB::table('ledgers')
                    ->where(function ($query) use ($sourceId): void {
                        $query->where(function ($legacy) use ($sourceId): void {
                            $legacy->where('type', 'JV')->where('type_id', $sourceId);
                        });
                        // Exact provenance takes precedence over legacy labels:
                        // even a misclassified ledger must keep its linked detail.
                        if (Schema::hasColumn('ledgers', 'journal_voucher_detail_id')) {
                            $query->orWhereIn('journal_voucher_detail_id',
                                DB::table('journal_voucher_details')->select('id')
                                    ->where('journal_voucher_id', $sourceId));
                        }
                    })->exists())) {
                throw new \DomainException('V2_JV_DRAFT_IMMUTABLE');
            }
            $prepared = [];
            $debit = $credit = 0;
            foreach ($lines as $line) {
                if (!is_array($line) || !isset($line['account_id'], $line['debit'], $line['credit'])
                    || !is_int($line['account_id'])
                    || !DB::table('project_head_subheads')->where('id', $line['account_id'])
                        ->where('project_id', $projectId)->exists()) {
                    throw new \DomainException('V2_JV_DRAFT_INVALID_ACCOUNT');
                }
                $amounts = [];
                foreach (['debit', 'credit'] as $field) {
                    $raw = (string) $line[$field];
                    if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $raw, $m)) {
                        throw new \DomainException('V2_JV_DRAFT_MONEY_INVALID');
                    }
                    $amounts[$field] = ((int) $m[1] * 100)
                        + (int) str_pad($m[2] ?? '', 2, '0');
                }
                if (($amounts['debit'] > 0) === ($amounts['credit'] > 0)) {
                    throw new \DomainException('V2_JV_DRAFT_INVALID_SIDE');
                }
                $debit += $amounts['debit'];
                $credit += $amounts['credit'];
                $prepared[] = [
                    'journal_voucher_id' => $sourceId,
                    'account_id' => $line['account_id'],
                    'debit' => $this->money($amounts['debit']),
                    'credit' => $this->money($amounts['credit']),
                    'description' => $line['description'] ?? '',
                ];
            }
            if ($debit < 1 || $debit !== $credit) {
                throw new \DomainException('V2_JV_DRAFT_NOT_BALANCED');
            }
            DB::table('journal_voucher_details')
                ->where('journal_voucher_id', $sourceId)->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
            DB::table('journal_voucher_details')->insert($prepared);
            DB::table('journal_vouchers')->where('id', $sourceId)->update([
                'date' => $date, 'total_debit' => $this->money($debit),
                'total_credit' => $this->money($credit),
            ]);
        }, 3);
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
