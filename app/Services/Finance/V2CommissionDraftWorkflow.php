<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isolated V2 commission draft lifecycle. No legacy ledger writes.
 * Drafts may be edited only before approval; posted data is immutable.
 */
final class V2CommissionDraftWorkflow
{
    private function cents(mixed $value): int
    {
        $s = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $s, $m)) {
            throw new \DomainException('V2_CV_INVALID_MONEY');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function lines(int $projectId, array $lines): array
    {
        if (count($lines) < 2 || count($lines) > 100) {
            throw new \DomainException('V2_CV_INVALID_LINES');
        }
        $debit = $credit = 0;
        $prepared = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !isset($line['account_id'], $line['debit'], $line['credit'])
                || !is_int($line['account_id'])) {
                throw new \DomainException('V2_CV_INVALID_LINE');
            }
            if (!DB::table('project_head_subheads')->where('id', $line['account_id'])
                ->where('project_id', $projectId)->exists()) {
                throw new \DomainException('V2_CV_FOREIGN_ACCOUNT');
            }
            $dr = $this->cents($line['debit']);
            $cr = $this->cents($line['credit']);
            if (($dr > 0) === ($cr > 0)) {
                throw new \DomainException('V2_CV_INVALID_LINE_SIDE');
            }
            $debit += $dr;
            $credit += $cr;
            $prepared[] = [
                'account_id' => $line['account_id'],
                'debit' => $this->money($dr),
                'credit' => $this->money($cr),
                'description' => $line['description'] ?? null,
            ];
        }
        if ($debit < 1 || $debit !== $credit) {
            throw new \DomainException('V2_CV_UNBALANCED');
        }
        return [$prepared, $this->money($debit)];
    }

    public function createDraft(
        int $projectId, string $number, string $date, array $lines,
        ?int $actorId = null
    ): int {
        V2IsolatedConnectionGuard::assertAllowed();
        if ($projectId < 1 || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $number)
            || !($d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date))
            || $d->format('Y-m-d') !== $date) {
            throw new \DomainException('V2_CV_INVALID_HEADER');
        }
        foreach (['commision_vouchers', 'commision_voucher_details', 'project_head_subheads'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException('V2_CV_MISSING_SCHEMA');
            }
        }
        return DB::transaction(function () use ($projectId, $number, $date, $lines, $actorId): int {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_CV_PROJECT_MISSING');
            }
            [$prepared, $amount] = $this->lines($projectId, $lines);
            if (DB::table('commision_vouchers')->where('project_id', $projectId)
                ->where('voucher_number', $number)->exists()) {
                throw new \DomainException('V2_CV_NUMBER_CONFLICT');
            }
            $id = DB::table('commision_vouchers')->insertGetId([
                'project_id' => $projectId, 'voucher_number' => $number,
                'date' => $date, 'total_debit' => $amount, 'total_credit' => $amount,
                'status' => 'draft', 'created_by' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($prepared as $line) {
                DB::table('commision_voucher_details')->insert($line + [
                    'commision_voucher_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return (int) $id;
        }, 3);
    }

    /**
     * Replace lines only while a voucher is an unposted draft.
     * The project row serializes competing edits and approval attempts.
     */
    public function updateDraft(
        int $projectId, int $sourceId, string $date, array $lines
    ): void {
        V2IsolatedConnectionGuard::assertAllowed();
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($projectId < 1 || $sourceId < 1 || !$d || $d->format('Y-m-d') !== $date) {
            throw new \DomainException('V2_CV_INVALID_HEADER');
        }

        DB::transaction(function () use ($projectId, $sourceId, $date, $lines): void {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_CV_PROJECT_MISSING');
            }
            $header = DB::table('commision_vouchers')
                ->where('id', $sourceId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$header || $header->deleted_at !== null || $header->status !== 'draft'
                || DB::table('finance_posting_commission_sources')
                    ->where('commision_voucher_id', $sourceId)->exists()) {
                throw new \DomainException('V2_CV_DRAFT_IMMUTABLE');
            }
            [$prepared, $amount] = $this->lines($projectId, $lines);
            DB::table('commision_voucher_details')
                ->where('commision_voucher_id', $sourceId)->delete();
            foreach ($prepared as $line) {
                DB::table('commision_voucher_details')->insert($line + [
                    'commision_voucher_id' => $sourceId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('commision_vouchers')->where('id', $sourceId)->update([
                'date' => $date, 'total_debit' => $amount, 'total_credit' => $amount,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    public function cancelDraft(int $projectId, int $sourceId): void
    {
        V2IsolatedConnectionGuard::assertAllowed();
        DB::transaction(function () use ($projectId, $sourceId): void {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_CV_PROJECT_MISSING');
            }
            $header = DB::table('commision_vouchers')
                ->where('id', $sourceId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$header || $header->deleted_at !== null || $header->status !== 'draft'
                || DB::table('finance_posting_commission_sources')
                    ->where('commision_voucher_id', $sourceId)->exists()) {
                throw new \DomainException('V2_CV_DRAFT_IMMUTABLE');
            }
            DB::table('commision_voucher_details')
                ->where('commision_voucher_id', $sourceId)
                ->update(['deleted_at' => now(), 'updated_at' => now()]);
            DB::table('commision_vouchers')->where('id', $sourceId)->update([
                'deleted_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function approveAndPost(
        int $projectId, int $sourceId, string $requestKey, ?int $actorId = null
    ): int {
        V2IsolatedConnectionGuard::assertAllowed();
        return DB::transaction(function () use ($projectId, $sourceId, $requestKey, $actorId): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $source = DB::table('commision_vouchers')
                ->where('id', $sourceId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$source || $source->deleted_at !== null
                || !in_array($source->status, ['draft', 'approved'], true)) {
                throw new \DomainException('V2_CV_NOT_APPROVABLE');
            }
            if ($source->status === 'draft') {
                DB::table('commision_vouchers')->where('id', $sourceId)
                    ->update(['status' => 'approved', 'updated_at' => now()]);
            }
            // Nested DB transaction: a failed posting rolls back approval too.
            return (new V2PostingGateway())->postCommission(
                $projectId, $sourceId, $requestKey, $actorId
            );
        }, 3);
    }
}
