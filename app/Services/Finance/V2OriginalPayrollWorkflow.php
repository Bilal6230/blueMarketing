<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class V2OriginalPayrollWorkflow
{
    public function pay(int $projectId, string $key, string $date, string $week, array $workers, array $legs, ?int $actor, ?int $contractorId = null): array
    {
        V2IsolatedConnectionGuard::assertAllowed();
        return DB::transaction(function () use ($projectId, $key, $date, $week, $workers, $legs, $actor, $contractorId): array {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $contractor = null;
            if ($contractorId !== null) {
                $contractor = DB::table('contractors')->where('id', $contractorId)->where('project_id', $projectId)
                    ->where('is_active', true)->lockForUpdate()->first();
                if (!$contractor) { throw new \DomainException('V2_PAYROLL_CONTRACTOR_INVALID'); }
            }
            $canonical = []; $total = 0;
            foreach ($workers as $worker) {
                $id = (int) $worker['id'];
                if (isset($canonical[$id]) || !\App\Models\Labour::query()->whereKey($id)
                    ->where('project_id', $projectId)->lockForUpdate()->first(['id'])) {
                    throw new \DomainException('V2_PAYROLL_WORKER_INVALID');
                }
                $amount = V2OriginalCustomerWorkflow::money($worker['amount']);
                if ($amount === '0.00') { throw new \DomainException('V2_PAYROLL_AMOUNT_INVALID'); }
                $canonical[$id] = ['id' => $id, 'amount' => $amount];
                $total += (int) str_replace('.', '', $amount);
            }
            ksort($canonical);
            if ($contractor) {
                $contractorDebit = $contractorCredit = 0;
                foreach ($legs as $leg) {
                    if ((int) ($leg['account_id'] ?? 0) !== (int) $contractor->project_head_subhead_id) continue;
                    $debit = V2OriginalCustomerWorkflow::money($leg['debit'] ?? '');
                    $credit = V2OriginalCustomerWorkflow::money($leg['credit'] ?? '');
                    $contractorDebit += (int) str_replace('.', '', $debit);
                    $contractorCredit += (int) str_replace('.', '', $credit);
                }
                if (!(($contractorDebit === $total && $contractorCredit === 0)
                    || ($contractorCredit === $total && $contractorDebit === 0))) {
                    throw new \DomainException('V2_PAYROLL_CONTRACTOR_ACCOUNT_NOT_POSTED');
                }
            }
            $requestContext = ['week' => $week, 'workers' => array_values($canonical)];
            if ($contractorId !== null) $requestContext['contractor_id'] = $contractorId;
            $source = [
                'date' => $date, 'amount' => intdiv($total, 100).'.'.str_pad((string) ($total % 100), 2, '0', STR_PAD_LEFT),
                'description' => 'Labour payroll '.$week, 'payment_type' => 1,
                'request_context' => $requestContext,
            ];
            if ($contractorId !== null) $source['contractor_id'] = $contractorId;
            $result = (new V2OriginalCustomerWorkflow())->create($projectId, 'CP', $key, $source, $legs, $actor);
            if ($result['replayed']) {
                $stored = DB::table('finance_labour_payment_members as member')->join('labour_ledgers as line', 'line.id', '=', 'member.labour_ledger_id')
                    ->where('member.customer_ledger_id', $result['source_id'])->orderBy('line.labour_id')
                    ->get(['line.labour_id', 'line.amount', 'line.project_id', 'line.date', 'line.contractor_id']);
                $actual = [];
                foreach ($stored as $line) {
                    if ((int) $line->project_id !== $projectId || (string) $line->date !== $date
                        || (int) ($line->contractor_id ?? 0) !== (int) ($contractorId ?? 0)) {
                        throw new \DomainException('V2_PAYROLL_REPLAY_CHANGED');
                    }
                    $actual[] = ['id' => (int) $line->labour_id, 'amount' => V2OriginalCustomerWorkflow::money($line->amount)];
                }
                if ($actual !== array_values($canonical)) { throw new \DomainException('V2_PAYROLL_REPLAY_CHANGED'); }
                return $result;
            }
            foreach ($canonical as $worker) {
                $id = DB::table('labour_ledgers')->insertGetId([
                    'project_id' => $projectId, 'labour_id' => $worker['id'], 'amount' => $worker['amount'], 'date' => $date,
                    'contractor_id' => $contractorId,
                    'details' => json_encode(['week' => $week, 'origin' => 'typed_payroll'], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('finance_labour_payment_members')->insert([
                    'labour_ledger_id' => $id, 'customer_ledger_id' => $result['source_id'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $result;
        }, 3);
    }
}
