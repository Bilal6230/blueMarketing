<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EmployeePayrollPaymentLinker
{
    public function link(int $projectId, int $runId, int $itemId, int $sourceId, string $amount, ?int $actorId): int
    {
        abort_unless($projectId > 0 && Schema::hasTable('employee_payroll_payment_links'), 409,
            'Payroll payment link migration is required.');
        $cents = $this->cents($amount);
        abort_unless($cents > 0, 422, 'Payment amount must be positive.');

        return DB::transaction(function () use ($projectId, $runId, $itemId, $sourceId, $cents, $actorId): int {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $run = DB::table('employee_payroll_runs')->where('project_id', $projectId)
                ->where('id', $runId)->lockForUpdate()->first();
            abort_unless($run && $run->status === 'approved', 409, 'Only approved payroll can be linked to payment.');
            $item = DB::table('employee_payroll_run_items')->where('project_id', $projectId)
                ->where('payroll_run_id', $runId)->where('id', $itemId)->lockForUpdate()->first();
            abort_unless($item, 404, 'Payroll item not found in this project and run.');
            $source = DB::table('customer_ledger')->where('project_id', $projectId)
                ->where('id', $sourceId)->where('transaction_type', 'CP')
                ->where('is_active', 1)->where('is_approve', 1)->lockForUpdate()->first();
            abort_unless($source, 422, 'Select an approved, active CP voucher from this project.');
            $sourceCents = $this->cents($source->amount_out);
            abort_unless($sourceCents > 0, 422, 'CP voucher has no payment amount.');
            $posted = DB::table('ledgers as ledger')
                ->join('project_head_subheads as account', 'account.id', '=', 'ledger.project_head_subheads_id')
                ->where('account.project_id', $projectId)->where('ledger.customer_ledger_id', $sourceId)
                ->where('ledger.type', 'CP')->where('ledger.is_active', 1)
                ->get(['ledger.amount_out']);
            abort_unless($posted->isNotEmpty() && $posted->sum(fn ($row) => $this->cents($row->amount_out)) === $sourceCents,
                409, 'CP voucher posting does not match its source payment.');

            $existing = DB::table('employee_payroll_payment_links')->where('payroll_run_item_id', $itemId)
                ->where('customer_ledger_id', $sourceId)->first();
            if ($existing) {
                abort_unless($existing->project_id == $projectId && $existing->payroll_run_id == $runId
                    && $this->cents($existing->amount) === $cents, 409, 'Payment link already exists with different details.');
                return (int) $existing->id;
            }
            $links = DB::table('employee_payroll_payment_links')->where('project_id', $projectId)
                ->where(fn ($query) => $query->where('payroll_run_item_id', $itemId)
                    ->orWhere('customer_ledger_id', $sourceId))->get();
            $paidItem = $links->where('payroll_run_item_id', $itemId)->sum(fn ($row) => $this->cents($row->amount));
            $usedSource = $links->where('customer_ledger_id', $sourceId)->sum(fn ($row) => $this->cents($row->amount));
            abort_unless($paidItem + $cents <= $this->cents($item->net_amount), 409, 'Payroll item would be overpaid.');
            abort_unless($usedSource + $cents <= $sourceCents, 409, 'CP voucher amount is already fully allocated.');

            $id = DB::table('employee_payroll_payment_links')->insertGetId([
                'project_id' => $projectId, 'payroll_run_id' => $runId,
                'payroll_run_item_id' => $itemId, 'customer_ledger_id' => $sourceId,
                'amount' => number_format($cents / 100, 2, '.', ''),
                'created_by' => $actorId, 'created_at' => now(),
            ]);
            DB::table('employee_payroll_run_audits')->insert([
                'project_id' => $projectId, 'payroll_run_id' => $runId, 'action' => 'payment_linked',
                'details' => json_encode(['item_id' => $itemId, 'customer_ledger_id' => $sourceId,
                    'amount_cents' => $cents], JSON_THROW_ON_ERROR),
                'actor_id' => $actorId, 'created_at' => now(),
            ]);
            return $id;
        }, 3);
    }

    private function cents(mixed $amount): int
    {
        $value = (string) $amount;
        if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $value)) {
            throw new HttpException(422, 'Invalid payment amount.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
