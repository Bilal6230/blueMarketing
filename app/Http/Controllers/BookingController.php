<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingVoucher;
use App\Models\ChargeType;
use App\Models\CustomerLedger;
use App\Models\DraftLedger;
use App\Models\HeadAccounting;
use App\Models\Installment;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\PendingUpdate;
use App\Models\Plot;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\SubheadAccounting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $projectId = getSelectedTown();
        $x['title'] = 'Plots Sale List';
        $x['data'] = Booking::with('customer', 'plot', 'project', 'broker')->where('cancel_status', '0')->where('project_id', $projectId)->get();
        $x['role'] = Role::get();

        // Calculate the sum of the sale amount
        $x['totalSaleAmount'] = $x['data']->sum('total_price'); // Sum of the total_price field

        return view('admin.booking.index', $x);
    }


    public function recovery_list()
    {
        $x['title'] = 'Project Recovery Report';
        $today = Carbon::today()->toDateString();

        // Fetch data with required relations
        $x['data'] = Booking::with([
            'customer',
            'plot',
            'project',
            'broker',
            'bookingDetails' => function ($query) use ($today) {
                $query->whereDate('due_date', '<=', $today);
            }
        ])
            ->where('project_id', getSelectedTown())
            ->where('cancel_status', '0')
            ->get();

        // Calculate the sum of Due Amounts and Received
        $x['sum_due_amount'] = $x['data']->sum(function ($booking) {
            return getSumDueAmount($booking->id); // Helper to calculate due amount
        });

        $x['sum_received'] = $x['data']->sum(function ($booking) {
            return getSumRecovery($booking->plot_id, 'amount_out'); // Helper to calculate received amount
        });

        $x['role'] = Role::get();

        return view('admin.reports.bookings.project_recovery', $x);
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function sale()
    {
        $x['title'] = 'New Booking invoice';
        $x['data'] = Plot::get();
        $x['role'] = Role::get();
        $x['installments'] = Installment::get();
        $projects = Project::where('id', getSelectedTown())->get();
        $x['projects'] = $projects;
        // Retrieve the joined data from project_head_subheads and subhead_accountings
        $brokers = ProjectHeadSubhead::where('head_accounting_id', 6)
            ->join('subhead_accountings', 'project_head_subheads.subhead_accounting_id', '=', 'subhead_accountings.id')
            ->get();

        $x['brokers'] = $brokers;

        return view('admin.booking.sale', $x);
    }
    public function fileTransfer($id)
    {
        $x['title'] = 'File Transfer';
        $x['data'] = Plot::get();
        $x['role'] = Role::get();
        $x['installments'] = Installment::get();
        $projects = Project::where('id', getSelectedTown())->get();
        $x['projects'] = $projects;
        $x['booking'] = $booking = Booking::findOrFail($id);
        $x['customers'] = Lead::where('project_id', getSelectedTown())->get();
        $x['plots'] = Plot::whereDoesntHave('holdPlots')
            ->where('type', $booking->plot_type)
            ->where('project_id', getSelectedTown())
            ->where(function ($query) use ($booking) {
                $query->where('sold', 0)
                    ->orWhere('id', $booking->plot_id); // include id = 2 even if sold = 1
            })
            ->get();
        // Retrieve the joined data from project_head_subheads and subhead_accountings
        $brokers = ProjectHeadSubhead::where('head_accounting_id', 6)
            ->join('subhead_accountings', 'project_head_subheads.subhead_accounting_id', '=', 'subhead_accountings.id')
            ->get();

        $x['brokers'] = $brokers;

        return view('admin.booking.file_transfer', $x);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'customer_id' => 'required|exists:leads,id',
            'plot_id' => 'required|string',
            'plot_type' => 'required',
            'plot_size' => 'required',
            'plot_rate' => 'required',
            'total_price' => 'required',
            'booking_date' => 'required|date',
            'status' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $plotType = $request->plot_type;
        if ($plotType == 1) {
            $plotType = 'R-';
        } elseif ($plotType == 2) {
            $plotType = 'C-';
        }
        DB::beginTransaction();

        try {
            // Create the booking
            $booking = Booking::updateOrCreate(
                ['id' => $request->id], // condition to check if booking exists
                [
                    'project_id' => $request->input('project_id'),
                    'customer_id' => $request->input('customer_id'),
                    'plot_id' => $request->input('plot_id'),
                    'plot_type' => $request->input('plot_type'),
                    'plot_size' => $request->input('plot_size'),
                    'plot_rate' => str_replace(',', '', $request->input('plot_rate')),
                    'is_corner' => $request->input('is_corner') ?? 0,
                    'is_park' => $request->input('is_park') ?? 0,
                    'park_facing' => $request->input('park_facing') ?? 0,
                    'carner_price' => $request->input('carner_price') ?? 0,
                    'dicount_value' => $request->input('discount_value') ?? 0,
                    'total_price' => str_replace(',', '', $request->input('total_price')),
                    'broker_id' => $request->input('broker_id'),
                    'booking_date' => $request->input('booking_date'),
                    'status' => $request->input('status'),
                    'user_id' => Auth::id(),
                ]
            );

            // Update the plot status
            Plot::where('id', $booking->plot_id)->update(['sold' => 1]);

            // Fetch plot and customer details
            $plotName = Plot::where('id', $booking->plot_id)->value('name');
            $customer = Lead::findOrFail($booking->customer_id);

            // Create customer ledger entry
            $customerLedger = CustomerLedger::create([
                'customer_id' => $booking->customer_id,
                'transaction_type' => 'Bo',
                'type_id' => get_new_typeID('Bo'),
                'project_id' => $booking->project_id,
                'plot_id' => $booking->plot_id,
                'amount_in' => $booking->total_price,
                'amount_out' => 0,
                'description' => 'Booking for plot ' . $plotType . $plotName,
                'date' => $booking->booking_date,
                'is_approve' => 1,
                'is_active' => 1,
            ]);

            // Create subhead accounting
            $subheadAccounting = SubheadAccounting::create([
                'name' => '(' . $plotType . $plotName . ') ' . $customer->first_name . ' ' . $customer->last_name,
                'is_active' => 1,
                'cnic' => $customer->nic_number ?? '00000',
                'phone' => $customer->phone_number ?? '00000',
                'create_by' => Auth::user()->id,
            ]);

            $pivot = ProjectHeadSubhead::create([
                'project_id' => $booking->project_id,
                'head_accounting_id' => 16,
                'subhead_accounting_id' => $subheadAccounting->id,
                'plot_id' => $booking->plot_id,
                'customer_id' => $booking->customer_id,
            ]);



            $salesVoucher = $this->createSalesVoucher([
                'project_id' => (int) $booking->project_id,
                'reference' => 'BOOKING-' . $booking->id,
                'date' => $booking->booking_date,
                'description' => 'New Booking: Plot ' . $plotType . $plotName . ' booked by ' . trim($customer->first_name . ' ' . $customer->last_name),
                'total_amount' => $booking->total_price,
                'source_type' => 'BOOKING',
                'source_id' => $booking->id,
            ]);

            // Create ledger entry
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $pivot->id,
                'reference' => $booking->id,
                'customer_ledger_id' => $customerLedger->id,
                'amount_in' => 0,
                'amount_out' => $booking->total_price,
                'date' => $booking->booking_date,
                'description' => 'Booking for plot ' . $plotType . $plotName,
                'update_by' => Auth::user()->id,
                'create_by' => Auth::user()->id,
                'status' => 0,
            ]);

            // Ensure credit account exists
            $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', 16)
                ->where('project_id', $booking->project_id)
                ->where('subhead_accounting_id', 123)
                ->value('id');

            if (!$creditAccountId) {
                throw new \Exception('Credit account ID not found.');
            }

            // Create credit ledger entry
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $creditAccountId,
                'reference' => $booking->id,
                'customer_ledger_id' => $customerLedger->id,
                'amount_in' => $booking->total_price,
                'amount_out' => 0,
                'date' => $booking->booking_date,
                'description' => 'Booking for plot ' . $plotType . $plotName,
                'update_by' => Auth::user()->id,
                'create_by' => Auth::user()->id,
                'status' => 0,
            ]);
            $this->postResaleProfitSplit($booking, $creditAccountId, $plotType, $plotName, $salesVoucher);

            DB::commit();

            Alert::success('Notification', 'Data saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            dd($th->getMessage());
            DB::rollback();
            Log::error('Error storing booking: ' . $th->getMessage());
            Alert::error('Notification', 'An error occurred: ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }

    private function pvMoneyToDecimal2cancel($value): string
    {
        $raw = str_replace(',', '', (string) ($value ?? '0'));
        $raw = trim($raw);
        $raw = preg_replace('/[^0-9.]/', '', $raw);

        if ($raw === '' || $raw === '.')
            return '0.00';
        if (strpos($raw, '.') === false)
            return $raw . '.00';

        [$a, $b] = array_pad(explode('.', $raw, 2), 2, '0');
        $b = substr($b . '00', 0, 2);

        return ($a === '' ? '0' : $a) . '.' . $b;
    }

    private function nextTypeId(string $type): int
    {
        $last = (int) (getLastLedgerIdByType($type) ?? 0);
        return $last + 1;
    }
    private function ensureProjectSaleSystemAccountId(int $projectId, string $subheadName): int
    {
        $userId = Auth::id();

        $sub = SubheadAccounting::firstOrCreate(
            ['name' => $subheadName],
            [
                'is_active' => 1,
            ]
        );

        $phs = ProjectHeadSubhead::firstOrCreate(
            [
                'project_id' => $projectId,
                'head_accounting_id' => 16,
                'subhead_accounting_id' => $sub->id,
                'plot_id' => null,
                'customer_id' => null,
            ],
            []
        );

        return (int) $phs->id;
    }
    private function postResaleProfitSplit($booking, int $totalSaleAccountId, string $plotType, string $plotName, JournalVoucher $salesVoucher): void
    {
        // Find latest cancelled booking for this plot (excluding current booking)
        $lastCancelled = Booking::where('plot_id', $booking->plot_id)
            ->where('cancel_status', 1)
            ->whereNotNull('cancel_posted_at')
            ->where('id', '!=', $booking->id)
            ->orderByDesc('cancel_posted_at')
            ->first();

        if (!$lastCancelled) {
            return; // no previous cancellation => no resale profit case
        }

        $lastSale = $this->pvMoneyToDecimal2cancel($lastCancelled->cancel_sale_amount ?: $lastCancelled->total_price);
        $newSale = $this->pvMoneyToDecimal2cancel($booking->total_price);

        // diff = new - last
        $diff = bcsub($newSale, $lastSale, 2);

        // If diff <= 0 => no profit
        if (bccomp($diff, '0.00', 2) !== 1) {
            return;
        }

        // Idempotency guard: prevent double posting if store called again for same booking
        $marker = 'RESALE_PROFIT#' . $booking->id;
        $exists = Ledger::where('reference', $booking->id)
            ->where('detail', 'like', $marker . '%')
            ->exists();

        if ($exists) {
            return;
        }

        // Split logic
        $partyProfitCredit = '0.00';
        $resaleProfitCredit = $diff;

        $effect = $lastCancelled->cancel_effect; // charge_customer | give_profit | null
        if ($effect === 'give_profit') {
            $priorProfit = $this->pvMoneyToDecimal2($lastCancelled->cancel_adjustment_amount);

            // partyProfit = min(priorProfit, diff)
            $partyProfitCredit = (bccomp($priorProfit, $diff, 2) === 1) ? $diff : $priorProfit;

            // resaleProfit = diff - partyProfit
            $resaleProfitCredit = bcsub($diff, $partyProfitCredit, 2);
        }

        // Ensure system accounts exist
        $resaleProfitAccId = $this->ensureProjectSaleSystemAccountId((int) $booking->project_id, 'Resale Profit');

        $partyProfitAccId = null;
        if (bccomp($partyProfitCredit, '0.00', 2) === 1) {
            $partyProfitAccId = $this->ensureProjectSaleSystemAccountId((int) $booking->project_id, 'Party Profit');
        }

        $userId = Auth::id();
        $date = $booking->booking_date;


        // 2) CR Party Profit (optional)
        if ($partyProfitAccId && bccomp($partyProfitCredit, '0.00', 2) === 1) {
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $partyProfitAccId,
                'reference' => $booking->id,
                'amount_in' => $partyProfitCredit,
                'amount_out' => 0,
                'date' => $date,
                'description' => $marker . " | Party Profit Credit | {$plotType}{$plotName}",
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }

        // 3) CR Resale Profit
        if (bccomp($resaleProfitCredit, '0.00', 2) === 1) {
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $resaleProfitAccId,
                'reference' => $booking->id,
                'amount_in' => $resaleProfitCredit,
                'amount_out' => 0,
                'date' => $date,
                'description' => $marker . " | Resale Profit Credit | {$plotType}{$plotName}",
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }
    }


    public function show(Request $request)
    {
        $data_list = Plot::where(['id' => $request->id])->first();


        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }


    public function edit(Plot $plot)
    {
        //
    }


    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'customer_id' => 'required|exists:leads,id',
            'plot_id' => 'required|string',
            'plot_type' => 'required',
            'plot_size' => 'required',
            'plot_rate' => 'required',
            'total_price' => 'required',
            'booking_date' => 'required|date',
            'status' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $userId = Auth::id();
        $plotType = $this->plotTypePrefix((int) $request->plot_type);

        // Load existing booking BEFORE update (used for old amount / transfer profit)
        $existingBooking = null;
        if (!empty($request->id)) {
            $existingBooking = Booking::find($request->id);
        }

        // Determine transfer
        $oldCustomerId = (int) $request->input('old_customer_id');
        $newCustomerId = (int) $request->input('customer_id');
        $isTransfer = ($oldCustomerId > 0 && $oldCustomerId !== $newCustomerId);

        // Money (sanitize)
        $newTotalPrice = $this->sanitizeMoney($request->input('total_price'));
        $newPlotRate = $this->sanitizeMoney($request->input('plot_rate'));
        try {
            DB::transaction(function () use ($request, $userId, $plotType, $existingBooking, $isTransfer, $oldCustomerId, $newTotalPrice, $newPlotRate) {
                // 1) Upsert booking
                $booking = Booking::updateOrCreate(
                    ['id' => $request->id],
                    [
                        'project_id' => $request->input('project_id'),
                        'customer_id' => $request->input('customer_id'),
                        'plot_id' => $request->input('plot_id'),
                        'plot_type' => $request->input('plot_type'),
                        'plot_size' => $request->input('plot_size'),
                        'plot_rate' => $newPlotRate,
                        'is_corner' => $request->input('is_corner') ?? 0,
                        'is_park' => $request->input('is_park') ?? 0,
                        'park_facing' => $request->input('park_facing') ?? 0,
                        'carner_price' => $request->input('carner_price') ?? 0,
                        'dicount_value' => $request->input('discount_value') ?? 0,
                        'total_price' => $newTotalPrice,
                        'broker_id' => $request->input('broker_id'),
                        'booking_date' => $request->input('booking_date'),
                        'status' => $request->input('status'),
                        'user_id' => $userId,
                    ]
                );

                // 2) Mark plot sold
                Plot::where('id', $booking->plot_id)->update(['sold' => 1]);

                // 3) Load plot name + customer
                $plotName = Plot::where('id', $booking->plot_id)->value('name');
                if (!$plotName) {
                    throw new \Exception('Plot not found for booking.');
                }

                $customer = Lead::findOrFail($booking->customer_id);

                // 4) Ensure NEW customer pivot exists (for ledger mapping)
                $this->ensureCustomerAccountPivot(
                    (int) $booking->project_id,
                    16,
                    $booking->plot_id,
                    (int) $booking->customer_id,
                    '(' . $plotType . $plotName . ') ' . trim($customer->first_name . ' ' . $customer->last_name),
                    $customer->nic_number ?? '00000',
                    $customer->phone_number ?? '00000',
                    $userId
                );

                // 5) If it is transfer, do transfer postings (principal + profit + fee)
                if ($isTransfer) {
                    if (!$existingBooking) {
                        throw new \Exception('Transfer update requires an existing booking record (old amount not found).');
                    }

                    $oldAmount = $this->sanitizeMoney($existingBooking->total_price);
                    $newAmount = $this->sanitizeMoney($booking->total_price);
                    if (bccomp($oldAmount, '0.00', 2) !== 1) {
                        throw new \Exception('Old booking amount is invalid for transfer.');
                    }

                    $oldLead = Lead::find($oldCustomerId);
                    $oldCustomerName = trim((optional($oldLead)->first_name ?? '') . ' ' . (optional($oldLead)->last_name ?? ''));
                    $newCustomerName = trim($customer->first_name . ' ' . $customer->last_name);
                    $salesVoucher = $this->createSalesVoucher([
                        'project_id' => (int) $booking->project_id,
                        'reference' => 'TRANSFER-' . $booking->id,
                        'date' => $booking->booking_date,
                        'description' => 'File Transfer: Plot ' . $plotType . $plotName . ' transferred from ' . ($oldCustomerName ?: 'Old Customer') . ' to ' . ($newCustomerName ?: 'New Customer'),
                        'total_amount' => $newAmount,
                        'source_type' => 'TRANSFER',
                        'source_id' => $booking->id,
                    ]);

                    $this->postFileTransferEntries(
                        $booking,
                        $oldCustomerId,
                        $oldAmount,
                        $newAmount,
                        $plotType,
                        $plotName,
                        $request,
                        $userId,
                        $salesVoucher
                    );
                }
            });
            session(['last_submit_date' => $request->booking_date]);
            $this->attechCustomerToOldProjectSale($request->project_id, $existingBooking->customer_id, $existingBooking->plot_id);
            Alert::success('Notification', 'Data updated successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Log::error('Booking update failed', [
                'booking_id' => $request->id ?? null,
                'project_id' => $request->project_id ?? null,
                'plot_id' => $request->plot_id ?? null,
                'customer_id' => $request->customer_id ?? null,
                'error' => $th->getMessage(),
            ]);
            dd($th->getMessage());
            Alert::error('Notification', 'Something went wrong. Please try again.')->toToast()->toHtml();
        }

        return back();
    }

    private function sanitizeMoney($value): string
    {
        if ($value === null)
            return '0';
        return str_replace(',', '', (string) $value);
    }

    private function nextSalesVoucherNumber(int $projectId): int
    {
        return ((int) JournalVoucher::query()
            ->where('project_id', $projectId)
            ->where('type', 'SV')
            ->lockForUpdate()
            ->orderByDesc('voucher_number')
            ->value('voucher_number')) + 1;
    }

    private function createSalesVoucher(array $payload): JournalVoucher
    {
        $projectId = (int) ($payload['project_id'] ?? getSelectedTown());
        $sourceType = strtoupper((string) ($payload['source_type'] ?? 'SOURCE'));
        $sourceId = (string) ($payload['source_id'] ?? '');
        $reference = trim((string) ($payload['reference'] ?? ($sourceType . '-' . $sourceId)));

        if ($projectId <= 0 || $reference === '') {
            throw new \InvalidArgumentException('Project ID and reference are required for sales voucher creation.');
        }

        $existingVoucher = JournalVoucher::where('project_id', $projectId)
            ->where('type', 'SV')
            ->where('reference', $reference)
            ->first();

        if ($existingVoucher) {
            return $existingVoucher;
        }

        $date = !empty($payload['date'])
            ? Carbon::parse($payload['date'])->format('Y-m-d')
            : now()->format('Y-m-d');
        $description = trim((string) ($payload['description'] ?? 'Sales Voucher'));

        return JournalVoucher::create([
            'voucher_number' => $this->nextSalesVoucherNumber($projectId),
            'type' => 'SV',
            'reference' => $reference,
            'date' => $date,
            'description' => $description,
            'total_debit' => $this->pvMoneyToDecimal2($payload['total_amount'] ?? 0),
            'total_credit' => $this->pvMoneyToDecimal2($payload['total_amount'] ?? 0),
            'project_id' => $projectId,
            'created_by' => Auth::id(),
            'status' => 'pending',
        ]);
    }

    private function addSalesVoucherDetail(JournalVoucher $voucher, array $payload): JournalVoucherDetail
    {
        $detail = JournalVoucherDetail::firstOrCreate([
            'journal_voucher_id' => $voucher->id,
            'account_id' => $payload['account_id'],
            'debit' => $this->pvMoneyToDecimal2($payload['amount_in'] ?? 0),
            'credit' => $this->pvMoneyToDecimal2($payload['amount_out'] ?? 0),
            'description' => trim((string) ($payload['description'] ?? 'Sales Voucher')),
        ]);

        $this->syncSalesVoucherTotals($voucher);

        return $detail;
    }

    private function createSalesVoucherLedger(JournalVoucher $voucher, array $payload): Ledger
    {
        return Ledger::firstOrCreate([
            'type' => 'SV',
            'type_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'project_head_subheads_id' => $payload['account_id'],
            'reference' => $payload['reference'],
            'amount_in' => $this->pvMoneyToDecimal2($payload['amount_in'] ?? 0),
            'amount_out' => $this->pvMoneyToDecimal2($payload['amount_out'] ?? 0),
            'detail' => trim((string) ($payload['description'] ?? 'Sales Voucher')),
            'date' => $payload['date'],
        ], [
            'customer_ledger_id' => $payload['customer_ledger_id'] ?? null,
            'is_active' => $payload['is_active'] ?? 1,
            'create_by' => $payload['create_by'] ?? Auth::id(),
            'update_by' => $payload['update_by'] ?? Auth::id(),
            'status' => $payload['status'] ?? 0,
        ]);
    }

    private function recordSalesVoucherEntry(JournalVoucher $voucher, array $payload): void
    {
        $this->addSalesVoucherDetail($voucher, $payload);
        $this->createSalesVoucherLedger($voucher, $payload);
    }

    private function syncSalesVoucherTotals(JournalVoucher $voucher): void
    {
        $voucher->forceFill([
            'total_debit' => $this->pvMoneyToDecimal2($voucher->details()->sum('debit')),
            'total_credit' => $this->pvMoneyToDecimal2($voucher->details()->sum('credit')),
        ])->save();
    }

    private function plotTypePrefix(int $plotType): string
    {
        if ($plotType === 1)
            return 'R-';
        if ($plotType === 2)
            return 'C-';
        return '';
    }

    /**
     * Concurrency-safe incremental type_id per ledger type (CP/CR)
     */
    private function nextLedgerTypeId(string $type): int
    {
        $max = Ledger::where('type', $type)->lockForUpdate()->max('type_id');
        return ((int) $max) + 1;
    }

    /**
     * Finds/creates a system subhead (by name) and returns ProjectHeadSubhead->id for that project+head.
     * For File Transfer Fee we allow auto-create.
     * For Party Profit we do NOT auto-create (since you said it was added already).
     */
    private function attechCustomerToOldProjectSale(int $projectId, int $customerId, int $plotId): int
    {
        $head = HeadAccounting::where('name', 'Old Project Sales')->first();

        if (!$head) {
            $head = HeadAccounting::create([
                'name' => 'Old Project Sales',
                'is_active' => 1,
                'acct_type' => 0,
                'create_by' => Auth::id(),
            ]);
        }

        $phs = ProjectHeadSubhead::updateOrCreate(
            // 🔹 Find record by these fields (DO NOT change them)
            [
                'project_id' => $projectId,
                'plot_id' => $plotId,
                'customer_id' => $customerId,
            ],
            // 🔹 Only this field will be updated
            [
                'head_accounting_id' => $head->id,
            ]
        );

        return (int) $phs->id;
    }
    private function systemAccountIdBySubheadName(int $projectId, int $headId, string $subheadName, bool $createIfMissing = false): int
    {
        $subhead = SubheadAccounting::where('name', $subheadName)->first();

        if (!$subhead && $createIfMissing) {
            $subhead = SubheadAccounting::create([
                'name' => $subheadName,
                'is_active' => 1,
                'create_by' => Auth::id(),
            ]);
        }

        if (!$subhead) {
            throw new \Exception("System subhead not found: {$subheadName}");
        }

        $phs = ProjectHeadSubhead::firstOrCreate(
            [
                'project_id' => $projectId,
                'head_accounting_id' => $headId,
                'subhead_accounting_id' => $subhead->id,
                'plot_id' => null,
                'customer_id' => null,
            ]
        );

        return (int) $phs->id;
    }

    /**
     * Your existing Total Sale account mapping (keeps 123 fallback).
     * If you ever change 123, you only update here.
     */
    private function totalSaleAccountId(int $projectId): int
    {
        $id = ProjectHeadSubhead::where('project_id', $projectId)
            ->where('head_accounting_id', 16)
            ->where('subhead_accounting_id', 123)
            ->value('id');

        if (!$id) {
            // Optional: attempt lookup by name if you want:
            // $id = $this->systemAccountIdBySubheadName($projectId, 16, 'Total Sale', false);
            throw new \Exception('Total Sale account not found (expected subhead_accounting_id = 123).');
        }

        return (int) $id;
    }

    /**
     * Ensures customer subhead + pivot exists (same as before).
     */
    private function ensureCustomerAccountPivot(
        int $projectId,
        int $headAccountingId,
        $plotId,
        int $customerId,
        string $subheadName,
        string $cnic,
        string $phone,
        int $userId
    ): ProjectHeadSubhead {
        $existing = ProjectHeadSubhead::where('project_id', $projectId)
            ->where('head_accounting_id', $headAccountingId)
            ->where('plot_id', $plotId)
            ->where('customer_id', $customerId)
            ->first();

        if ($existing)
            return $existing;

        $subheadAccounting = SubheadAccounting::create([
            'name' => $subheadName,
            'is_active' => 1,
            'cnic' => $cnic,
            'phone' => $phone,
            'create_by' => $userId,
        ]);

        return ProjectHeadSubhead::create([
            'project_id' => $projectId,
            'head_accounting_id' => $headAccountingId,
            'subhead_accounting_id' => $subheadAccounting->id,
            'plot_id' => $plotId,
            'customer_id' => $customerId,
        ]);
    }
    private function postFileTransferEntries(
        Booking $booking,
        int $oldCustomerId,
        string $oldAmount,
        string $newAmount,
        string $plotType,
        string $plotName,
        Request $request,
        int $userId,
        JournalVoucher $salesVoucher
    ): void {
        // Profit = new - old (only if positive)
        $profit = bcsub($newAmount, $oldAmount, 2);
        if (bccomp($profit, '0.00', 2) < 0) {
            $profit = '0.00';
        }

        // System accounts (Project Sale head = 16)
        $totalSalePhsId = $this->totalSaleAccountId((int) $booking->project_id);
        $partyProfitPhsId = null;

        if (bccomp($profit, '0.00', 2) === 1) {
            // Party Profit must exist as per your seniors; we do NOT auto-create
            $partyProfitPhsId = $this->systemAccountIdBySubheadName((int) $booking->project_id, 16, 'Party Profit', false);
        }


        // Customer pivots
        $oldLead = Lead::findOrFail($oldCustomerId);
        $oldCustomerPivot = $this->ensureCustomerAccountPivot(
            (int) $booking->project_id,
            16,
            $booking->plot_id,
            $oldCustomerId,
            '(' . $plotType . $plotName . ') ' . trim($oldLead->first_name . ' ' . $oldLead->last_name),
            $oldLead->nic_number ?? '00000',
            $oldLead->phone_number ?? '00000',
            $userId
        );

        $newLead = Lead::findOrFail((int) $booking->customer_id);
        $newCustomerPivot = $this->ensureCustomerAccountPivot(
            (int) $booking->project_id,
            16,
            $booking->plot_id,
            (int) $booking->customer_id,
            '(' . $plotType . $plotName . ') ' . trim($newLead->first_name . ' ' . $newLead->last_name),
            $newLead->nic_number ?? '00000',
            $newLead->phone_number ?? '00000',
            $userId
        );
        /**
         * ==============================================
         * SYSTEM ACCOUNTS: settle OUT to old party (CP)
         * ==============================================
         * 50 lac Total Sale Debit
         * 10 lac Party Profit Debit
         */
        $this->recordSalesVoucherEntry($salesVoucher, [
            'account_id' => $totalSalePhsId,
            'reference' => $booking->id,
            'amount_in' => 0,
            'amount_out' => $oldAmount,
            'date' => $booking->booking_date,
            'description' => "Total Sale Settlement OUT (Old Party) - {$plotType}{$plotName}",
            'update_by' => $userId,
            'create_by' => $userId,
            'status' => 0,
        ]);

        if ($partyProfitPhsId && bccomp($profit, '0.00', 2) === 1) {
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $partyProfitPhsId,
                'reference' => $booking->id,
                'amount_in' => 0,
                'amount_out' => $profit,
                'date' => $booking->booking_date,
                'description' => "Party Profit Settlement OUT (Old Party) - {$plotType}{$plotName}",
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }

        /**
         * ============================
         * OLD PARTY (incoming credits)
         * ============================
         * 50 lac Old Party credit (plot payment)
         * 10 lac Old Party credit (profit) IF profit > 0
         */

        // Principal credit to old party
        $oldPrincipalCL = CustomerLedger::create([
            'customer_id' => $oldCustomerId,
            'transaction_type' => 'Bo', // keep your existing booking-type for compatibility
            'type_id' => get_new_typeID('Bo'),
            'project_id' => $booking->project_id,
            'plot_id' => $booking->plot_id,
            'amount_in' => $oldAmount,
            'amount_out' => 0,
            'description' => "Plot Transfer Principal Credit ({$plotType}{$plotName})",
            'date' => $booking->booking_date,
            'is_approve' => 1,
            'is_active' => 1,
        ]);
        $this->recordSalesVoucherEntry($salesVoucher, [
            'account_id' => $oldCustomerPivot->id,
            'reference' => $booking->id,
            'customer_ledger_id' => $oldPrincipalCL->id,
            'amount_in' => $oldAmount,
            'amount_out' => 0,
            'date' => $booking->booking_date,
            'description' => "Old Party Credit  - {$plotType}{$plotName}",
            'update_by' => $userId,
            'create_by' => $userId,
            'status' => 0,
        ]);

        // Profit credit to old party
        if (bccomp($profit, '0.00', 2) === 1) {
            $oldProfitCL = CustomerLedger::create([
                'customer_id' => $oldCustomerId,
                'transaction_type' => 'Bo',
                'type_id' => get_new_typeID('Bo'),
                'project_id' => $booking->project_id,
                'plot_id' => $booking->plot_id,
                'amount_in' => $profit,
                'amount_out' => 0,
                'description' => "Plot Transfer Profit Credit ({$plotType}{$plotName})",
                'date' => $booking->booking_date,
                'is_approve' => 1,
                'is_active' => 1,
            ]);
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $oldCustomerPivot->id,
                'reference' => $booking->id,
                'customer_ledger_id' => $oldProfitCL->id,
                'amount_in' => $profit,
                'amount_out' => 0,
                'date' => $booking->booking_date,
                'description' => "Old Party Credit (Profit) - {$plotType}{$plotName}",
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }

        /**
         * ===============================
         * NEW CUSTOMER (payment outgoing)
         * ===============================
         * 60 lac Project Sale Debit New Party  (means customer pays out)
         */
        $newCL = CustomerLedger::create([
            'customer_id' => (int) $booking->customer_id,
            'transaction_type' => 'Bo',
            'type_id' => get_new_typeID('Bo'),
            'project_id' => $booking->project_id,
            'plot_id' => $booking->plot_id,
            'amount_in' => 0,
            'amount_out' => $newAmount,
            'description' => "Plot Transfer Payment ({$plotType}{$plotName})",
            'date' => $booking->booking_date,
            'is_approve' => 1,
            'is_active' => 1,
        ]);
        $this->recordSalesVoucherEntry($salesVoucher, [
            'account_id' => $newCustomerPivot->id,
            'reference' => $booking->id,
            'customer_ledger_id' => $newCL->id,
            'amount_in' => 0,
            'amount_out' => $newAmount,
            'date' => $booking->booking_date,
            'description' => "New Party Payment OUT - {$plotType}{$plotName}",
            'update_by' => $userId,
            'create_by' => $userId,
            'status' => 0,
        ]);

        /**
         * ============================================
         * SYSTEM ACCOUNTS: settle IN from new customer
         * ============================================
         * 50 lac Total Sale Credit
         * 10 lac Party Profit Credit
         */
        $this->recordSalesVoucherEntry($salesVoucher, [
            'account_id' => $totalSalePhsId,
            'reference' => $booking->id,
            'amount_in' => $oldAmount,
            'amount_out' => 0,
            'date' => $booking->booking_date,
            'description' => "Total Sale Settlement IN (New Party) - {$plotType}{$plotName}",
            'update_by' => $userId,
            'create_by' => $userId,
            'status' => 0,
        ]);

        if ($partyProfitPhsId && bccomp($profit, '0.00', 2) === 1) {
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $partyProfitPhsId,
                'reference' => $booking->id,
                'amount_in' => $profit,
                'amount_out' => 0,
                'date' => $booking->booking_date,
                'description' => "Party Profit Settlement IN (New Party) - {$plotType}{$plotName}",
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }



        /**
         * =========================================
         * FILE TRANSFER FEE (debit party + credit fee account)
         * =========================================
         */
        $partyFee = $this->sanitizeMoney($request->input('party_fee'));
        if ($request->filled('party') && bccomp($partyFee, '0.00', 2) === 1) {

            $feePayerCustomerId = ($request->input('party') === 'old')
                ? $oldCustomerId
                : (int) $booking->customer_id;
            // Debit payer (CP)
            $feePayerPivot = ($feePayerCustomerId === $oldCustomerId) ? $oldCustomerPivot : $newCustomerPivot;

            $feeCL = CustomerLedger::create([
                'customer_id' => $feePayerCustomerId,
                'transaction_type' => 'CP', // you already use CP in your fee logic
                'type_id' => get_new_typeID('CP'),
                'reference' => $request->input('reference'),
                'project_id' => $booking->project_id,
                'plot_id' => $booking->plot_id,
                'amount_in' => 0,
                'amount_out' => $partyFee,
                'description' => $request->input('details') ?: "File Transfer Fee ({$plotType}{$plotName})",
                'date' => $booking->booking_date,
                'payment_type' => $request->input('payment_type') ?? '1',
                't_number' => null,
                'bank_id' => null,
                'is_active' => 1,
                'is_approve' => 0,
                'passing_date' => $request->input('passing_date'),
            ]);

            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $feePayerPivot->id,
                'reference' => $booking->id,
                'customer_ledger_id' => $feeCL->id,
                'amount_in' => 0,
                'amount_out' => $partyFee,
                'date' => $booking->booking_date,
                'description' => "File Transfer Fee OUT - {$plotType}{$plotName}",
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);

            // Credit File Transfer Fee account (CR) (auto-create allowed)
            $feeAccountId = $this->systemAccountIdBySubheadName((int) $booking->project_id, 16, 'File Transfer Fee', true);
            $this->recordSalesVoucherEntry($salesVoucher, [
                'account_id' => $feeAccountId,
                'reference' => $booking->id,
                'amount_in' => $partyFee,
                'amount_out' => 0,
                'date' => $booking->booking_date,
                'description' => "File Transfer Fee IN - {$plotType}{$plotName}",
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }
    }

    // public function update_backup(Request $request)
    // {
    //     // dd($request->all());
    //         // Validate the incoming request data
    //     $validator = Validator::make($request->all(), [
    //         'project_id' => 'required|exists:projects,id',
    //         'customer_id' => 'required|exists:leads,id',
    //         'plot_id' => 'required|string',
    //         'plot_type' => 'required',
    //         'plot_size' => 'required',
    //         'plot_rate' => 'required',
    //         'total_price' => 'required',
    //         'booking_date' => 'required|date',
    //         'status' => 'required|numeric',
    //     ]);
    //     if ($validator->fails()) {
    //         return back()->withErrors($validator)->withInput();
    //     }

    //     $plotType = $request->plot_type;
    //     if ($plotType == 1) {
    //         $plotType = 'R-';
    //     } elseif ($plotType == 2) {
    //         $plotType = 'C-';
    //     }
    //     DB::beginTransaction();

    //     try {
    //         // Create the booking
    //         // return $request->all();
    //         $booking = Booking::updateOrCreate(
    //             ['id' => $request->id], // condition to check if booking exists
    //             [
    //                 'project_id'   => $request->input('project_id'),
    //                 'customer_id'  => $request->input('customer_id'),
    //                 'plot_id'      => $request->input('plot_id'),
    //                 'plot_type'    => $request->input('plot_type'),
    //                 'plot_size'    => $request->input('plot_size'),
    //                 'plot_rate'    => str_replace(',', '', $request->input('plot_rate')),
    //                 'is_corner'    => $request->input('is_corner') ?? 0,
    //                 'is_park'      => $request->input('is_park') ?? 0,
    //                 'park_facing'  => $request->input('park_facing') ?? 0,
    //                 'carner_price' => $request->input('carner_price') ?? 0,
    //                 'dicount_value'=> $request->input('discount_value') ?? 0,
    //                 'total_price'  => str_replace(',', '', $request->input('total_price')),
    //                 'broker_id'    => $request->input('broker_id'),
    //                 'booking_date' => $request->input('booking_date'),
    //                 'status'       => $request->input('status'),
    //                 'user_id'      => Auth::id(),
    //             ]
    //         );

    //         // Update the plot status
    //         Plot::where('id', $booking->plot_id)->update(['sold' => 1]);

    //         // Fetch plot and customer details
    //         $plotName = Plot::where('id', $booking->plot_id)->value('name');
    //         $customer = Lead::findOrFail($booking->customer_id);

    //         // Create customer ledger entry for new
    //         $customerLedger = CustomerLedger::updateOrCreate(
    //             [
    //                 'project_id' => $booking->project_id,
    //                 'plot_id'    => $booking->plot_id,
    //             ],
    //             [
    //                 'customer_id'       => $booking->customer_id,
    //                 'transaction_type'  => 'Bo',
    //                 'type_id'           => get_new_typeID('Bo'),
    //                 'amount_in'         => 0,
    //                 'amount_out'        => $booking->total_price,
    //                 'description'       => 'Booking for plot ' . $plotType . $plotName,
    //                 'date'              => $booking->booking_date,
    //                 'is_approve'        => 1,
    //                 'is_active'         => 1,
    //             ]
    //         );

    //         // Create subhead accounting
    //         $subheadAccounting = SubheadAccounting::create([
    //             'name' => '(' . $plotType . $plotName . ') ' . $customer->first_name . ' ' . $customer->last_name,
    //             'is_active' => 1,
    //             'cnic' => $customer->nic_number ?? '00000',
    //             'phone' => $customer->phone_number ?? '00000',
    //             'create_by' => Auth::user()->id,
    //         ]);

    //         $pivot = ProjectHeadSubhead::create([
    //             'project_id' => $booking->project_id,
    //             'head_accounting_id' => 16,
    //             'subhead_accounting_id' => $subheadAccounting->id,
    //             'plot_id' => $booking->plot_id,
    //             'customer_id' => $booking->customer_id,
    //         ]);



    //         // Create ledger entry
    //         $lastId = getLastLedgerIdByType("BO");
    //         $ledger = Ledger::where('type', 'BO')
    //             ->where('reference', $booking->id)
    //             ->first();

    //         if ($ledger) {
    //             // 🔹 Update existing record (don't touch type_id)
    //             $ledger->update([
    //                 'customer_ledger_id'       => $customerLedger->id,
    //                 'project_head_subheads_id' => $pivot->id,
    //                 'amount_in'                => 0.00,
    //                 'amount_out'               => $booking->total_price,
    //                 'is_active'                => 1,
    //                 'date'                     => $booking->booking_date,
    //                 'detail'                   => 'Booking for plot ' . $plotType . $plotName,
    //                 'update_by'                => Auth::user()->id,
    //                 'status'                   => 0,
    //             ]);
    //         } else {
    //             // 🔹 Create new record (include type_id)
    //             $ledger = Ledger::create([
    //                 'type'                    => 'BO',
    //                 'reference'               => $booking->id,
    //                 'customer_ledger_id'      => $customerLedger->id,
    //                 'type_id'                 => $lastId + 1, // only here
    //                 'project_head_subheads_id'=> $pivot->id,
    //                 'amount_in'               => 0.00,
    //                 'amount_out'              => $booking->total_price,
    //                 'is_active'               => 1,
    //                 'date'                    => $booking->booking_date,
    //                 'detail'                  => 'Booking for plot ' . $plotType . $plotName,
    //                 'update_by'               => Auth::user()->id,
    //                 'create_by'               => Auth::user()->id,
    //                 'status'                  => 0,
    //             ]);
    //         }



    //         // Ensure credit account exists
    //         $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', 16)
    //             ->where('project_id', $booking->project_id)
    //             ->where('subhead_accounting_id', 123)
    //             ->value('id');

    //         if (!$creditAccountId) {
    //             throw new \Exception('Credit account ID not found.');
    //         }

    //         $ledger = Ledger::where('reference', $booking->id)
    //             ->where('type', '!=', 'BO')
    //             ->first();

    //         if ($ledger) {
    //             // Update existing record
    //             $ledger->update([
    //                 'type'                    => 'CR',
    //                 'project_head_subheads_id'=> $creditAccountId,
    //                 'amount_in'               => $booking->total_price,
    //                 'amount_out'              => 0.00,
    //                 'is_active'               => 1,
    //                 'date'                    => $booking->booking_date,
    //                 'detail'                  => 'Booking for plot ' . $plotType . $plotName,
    //                 'update_by'               => Auth::user()->id,
    //                 'status'                  => 0,
    //             ]);
    //         } else {
    //             // Create new record
    //             $ledger = Ledger::create([
    //                 'type'                    => 'CR',
    //                 'type_id'                 => $lastId + 2,
    //                 'project_head_subheads_id'=> $creditAccountId,
    //                 'reference'               => $booking->id,
    //                 'amount_in'               => $booking->total_price,
    //                 'amount_out'              => 0.00,
    //                 'is_active'               => 1,
    //                 'date'                    => $booking->booking_date,
    //                 'detail'                  => 'Booking for plot ' . $plotType . $plotName,
    //                 'update_by'               => Auth::user()->id,
    //                 'create_by'               => Auth::user()->id,
    //                 'status'                  => 0,
    //             ]);
    //         }


    //         if($request->has('party') && $request->input('party_fee') > 0)  {
    //             if($request->input('party') == 'old') {
    //                 $customer_id = $request->input('old_customer_id');
    //             } else {
    //                 $customer_id = $request->input('customer_id');
    //             }

    //             $t_number = $bank_id = null;

    //             $plotName = Plot::where('id', $request->input('plot_id'))->value('name');
    //             $customerLedger = CustomerLedger::create([
    //                 'customer_id' => $customer_id,
    //                 'transaction_type' => 'CP',
    //                 'type_id' => get_new_typeID('CP'),
    //                 'reference' => $request->input('reference'),
    //                 'project_id' => $request->input('project_id'),
    //                 'plot_id' => $request->input('plot_id'),
    //                 'amount_in' => 0,
    //                 'amount_out' => str_replace(',', '', $request->input('party_fee')),
    //                 'description' => $request->input('details'),
    //                 'date' => $request->input('booking_date'), // Assuming booking date is the transaction date
    //                 'payment_type' => $request->input('payment_type') ?? '1',
    //                 't_number' => $t_number,
    //                 'bank_id' => $bank_id,
    //                 'is_active' => 1,
    //                 'is_approve' => 0,
    //                 'passing_date' => $request->input('passing_date'),
    //             ]);


    //             $lastId = getLastLedgerIdByType('CP');


    //             $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', 16)
    //                 ->where('customer_id', $customer_id)
    //                 ->where('plot_id', $request->input('plot_id'))
    //                 ->where('project_id', $request->input('project_id'))
    //                 ->first();
    //             // dd($projectHeadSubhead->id);
    //             // $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
    //             //     ->where('subhead_accounting_id', $request->subaccounts_id)
    //             //     ->where('project_id', $selectedProjectId)
    //             //     ->where('plot_id', $request->input('plot_id'))
    //             //     ->where('customer_id', $customer_id)
    //             //     ->value('id');
    //             //     dd($creditAccountId);
    //             if (!$projectHeadSubhead) {
    //                 throw new \Exception('Credit account ID not found.');
    //             }
    //             $data = Ledger::create([
    //                 'customer_ledger_id' => $customerLedger->id,
    //                 'type' => 'CP',
    //                 'type_id' => $lastId + 1,
    //                 'project_head_subheads_id' => $projectHeadSubhead->id,
    //                 'reference' => $request->reference,
    //                 'amount_in' => 0,
    //                 'amount_out' => str_replace(',', '', $request->input('party_fee')),
    //                 'is_active' => 1,
    //                 'date' => $request->booking_date,
    //                 'detail' => $request->details,
    //                 'update_by' => Auth::user()->id,
    //                 'create_by' => Auth::user()->id,
    //                 'status' => 0,

    //             ]);
    //             $lastSubmitDate = $request->booking_date; // Adjust this according to your actual form field
    //             session(['last_submit_date' => $lastSubmitDate]);

    //             // if ($request->id) {
    //                 // DraftLedger::find($request->input('id'))->delete();
    //             // }
    //         }
    //         DB::commit();

    //         Alert::success('Notification', 'Data updated successfully')->toToast()->toHtml();
    //     } catch (\Throwable $th) {
    //         DB::rollback();
    //         Log::error('Error storing booking: ' . $th->getMessage());
    //         Alert::error('Notification', 'An error occurred: ' . $th->getMessage())->toToast()->toHtml();
    //     }

    //     return back();
    // }


    public function destroy(Request $request)
    {
        $data = [
            'is_active' => "0",
        ];
        DB::beginTransaction();
        try {
            $ledger = CustomerLedger::find($request->id);

            if (Auth::user()->hasRole('super-admin') || Auth::user()->can('direct-update')) {
                $ledger->update(['is_active' => 0]);
                DB::commit();

                Alert::success('Notification', 'Voucher <b>' . $ledger->detail . '</b> deleted successfully.')
                    ->toToast()->toHtml();
                return back();
            }

            PendingUpdate::create([
                'table_name' => 'customer_ledger',
                'record_id' => $ledger->id,
                'old_values' => json_encode($ledger->toArray()),
                'new_values' => json_encode($data),
                'status' => 'pending',
                'submitted_by' => Auth::id(),
            ]);
            DB::commit();
            Alert::info('Notification', 'Delete request for <b>' . $ledger->detail . '</b> is pending admin approval.')
                ->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $ledger->name . '</b> failed to delete: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function approve(Request $request)
    {
        // dd($request->input());
        $data = [
            'is_approve' => $request->is_approve,
        ];
        DB::beginTransaction();
        try {
            $result = CustomerLedger::find($request->id);
            $result->update($data);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> update')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> failed to update: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function getCustomers(Request $request)
    {
        // Retrieve project ID from the AJAX request
        $projectId = $request->input('project_id');

        // Query the database to get customers associated with the project
        $customers = Lead::where('project_id', $projectId)->get();

        // Return customers as JSON response
        return response()->json($customers);
    }
    public function getCustomersbyPlot_old(Request $request)
    {
        // Retrieve project ID from the AJAX request
        $projectId = $request->input('project_id');

        // Query the database to get customers associated with the project
        $customers = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')->where('leads.project_id', $projectId)
            ->select('leads.*') // Select all columns from the leads table
            ->get();


        // Return customers as JSON response
        return response()->json($customers);
    }

    public function getCustomersbyPlot(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
        ]);

        // Retrieve project ID
        $projectId = $request->input('project_id');

        // Query the database to get unique customers associated with the project
        $customers = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $projectId)
            ->whereNull('bookings.deleted_at') // Exclude soft-deleted bookings
            ->select('leads.*') // Select all columns from the leads table
            ->distinct() // Ensure uniqueness by lead id
            ->get();

        // Check if customers are found
        if ($customers->isEmpty()) {
            return response()->json(['message' => 'No customers found'], 404);
        }

        // Return unique customers as JSON response
        return response()->json($customers);
    }


    public function getPlots(Request $request)
    {
        $plot_type = $request->input('plot_type');
        $projectId = $request->input('project_id');

        $customers = Plot::whereDoesntHave('holdPlots')->where('type', $plot_type)->where('project_id', $projectId)->where('sold', 0)->get();

        // Return customers as JSON response
        return response()->json($customers);
    }

    public function getCustomerPlots(Request $request)
    {
        $projectId = $request->input('project_id');
        $customerId = $request->input('customer_id');

        $customers = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
            ->where('plots.project_id', $projectId)
            ->where('bookings.customer_id', $customerId)
            ->get();

        // Return customers as JSON response
        return response()->json($customers);
    }
    public function getPlotCustomer(Request $request)
    {
        $plotId = $request->input('plot_id');

        $customer = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
            ->join('leads', 'bookings.customer_id', '=', 'leads.id') // if you want customer data
            ->where('plots.id', $plotId)
            ->select('leads.*') // select customer info only
            ->first(); // use `first()` for a single record

        return response()->json($customer);
    }

    public function scheduleForm(Request $request, $id)
    {
        $booking_id = $id;
        $x['title'] = 'Plots Sale List';
        $x['data'] = Booking::with('customer', 'plot', 'project')->where('cancel_status', '0')->findOrFail($booking_id);
        $x['role'] = Role::get();

        return view('admin.booking.schedule_form', $x);
    }

    public function PriceForm(Request $request, $id)
    {
        $booking_id = $id;
        $x['title'] = 'Plots Price Update';
        $x['data'] = Booking::with('customer', 'plot', 'project')->where('cancel_status', '0')->findOrFail($booking_id);
        $x['role'] = Role::get();

        return view('admin.booking.price_form', $x);
    }


    public function storePaymentSchedule(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'total_price' => 'required|numeric',
            'instalment' => 'required|numeric|digits_between:1,2', // Set maximum 2 digits
            'payment_plan' => 'required|in:1,3,6,12',
            'start_date' => 'required|date',
            'installment_number.*' => 'required',
            'amount.*' => 'required|numeric',
            'due_date.*' => 'required|date',
        ]);

        // Delete existing booking details with the provided booking_id
        BookingDetail::where('booking_id', $request->input('booking_id'))->delete();

        // Store booking details data
        $bookingId = $request->input('booking_id');
        $totalPrice = $request->input('total_price');
        $instalment = $request->input('instalment');
        $paymentPlan = $request->input('payment_plan');
        $startDate = $request->input('start_date');
        $installmentNumbers = $request->input('installment_number');
        $amounts = $request->input('amount');
        $dueDates = $request->input('due_date');

        // Store initial booking details
        foreach ($installmentNumbers as $key => $installmentNumber) {
            BookingDetail::create([
                'booking_id' => $bookingId,
                'installment_details' => $installmentNumber,
                'amount' => $amounts[$key],
                'due_date' => $dueDates[$key],
            ]);
        }

        // Calculate remaining amount after deducting initial installments
        $installmentAmount = array_sum($amounts);
        $remaining = $totalPrice - $installmentAmount;

        // Calculate installment amount excluding decimals
        $installmentNewAmount = floor($remaining / $instalment);

        // Calculate total amount distributed among installments including decimals
        $totalDistributedAmount = $installmentNewAmount * $instalment;

        // Calculate the remaining decimal amount
        $remainingDecimals = $remaining - $totalDistributedAmount;
        // dd( $remainingDecimals);

        // Calculate next installment due date
        $nextDueDate = $startDate;

        // Store booking details data for new installments
        for ($i = 1; $i <= $instalment; $i++) {
            // dd($instalment);
            // Calculate due date for this installment
            $dueDate = $nextDueDate;

            // If it's not the last installment, calculate next due date
            if ($i < $instalment) {
                $nextDueDate = date('Y-m-d', strtotime("+" . $paymentPlan . " months", strtotime($nextDueDate)));
            }

            // Determine the amount for this installment
            $amount = $installmentNewAmount;

            // If it's the last installment, add the remaining amount including decimals
            if ($i == $instalment) {
                $amount += $remainingDecimals;
            }

            // Create booking detail
            BookingDetail::create([
                'booking_id' => $bookingId,
                'installment_details' => 'Installment ' . $i,
                'amount' => $amount,
                'due_date' => $dueDate,
            ]);
        }

        // Redirect back with success message
        return redirect()->route('booking.plot.index')->with('success', 'Booking details created successfully.');

        // return back()->with('success', 'Booking details created successfully.');
    }




    public function cash_in(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Receive Plot Payment';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'PPR';
        $x['class'] = 'cash-in';
        $x['bg_voucher'] = 'info-cash-in';

        $baseQuery = BookingVoucher::with([
            'customerLedger',
            'customerLedger.customer_list',
            'customerLedger.plot_list',
            'customerLedger.project_list',
            'ledger.projectHeadSubhead.subheadAccounting',
            'ledger.projectHeadSubhead.plot',
            'customer',
            'plot',
            'project',
        ])
            ->where('voucher_series', 'PPR')
            ->where('is_active', '1')
            ->where('project_id', $selectedProjectId);
        $query = clone $baseQuery;

        if ($request->filled('customer_id')) {
            $customerFilter = (string) $request->input('customer_id');

            if (str_starts_with($customerFilter, 'subhead:')) {
                $subheadId = (int) substr($customerFilter, 8);
                $query->whereHas('ledger.projectHeadSubhead', function ($relationQuery) use ($subheadId) {
                    $relationQuery->where('subhead_accounting_id', $subheadId);
                });
            } elseif (str_starts_with($customerFilter, 'lead:')) {
                $leadId = (int) substr($customerFilter, 5);
                $query->where('customer_id', $leadId);
            } else {
                $query->where('customer_id', $customerFilter);
            }
        }

        if ($request->filled('plot_id')) {
            $plotId = (int) $request->input('plot_id');
            $query->where(function ($plotQuery) use ($plotId) {
                $plotQuery->where('plot_id', $plotId)
                    ->orWhereHas('ledger.projectHeadSubhead', function ($relationQuery) use ($plotId) {
                        $relationQuery->where('plot_id', $plotId);
                    });
            });
        }

        if ($request->filled('reference')) {
            $reference = trim($request->input('reference'));
            $query->where(function ($referenceQuery) use ($reference) {
                $referenceQuery->where('slip_reference', 'like', '%' . $reference . '%')
                    ->orWhereHas('customerLedger', function ($customerLedgerQuery) use ($reference) {
                        $customerLedgerQuery->where('reference', 'like', '%' . $reference . '%');
                    });
            });
        }

        if ($request->filled('fdate')) {
            $query->whereDate('receipt_date', '>=', $request->input('fdate'));
        }

        if ($request->filled('tdate')) {
            $query->whereDate('receipt_date', '<=', $request->input('tdate'));
        }

        if ($request->filled('status') && in_array((string) $request->input('status'), ['0', '1', '2'], true)) {
            $query->where('is_approve', $request->input('status'));
        }

        $data = $query->orderByDesc('id')->get();
        $data = $data->map(function ($item) {
            $pending = PendingUpdate::where('table_name', 'customer_ledger') // 👈 dynamic table name
                ->where('record_id', $item->customer_ledger_id)
                ->latest()
                ->first();
            $item['submitted_by'] = $pending ? $pending->submittedBy?->name : '';
            $item['new_values'] = $pending ? json_decode($pending->new_values, true) : '';
            $item['old_values'] = $pending ? json_decode($pending->old_values, true) : '';
            $item['status'] = $pending ? ucfirst($pending->status) : 'Approved';
            return $item;
        });
        $x['data'] = $data;

        $baseRows = (clone $baseQuery)->get();
        $plotIds = $baseRows->pluck('plot_id')
            ->merge($baseRows->map(function ($item) {
                return optional(optional($item->ledger)->projectHeadSubhead)->plot_id;
            }))
            ->filter()
            ->unique()
            ->values();

        $plotLookup = Plot::whereIn('id', $plotIds)->get()->keyBy('id');
        $plotLabels = $plotLookup->mapWithKeys(function ($plot) {
            $label = trim($this->plotTypePrefix((int) ($plot->type ?? 0)) . ($plot->name ?? ''));
            return [$plot->id => $label !== '' ? $label : '—'];
        })->toArray();

        $x['projects'] = Project::where('id', $selectedProjectId)->get();
        $x['customers'] = $baseRows->map(function ($item) {
            $customer = $item->customer ?: optional($item->customerLedger)->customer_list;
            if ($customer) {
                $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                if ($name !== '') {
                    return [
                        'value' => 'lead:' . $customer->id,
                        'label' => $name,
                    ];
                }
            }

            $subhead = optional(optional($item->ledger)->projectHeadSubhead)->subheadAccounting;
            if ($subhead && !empty($subhead->name)) {
                return [
                    'value' => 'subhead:' . $subhead->id,
                    'label' => $subhead->name,
                ];
            }

            return null;
        })->filter()->unique('value')->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $x['plotLabels'] = $plotLabels;
        $x['plots'] = $plotIds->map(function ($plotId) use ($plotLabels) {
            $label = $plotLabels[$plotId] ?? '—';

            if ($label === '—') {
                return null;
            }

            return [
                'value' => $plotId,
                'label' => $label,
            ];
        })->filter()->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $x['filters'] = [
            'customer_id' => $request->input('customer_id'),
            'plot_id' => $request->input('plot_id'),
            'reference' => $request->input('reference'),
            'fdate' => $request->input('fdate'),
            'tdate' => $request->input('tdate'),
            'status' => $request->input('status'),
        ];
        $x['nextVoucherNumber'] = $this->getNextBookingVoucherNumberForProject((int) $selectedProjectId, 'PPR');

        return view('admin.booking.receive', $x);
    }
    public function updateCashIn(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string|max:255',
            'date' => 'required|date',
            'amount_out' => ['required', 'regex:/^\d[\d,]*(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:255',
            'payment_type' => 'required|in:1,2,3',
            't_number' => 'nullable|string|max:255',
            'bank_id' => 'nullable',
            'passing_date' => 'nullable|date',
        ]);

        $validator->sometimes('t_number', 'required|string|max:255', function ($input) {
            return in_array((string) $input->payment_type, ['2', '3'], true);
        });

        $validator->sometimes('bank_id', 'required', function ($input) {
            return in_array((string) $input->payment_type, ['2', '3'], true);
        });

        $validated = $validator->validate();

        // 🛑 Remove commas from amount_out
        $validated['amount_out'] = str_replace(',', '', $validated['amount_out']);

        if ((string) $validated['payment_type'] === '1') {
            $validated['t_number'] = null;
            $validated['bank_id'] = null;
            $validated['passing_date'] = null;
        }

        try {
            DB::transaction(function () use ($validated, $id) {
                $customerLedger = CustomerLedger::with('ledger')
                    ->where('id', $id)
                    ->where('project_id', getSelectedTown())
                    ->firstOrFail();

                if (auth()->user()->cannot('direct-update')) {
                    PendingUpdate::create([
                        'record_id' => $customerLedger->id,
                        'table_name' => 'customer_ledger',
                        'new_values' => json_encode($validated),
                        'old_values' => json_encode($customerLedger->only(array_keys($validated))),
                        'status' => 'pending',
                        'submitted_by' => Auth::id(),
                    ]);

                    return;
                }

                $customerLedger->update($validated);

                if ($customerLedger->ledger) {
                    $ledgerPayload = [
                        'date' => $validated['date'],
                        'detail' => '(Cash slip#' . $validated['reference'] . ') ' . ($validated['description'] ?? ''),
                    ];

                    if (in_array((string) $customerLedger->ledger->type, ['CR', 'PPR'], true)) {
                        $ledgerPayload['amount_in'] = $validated['amount_out'];
                    } else {
                        $ledgerPayload['amount_out'] = $validated['amount_out'];
                    }

                    $customerLedger->ledger->update($ledgerPayload);
                }

                $this->syncBookingVoucherRecord($customerLedger->fresh(['ledger']));
            });
        } catch (\Throwable $th) {
            Log::error('Receive plot payment update failed', [
                'customer_ledger_id' => $id,
                'user_id' => Auth::id(),
                'message' => $th->getMessage(),
            ]);

            return redirect()->back()->withErrors([
                'msg' => 'Unable to update the voucher right now. Please try again.',
            ])->withInput();
        }

        $message = auth()->user()->cannot('direct-update')
            ? 'Update request submitted for approval.'
            : 'Ledger updated successfully!';

        return redirect()->back()->with('success', $message);
    }


    public function extraCharge()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Extra Charges';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'PPR';
        $x['class'] = 'cash-in';
        $x['bg_voucher'] = 'info-cash-in';
        $projectId = getSelectedTown();
        $data = CustomerLedger::with('customer_list', 'plot_list')->where('transaction_type', 'null')->where('is_active', '1')->where('project_id', getSelectedTown())->get();
        // Query the database to get unique customers associated with the project
        $x['customers'] = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $projectId)
            ->whereNull('bookings.deleted_at') // Exclude soft-deleted bookings
            ->select('leads.*') // Select all columns from the leads table
            ->distinct() // Ensure uniqueness by lead id
            ->get();
        $data = $data->map(function ($item) {
            $pending = PendingUpdate::where('table_name', 'customer_ledger') // 👈 dynamic table name
                ->where('record_id', $item->id)
                ->latest()
                ->first();
            $item['submitted_by'] = $pending ? $pending->submittedBy?->name : '';
            $item['new_values'] = $pending ? json_decode($pending->new_values, true) : '';
            $item['old_values'] = $pending ? json_decode($pending->old_values, true) : '';
            $item['status'] = $pending ? ucfirst($pending->status) : 'Approved';
            return $item;
        });
        $x['data'] = $data;

        $chargeTypes = ChargeType::get();
        $x['chargeTypes'] = $chargeTypes;

        return view('admin.booking.extra_charge', $x);
    }

    public function customer_report_form(Request $request)
    {
        $x['title'] = 'Customer Report';
        // $x['data']      = Booking::with('customer', 'plot', 'project')->findOrFail(1);
        $x['role'] = Role::get();
        // dd("asda");
        $selectedProjectId = getSelectedTown();
        $x['customers'] = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $selectedProjectId)
            ->whereNull('bookings.deleted_at') // Exclude soft-deleted bookings
            ->select('leads.*') // Select all columns from the leads table
            ->distinct() // Ensure uniqueness by lead id
            ->get();
        $x['plots'] = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
            ->where('plots.project_id', $selectedProjectId)
            ->get();

        return view('admin.reports.bookings.customer_report', $x);
    }


    public function deposit(Request $request)
    {
        // dd($request->input());
        // Validate the incoming request data
        $validatedData = $request->validate([
            'customer_id' => 'required',
            'plot_id' => 'required',
            'amount' => 'required',
            'detail' => 'required',
            'payment_type' => 'required',
            'reference' => 'required',

        ]);;

        $action = $request->input('action');
        // dd($request->input());
        $customer_id = $request->input('customer_id');
        $x['today'] = date("d-m-Y");

        try {
            switch ($action) {

                case 'deposit':
                    DB::transaction(function () use ($request, $customer_id) {
                        $payment_type = $request->input('payment_type');
                        $isPendingBankPayment = in_array((int) $payment_type, [2, 3], true);
                        $projectId = (int) $request->input('project_id');
                        $displayVoucherNumber = $this->allocateNextBookingVoucherNumber($projectId, 'PPR');
                        if ($payment_type == 1) {
                            $t_number = $bank_id = null;
                        } else {
                            $t_number = $request->input('t_number');
                            $bank_id = $request->input('bank_id');
                        }

                        $customerLedger = CustomerLedger::create([
                            'customer_id' => $customer_id,
                            'transaction_type' => 'PPR',
                            'type_id' => get_new_typeID('PPR'),
                            'reference' => $request->input('reference'),
                            'project_id' => $projectId,
                            'plot_id' => $request->input('plot_id'),
                            'amount_in' => 0,
                            'amount_out' => str_replace(',', '', $request->input('amount')),
                            'description' => $request->input('detail'),
                            'date' => $request->input('date'),
                            'payment_type' => $request->input('payment_type'),
                            't_number' => $t_number,
                            'bank_id' => $bank_id,
                            'is_active' => 1,
                            'is_approve' => 0,
                            'passing_date' => $request->input('passing_date'),
                            'passing_status' => $isPendingBankPayment ? 0 : null,
                        ]);

                        $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', 16)
                            ->where('project_id', $request->input('project_id'))
                            ->where('plot_id', $request->input('plot_id'))
                            ->where('customer_id', $customer_id)
                            ->value('id');

                        if (!$creditAccountId) {
                            throw new \Exception('Credit account ID not found.');
                        }

                        $ledgerType = 'PPR';
                        $voucherNumber = $displayVoucherNumber;
                        $lastId = getLastLedgerIdByType($ledgerType);

                        $ledger = null;
                        if (!$isPendingBankPayment) {
                            $ledger = Ledger::create([
                                'customer_ledger_id' => $customerLedger->id,
                                'type' => $ledgerType,
                                'voucher_number' => $voucherNumber,
                                'type_id' => ((int) $lastId) + 1,
                                'project_head_subheads_id' => $creditAccountId,
                                'reference' => $request->input('reference'),
                                'amount_in' => str_replace(',', '', $request->input('amount')),
                                'amount_out' => 0.00,
                                'is_active' => 1,
                                'date' => $request->input('date'),
                                'detail' => '(Cash slip#' . $request->input('reference') . ') ' . $request->input('detail'),
                                'update_by' => Auth::id(),
                                'create_by' => Auth::id(),
                                'status' => 0,
                            ]);
                        }

                        $this->syncBookingVoucherRecord($customerLedger, $ledger, $displayVoucherNumber > 0 ? $displayVoucherNumber : null);
                    });


                    Alert::success('Notification', 'Data <b></b> Save successfully ')->toToast()->toHtml();


                    break;

                case 'extra_charge':
                    $projectId = getSelectedTown();
                    // dd($request->input());
                    // Create entry in customer ledger
                    // dd($request->input());
                    $payment_type = $request->input('payment_type');
                    if ($payment_type == 1) {
                        $t_number = $bank_id = null;
                    } else {
                        $t_number = $request->input('t_number');
                        $bank_id = $request->input('bank_id');
                    }
                    $plotName = Plot::where('id', $request->input('plot_id'))->value('name');
                    $customerLedger = CustomerLedger::create([
                        'customer_id' => $customer_id,
                        'transaction_type' => 'null',
                        'type_id' => get_new_typeID('null'),
                        'reference' => $request->input('reference'),
                        'project_id' => $projectId,
                        'plot_id' => $request->input('plot_id'),
                        'amount_in' => 0,
                        'amount_out' => str_replace(',', '', $request->input('amount')),
                        'description' => $request->input('detail'),
                        'date' => $request->input('date'), // Assuming booking date is the transaction date
                        'payment_type' => $request->input('payment_type'),
                        't_number' => $t_number,
                        'bank_id' => $bank_id,
                        'is_active' => 1,
                        'is_approve' => 0,
                        'passing_date' => $request->input('passing_date'),
                    ]);


                    // Ensure credit account exists
                    $extraChargeHeadAccountId = HeadAccounting::where('name', 'Extra Charges')->value('id');
                    $extraChargeSubHeadAccountId = SubheadAccounting::where('name', 'Extra Charges')->value('id');
                    $creditAccountId = ProjectHeadSubhead::where('project_id', $projectId)
                        ->where('head_accounting_id', $extraChargeHeadAccountId)
                        ->where('subhead_accounting_id', $extraChargeSubHeadAccountId)
                        ->value('id');
                    // dd($creditAccountId);
                    if (!$creditAccountId) {
                        throw new \Exception('Credit account ID not found.');
                    }

                    $plotName = Plot::where('id', $request->input('plot_id'))->value('name');

                    $lastId = getLastLedgerIdByType("CR");

                    $voucherNumber = getVocuherNumber('CP');

                    Ledger::create([
                        'charge_type_id' => $request->input('charge_type_id'),
                        'customer_ledger_id' => $customerLedger->id,
                        'voucher' => $voucherNumber,
                        'type' => 'CR',
                        'type_id' => $lastId + 1,
                        'project_head_subheads_id' => $creditAccountId,
                        'reference' => $request->input('voucher'),
                        'amount_in' => str_replace(',', '', $request->input('amount')),
                        'amount_out' => 0.00,
                        'is_active' => 1,
                        'date' => $request->input('date'), // Assuming booking date is the transaction date
                        'detail' => '(Cash slip#' . $request->input('reference') . ') ' . $request->input('detail'),
                        'update_by' => Auth::user()->id,
                        'create_by' => Auth::user()->id,
                        'status' => 0,
                    ]);
                    $debitAccountId = ProjectHeadSubhead::where('head_accounting_id', 16)
                        ->where('project_id', $projectId)
                        ->where('plot_id', $request->input('plot_id'))
                        ->where('customer_id', $customer_id)
                        ->value('id');

                    if (!$debitAccountId) {
                        throw new \Exception('Debit account ID not found.');
                    }

                    $voucherNumber = getVocuherNumber('CP');
                    Ledger::create([
                        'charge_type_id' => $request->input('charge_type_id'),
                        'customer_ledger_id' => $customerLedger->id,
                        'type' => 'CP',
                        'voucher' => $voucherNumber,
                        'type_id' => $lastId + 1,
                        'project_head_subheads_id' => $debitAccountId,
                        'reference' => $request->input('voucher'),
                        'amount_in' => 0.00,
                        'amount_out' => str_replace(',', '', $request->input('amount')),
                        'is_active' => 1,
                        'date' => $request->input('date'), // Assuming booking date is the transaction date
                        'detail' => '(Cash slip#' . $request->input('reference') . ') ' . $request->input('detail'),
                        'update_by' => Auth::user()->id,
                        'create_by' => Auth::user()->id,
                        'status' => 0,
                    ]);


                    Alert::success('Notification', 'Data <b></b> Save successfully ')->toToast()->toHtml();


                    break;
            }
        } catch (\Throwable $e) {
            Log::error('Receive plot payment save failed', [
                'action' => $action,
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors(['msg' => 'Unable to save the voucher right now. Please try again.'])->withInput();
        }
        return back();
    }

    public function customer_report_display(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'customer_id' => 'required',
            'action' => 'required',

        ]);

        $action = $request->input('action');
        $customer_id = $request->input('customer_id');
        $x['today'] = date("d-m-Y");

        try {
            switch ($action) {
                case 'customer_report':
                    $x['title'] = 'Customer Report';
                    $x['data'] = CustomerLedger::with('customer_list', 'plot_list', 'project_list', 'ledger')
                        ->where('project_id', getSelectedTown())
                        ->where('customer_id', $customer_id)
                        ->where('is_active', 1)
                        ->where(function ($query) {
                            $query->where('is_approve', 1)
                                ->orWhereIn('transaction_type', ['CR', 'PPR']);
                        })
                        ->when($request->input('plot_id'), function ($query) use ($request) {
                            return $query->where('plot_id', $request->input('plot_id'));
                        })
                        ->orderBy('plot_id')
                        ->orderBy('date')
                        ->orderBy('id')
                        ->get()
                        ->unique('id')
                        ->values()
                        ->map(function ($row) {
                            $ledger = $row->ledger;
                            $transactionType = strtoupper(trim((string) $row->transaction_type));
                            $typeId = trim((string) ($row->type_id ?? ''));
                            $reference = trim((string) ($row->reference ?? ''));
                            $ledgerType = trim((string) optional($ledger)->type);
                            $ledgerVoucherNumber = trim((string) (optional($ledger)->voucher_number ?? optional($ledger)->voucher));
                            $displayAmount = (float) (($row->amount_out > 0) ? $row->amount_out : $row->amount_in);
                            $paymentTypeDetails = getPaymentTypeDetails($row->payment_type);
                            $statusMeta = collect(check_status())->firstWhere('id', $row->passing_status);

                            if ($ledgerType !== '' && $ledgerVoucherNumber !== '') {
                                $voucherNumberDisplay = $ledgerType . '-' . $ledgerVoucherNumber;
                            } elseif ($transactionType === 'PPR' && $typeId !== '') {
                                $voucherNumberDisplay = 'PPR-' . $typeId;
                            } elseif ($transactionType === 'CR' && $typeId !== '') {
                                $voucherNumberDisplay = 'CR-' . $typeId;
                            } elseif ($reference !== '' && preg_match('/[A-Za-z]/', $reference)) {
                                $voucherNumberDisplay = $reference;
                            } else {
                                $voucherNumberDisplay = 'Pending #' . $row->id;
                            }

                            if ($transactionType === 'PPR') {
                                $sourceLabel = 'Received Payment';
                            } elseif ($transactionType === 'CR') {
                                $sourceLabel = 'Cash In';
                            } else {
                                $sourceLabel = $transactionType !== '' ? $transactionType : 'Entry';
                            }

                            $row->setAttribute('voucher_number_display', $voucherNumberDisplay);
                            $row->setAttribute('source_label', $sourceLabel);
                            $row->setAttribute('display_amount', $displayAmount);
                            $row->setAttribute('balance_delta', (float) $row->amount_in - (float) $row->amount_out);
                            $row->setAttribute('payment_type_label', $paymentTypeDetails['name'] ?? '-');
                            $row->setAttribute(
                                'passing_status_label',
                                in_array((int) $row->payment_type, [2, 3], true) && $statusMeta
                                    ? ($statusMeta['name'] ?? '-')
                                    : '-'
                            );
                            $row->setAttribute(
                                'report_description',
                                trim(collect([
                                    $voucherNumberDisplay,
                                    $sourceLabel,
                                    $reference !== '' ? 'Slip: ' . $reference : null,
                                    $row->description,
                                ])->filter()->implode(' | '))
                            );

                            return $row;
                        });

                    // dd( $x['data']);
                    return view('admin.reports.bookings.customer', $x);

                    break;

                case 'booking_file':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',

                    ]);

                    $plot_id = $request->input('plot_id');
                    $x['title'] = 'Payment Plan';
                    $x['data'] = $booking = Booking::with('customer', 'plot', 'project', 'bookingDetails')
                        ->where('cancel_status', '0')
                        ->where('customer_id', $customer_id)
                        ->where('plot_id', $plot_id)
                        ->first();

                    // dd($x['data']);
                    return view('admin.reports.bookings.booking', $x);


                    break;

                case 'recovery_report':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',

                    ]);

                    $today = Carbon::today()->toDateString();


                    $plot_id = $request->input('plot_id');
                    $x['title'] = 'Customer Recovery Report';
                    $x['data'] = Booking::with([
                        'customer',
                        'plot',
                        'project',
                        'bookingDetails' => function ($query) use ($today) {
                            $query->whereDate('due_date', '<=', $today);
                        }
                    ])
                        ->where('customer_id', $customer_id)
                        ->where('cancel_status', '0')
                        ->where('plot_id', $plot_id)
                        ->first();

                    // dd($x['data']);
                    $customerLedgers = CustomerLedger::where('customer_id', $customer_id)->where('is_active', 1)
                        ->when($request->input('plot_id'), function ($query) use ($request) {
                            return $query->where('plot_id', $request->input('plot_id'));
                        })
                        ->orderBy('plot_id')
                        ->orderBy('id')
                        ->get();

                    $sumAmountOut = $customerLedgers->sum('amount_out');
                    // dd($sumAmountOut);

                    $x['recovery'] = $sumAmountOut;
                    $x['total_recovery'] = $sumAmountOut;

                    return view('admin.reports.bookings.customer_recovery', $x);


                    break;

                case 'recovery_report_details':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',
                    ]);

                    $today = Carbon::today()->toDateString();
                    $plot_id = $request->input('plot_id');

                    $x['title'] = 'Customer Payment Report';
                    $booking = Booking::with([
                        'customer',
                        'plot',
                        'project',
                        'bookingDetails' => function ($query) use ($today) {
                            $query->whereDate('due_date', '<=', $today);
                        }
                    ])
                        ->where('customer_id', $customer_id)
                        ->where('cancel_status', '0')
                        ->where('plot_id', $plot_id)
                        ->first();
                    $x['data'] = $booking;

                    $customerLedgers = CustomerLedger::where('customer_id', $customer_id)
                        ->where('is_active', 1)
                        ->where('transaction_type', "PPR")
                        ->when($request->input('plot_id'), function ($query) use ($request) {
                            return $query->where('plot_id', $request->input('plot_id'));
                        })
                        ->orderBy('plot_id')
                        ->orderBy('id')
                        ->get();
                    $bookingDetails = BookingDetail::where('booking_id', $booking->id)->whereDate('due_date', '<=', $today)->get();

                    $customer_ledger_record = [];


                    // Combine data from $customerLedgers and $bookingDetails


                    foreach ($bookingDetails as $detail) {
                        $customer_ledger_record[] = [
                            'date' => $detail->due_date, // Assuming `due_date` is the date field in the `BookingDetail` model
                            'details' => $detail->installment_details, // Provide a description for booking details
                            'amount_in' => $detail->amount, // Assuming `amount` is a column in the `BookingDetail` model
                            'amount_out' => 0, // Assuming there is no `amount_out` in `BookingDetail`
                        ];
                    }

                    foreach ($customerLedgers as $ledger) {
                        $customer_ledger_record[] = [
                            'date' => $ledger->date, // Assuming `date` is a column in the `CustomerLedger` model
                            'details' => 'Slip:' . $ledger->reference . ' ' . $ledger->description, // Assuming `details` is a column in the `CustomerLedger` model
                            'amount_in' => $ledger->amount_in, // Assuming `amount_in` is a column in the `CustomerLedger` model
                            'amount_out' => $ledger->amount_out, // Assuming `amount_out` is a column in the `CustomerLedger` model
                        ];
                    }

                    // Sort the combined array by date
                    usort($customer_ledger_record, function ($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });


                    return view('admin.reports.bookings.customer_recovery_by_details', array_merge($x, ['customer_ledger_record' => $customer_ledger_record]));

                    break;

                case 'customer_ledger':

                    $validatedData = $request->validate([
                        'plot_id' => 'required',
                    ]);

                    $today = Carbon::today()->toDateString();
                    $plot_id = $request->input('plot_id');

                    $x['title'] = 'Customer Payment Report';
                    $booking = Booking::with([
                        'customer',
                        'plot',
                        'project',
                        'bookingDetails' => function ($query) use ($today) {
                            $query->whereDate('due_date', '<=', $today);
                        }
                    ])
                        ->where('customer_id', $customer_id)
                        ->where('cancel_status', '0')
                        ->where('plot_id', $plot_id)
                        ->first();
                    $x['data'] = $booking;

                    $customerLedgers = CustomerLedger::where('customer_id', $customer_id)
                        ->where('is_active', 1)
                        ->where('transaction_type', "PPR")
                        ->when($request->input('plot_id'), function ($query) use ($request) {
                            return $query->where('plot_id', $request->input('plot_id'));
                        })
                        ->orderBy('plot_id')
                        ->orderBy('id')
                        ->get();
                    $bookingDetails = BookingDetail::where('booking_id', $booking->id)->get();

                    $customer_ledger_record = [];


                    // Combine data from $customerLedgers and $bookingDetails


                    foreach ($bookingDetails as $detail) {
                        $customer_ledger_record[] = [
                            'date' => $detail->due_date, // Assuming `due_date` is the date field in the `BookingDetail` model
                            'details' => $detail->installment_details, // Provide a description for booking details
                            'amount_in' => $detail->amount, // Assuming `amount` is a column in the `BookingDetail` model
                            'amount_out' => 0, // Assuming there is no `amount_out` in `BookingDetail`
                        ];
                    }

                    foreach ($customerLedgers as $ledger) {
                        $customer_ledger_record[] = [
                            'date' => $ledger->date, // Assuming `date` is a column in the `CustomerLedger` model
                            'details' => 'Slip:' . $ledger->reference . ' ' . $ledger->description, // Assuming `details` is a column in the `CustomerLedger` model
                            'amount_in' => $ledger->amount_in, // Assuming `amount_in` is a column in the `CustomerLedger` model
                            'amount_out' => $ledger->amount_out, // Assuming `amount_out` is a column in the `CustomerLedger` model
                        ];
                    }

                    // Sort the combined array by date
                    usort($customer_ledger_record, function ($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });


                    return view('admin.reports.bookings.customer_recovery_by_details', array_merge($x, ['customer_ledger_record' => $customer_ledger_record]));

                    break;
            }
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function fatch_voucher(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $dataList = CustomerLedger::with(['customer_list', 'plot_list', 'project_list', 'ledger'])
            ->where('id', $request->id)
            ->where('project_id', getSelectedTown())
            ->first();

        if (!$dataList) {
            return response()->json([
                'status' => Response::HTTP_NOT_FOUND,
                'message' => 'Voucher not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $customer = $dataList->customer_list;
        $plot = $dataList->plot_list;
        $project = $dataList->project_list;
        $ledger = $dataList->ledger;

        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Voucher loaded successfully.',
            'data' => [
                'id' => $dataList->id,
                'date' => $dataList->date,
                'reference' => $dataList->reference,
                'amount_out' => $dataList->amount_out,
                'description' => $dataList->description,
                'payment_type' => $dataList->payment_type,
                'payment_type_label' => getPaymentTypeDetails($dataList->payment_type)['name'],
                't_number' => $dataList->t_number,
                'bank_id' => $dataList->bank_id,
                'bank_name' => $dataList->bank_id ? getBankNameById($dataList->bank_id) : null,
                'passing_date' => $dataList->passing_date,
                'status' => $dataList->is_approve,
                'status_label' => approveStatus($dataList->is_approve),
                'created_at' => optional($dataList->created_at)->format('Y-m-d H:i:s'),
                'customer' => [
                    'id' => $customer?->id,
                    'name' => trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? '')),
                    'relate' => $customer?->relate,
                    'father_name' => $customer?->father_name,
                    'phone_number' => $customer?->phone_number,
                    'mobile_number' => $customer?->mobile_number,
                    'nic_number' => $customer?->nic_number,
                    'home_address' => $customer?->home_address,
                ],
                'plot' => [
                    'id' => $plot?->id,
                    'name' => $plot?->name,
                    'type' => $plot?->type,
                    'size' => $plot?->size,
                    'unit' => $plot?->unit,
                ],
                'project' => [
                    'id' => $project?->id,
                    'name' => $project?->project,
                ],
                'ledger' => [
                    'id' => $ledger?->id,
                    'type' => $ledger?->type,
                    'voucher_number' => $ledger?->voucher_number ?? $ledger?->voucher ?? null,
                    'reference' => $ledger?->reference,
                    'amount_in' => $ledger?->amount_in,
                    'amount_out' => $ledger?->amount_out,
                    'detail' => $ledger?->detail,
                    'date' => $ledger?->date,
                ],
            ]
        ], Response::HTTP_OK);
    }

    public function printCashIn($id)
    {
        $x['voucher'] = $customerLedger = CustomerLedger::with([
            'bookingVoucher',
            'customer_list',
            'plot_list',
            'project_list',
            'ledger.projectHeadSubhead.project',
            'ledger.projectHeadSubhead.subheadAccounting',
            'ledger.projectHeadSubhead.plot',
        ])
            ->where('id', $id)
            ->where('project_id', getSelectedTown())
            ->firstOrFail();

        $today = Carbon::today()->toDateString();

        $booking = Booking::where('customer_id', $customerLedger->customer_id)
            ->where('cancel_status', '0')
            ->where('plot_id', $customerLedger->plot_id)
            ->first();

        if (!$booking) {
            $x['balance'] = 0;
            $x['balances'] = 0;

            return view('admin.booking.print_receive', $x);
        }

        $customerLedgersQuery = CustomerLedger::where('customer_id', $customerLedger->customer_id)
            ->where('is_active', 1)
            ->where('transaction_type', 'PPR')
            ->when($customerLedger->plot_id, function ($query) use ($customerLedger) {
                return $query->where('plot_id', $customerLedger->plot_id);
            });

        $ledgerAmountIn = (float) (clone $customerLedgersQuery)->sum('amount_in');
        $ledgerAmountOut = (float) (clone $customerLedgersQuery)->sum('amount_out');

        $allBookingAmount = (float) BookingDetail::where('booking_id', $booking->id)
            ->sum('amount');

        $dueBookingAmount = (float) BookingDetail::where('booking_id', $booking->id)
            ->whereDate('due_date', '<=', $today)
            ->sum('amount');

        $x['balance'] = $allBookingAmount + $ledgerAmountIn - $ledgerAmountOut;

        $x['balances'] = $dueBookingAmount + $ledgerAmountIn - $ledgerAmountOut;

        return view('admin.booking.print_receive', $x);
    }

    private function getNextBookingVoucherNumberForProject(int $projectId, string $voucherSeries = 'PPR'): int
    {
        return $this->resolveNextBookingVoucherNumber($projectId, $voucherSeries, false);
    }

    private function allocateNextBookingVoucherNumber(int $projectId, string $voucherSeries = 'PPR'): int
    {
        return $this->resolveNextBookingVoucherNumber($projectId, $voucherSeries, true);
    }

    private function resolveNextBookingVoucherNumber(int $projectId, string $voucherSeries, bool $lockRows): int
    {
        $bookingVoucherQuery = BookingVoucher::query()
            ->where('project_id', $projectId)
            ->where('voucher_series', $voucherSeries)
            ->whereNotNull('voucher_number')
            ->where('voucher_number', '>', 0);

        if ($lockRows) {
            $bookingVoucherQuery->lockForUpdate();
        }

        $bookingVoucherNumbers = $bookingVoucherQuery->pluck('voucher_number');

        $ledgerQuery = Ledger::query()
            ->join('project_head_subheads', 'project_head_subheads.id', '=', 'ledgers.project_head_subheads_id')
            ->where('project_head_subheads.project_id', $projectId)
            ->where('ledgers.type', $voucherSeries)
            ->whereNotNull('ledgers.voucher_number')
            ->where('ledgers.voucher_number', '>', 0);

        if ($lockRows) {
            $ledgerQuery->lockForUpdate();
        }

        $ledgerNumbers = $ledgerQuery->pluck('ledgers.voucher_number');

        $numbers = $bookingVoucherNumbers->merge($ledgerNumbers)
            ->map(fn($number) => (int) $number)
            ->filter(fn(int $number) => $number > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $nextNumber = 1;

        foreach ($numbers as $number) {
            if ($number !== $nextNumber) {
                break;
            }

            $nextNumber++;
        }

        return $nextNumber;
    }

    private function syncBookingVoucherRecord(CustomerLedger $customerLedger, ?Ledger $ledger = null, ?int $fallbackVoucherNumber = null): BookingVoucher
    {
        $ledger = $ledger ?: $customerLedger->ledger;
        $bookingId = Booking::where('project_id', $customerLedger->project_id)
            ->where('customer_id', $customerLedger->customer_id)
            ->where('plot_id', $customerLedger->plot_id)
            ->where('cancel_status', '0')
            ->latest('id')
            ->value('id');

        return BookingVoucher::updateOrCreate(
            ['customer_ledger_id' => $customerLedger->id],
            [
                'booking_id' => $bookingId,
                'ledger_id' => $ledger?->id,
                'project_id' => $customerLedger->project_id,
                'customer_id' => $customerLedger->customer_id,
                'plot_id' => $customerLedger->plot_id,
                'voucher_series' => $ledger?->type ?? $customerLedger->transaction_type,
                'voucher_number' => $ledger?->voucher_number ?? $fallbackVoucherNumber ?? $customerLedger->type_id,
                'slip_reference' => $customerLedger->reference,
                'payment_type' => $customerLedger->payment_type,
                'amount' => $customerLedger->amount_out,
                'receipt_date' => $customerLedger->date,
                'description' => $customerLedger->description,
                'bank_id' => $customerLedger->bank_id,
                't_number' => $customerLedger->t_number,
                'passing_date' => $customerLedger->passing_date,
                'is_active' => $customerLedger->is_active,
                'is_approve' => $customerLedger->is_approve,
                'create_by' => $ledger?->create_by ?? Auth::id(),
                'update_by' => Auth::id(),
            ]
        );
    }


    public function booking_price_update(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'plot_size' => 'required|numeric',
            'new_rate' => 'required|numeric',
            'discount_price' => 'required|numeric',
            'total_new_value' => 'required|numeric',
        ]);

        try {
            // Find the booking by ID
            $booking = Booking::findOrFail($validatedData['booking_id']);

            // Update the booking details
            $booking->plot_rate = $validatedData['new_rate'];
            $booking->dicount_value = $validatedData['discount_price'];
            $booking->total_price = $validatedData['total_new_value'];

            // Save the updated booking
            $booking->save();

            // Return a success response in JSON format
            return response()->json([
                'status' => 'success',
                'message' => 'Booking price updated successfully!',
                'data' => $booking,
            ], 200);
        } catch (\Exception $e) {
            // Return an error response in JSON format
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while updating the booking price. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy_booking(Request $request)
    {
        $booking = Booking::findOrFail($request->id);

        // Get the plot linked to this booking
        $plot = Plot::find($booking->plot_id);

        if ($plot) {
            // Save current booking data to plot_history
            $history = json_decode($plot->plot_history, true) ?? []; // Get existing history or empty array
            $history[] = [
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'plot_id' => $booking->plot_id,
                'plot_size' => $booking->plot_size,
                'plot_rate' => $booking->plot_rate,
                'total_price' => $booking->total_price,
                'broker_id' => $booking->broker_id,
                'booking_date' => $booking->booking_date,
                'name' => $booking->customer->first_name . ' ' . $booking->customer->last_name,
                'father_name' => $booking->customer->father_name,
                'cnic' => $booking->customer->nic_number,
                'phone_number' => $booking->customer->phone_number,
                'deleted_at' => now(),
            ];

            // Save updated history back to plot
            $plot->update([
                'sold' => 0, // Reset sold status
                'plot_history' => json_encode($history),
            ]);
        }

        // Perform soft delete on booking
        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully.']);
    }

    public function cancel(Request $request)
    {
        // dd($request);
        $bookingId = (int) ($request->input('booking_id') ?: $request->id);

        $validator = Validator::make($request->all(), [
            'booking_id' => 'nullable', // we read it manually
            'action_type' => 'required|in:payment_not_received,plot_purchase,re_sale',
            'reason' => 'nullable|string|max:2000',
            'cancel_effect' => 'required|in:charge_customer,give_profit,none',
            'deduction_amount' => 'nullable|string', // "100,000"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $bookingId) {

                $userId = Auth::id();

                // Lock booking row to avoid double cancellation posting
                $booking = Booking::where('id', $bookingId)->lockForUpdate()->firstOrFail();

                // If already canceled, don’t repost ledgers (idempotency)
                if ((int) $booking->cancel_status === 1) {
                    return;
                }

                // Lock plot row too (consistent state)
                $plot = Plot::where('id', $booking->plot_id)->lockForUpdate()->first();

                // Update plot history + reset sold
                if ($plot) {
                    $history = json_decode($plot->plot_history, true) ?? [];
                    $history[] = [
                        'booking_id' => $booking->id,
                        'customer_id' => $booking->customer_id,
                        'plot_id' => $booking->plot_id,
                        'plot_size' => $booking->plot_size,
                        'plot_rate' => $booking->plot_rate,
                        'total_price' => $booking->total_price,
                        'broker_id' => $booking->broker_id,
                        'booking_date' => $booking->booking_date,
                        'name' => optional($booking->customer)->first_name . ' ' . optional($booking->customer)->last_name,
                        'father_name' => optional($booking->customer)->father_name,
                        'cnic' => optional($booking->customer)->nic_number,
                        'phone_number' => optional($booking->customer)->phone_number,
                        'cancel_reason' => $request->reason,
                        'deleted_at' => now(),
                    ];

                    $plot->update([
                        'sold' => 0,
                        'plot_history' => json_encode($history),
                    ]);
                }
                $effect = $request->input('cancel_effect');
                $saleAmount = $this->pvMoneyToDecimal2($booking->total_price); // from DB
                $adjustment = $effect === 'none' ? 0 : $this->pvMoneyToDecimal2($request->input('deduction_amount')); // fine/profit input
                // Mark booking canceled
                $booking->update([
                    'cancel_status' => 1,
                    'cancel_type' => $request->action_type,
                    'reason' => $request->reason,

                    // still store effect + amounts if you want (optional)
                    'cancel_effect' => $effect,
                    'cancel_sale_amount' => $saleAmount,
                    'cancel_adjustment_amount' => $adjustment,
                    'cancel_net_amount' => $saleAmount,
                    'cancel_posted_at' => now(),
                ]);
                /**
                 * If payment was not received, we only cancel plot/booking and stop here.
                 * (No financial reversals)
                 */
                // ========= Accounting =========

                // Sale amount from DB (trusted)
                $effect = $request->cancel_effect; // charge_customer | give_profit
                $sale = $this->pvMoneyToDecimal2($booking->total_price); // "5000000.00"
                $adj = $effect === 'none' ? 0 : $this->pvMoneyToDecimal2($request->input('deduction_amount')); // fine/profit


                if (bccomp($adj, '0.00', 2) < 0) {
                    throw new \Exception('Invalid adjustment amount.');
                }

                // Compute customer credit and system postings
                if ($effect === 'charge_customer') {
                    // fine: customer refund = sale - fine
                    if (bccomp($adj, $sale, 2) === 1) {
                        throw new \Exception('Deduction cannot be greater than sale amount.');
                    }
                    $customerCredit = bcsub($sale, $adj, 2);
                } else {
                    // profit: customer refund = sale + profit
                    $customerCredit = bcadd($sale, $adj, 2);
                }

                // Resolve plot label (for detail)
                $plotName = Plot::where('id', $booking->plot_id)->value('name') ?: '';
                $plotPrefix = $this->plotTypePrefix((int) $booking->plot_type); // "R-" / "C-"
                $customerName = trim(optional($booking->customer)->first_name . ' ' . optional($booking->customer)->last_name);
                $cancelReference = 'CANCEL-' . $booking->id . '-' . strtoupper($request->action_type);
                $cancelDescription = 'Booking Cancellation: Plot ' . $plotPrefix . $plotName . ' cancelled for ' . ($customerName ?: 'Customer');

                if ($request->action_type === 'payment_not_received') {
                    $cancelDescription .= ' - payment not received';
                }

                // Customer pivot (must exist)
                $phsCustomer = ProjectHeadSubhead::where('project_id', $booking->project_id)
                    ->where('head_accounting_id', 16)
                    ->where('plot_id', $booking->plot_id)
                    ->where('customer_id', $booking->customer_id)
                    ->first();

                if (!$phsCustomer) {
                    throw new \Exception('Customer account pivot not found for cancellation.');
                }

                // System accounts under Project Sale (head=16)
                $totalSaleId = $this->totalSaleAccountId((int) $booking->project_id);
                // Marker to avoid duplicates (optional but safe)
                $marker = 'CANCEL#' . $booking->id;
                $alreadyPosted = Ledger::where('reference', $booking->id)
                    ->where('detail', 'like', $marker . '%')
                    ->exists();

                if ($alreadyPosted) {
                    return;
                }
                $salesVoucher = $this->createSalesVoucher([
                    'project_id' => (int) $booking->project_id,
                    'reference' => $cancelReference,
                    'date' => $booking->booking_date,
                    'description' => $cancelDescription,
                    'total_amount' => $sale,
                    'source_type' => 'CANCEL',
                    'source_id' => $booking->id,
                ]);
                $this->recordSalesVoucherEntry($salesVoucher, [
                    'account_id' => $totalSaleId,
                    'reference' => $booking->id,
                    'amount_in' => 0,
                    'amount_out' => $sale,
                    'date' => $booking->booking_date,
                    'description' => $marker . " | Cancel Reverse Total Sale | {$plotPrefix}{$plotName}",
                    'update_by' => $userId,
                    'create_by' => $userId,
                    'status' => 0,
                ]);

                // 2) Credit Customer (CR in) = customerCredit
                // (Optional customer_ledger record if you want to track customer side too)
                $cl = CustomerLedger::create([
                    'date' => $booking->booking_date,
                    'customer_id' => $booking->customer_id,
                    'project_id' => $booking->project_id,
                    'transaction_type' => 'CR',
                    'amount_in' => $customerCredit,
                    'amount_out' => 0,
                    'description' => "Plot Cancellation Refund {$plotPrefix}{$plotName}",
                ]);
                $this->recordSalesVoucherEntry($salesVoucher, [
                    'account_id' => $phsCustomer->id,
                    'reference' => $booking->id,
                    'customer_ledger_id' => $cl->id,
                    'amount_in' => $customerCredit,
                    'amount_out' => 0,
                    'date' => $booking->booking_date,
                    'description' => $marker . " | Customer Credit | {$plotPrefix}{$plotName}",
                    'update_by' => $userId,
                    'create_by' => $userId,
                    'status' => 0,
                ]);

                // 3) Post fine/profit system side
                if (bccomp($adj, '0.00', 2) === 1 && $effect != 'none') {
                    if ($effect === 'charge_customer') {
                        // Fine: credit Deduction account (CR in) = fine
                        $deductionAccId = $this->systemAccountIdBySubheadName(
                            (int) $booking->project_id,
                            16,
                            'Deduction',
                            true // create if missing
                        );
                        $this->recordSalesVoucherEntry($salesVoucher, [
                            'account_id' => $deductionAccId,
                            'reference' => $booking->id,
                            'amount_in' => $adj,
                            'amount_out' => 0,
                            'date' => $booking->booking_date,
                            'description' => $marker . " | Deduction Credit | {$plotPrefix}{$plotName}",
                            'update_by' => $userId,
                            'create_by' => $userId,
                            'status' => 0,
                        ]);
                    } else {
                        // Profit: debit Party Profit (SV out) = profit
                        $partyProfitAccId = $this->systemAccountIdBySubheadName(
                            (int) $booking->project_id,
                            16,
                            'Party Profit',
                            true // must exist
                        );
                        $this->recordSalesVoucherEntry($salesVoucher, [
                            'account_id' => $partyProfitAccId,
                            'reference' => $booking->id,
                            'amount_in' => 0,
                            'amount_out' => $adj,
                            'date' => $booking->booking_date,
                            'description' => $marker . " | Party Profit Debit | {$plotPrefix}{$plotName}",
                            'update_by' => $userId,
                            'create_by' => $userId,
                            'status' => 0,
                        ]);
                    }
                }
                $this->attechCustomerToOldProjectSale($booking->project_id, $booking->customer_id, $booking->plot_id);
            });

            return response()->json(['message' => 'Booking canceled successfully.']);
        } catch (\Throwable $e) {
            Log::error('Booking cancel failed', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Cancellation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    private function pvMoneyToDecimal2($value): string
    {
        $raw = str_replace(',', '', (string) ($value ?? '0'));
        $raw = trim($raw);

        // keep only digits + dot
        $raw = preg_replace('/[^0-9.]/', '', $raw);
        if ($raw === '' || $raw === '.')
            return '0.00';

        // normalize to 2 decimals
        if (strpos($raw, '.') === false) {
            return $raw . '.00';
        }

        [$a, $b] = array_pad(explode('.', $raw, 2), 2, '0');
        $b = substr($b . '00', 0, 2);

        return ($a === '' ? '0' : $a) . '.' . $b;
    }


    public function chargeTypeStore(Request $request)
    {
        $charge = ChargeType::create([
            'name' => $request->name
        ]);
        return back();
    }
}
