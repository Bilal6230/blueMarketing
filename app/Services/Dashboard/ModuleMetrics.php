<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ModuleMetrics
{
    public function monthlyRecovery(int $projectId, int $months = 12): array
    {
        if (!Schema::hasTable('customer_ledger')) return [];
        $start = now()->startOfMonth()->subMonths($months - 1);
        $totals = DB::table('customer_ledger')->where('project_id', $projectId)->where('is_active', 1)
            ->whereDate('date', '>=', $start->toDateString())->get(['date', 'amount_out'])
            ->groupBy(fn ($row) => Carbon::parse($row->date)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount_out'));
        return collect(range(0, $months - 1))->map(function (int $offset) use ($start, $totals): array {
            $month = $start->copy()->addMonths($offset);
            return ['key' => $month->format('Y-m'), 'label' => $month->format('M y'),
                'value' => (float) ($totals[$month->format('Y-m')] ?? 0)];
        })->all();
    }

    public function finance(int $projectId, float $cashBalance, float $bankBalance): array
    {
        $accounts = DB::table('ledgers as l')
            ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->join('head_accountings as h', 'h.id', '=', 'a.head_accounting_id')
            ->where('l.is_active', 1)->where('a.project_id', $projectId)
            ->groupBy('a.id', 'h.acct_type', 'h.name')
            ->get(['h.acct_type', 'h.name', DB::raw('SUM(l.amount_in-l.amount_out) balance')]);
        $receivables = $accounts->where('acct_type', 3)->sum(fn ($a) => max(0, (float) $a->balance));
        $payables = $accounts->filter(fn ($a) => preg_match('/supplier|labour|payable/i', (string) $a->name))
            ->sum(fn ($a) => max(0, -(float) $a->balance));
        $today = now()->toDateString();
        $flows = DB::table('ledgers as l')
            ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
            ->where('l.is_active', 1)->where('a.project_id', $projectId)->whereDate('l.date', $today)
            ->whereIn('l.type', ['CR', 'CP', 'BR', 'BO'])->groupBy('l.type')
            ->selectRaw('l.type, SUM(l.amount_in) debit, SUM(l.amount_out) credit')->get()->keyBy('type');
        return [
            'cash_balance' => $cashBalance, 'bank_balance' => $bankBalance,
            'receivables' => $receivables, 'payables' => $payables,
            'cash_receipts' => (float) ($flows['CR']->debit ?? 0),
            'cash_payments' => (float) ($flows['CP']->credit ?? 0),
            'bank_receipts' => (float) ($flows['BR']->debit ?? 0),
            'bank_payments' => (float) ($flows['BO']->credit ?? 0),
        ];
    }

    public function plots(int $projectId): array
    {
        $base = DB::table('plots')->whereNull('deleted_at')->where('project_id', $projectId)->where('is_active', 1);
        $total = (clone $base)->count();
        $sold = (clone $base)->where('sold', 1)->count();
        $hold = (clone $base)->where('sold', 0)->whereExists(fn ($q) => $q->selectRaw('1')->from('hold_plots')
            ->whereColumn('hold_plots.plot_id', 'plots.id')->whereNull('hold_plots.deleted_at'))->count();
        $booked = Schema::hasTable('bookings') ? DB::table('bookings as b')->join('plots as p', 'p.id', '=', 'b.plot_id')
            ->where('p.project_id', $projectId)->where('p.sold', 0)->where('b.cancel_status', '0')
            ->distinct()->count('b.plot_id') : 0;
        $cancelled = Schema::hasTable('bookings') ? DB::table('bookings as b')->join('plots as p', 'p.id', '=', 'b.plot_id')
            ->where('p.project_id', $projectId)->where('b.cancel_status', '1')
            ->distinct()->count('b.plot_id') : 0;
        return ['total' => $total, 'sold' => $sold, 'hold' => $hold, 'booked' => $booked,
            'cancelled' => $cancelled, 'available' => max(0, $total - $sold - $hold - $booked)];
    }

    public function stock(int $projectId): array
    {
        if (!Schema::hasTable('stock_items') || !Schema::hasTable('stock_project_item_balances')) {
            return ['items' => 0, 'quantity' => 0, 'value' => 0, 'inward' => 0, 'outward' => 0];
        }
        $inventory = DB::table('stock_items as i')->leftJoin('stock_project_item_balances as b', function ($join) use ($projectId) {
            $join->on('b.item_id', '=', 'i.id')->where('b.project_id', '=', $projectId);
        })
            ->whereNull('i.deleted_at')->where('i.is_active', 1)
            ->selectRaw('COUNT(i.id) items, COALESCE(SUM(b.on_hand_qty),0) quantity, COALESCE(SUM(b.on_hand_qty*i.default_rate_minor),0) value_minor')->first();
        $today = Schema::hasTable('stock_movements') ? DB::table('stock_movements')->where('project_id', $projectId)->whereDate('movement_date', now()->toDateString())
            ->groupBy('direction')->selectRaw('direction, COALESCE(SUM(qty),0) qty')->pluck('qty', 'direction') : collect();
        return ['items' => (int) $inventory->items, 'quantity' => (float) $inventory->quantity,
            'value' => (float) $inventory->value_minor / 100, 'inward' => (float) ($today['IN'] ?? 0),
            'outward' => (float) ($today['OUT'] ?? 0)];
    }

    public function labour(int $projectId): array
    {
        if (!Schema::hasTable('labours') || !Schema::hasTable('labour_attendances')) {
            return ['active' => 0, 'present' => 0, 'pending_bills' => 0, 'payable' => 0, 'cost' => 0];
        }
        $attendance = DB::table('labour_attendances')->where('project_id', $projectId);
        return [
            'active' => DB::table('labours')->where('project_id', $projectId)->where('status', 'active')->count(),
            'present' => (clone $attendance)->whereDate('date', now())->where('status', 'present')->count(),
            'pending_bills' => (clone $attendance)->where('voucher_status', 'notcreated')->count(),
            'payable' => (float) (clone $attendance)->where('paid_status', 'unpaid')->sum('amount'),
            'cost' => (float) (clone $attendance)->sum('amount'),
        ];
    }

    public function approvals(int $projectId): array
    {
        if (!Schema::hasTable('pending_updates')) {
            return ['finance' => 0, 'leads' => 0, 'sales' => 0, 'commission' => 0, 'total' => 0];
        }
        $finance = DB::table('pending_updates as p')->where('p.status', 'pending')->where(function ($q) use ($projectId) {
            $q->where(function ($x) use ($projectId) {
                $x->where('p.table_name', 'ledgers')->whereExists(fn ($s) => $s->selectRaw('1')->from('ledgers as l')
                    ->join('project_head_subheads as a', 'a.id', '=', 'l.project_head_subheads_id')
                    ->whereColumn('l.id', 'p.record_id')->where('a.project_id', $projectId));
            })->orWhere(function ($x) use ($projectId) {
                $x->where('p.table_name', 'draft_ledgers')->whereExists(fn ($s) => $s->selectRaw('1')->from('draft_ledgers as d')
                    ->whereColumn('d.id', 'p.record_id')->where('d.project_id', $projectId));
            });
        })->count();
        $leads = DB::table('pending_updates as p')->where('p.status', 'pending')->where('p.table_name', 'leads')
            ->whereExists(fn ($s) => $s->selectRaw('1')->from('leads')->whereColumn('leads.id', 'p.record_id')->where('leads.project_id', $projectId))->count();
        $sales = DB::table('pending_updates as p')->where('p.status', 'pending')->where('p.table_name', 'customer_ledger')
            ->whereExists(fn ($s) => $s->selectRaw('1')->from('customer_ledger as c')->whereColumn('c.id', 'p.record_id')->where('c.project_id', $projectId))->count();
        $commission = Schema::hasTable('commision_vouchers') ? DB::table('commision_vouchers')->whereNull('deleted_at')
            ->where('project_id', $projectId)->where('status', 'pending')->count() : 0;
        return compact('finance', 'leads', 'sales', 'commission') + ['total' => $finance + $leads + $sales + $commission];
    }

    public function attendance(int $projectId): array
    {
        $total = DB::table('users')->where('project_id', $projectId)->where('status_id', 1)->whereNull('deleted_at')->count();
        $today = DB::table('punches')->where('project_id', $projectId)->whereDate('punch_in', now()->toDateString());
        $present = (clone $today)->distinct()->count('user_id');
        $missingPunchOut = (clone $today)->whereNull('punch_out')->count();
        $lateArrival = (clone $today)->whereTime('punch_in', '>', config('app.attendance_start_time', '09:00:00'))
            ->distinct()->count('user_id');
        return ['total' => $total, 'present' => $present, 'absent' => max(0, $total - $present),
            'missing_punch' => $missingPunchOut, 'late_arrival' => $lateArrival];
    }

    public function paymentInstruments(int $projectId): array
    {
        if (!Schema::hasTable('customer_ledger')) {
            return ['cheques' => [], 'online' => []];
        }
        $base = DB::table('customer_ledger')->where('project_id', $projectId)->where('is_active', 1)
            ->whereIn('transaction_type', ['CR', 'PPR']);
        $counts = function (int $paymentType) use ($base): array {
            $q = (clone $base)->where('payment_type', $paymentType);
            $byStatus = (clone $q)->selectRaw('passing_status, COUNT(*) total')->groupBy('passing_status')
                ->pluck('total', 'passing_status');
            return [
                'total' => (clone $q)->count(), 'pending' => (int) ($byStatus[0] ?? 0),
                'passed' => (int) ($byStatus[1] ?? 0), 'memo' => (int) ($byStatus[2] ?? 0),
                'cancelled' => (int) ($byStatus[3] ?? 0), 'returned' => (int) ($byStatus[4] ?? 0),
                'received_today' => (clone $q)->whereDate('date', now()->toDateString())->count(),
                'cleared_today' => (clone $q)->where('passing_status', 1)->whereDate('bank_post_at', now()->toDateString())->count(),
                'processed' => (clone $q)->whereIn('passing_status', [1, 2, 3, 4])->count(),
            ];
        };
        return ['cheques' => $counts(3), 'online' => $counts(2)];
    }

    public function employeeTasks(int $projectId, ?int $employeeId = null): array
    {
        if (!Schema::hasTable('employee_tasks')) return ['metrics'=>array_fill_keys(['total','new','pending','in_progress','completed','due_today','overdue','rework','cancelled'],0),'performance'=>[]];
        $q = DB::table('employee_tasks as et')->whereNull('et.deleted_at')->where('et.project_id',$projectId)
            ->when($employeeId, fn($x)=>$x->where('et.assigned_to',$employeeId));
        $metrics = [
            'total'=>(clone $q)->count(), 'new'=>(clone $q)->where('status','new')->count(),
            'pending'=>(clone $q)->where('status','pending')->count(), 'in_progress'=>(clone $q)->where('status','in_progress')->count(),
            'completed'=>(clone $q)->where('status','completed')->count(), 'due_today'=>(clone $q)->whereNotIn('status',['completed','cancelled'])->whereDate('due_date',now())->count(),
            'overdue'=>(clone $q)->whereNotIn('status',['completed','cancelled'])->whereDate('due_date','<',now())->count(),
            'rework'=>(clone $q)->where('status','rework')->count(), 'cancelled'=>(clone $q)->where('status','cancelled')->count(),
        ];
        $performance = (clone $q)->join('users','users.id','=','et.assigned_to')
            ->groupBy('users.id','users.name')->orderByDesc(DB::raw("SUM(status='completed')"))->limit(10)
            ->get(['users.name', DB::raw('COUNT(*) assigned'),
                DB::raw("SUM(status='completed') completed"), DB::raw("SUM(status IN ('new','pending','rework')) pending"),
                DB::raw("SUM(status='in_progress') in_progress"),
                DB::raw("SUM(status NOT IN ('completed','cancelled') AND due_date < CURRENT_DATE) overdue"),
                DB::raw("SUM(status='completed' AND due_date IS NOT NULL AND completed_at <= CONCAT(due_date,' 23:59:59')) on_time")])
            ->map(fn($r)=>['employee'=>$r->name,'assigned'=>(int)$r->assigned,'completed'=>(int)$r->completed,
                'pending'=>(int)$r->pending,'in_progress'=>(int)$r->in_progress,'overdue'=>(int)$r->overdue,
                'on_time'=>(int)$r->on_time,'completion_rate'=>$r->assigned?round($r->completed/$r->assigned*100,1):0])->all();
        return compact('metrics','performance');
    }
}
