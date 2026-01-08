<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Site;
use App\Models\Labour;
use App\Models\Ledger;
use Illuminate\Http\Request;
use App\Models\HeadAccounting;
use App\Models\LabourAttendance;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use App\Http\Controllers\Controller;
use App\Models\CustomerLedger;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\LabourLedger;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function Symfony\Component\String\b;

class LabourController extends Controller
{
    public function index(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $x['selectedSiteId'] = null;
        $x['title'] = 'Labour';
        $x['labours'] = Labour::get(); // You can also filter by $selectedProjectId if needed
        $x['count'] = Labour::count();
        $x['sites'] = Site::where('project_id', $selectedProjectId)->get();
        $x['sitecount'] = Site::where('project_id', $selectedProjectId)->count();
        $data_list = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();

        $start = now()->copy()->startOfWeek(Carbon::FRIDAY);

        // End of week should be Thursday (6 days after Friday)
        $end = $start->copy()->addDays(6);

        // Generate 7 days Friday → Thursday
        $days = [];
        $day = $start->copy();

        while ($day <= $end) {
            $days[] = $day->format('Y-m-d');
            $day->addDay();
        }
        $x['attendance_labours'] = Labour::whereHas('attendances', function ($query) use ($days, $selectedProjectId) {
            $query->where('project_id', $selectedProjectId)->whereIn('date', $days)->with('site');
        })->get();


        $x['headaccounts'] = $data_list;

        return view('admin.labours.index', $x);
    }
    function formatDates(array $dates): string
    {
        return collect($dates)
            ->map(fn($d) => Carbon::parse($d)->format('d M'))
            ->implode(', ');
    }

    public function labourHistory($id)
    {
        $x['title'] = 'Labour History';
        $x['labour'] = $labour = Labour::find($id);
        $labourAttendances = LabourAttendance::where('labour_id', $id)->where('status', 'present')->get();
        $x['summary'] = [
            'total_days'   => $labour->attendances->count(),
            'total_hours'  => $labourAttendances->sum('hours'),
            'total_amount' => $labourAttendances->sum('amount'),
            'unpaid'       => $labourAttendances->sum('amount') - $labour->labourLedgers->sum('amount'),
        ];
        return view('admin.labours.history', $x);
    }

    private const LABOUR_Head_Acount_ID = 372;
    private const LABOUR_Sub_Acount_ID = 301;
    private const LedgerType = 'CP';

    public function labourPayment(Request $request)
    {
        $request->validate([
            'labours' => 'required|array|min:1',
            'week' => 'required',
        ]);

        try {
            $selected_project_id = getSelectedTown();
            $head_sub_head = $this->systemHeadSubheadAccountId(
                (int) $selected_project_id,
                'Labour',
                'Labour Party',
                true // create if missing
            );
            if (!$head_sub_head) {
                $project = Project::find($selected_project_id);
                return response()->json([
                    'success' => false,
                    'message' => 'Labour head/subhead account is not configured for the selected project : ' . $project->project . '. Please contact admin.',
                ], 500);
            }
            return DB::transaction(function () use ($request, $head_sub_head) {

                $labours = $request->labours;
                [$year, $weekNo] = explode('-W', $request->week);

                $ledgerLines = [];
                $totalAmount = 0;

                /** ------------------------
                 *  Create Labour Ledgers
                 * ------------------------ */
                foreach ($labours as $labour) {

                    $amount = (float) $labour['amount'];
                    $dates = $this->formatDates($labour['attendanceDates'] ?? []);
                    $days = count($labour['attendanceDates'] ?? []);

                    LabourLedger::create([
                        'project_id' => getSelectedTown(),
                        'labour_id' => $labour['id'],
                        'amount' => $amount,
                        'date' => now()->toDateString(),
                        'details' => json_encode($labour, JSON_UNESCAPED_UNICODE),
                    ]);

                    $ledgerLines[] =
                        "{$labour['name']} ({$labour['role']}) – {$days} day(s) "
                        . "[{$dates}] @ {$labour['rate']} = {$amount}";

                    $totalAmount += $amount;
                }

                /** ------------------------
                 *  Ledger Narration
                 * ------------------------ */
                $ledgerDetail =
                    "Labour payment (Week {$weekNo}, {$year}):\n"
                    . "• " . implode("\n• ", $ledgerLines)
                    . "\nTotal Paid: {$totalAmount}";

                /** ------------------------
                 *  Voucher Number
                 * ------------------------ */

                /** ------------------------
                 *  Customer Ledger
                 * ------------------------ */
                $customerLedger = CustomerLedger::create([
                    'transaction_type' => self::LedgerType,
                    'type_id' => get_new_typeID(self::LedgerType),
                    'reference' => $request->reference,
                    'project_id' => getSelectedTown(),
                    'customer_id' => self::LABOUR_Sub_Acount_ID,
                    'amount_in' => 0,
                    'amount_out' => $totalAmount,
                    'description' => $ledgerDetail,
                    'date' => now()->toDateString(),
                    'payment_type' => 1,
                    'is_active' => 1,
                    'is_approve' => 0,
                ]);


                /** ------------------------
                 *  General Ledger
                 * ------------------------ */
                $voucherNumber = getVocuherNumber(self::LedgerType);
                Ledger::create([
                    'customer_ledger_id' => $customerLedger->id,
                    'voucher_number' => $voucherNumber,
                    'type' => self::LedgerType,
                    'type_id' => getLastLedgerIdByType('CP') + 1,
                    'project_head_subheads_id' => $head_sub_head,
                    'reference' => $request->reference,
                    'amount_in' => 0,
                    'amount_out' => $totalAmount,
                    'detail' => $ledgerDetail,
                    'date' => now()->toDateString(),
                    'create_by' => auth()->id(),
                    'update_by' => auth()->id(),
                    'status' => 0,
                    'is_active' => 1,
                ]);

                /** ------------------------
                 *  Rebuild Report View
                 * ------------------------ */
                $x = $this->buildPersonWiseReport($request);
                $view = '';
                $view .= view('admin.labours.person-wise-report', $x)->render();
                return response()->json([
                    'success' => true,
                    'view' => $view,
                ]);
            });
        } catch (\Throwable $e) {

            Log::error('labourPayment failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while processing labour payment.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
    private function buildPersonWiseReport(Request $request)
    {
        $startOfWeek = Carbon::parse($request->start_date);

        // End of week should be Thursday (6 days after Friday)
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
        }

        $x['personWiseReports'] = $personWiseReports = Labour::select('labours.id', 'labours.name', 'labours.father_name', 'labours.cnic', 'labours.advance', 'labours.phone as mobile', 'labours.role as designation');

        if ($request->search) {
            $search = $request->search;

            $personWiseReports->where(function ($q) use ($search) {
                // If search contains any digit, search in phone or cnic
                if (preg_match('/\d/', $search)) {
                    $q->where('phone', 'like', "%{$search}%")
                        ->orWhere('cnic', 'like', "%{$search}%");
                } else {
                    // Otherwise search in name
                    $q->where('name', 'like', "%{$search}%");
                }
            });
        }

        $x['personWiseReports'] = $personWiseReports = $personWiseReports
            ->with([
                'attendances' => function ($query) use ($request, $days) {
                    $query->whereIn('date', $days)
                        ->where('status', 'present')
                        ->where('project_id', getSelectedTown());
                }
            ])
            ->whereHas('attendances', function ($query) use ($request, $days) {
                $query->whereIn('date', $days)
                    ->where('status', 'present')
                    ->where('project_id', getSelectedTown());
            })
            ->get()
            ->map(function ($labour) {

                $attendanceIds = $labour->attendances->pluck('id')->toArray();
                $attendanceDates = $labour->attendances->pluck('date')->toArray();
                $totalHours = $labour->attendances->sum('hours');
                $totalOT = $labour->attendances->sum('ot_hours');
                $ratings = $labour->attendances->sum('ratings') / $labour->attendances->count();

                $rate = optional($labour->attendances->first())->rate
                    ?? $labour->daily_wage
                    ?? 0;

                $days = $totalHours / 8;
                $amount = ($days * $rate) + ($totalOT * ($rate / 8));
                $totalAmount = $labour->tAttendances->sum('amount');
                $remaningAmount = $totalAmount - $labour->labourLedgers->sum('amount');

                return [
                    'id' => $labour->id,
                    'name' => $labour->name,
                    'father_name' => $labour->father_name,
                    'cnic' => $labour->cnic,
                    'mobile' => $labour->mobile,
                    'designation' => $labour->designation,
                    'rate' => number_format($rate, 0),
                    'days' => number_format($days, 0),
                    'overtime' => number_format($totalOT, 0),
                    'amount' => number_format($amount, 0),
                    'remaningAmount' => number_format($remaningAmount, 0),
                    'advance' => $labour->advance,
                    'amount_raw' => $amount,
                    'ratings' => number_format($ratings, 1),
                    'attendance_ids' => json_encode($attendanceIds, JSON_UNESCAPED_UNICODE), // ✅ real attendance IDs
                    'attendance_dates' => json_encode($attendanceDates, JSON_UNESCAPED_UNICODE), // ✅ real attendance IDs
                    'paid_status' => optional($labour->attendances->first())->paid_status
                ];
            });
        $x['total_amount'] = $personWiseReports->sum('amount_raw');
        // Render table partial
        return $x;
    }



    public function create()
    {
        return view('labours.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:labours,name',
            'father_name' => 'required|string|max:255',
            'cnic' => 'required|string|max:20|unique:labours,cnic',
            'phone' => 'nullable|string|max:20|unique:labours,phone',
            'role' => 'nullable|string|max:20',
            'daily_wage' => 'required|numeric',
        ]);
        $validated['join_date'] = date('Y-m-d');

        Labour::create($validated);
        $labours = Labour::get();
        $view = '';
        $view .= view('admin.labours.labour', compact('labours'))->render();

        return response()->json([
            'success' => 'Labour added successfully.',
            'view' => $view
        ]);
    }
    public function siteStore(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_address' => 'required|string',
            'accounts_id' => 'required',
            'subaccounts_id' => 'required',
        ]);
        Site::updateOrCreate(
            ['id' => $request->site_id], // ← condition (update when ID exists)
            [
                'project_id' => $selectedProjectId,
                'site_name' => $request->site_name,
                'site_address' => $request->site_address,
                'head_accounting_id' => $request->accounts_id,
                'subhead_accounting_id' => $request->subaccounts_id,
            ]
        );
        $sites = Site::get();
        $view = '';
        $view .= view('admin.labours.sites', compact('sites'))->render();

        return response()->json([
            'success' => 'Site added successfully.',
            'view' => $view
        ]);
    }
    public function attendanceStore(Request $request)
    {
        $validated = $request->validate([
            'labour_id' => 'required|exists:labours,id',
            'site_id' => 'nullable|exists:sites,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,leave,holiday,not-marked',
            'hours' => 'nullable|numeric|min:0',
            'ot_hours' => 'nullable|numeric|min:0',
            'rate' => 'nullable|numeric|min:0',
            'ratings' => 'nullable',
        ]);

        // Auto-calculate amount if not provided
        if (empty($validated['amount']) && isset($validated['rate'])) {
            $hours = $validated['hours'] ?? 0;
            $ot = $validated['ot_hours'] ?? 0;
            $rate = $validated['rate'];
            $oneHourRate = $rate / 8;
            $validated['amount'] = ($hours + $ot) * $oneHourRate;
        }

        $validated['marked_by'] = auth()->id();
        $validated['project_id'] = getSelectedTown();
        // dd($validated);
        // 🧠 Create or Update logic
        $attendance = LabourAttendance::updateOrCreate(
            [
                'labour_id' => $validated['labour_id'],
                'date' => $validated['date'],
            ],
            $validated
        );
        $labour = Labour::find($validated['labour_id']);
        $attendance['name'] = $labour->name;
        $attendance['role'] = $labour->role;

        return response()->json([
            'success' => true,
            'data' => $attendance,
            'message' => 'Attendance saved successfully',
        ]);
    }



    public function update(Request $request, Labour $labour)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cnic' => 'required|string|max:20|unique:labours,cnic,' . $labour->id,
            'phone' => 'nullable|string|max:20',
            'daily_wage' => 'required|numeric',
        ]);

        $labour->update($request->all());
        $labours = Labour::get();
        $view = '';
        $view .= view('admin.labours.labour', compact('labours'))->render();

        return response()->json([
            'success' => 'Labour added successfully.',
            'view' => $view
        ]);
        // return redirect()->route('labours.index')->with('success', 'Labour updated successfully.');
    }
    public function updateStatus($id)
    {
        $labour = Labour::findOrFail($id);
        $labour->status = $labour->status === 'active' ? 'inactive' : 'active';
        $labour->save();

        return redirect()->route('labours.index')->with('success', 'Status updated successfully!');
    }


    public function destroy(Labour $labour)
    {
        $labour->delete();

        return redirect()->route('labours.index')->with('success', 'Labour deleted successfully.');
    }

    public function attendanceReport(Request $request)
    {
        $request->validate(
            [
                'week' => 'required',
                'site_id' => 'required|integer|exists:sites,id',
            ],
            [
                'week.required' => 'Please select a week before generating the report.',
                'site_id.required' => 'Please select a site to continue.',
                'site_id.integer' => 'Invalid site selected.',
                'site_id.exists' => 'The selected site does not exist.',
            ]
        );
        $x = $this->buildSiteWiseReport($request);
        // Render table partial
        $view = view('admin.labours.site-report', $x)->render();

        return response()->json([
            'success' => true,
            'site_id' => $request->site_id,
            'attendanceIds' => $x['attendanceIds'], // ✅ perfect output here
            'total_amount' => number_format($x['total_amount'], 2),
            'reports' => json_encode($x['reports'], JSON_UNESCAPED_UNICODE),
            'view' => $view
        ]);
    }

    public function printSiteReport(Request $request)
    {
        $request->validate(
            [
                'week' => 'required',
                'site_id' => 'required|integer|exists:sites,id',
            ],
            [
                'week.required' => 'Please select a week before generating the report.',
                'site_id.required' => 'Please select a site to continue.',
                'site_id.integer' => 'Invalid site selected.',
                'site_id.exists' => 'The selected site does not exist.',
            ]
        );
        $x = $this->buildSiteWiseReport($request);
        $x['start'] = $request->start_date;
        $x['end'] = $request->end_date;
        $x['site_name'] = Site::find($request->site_id)?->site_name;
        // Render table partial
        return view('admin.labours.print-site-report', $x);
    }
    private function buildSiteWiseReport($request)
    {
        $selectedProjectId = getSelectedTown();
        $startOfWeek = Carbon::parse($request->start_date);

        // End of week should be Thursday (6 days after Friday)
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
        }

        $reports = Labour::select('labours.id', 'labours.name', 'labours.father_name', 'labours.cnic', 'labours.phone as mobile', 'labours.role as designation')
            ->with([
                'attendances' => function ($query) use ($request, $days, $selectedProjectId) {
                    $query->whereIn('date', $days)
                        ->where('project_id', $selectedProjectId)
                        ->where('site_id', $request->site_id)
                        ->where('status', 'present');
                    if ($request->id == 'voucherBtnSiteRun') {
                        $query->where('voucher_status', 'notcreated');
                    }
                }
            ])
            ->whereHas('attendances', function ($query) use ($request, $days) {
                $query->whereIn('date', $days)
                    ->where('project_id', getSelectedTown())
                    ->where('site_id', $request->site_id)
                    ->where('status', 'present');
                if ($request->id == 'voucherBtnSiteRun') {
                    $query->where('voucher_status', 'notcreated');
                }
            })
            ->get()
            ->map(function ($labour) {

                // ✅ Collect attendance IDs for this labour
                $attendanceIds = $labour->attendances->pluck('id')->toArray();

                $totalHours = $labour->attendances->sum('hours');
                $totalOT = $labour->attendances->sum('ot_hours');

                $rate = optional($labour->attendances->first())->rate
                    ?? $labour->daily_wage
                    ?? 0;

                $days = $totalHours / 8;
                $amount = ($days * $rate) + ($totalOT * ($rate / 8));

                return [
                    'id' => $labour->id,
                    'name' => $labour->name,
                    'father_name' => $labour->father_name,
                    'cnic' => $labour->cnic,
                    'mobile' => $labour->mobile,
                    'designation' => $labour->designation,
                    'rate' => number_format($rate, 0),
                    'days' => number_format($days, 0),
                    'overtime' => number_format($totalOT, 0),
                    'amount' => number_format($amount, 0),
                    'amount_raw' => $amount,   // used for grand total
                    'attendance_ids' => $attendanceIds, // ✅ real attendance IDs
                ];
            });

        // ✅ Grand Total
        $x['total_amount'] = $reports->sum('amount_raw');

        // Remove helper amount_raw from final report
        $x['reports'] = $reports->map(function ($r) {
            unset($r['amount_raw']);
            return $r;
        });

        // Collect ALL attendance IDs for voucher, flatten & unique
        $x['attendanceIds'] = $reports
            ->pluck('attendance_ids')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        return $x;
    }
    public function personAttendanceReport(Request $request)
    {
        $request->validate([
            'week' => 'required|date',
        ]);

        $x = $this->buildPersonWiseReport($request);
        $view = '';
        $view .= view('admin.labours.person-wise-report', $x)->render();
        return response()->json([
            'success' => true,
            'x' => $x,
            'view' => $view
        ]);
    }
    public function printPersonReport(Request $request)
    {
        $request->validate([
            'week' => 'required|date',
        ]);
        $x = $this->buildPersonWiseReport($request);
        $x['start'] = $request->start_date;
        $x['end'] = $request->end_date;
        $x['request'] = $request->except(['_token', 'week', 'start_date', 'end_date']);
        return view('admin.labours.print-person-wise-report', $x);
    }


    public function checkValidate(Request $request)
    {
        // dd($request->all());
        $query = Labour::query();
        if ($request->has('name') && $request->name)
            $query->where('name', $request->name);
        if ($request->has('cnic') && $request->cnic)
            $query->where('cnic', $request->cnic);
        if ($request->has('phone') && $request->phone)
            $query->where('phone', $request->phone);
        $exist = $query->exists();
        return response()->json(['exists' => $exist]);
    }

    public function loadAttendanceWeek(Request $request)
    {
        $x = $this->loadAttendance($request);
        $view = '';
        $view .= view('admin.labours.attn-board', $x)->render();

        return response()->json([
            'success' => true,
            'view' => $view
        ]);

        return [$startOfWeek, $endOfWeek, $days];
    }
    public function printAttendanceReport(Request $request)
    {
        $x = $this->loadAttendance($request);
        $x['start'] = $request->start_date;
        $x['end'] = $request->end_date;
        $x['site_name'] = Site::find($request->site_id)?->site_name;
        return view('admin.labours.print-attn-board', $x);
    }

    public function createVoucher(Request $request)
    {
        $labours = json_decode($request->detail, true);

        $labourNames = collect($labours)->map(function ($labour) {
            return $labour['name']
                . ' (' . $labour['days'] . ' days'
                . ', wage: ' . $labour['rate'] . ')';
        })->implode(', ');

        $site = Site::find($request->site_id);
        $siteName = $site?->site_name ?? 'Unknown Site';
        $voucherDescription = "Labour payment for Site: {$siteName}";
        $labourAccountDesc = "Labour payable for {$labourNames}";
        $siteAccountDesc = "Labour expense charged to Site: {$siteName}";

        $project_head_subheads_id = Site::where('id', $request->site_id)->first()->subhead_accounting_id;
        $amount = str_replace(',', '', $request->amount);

        $selectedProjectId = getSelectedTown();
        $lastVoucherId = getLastJvId();
        $journalVoucher = JournalVoucher::create([
            'voucher_number' => $lastVoucherId + 1,
            'reference' => null,
            'date' => Carbon::now()->format('Y-m-d'),
            'description' => $voucherDescription,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'created_by' => auth()->id(),
            'project_id' => $selectedProjectId,
        ]);
        $headSubheadId = $this->systemHeadSubheadAccountId(
            (int) $selectedProjectId,
            'Labour',
            'Labour Party',
            true // create if missing
        );

        JournalVoucherDetail::create([
            'journal_voucher_id' => $journalVoucher->id,
            'account_id' => $headSubheadId,
            'debit' => $amount,
            'credit' => 0,
            'description' => $labourAccountDesc,
            'created_by' => auth()->id(),
        ]);
        $voucherNumber = getVocuherNumber('JV');
        $toLedgerData = Ledger::create([
            'type' => 'JV',
            'voucher_number' => $voucherNumber,
            'type_id' => $journalVoucher->id,
            'project_head_subheads_id' => $headSubheadId,
            'reference' => null,
            'amount_in' => $amount,
            'amount_out' => 0,
            'detail' => $labourAccountDesc,
            'create_by' => auth()->id(),
            'is_active' => true,
            'status' => '0',
            'date' => Carbon::now()->format('Y-m-d'),
        ]);

        JournalVoucherDetail::create([
            'journal_voucher_id' => $journalVoucher->id,
            'account_id' => $project_head_subheads_id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $labourAccountDesc,
            'created_by' => auth()->id(),
        ]);
        $voucherNumber = getVocuherNumber('JV');
        $fromLedgerData = Ledger::create([
            'type' => 'JV',
            'voucher_number' => $voucherNumber,
            'type_id' => $journalVoucher->id,
            'project_head_subheads_id' => $project_head_subheads_id,
            'reference' => null,
            'amount_in' => 0,
            'amount_out' => $amount,
            'detail' => $labourAccountDesc,
            'create_by' => auth()->id(),
            'is_active' => true,
            'status' => '0',
            'date' => Carbon::now()->format('Y-m-d'),
        ]);


        $ids = array_filter(explode(',', $request->attendance_ids));

        LabourAttendance::whereIn('id', $ids)
            ->update(['voucher_status' => 'created']);
        return response()->json([
            'success' => true,
            'toLedgerData' => $toLedgerData,
            'fromLedgerData' => $fromLedgerData
        ]);
    }
    private function systemHeadSubheadAccountId(int $projectId, string $headName, string $subheadName, bool $createIfMissing = false): int
    {
        $head = HeadAccounting::where('name', $headName)->first();
        $subhead = SubheadAccounting::where('name', $subheadName)->first();

        if (!$head && $createIfMissing) {
            $head = HeadAccounting::create([
                'name' => $headName,
                'is_active' => 1,
                'acct_type' => 0,
                'create_by' => Auth::id(),
            ]);
        }
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
                'head_accounting_id' => $head->id,
                'subhead_accounting_id' => $subhead->id,
                'plot_id' => null,
                'customer_id' => null,
            ]
        );

        return (int) $phs->id;
    }
    public function updateRate(Request $request)
    {
        dd($request->all());
        $request->validate([
            'labour_id'   => 'required|exists:labours,id',
            'daily_wage'  => 'required|numeric|min:0',
            'apply_from_date' => 'required|date',
        ]);

        DB::transaction(function () use ($request) {

            $labourId = $request->labour_id;
            $newRate  = $request->daily_wage;
            $today    = Carbon::today();

            /** -------------------------
             * Date Ranges
             * --------------------------*/
            switch ($request->apply_scope) {

                case 'today':
                    $startDate = $today;
                    $endDate   = $today;
                    break;

                case 'from_today':
                    $startDate = $today;
                    $endDate   = $today;
                    break;

                case 'current_week':
                    $startDate = $today->copy()->startOfWeek();
                    $endDate   = $today->copy()->endOfWeek();
                    break;

                case 'from_current_week':
                    $startDate = $today->copy()->startOfWeek();
                    $endDate   = $today->copy()->endOfWeek();
                    break;
            }

            /** -------------------------
             * Update Attendance Records
             * --------------------------*/
            $attendanceQuery = LabourAttendance::where('labour_id', $labourId)
                ->where('status', 'present');

            if ($endDate) {
                $attendanceQuery->whereBetween('date', [$startDate, $endDate]);
            } else {
                $attendanceQuery->where('date', '>=', $startDate);
            }

            $attendanceQuery->chunkById(100, function ($records) use ($newRate) {

                foreach ($records as $att) {

                    $normalHours = min($att->hours, 8);
                    $otHours     = $att->ot_hours;

                    $hourlyRate  = $newRate / 8;

                    $normalAmount = $normalHours * $hourlyRate;
                    $otAmount     = $otHours * $hourlyRate; // OT multiplier if needed

                    $att->update([
                        'rate'   => $newRate,
                        'amount' => round($normalAmount + $otAmount, 2),
                    ]);
                }
            });

            /** -------------------------
             * Update Labour Master Rate
             * --------------------------*/
            if (in_array($request->apply_scope, ['from_today', 'from_current_week'])) {
                Labour::where('id', $labourId)
                    ->update(['daily_wage' => $newRate]);
            }
        });
        return back()->with('success', 'Labour rate updated successfully');
        return response()->json([
            'status'  => true,
            'message' => 'Labour rate updated successfully'
        ]);
    }

    private function loadAttendance($request)
    {
        $x['week'] = $weekInput = $request->week;
        $x['selectedSiteId'] = $request->site_id;
        // Parse ISO week from input (2025-W05)
        [$year, $week] = explode('-W', $weekInput);
        $startOfWeek = Carbon::parse($request->start_date);
        $endOfWeekDate = Carbon::now()->setISODate($year, $week)->endOfWeek(Carbon::SUNDAY)->toDateString();
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
        }
        // LABOUR FILTER
        $query = Labour::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                // If search has any digit, search in phone or cnic
                if (preg_match('/\d/', $search)) {
                    $q->where('phone', 'like', "%{$search}%")
                        ->orWhere('cnic', 'like', "%{$search}%");
                } else {
                    // Otherwise search in name
                    $q->where('name', 'like', "%{$search}%");
                }
            });
        } else {
            $query->whereHas('attendances', function ($query) use ($days, $request) {
                $query->whereIn('date', $days)
                    ->where('project_id', getSelectedTown());
            });
            if ($request->has('site_id') && $request->site_id) {
                $query->whereHas('attendances', function ($query) use ($days, $request) {
                    if ($request->has('site_id') && $request->site_id) {
                        $query->where('site_id', $request->site_id)
                            ->where('project_id', getSelectedTown());
                    }
                });
            }
        }


        $x['attendance_labours'] = $query->get();
        $x['week'] = $endOfWeekDate;
        return $x;
    }
}
