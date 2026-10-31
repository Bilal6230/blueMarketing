<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2OriginalAttendanceWorkflow
{
    public function post(int $projectId, array $data, array $ids, ?int $actor): int
    {
        V2IsolatedConnectionGuard::assertAllowed();
        return DB::transaction(function () use ($projectId, $data, $ids, $actor): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $site = DB::table('sites')->where('id', $data['site_id'])->where('project_id', $projectId)->lockForUpdate()->first();
            if (!$site) { throw new \DomainException('V2_ATTENDANCE_SITE_INVALID'); }
            $rows = DB::table('labour_attendances')->whereIn('id', $ids)->where('project_id', $projectId)
                ->where('site_id', $site->id)->where('status', 'present')->whereBetween('date', [$data['start_date'], $data['end_date']])
                ->orderBy('id')->lockForUpdate()->get();
            if ($rows->count() !== count($ids)) { throw new \DomainException('V2_ATTENDANCE_SOURCE_INVALID'); }
            $total = 0;
            foreach ($rows as $row) {
                if (!\App\Models\Labour::query()->whereKey($row->labour_id)->where('project_id', $projectId)->lockForUpdate()->first(['id'])) {
                    throw new \DomainException('V2_ATTENDANCE_WORKER_INVALID');
                }
                $amount = V2OriginalCustomerWorkflow::money($row->amount);
                if ($amount === '0.00') { throw new \DomainException('V2_ATTENDANCE_AMOUNT_INVALID'); }
                $total += (int) str_replace('.', '', $amount);
            }
            $amount = intdiv($total, 100).'.'.str_pad((string) ($total % 100), 2, '0', STR_PAD_LEFT);
            if (V2OriginalCustomerWorkflow::money($data['amount']) !== $amount) { throw new \DomainException('V2_ATTENDANCE_TOTAL_MISMATCH'); }
            $mapped = DB::table('finance_labour_attendance_journals')->whereIn('labour_attendance_id', $ids)->lockForUpdate()->get();
            if ($mapped->isNotEmpty()) {
                if ($mapped->count() !== count($ids) || $rows->contains(fn ($row) => $row->voucher_status !== 'created')
                    || $mapped->pluck('journal_voucher_id')->unique()->count() !== 1
                    || !DB::table('finance_posting_batches as batch')->join('finance_posting_journal_sources as source', 'source.posting_batch_id', '=', 'batch.id')
                        ->where('batch.project_id', $projectId)->where('batch.request_key', $data['request_key'])
                        ->where('source.journal_voucher_id', $mapped->first()->journal_voucher_id)->exists()) {
                    throw new \DomainException('V2_ATTENDANCE_ALREADY_POSTED');
                }
            } elseif ($rows->contains(fn ($row) => $row->voucher_status === 'created')) {
                throw new \DomainException('V2_ATTENDANCE_LEGACY_POSTING_UNVERIFIED');
            }
            $input = ['date' => $data['date'], 'description' => 'Labour attendance for '.$site->site_name,
                'reference' => 'LAB-ATT:'.hash('sha256', json_encode([$projectId, (int) $site->id, $rows->pluck('id')->all(), $data['start_date'], $data['end_date']])),
                'accounts' => [], 'sub_accounts' => [], 'debit' => [], 'credit' => [], 'line_description' => []];
            $debitTotal = $creditTotal = 0; $hasSiteAccount = false;
            foreach ($data['posting_lines'] as $leg) {
                $account = \App\Models\ProjectHeadSubhead::query()->whereKey($leg['account_id'])->where('project_id', $projectId)->lockForUpdate()->first();
                if (!$account) { throw new \DomainException('V2_ATTENDANCE_ACCOUNT_INVALID'); }
                $dr = V2OriginalCustomerWorkflow::money($leg['debit']); $cr = V2OriginalCustomerWorkflow::money($leg['credit']);
                $debitTotal += (int) str_replace('.', '', $dr); $creditTotal += (int) str_replace('.', '', $cr);
                $hasSiteAccount = $hasSiteAccount || ((int) $account->id === (int) $site->subhead_accounting_id && $cr === $amount && $dr === '0.00');
                $input['accounts'][] = (int) $account->head_accounting_id; $input['sub_accounts'][] = (int) $account->id;
                $input['debit'][] = $dr; $input['credit'][] = $cr; $input['line_description'][] = $input['description'];
            }
            if (!$hasSiteAccount || $debitTotal !== $total || $creditTotal !== $total) { throw new \DomainException('V2_ATTENDANCE_ALLOCATION_INVALID'); }
            $journal = (new V2OriginalJournalWorkflow())->createAndPost($projectId, $data['request_key'], $input, $actor);
            if ($mapped->isEmpty()) {
                foreach ($ids as $id) {
                    DB::table('finance_labour_attendance_journals')->insert([
                        'labour_attendance_id' => $id, 'journal_voucher_id' => $journal, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('labour_attendances')->whereIn('id', $ids)->where('project_id', $projectId)->update(['voucher_status' => 'created']);
            }
            return $journal;
        }, 3);
    }
}
