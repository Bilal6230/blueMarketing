<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CustomerLedger;
use App\Models\Lead;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentClearanceController extends Controller
{
    public function index()
    {
        $selectedProjectId = getSelectedTown();

        $baseLedgerQuery = CustomerLedger::query()
            ->where('project_id', $selectedProjectId)
            ->where('is_active', 1)
            ->whereIn('payment_type', [2, 3])
            ->whereIn('transaction_type', ['CR', 'PPR']);

        $customerIds = (clone $baseLedgerQuery)
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id');

        $plotIds = (clone $baseLedgerQuery)
            ->whereNotNull('plot_id')
            ->distinct()
            ->pluck('plot_id');

        return view('admin.finance.payment_clearance.index', [
            'title' => 'Payment Clearance Register',
            'customers' => Lead::query()
                ->select('id', 'first_name', 'last_name')
                ->whereIn('id', $customerIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'plots' => Plot::query()
                ->select('id', 'name', 'type')
                ->whereIn('id', $plotIds)
                ->orderBy('name')
                ->get(),
            'banks' => getPakistanBanks(),
            'dataRoute' => route('finance.payment_clearance.data'),
        ]);
    }

    public function data(Request $request)
    {
        $selectedProjectId = getSelectedTown();

        $query = CustomerLedger::with([
            'ledger.projectHeadSubhead.subheadAccounting:id,name',
            'customer_list:id,first_name,last_name',
            'plot_list:id,name,type',
            'project_list:id,project',
        ])
            ->where('project_id', $selectedProjectId)
            ->where('is_active', 1)
            ->whereIn('payment_type', [2, 3])
            ->whereIn('transaction_type', ['CR', 'PPR'])
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        $rows = $query->get()->map(function (CustomerLedger $customerLedger) {
            $amount = $this->resolveAmount($customerLedger);
            $statusMeta = $this->statusMeta($customerLedger->passing_status);
            $ledger = $customerLedger->ledger;

            return [
                'id' => $customerLedger->id,
                'date' => $customerLedger->date,
                'voucher_number' => $this->resolveVoucherNumber($customerLedger),
                'source' => $this->resolveSourceLabel($customerLedger),
                'customer' => trim(($customerLedger->customer_list?->first_name ?? '') . ' ' . ($customerLedger->customer_list?->last_name ?? '')),
                'plot' => $this->resolvePlotLabel($customerLedger),
                'payment_type' => getPaymentTypeDetails($customerLedger->payment_type)['name'] ?? 'Unknown',
                'payment_type_badge' => getPaymentTypeDetails($customerLedger->payment_type)['badge'] ?? 'badge-secondary',
                'bank' => $customerLedger->bank_id ? getBankNameById($customerLedger->bank_id) : '',
                't_number' => $customerLedger->t_number,
                'amount' => $amount,
                'passing_date' => $customerLedger->passing_date,
                'status' => $statusMeta['label'],
                'status_badge' => $statusMeta['badge'],
                'cleared_date' => $customerLedger->bank_post_at,
                'view_url' => $this->resolveViewUrl($customerLedger),
                'print_url' => $this->resolvePrintUrl($customerLedger),
                'ledger_exists' => (bool) $ledger,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'cards' => $this->buildCards(clone $query),
            'data' => $rows,
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        if ($request->filled('payment_type') && in_array((int) $request->input('payment_type'), [2, 3], true)) {
            $query->where('payment_type', (int) $request->input('payment_type'));
        }

        if ($request->filled('status') && in_array((int) $request->input('status'), [0, 1, 2, 3], true)) {
            $status = (int) $request->input('status');
            if ($status === 0) {
                $query->where(function (Builder $pendingQuery) {
                    $pendingQuery->whereNull('passing_status')->orWhere('passing_status', 0);
                });
            } else {
                $query->where('passing_status', $status);
            }
        }

        if ($request->filled('bank_id')) {
            $query->where('bank_id', (int) $request->input('bank_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        if ($request->filled('plot_id')) {
            $query->where('plot_id', (int) $request->input('plot_id'));
        }

        if ($request->filled('t_number')) {
            $term = trim((string) $request->input('t_number'));
            $query->where('t_number', 'like', '%' . $term . '%');
        }

        if ($request->filled('voucher_number')) {
            $term = trim((string) $request->input('voucher_number'));
            $query->where(function (Builder $voucherQuery) use ($term) {
                $voucherQuery->where('reference', 'like', '%' . $term . '%')
                    ->orWhereRaw("CONCAT(transaction_type, '-', type_id) like ?", ['%' . $term . '%'])
                    ->orWhereHas('ledger', function (Builder $ledgerQuery) use ($term) {
                        $ledgerQuery->whereRaw("CONCAT(type, '-', voucher_number) like ?", ['%' . $term . '%']);
                    });
            });
        }
    }

    private function buildCards(Builder $query): array
    {
        $today = now()->toDateString();
        $sumAmount = function (Builder $builder): float {
            return (float) (clone $builder)->sum(DB::raw('CASE WHEN amount_out > 0 THEN amount_out ELSE amount_in END'));
        };
        $countRows = function (Builder $builder): int {
            return (int) (clone $builder)->count();
        };
        $todayTotalBuilder = (clone $query)->whereDate('date', $today);
        $todayPendingBuilder = (clone $query)
            ->whereDate('date', $today)
            ->where(function (Builder $pendingQuery) {
                $pendingQuery->whereNull('passing_status')->orWhere('passing_status', 0);
            });
        $todayPassedBuilder = (clone $query)
            ->where('passing_status', 1)
            ->whereDate(DB::raw('COALESCE(bank_post_at, passing_date, date)'), $today);
        $todayReturnedBuilder = (clone $query)
            ->where('passing_status', 2)
            ->whereDate(DB::raw('COALESCE(bank_post_at, passing_date, date)'), $today);
        $todayBouncedBuilder = (clone $query)
            ->where('passing_status', 3)
            ->whereDate(DB::raw('COALESCE(bank_post_at, passing_date, date)'), $today);
        $allPendingBuilder = (clone $query)
            ->where(function (Builder $pendingQuery) {
                $pendingQuery->whereNull('passing_status')->orWhere('passing_status', 0);
            });

        return [
            'today_total_amount' => [
                'label' => 'Today Total Amount',
                'amount' => $sumAmount($todayTotalBuilder),
                'count' => $countRows($todayTotalBuilder),
            ],
            'today_pending' => [
                'label' => 'Today Pending',
                'amount' => $sumAmount($todayPendingBuilder),
                'count' => $countRows($todayPendingBuilder),
            ],
            'today_passed' => [
                'label' => 'Today Passed',
                'amount' => $sumAmount($todayPassedBuilder),
                'count' => $countRows($todayPassedBuilder),
            ],
            'today_returned' => [
                'label' => 'Today Returned',
                'amount' => $sumAmount($todayReturnedBuilder),
                'count' => $countRows($todayReturnedBuilder),
            ],
            'today_bounced' => [
                'label' => 'Today Bounced',
                'amount' => $sumAmount($todayBouncedBuilder),
                'count' => $countRows($todayBouncedBuilder),
            ],
            'all_pending_amount' => [
                'label' => 'All Pending Amount',
                'amount' => $sumAmount($allPendingBuilder),
                'count' => $countRows($allPendingBuilder),
            ],
        ];
    }

    private function resolveVoucherNumber(CustomerLedger $customerLedger): string
    {
        $ledgerType = trim((string) optional($customerLedger->ledger)->type);
        $ledgerVoucherNumber = trim((string) optional($customerLedger->ledger)->voucher_number);
        $transactionType = trim((string) $customerLedger->transaction_type);
        $typeId = trim((string) ($customerLedger->type_id ?? ''));
        $reference = trim((string) ($customerLedger->reference ?? ''));

        if ($ledgerType !== '' && $ledgerVoucherNumber !== '') {
            return $ledgerType . '-' . $ledgerVoucherNumber;
        }

        if (in_array($transactionType, ['CR', 'CP'], true) && $typeId !== '') {
            return $transactionType . '-' . $typeId;
        }

        if ($reference !== '' && preg_match('/^[A-Za-z]+-\S+$/', $reference) === 1) {
            return $reference;
        }

        if ($transactionType !== '' && $reference !== '') {
            return $transactionType . '-' . $reference;
        }

        return 'Pending #' . $customerLedger->id;
    }

    private function resolveSourceLabel(CustomerLedger $customerLedger): string
    {
        $transactionType = strtoupper(trim((string) $customerLedger->transaction_type));

        if ($transactionType === 'CR') {
            return 'Cash In';
        }

        if ($transactionType === 'PPR') {
            return 'Received Payment';
        }

        return $transactionType !== '' ? $transactionType : '-';
    }

    private function resolvePlotLabel(CustomerLedger $customerLedger): string
    {
        if (!$customerLedger->plot_list) {
            return '';
        }

        $prefix = (int) $customerLedger->plot_list->type === 1 ? 'R' : ((int) $customerLedger->plot_list->type === 2 ? 'C' : '');

        return trim($prefix !== '' ? $prefix . '-' . $customerLedger->plot_list->name : $customerLedger->plot_list->name);
    }

    private function resolveAmount(CustomerLedger $customerLedger): float
    {
        return (float) (($customerLedger->amount_out > 0) ? $customerLedger->amount_out : $customerLedger->amount_in);
    }

    private function statusMeta($status): array
    {
        $normalizedStatus = is_null($status) ? 0 : (int) $status;

        return match ($normalizedStatus) {
            1 => ['label' => 'Pass', 'badge' => 'badge-success'],
            2 => ['label' => 'Return', 'badge' => 'badge-secondary'],
            3 => ['label' => 'Cheque Bounce', 'badge' => 'badge-danger'],
            default => ['label' => 'Pending', 'badge' => 'badge-warning'],
        };
    }

    private function resolveViewUrl(CustomerLedger $customerLedger): ?string
    {
        $ledger = $customerLedger->ledger;

        if (!$ledger) {
            return null;
        }

        if ($ledger->type === 'PPR') {
            return route('booking.customer.print', $customerLedger->id);
        }

        return route('finance.voucher.print', $ledger->id);
    }

    private function resolvePrintUrl(CustomerLedger $customerLedger): ?string
    {
        return $this->resolveViewUrl($customerLedger);
    }
}
