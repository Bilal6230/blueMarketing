<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isolated V2 commission draft lifecycle. No legacy ledger writes.
 * Drafts may be edited only before approval; posted data is immutable.
 */
final class CommissionDraftWorkflow
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
            if (!DB::table('project_head_subheads as account')
                ->join('head_accountings as head', 'head.id', '=', 'account.head_accounting_id')
                ->join('subhead_accountings as sub', 'sub.id', '=', 'account.subhead_accounting_id')
                ->where('account.id', $line['account_id'])->where('account.project_id', $projectId)
                ->where('head.is_active', 1)->where('sub.is_active', 1)->exists()) {
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
        ?int $actorId = null, array $metadata = []
    ): int {
        FinanceConnectionGuard::assertAllowed();
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
        $metadata = array_intersect_key($metadata, array_flip(['reference', 'description']));
        return DB::transaction(function () use ($projectId, $number, $date, $lines, $actorId, $metadata): int {
            if (!DB::table('projects')->where('id', $projectId)->lockForUpdate()->exists()) {
                throw new \DomainException('V2_CV_PROJECT_MISSING');
            }
            [$prepared, $amount] = $this->lines($projectId, $lines);
            if (DB::table('commision_vouchers')->where('project_id', $projectId)
                ->where('voucher_number', $number)->exists()) {
                throw new \DomainException('V2_CV_NUMBER_CONFLICT');
            }
            $id = DB::table('commision_vouchers')->insertGetId($metadata + [
                'project_id' => $projectId, 'voucher_number' => $number,
                'date' => $date, 'total_debit' => $amount, 'total_credit' => $amount,
                'status' => 'draft', 'created_by' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(ProjectVoucherDisplayIdentity::class)->assign($projectId, 'commission_voucher', (int) $id, 'CV', $date);
            foreach ($prepared as $line) {
                DB::table('commision_voucher_details')->insert($line + [
                    'commision_voucher_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            // Draft leg records are persisted with the actual detail FK but
            // cannot affect balances until the approved posting is committed.
            app(CommissionLedgerSynchronizer::class)->ensureDraft($projectId,(int)$id,$actorId);
            return (int) $id;
        }, 3);
    }

    /**
     * Replace lines only while a voucher is an unposted draft.
     * The project row serializes competing edits and approval attempts.
     */
    public function updateDraft(
        int $projectId, int $sourceId, string $date, array $lines, array $metadata = []
    ): void {
        FinanceConnectionGuard::assertAllowed();
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($projectId < 1 || $sourceId < 1 || !$d || $d->format('Y-m-d') !== $date) {
            throw new \DomainException('V2_CV_INVALID_HEADER');
        }

        $metadata = array_intersect_key($metadata, array_flip(['reference', 'description']));
        DB::transaction(function () use ($projectId, $sourceId, $date, $lines, $metadata): void {
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
            $activeDetails=DB::table('commision_voucher_details')
                ->where('commision_voucher_id', $sourceId)->whereNull('deleted_at')
                ->orderBy('id')->lockForUpdate()->get();
            $candidateHeader=[
                'date'=>$date,
                'reference'=>array_key_exists('reference',$metadata) ? $metadata['reference'] : $header->reference,
                'description'=>array_key_exists('description',$metadata) ? $metadata['description'] : $header->description,
            ];
            $synchronizer=app(CommissionLedgerSynchronizer::class);
            $synchronizer->assertDraftCanReplace($projectId,$sourceId);
            // IDs come exclusively from the validated form and the locked
            // voucher details. No account/amount or array-index ID guessing.
            $submittedIds=[];
            foreach ($lines as $index=>$submitted) {
                $detailId=(int)($submitted['detail_id']??0);
                if ($detailId<1) continue;
                if (isset($submittedIds[$detailId]) || !$activeDetails->contains('id',$detailId)) {
                    throw new \DomainException('Commission detail ID is duplicated or belongs to another voucher.');
                }
                $submittedIds[$detailId]=true;
            }
            $sameIds=$activeDetails->count()===count($prepared)
                && $activeDetails->pluck('id')->map(fn($id)=>(int)$id)->values()->all()
                    ===array_map(fn($line)=>(int)($line['detail_id']??0),$lines);
            if ($sameIds && app(VoucherUnchangedUpdateDetector::class)
                ->same($header,$activeDetails,$candidateHeader,$prepared)) {
                $synchronizer->ensureDraft($projectId,$sourceId);
                return;
            }
            // Older drafts may not have their ledger projection yet. Create
            // the missing inactive FK-linked rows BEFORE changing any detail.
            $synchronizer->ensureDraft($projectId,$sourceId);
            foreach ($activeDetails as $detail) {
                if (isset($submittedIds[(int)$detail->id])) continue;
                DB::table('ledgers')->where('type','CV')->where('type_id',$sourceId)
                    ->where('commision_voucher_detail_id',$detail->id)
                    ->where('is_active',0)->lockForUpdate()->firstOrFail();
                DB::table('commision_voucher_details')->where('id',$detail->id)
                    ->where('commision_voucher_id',$sourceId)
                    ->whereNull('deleted_at')->update(['deleted_at'=>now(),'updated_at'=>now()]);
            }
            foreach ($prepared as $index=>$line) {
                $detailId=(int)($lines[$index]['detail_id']??0);
                $values=[
                    'account_id'=>$line['account_id'],'debit'=>$line['debit'],
                    'credit'=>$line['credit'],'description'=>$line['description'],
                    'updated_at'=>now(),
                ];
                if ($detailId>0) {
                    DB::table('commision_voucher_details')->where('id',$detailId)
                        ->where('commision_voucher_id',$sourceId)->whereNull('deleted_at')
                        ->update($values);
                } else {
                    $detailId=(int)DB::table('commision_voucher_details')->insertGetId($values+[
                        'commision_voucher_id'=>$sourceId,'created_at'=>now(),
                    ]);
                }
                // Draft ledger evidence has not posted yet; update it in
                // place, preserving each exact detail and ledger PK. Once
                // approved, this draft mutation path is inaccessible.
                $ledgerValues=[
                    'project_head_subheads_id'=>$line['account_id'],
                    'amount_in'=>$line['debit'],'amount_out'=>$line['credit'],
                    'detail'=>$line['description']??'','reference'=>$candidateHeader['reference'],
                    'date'=>$date,'updated_at'=>now(),
                ];
                $affected=DB::table('ledgers')->where('type','CV')->where('type_id',$sourceId)
                    ->where('commision_voucher_detail_id',$detailId)->where('is_active',0)
                    ->update($ledgerValues);
                if ($affected===0 && !DB::table('ledgers')->where('type','CV')
                    ->where('type_id',$sourceId)->where('commision_voucher_detail_id',$detailId)
                    ->where('is_active',0)->exists()) {
                    // A truly new row is created through the FK-validated
                    // synchronizer after the header has been updated.
                    continue;
                }
            }
            DB::table('commision_vouchers')->where('id', $sourceId)->update($metadata + [
                'date' => $date, 'total_debit' => $amount, 'total_credit' => $amount,
                'updated_at' => now(),
            ]);
            $synchronizer->ensureDraft($projectId,$sourceId);
        }, 3);
    }

    public function cancelDraft(int $projectId, int $sourceId): void
    {
        FinanceConnectionGuard::assertAllowed();
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
            // Check exact linked ledger ownership before archiving the draft.
            app(CommissionLedgerSynchronizer::class)->assertDraftCanReplace($projectId,$sourceId);
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
        FinanceConnectionGuard::assertAllowed();
        return DB::transaction(function () use ($projectId, $sourceId, $requestKey, $actorId): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $source = DB::table('commision_vouchers')
                ->where('id', $sourceId)->where('project_id', $projectId)
                ->lockForUpdate()->first();
            if (!$source || $source->deleted_at !== null
                || !in_array($source->status, ['draft', 'approved'], true)) {
                throw new \DomainException('V2_CV_NOT_APPROVABLE');
            }
            // Numeric IDs are local to their source tables: a JV header or
            // detail may have the same integer as this CV without sharing an
            // accounting record. Ownership is proven exclusively by the
            // enforced CV detail FK and exact source/project links below.
            // A legacy JV type_id coincidence must never block CV approval.
            if ($source->status === 'draft') {
                // Only a draft needs pre-posting inactive ledger integrity
                // verification. Approved retries validate the existing
                // posting and active leg links further below.
                app(CommissionLedgerSynchronizer::class)
                    ->assertDraftCanReplace($projectId,$sourceId);
                DB::table('commision_vouchers')->where('id', $sourceId)
                    ->update(['status' => 'approved', 'updated_at' => now()]);
            }
            // Nested transaction: approval, immutable finance posting and
            // legacy ledger activation must either all commit or all roll back.
            $batch=(new PostingGateway())->postCommission(
                $projectId, $sourceId, $requestKey, $actorId
            );
            app(CommissionLedgerSynchronizer::class)->activateApproved(
                $projectId,$sourceId,$actorId
            );
            return $batch;
        }, 3);
    }
}
