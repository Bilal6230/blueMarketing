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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Symfony\Component\String\b;

class LabourController extends Controller
{
    private static ?bool $journalVoucherHasSiteIdColumn = null;

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
        $x['labour'] = $labour = Labour::with([
            'attendances' => fn ($q) => $q->where('status', 'present')->orderBy('date'),
            'labourLedgers'
        ])->findOrFail($id);

        $labourAttendances = $labour->attendances;

        // payments grouped by date
        $paymentsByDate = $labour->labourLedgers
            ->groupBy(fn($item) => $item->created_at->toDateString())
            ->map(fn($items) => $items->sum('amount'));

        $x['paymentsByDate'] = $paymentsByDate;

        $x['summary'] = [
            'total_days'   => $labourAttendances->count(),
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
                        "{$labour['name']} - {$days} day(s) ";

                    $totalAmount += $amount;
                }

                /** ------------------------
                 *  Ledger Narration
                 * ------------------------ */
                $start = Carbon::parse($request->start_date)->format('d M Y');
                $end   = Carbon::parse($request->end_date)->format('d M Y');
                $ledgerDetail =
                    "Labour payment ({$start} - {$end}):\n"
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
                    'type_id' => get_new_typeID(self::LedgerType), // generate new type_id based on CP type
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
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
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

    public function siteVouchers(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $siteId = $request->site_id;

        if (empty($siteId)) {
            $view = view('admin.labours.site-vouchers', [
                'siteVouchers' => collect(),
                'selectedSite' => null,
            ])->render();

            return response()->json([
                'success' => true,
                'view' => $view,
            ]);
        }

        $selectedSite = Site::where('project_id', $selectedProjectId)->find($siteId);

        if (!$selectedSite) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid site selected.',
            ], 422);
        }

        $view = view('admin.labours.site-vouchers', [
            'siteVouchers' => $this->getSiteVoucherList($selectedSite, $selectedProjectId),
            'selectedSite' => $selectedSite,
        ])->render();

        return response()->json([
            'success' => true,
            'view' => $view,
        ]);
    }

    public function createVoucher(Request $request)
    {
        $traceId = (string) Str::uuid();
        $log = Log::channel('labour');
        $authUserId = auth()->id();
        $siteId = $request->site_id;
        $selectedProjectId = getSelectedTown();
        $amountRaw = str_replace(',', '', (string) $request->amount);
        $amount = is_numeric($amountRaw) ? (string) $amountRaw : null;
        $ids = array_filter(explode(',', (string) $request->attendance_ids));

        $log->info('labour.createVoucher.start', [
            'trace_id' => $traceId,
            'auth_user_id' => $authUserId,
            'site_id' => $siteId,
            'selectedProjectId' => $selectedProjectId,
            'amount' => $amount,
            'attendance_ids_count' => count($ids),
        ]);

        try {
            if (empty($siteId)) {
                $errorKey = 'invalid_site';
                $log->warning('labour.createVoucher.warn', [
                    'trace_id' => $traceId,
                    'auth_user_id' => $authUserId,
                    'site_id' => $siteId,
                    'selectedProjectId' => $selectedProjectId,
                    'error_key' => $errorKey,
                ]);
                return response()->json([
                    'success' => false,
                    'error_key' => $errorKey,
                    'message' => 'Invalid site selected.',
                    'trace_id' => $traceId,
                ], 422);
            }

            if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
                $errorKey = 'invalid_amount';
                $log->warning('labour.createVoucher.warn', [
                    'trace_id' => $traceId,
                    'auth_user_id' => $authUserId,
                    'site_id' => $siteId,
                    'selectedProjectId' => $selectedProjectId,
                    'error_key' => $errorKey,
                ]);
                return response()->json([
                    'success' => false,
                    'error_key' => $errorKey,
                    'message' => 'Invalid amount.',
                    'trace_id' => $traceId,
                ], 422);
            }

            if (empty($selectedProjectId)) {
                $errorKey = 'invalid_project';
                $log->warning('labour.createVoucher.warn', [
                    'trace_id' => $traceId,
                    'auth_user_id' => $authUserId,
                    'site_id' => $siteId,
                    'selectedProjectId' => $selectedProjectId,
                    'error_key' => $errorKey,
                ]);
                return response()->json([
                    'success' => false,
                    'error_key' => $errorKey,
                    'message' => 'Invalid project selection.',
                    'trace_id' => $traceId,
                ], 422);
            }

            $labours = json_decode($request->detail, true);
            $log->info('labour.createVoucher.input_parsing_done', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'amount' => (string) $amountRaw,
                'attendance_ids_count' => count($ids),
            ]);

            $site = Site::find($siteId);
            if (!$site) {
                $errorKey = 'invalid_site';
                $log->warning('labour.createVoucher.warn', [
                    'trace_id' => $traceId,
                    'auth_user_id' => $authUserId,
                    'site_id' => $siteId,
                    'selectedProjectId' => $selectedProjectId,
                    'error_key' => $errorKey,
                ]);
                return response()->json([
                    'success' => false,
                    'error_key' => $errorKey,
                    'message' => 'Invalid site selected.',
                    'trace_id' => $traceId,
                ], 422);
            }
            $log->info('labour.createVoucher.site_loaded', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
            ]);

            $project_head_subheads_id = $site->subhead_accounting_id;
            if (empty($project_head_subheads_id)) {
                $errorKey = 'missing_site_subhead';
                $log->warning('labour.createVoucher.warn', [
                    'trace_id' => $traceId,
                    'auth_user_id' => $authUserId,
                    'site_id' => $siteId,
                    'selectedProjectId' => $selectedProjectId,
                    'error_key' => $errorKey,
                ]);
                return response()->json([
                    'success' => false,
                    'error_key' => $errorKey,
                    'message' => 'Site subhead is not configured.',
                    'trace_id' => $traceId,
                ], 422);
            }

        $labourNames = collect($labours)->map(function ($labour) {
            return $labour['name']
                . ' (' . $labour['days'] . ' days'
                . ', wage: ' . $labour['rate'] . ')';
        })->implode(', ');
        $start = Carbon::parse($request->start_date)->format('d M Y');
        $end   = Carbon::parse($request->end_date)->format('d M Y');

        $site = Site::find($request->site_id);
        $siteName = $site?->site_name ?? 'Unknown Site';
        $voucherDescription = "Labour payment for Site: {$siteName}";
        $labourAccountDesc = "Labour payable for {$labourNames} ({$start} - {$end})";
        $siteAccountDesc = "Labour expense charged to Site: {$siteName}";

            $voucherNumber = getVocuherNumber('JV');
            $log->info('labour.createVoucher.voucher_number_computed', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'voucher_number' => $voucherNumber,
                'amount' => (string) $amountRaw,
            ]);

            $lastVoucherId = getLastJvVNumber() + 1;
            $journalVoucherData = [
                'voucher_number' => $lastVoucherId,
                'type' => 'JV',
                'reference' => null,
                'date' => Carbon::now()->format('Y-m-d'),
                'description' => $voucherDescription,
                'total_debit' => $amountRaw,
                'total_credit' => $amountRaw,
                'created_by' => $authUserId,
                'project_id' => $selectedProjectId,
            ];

            if ($this->journalVoucherHasSiteIdColumn()) {
                $journalVoucherData['site_id'] = $siteId;
            }

            $journalVoucher = JournalVoucher::create($journalVoucherData);
            $log->info('labour.createVoucher.journal_voucher_created', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'journal_voucher_id' => $journalVoucher->id,
            ]);

            try {
                $headSubheadId = $this->systemHeadSubheadAccountId(
                    (int) $selectedProjectId,
                    'Labour',
                    'Labour Party',
                    true
                );
            } catch (\Throwable $th) {
                $errorKey = 'missing_labour_subhead';
                $log->warning('labour.createVoucher.warn', [
                    'trace_id' => $traceId,
                    'auth_user_id' => $authUserId,
                    'site_id' => $siteId,
                    'selectedProjectId' => $selectedProjectId,
                    'error_key' => $errorKey,
                ]);
                return response()->json([
                    'success' => false,
                    'error_key' => $errorKey,
                    'message' => 'Labour subhead is not configured.',
                    'trace_id' => $traceId,
                ], 422);
            }
            if (empty($headSubheadId)) {
                $errorKey = 'missing_labour_subhead';
                $log->warning('labour.createVoucher.warn', [
                    'trace_id' => $traceId,
                    'auth_user_id' => $authUserId,
                    'site_id' => $siteId,
                    'selectedProjectId' => $selectedProjectId,
                    'error_key' => $errorKey,
                ]);
                return response()->json([
                    'success' => false,
                    'error_key' => $errorKey,
                    'message' => 'Labour subhead is not configured.',
                    'trace_id' => $traceId,
                ], 422);
            }
            $log->info('labour.createVoucher.head_subhead_resolved', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
            ]);

            JournalVoucherDetail::create([
                'journal_voucher_id' => $journalVoucher->id,
                'account_id' => $headSubheadId,
                'debit' => $amountRaw,
                'credit' => 0,
                'description' => $labourAccountDesc,
                'created_by' => $authUserId,
            ]);
            $log->info('labour.createVoucher.first_voucher_detail_created', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'journal_voucher_id' => $journalVoucher->id,
            ]);

            $toLedgerData = Ledger::create([
                'type' => 'JV',
                'voucher_number' => $voucherNumber,
                'type_id' => $journalVoucher->id,
                'project_head_subheads_id' => $headSubheadId,
                'reference' => null,
                'amount_in' => $amountRaw,
                'amount_out' => 0,
                'detail' => $labourAccountDesc,
                'create_by' => $authUserId,
                'is_active' => true,
                'status' => '0',
                'date' => Carbon::now()->format('Y-m-d'),
            ]);
            $log->info('labour.createVoucher.to_ledger_created', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'ledger_id' => $toLedgerData->id,
                'voucher_number' => $voucherNumber,
            ]);

            JournalVoucherDetail::create([
                'journal_voucher_id' => $journalVoucher->id,
                'account_id' => $project_head_subheads_id,
                'debit' => 0,
                'credit' => $amountRaw,
                'description' => $labourAccountDesc,
                'created_by' => $authUserId,
            ]);
            $log->info('labour.createVoucher.second_voucher_detail_created', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'journal_voucher_id' => $journalVoucher->id,
            ]);

            $fromLedgerData = Ledger::create([
                'type' => 'JV',
                'voucher_number' => $voucherNumber,
                'type_id' => $journalVoucher->id,
                'project_head_subheads_id' => $project_head_subheads_id,
                'reference' => null,
                'amount_in' => 0,
                'amount_out' => $amountRaw,
                'detail' => $labourAccountDesc,
                'create_by' => $authUserId,
                'is_active' => true,
                'status' => '0',
                'date' => Carbon::now()->format('Y-m-d'),
            ]);
            $log->info('labour.createVoucher.from_ledger_created', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'ledger_id' => $fromLedgerData->id,
                'voucher_number' => $voucherNumber,
            ]);

            LabourAttendance::whereIn('id', $ids)
                ->update(['voucher_status' => 'created']);
            $log->info('labour.createVoucher.attendance_updated', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'attendance_ids_count' => count($ids),
            ]);

            $log->info('labour.createVoucher.success', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'journal_voucher_id' => $journalVoucher->id,
                'to_ledger_id' => $toLedgerData->id,
                'from_ledger_id' => $fromLedgerData->id,
                'voucher_number' => $voucherNumber,
                'amount' => (string) $amountRaw,
                'attendance_ids_count' => count($ids),
            ]);

            return response()->json([
                'success' => true,
                'toLedgerData' => $toLedgerData,
                'fromLedgerData' => $fromLedgerData,
                'voucher_id' => $journalVoucher->id,
                'trace_id' => $traceId,
            ]);
        } catch (\Throwable $e) {
            $errorKey = 'labour_voucher_failed';
            $log->error('labour.createVoucher.error', [
                'trace_id' => $traceId,
                'auth_user_id' => $authUserId,
                'site_id' => $siteId,
                'selectedProjectId' => $selectedProjectId,
                'amount' => $amount,
                'attendance_ids_count' => count($ids),
                'error_key' => $errorKey,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'error_key' => $errorKey,
                'message' => 'Unable to create voucher. Please try again or contact support.',
                'trace_id' => $traceId,
            ], 500);
        }
    }

    private function getSiteVoucherList(Site $site, int $selectedProjectId)
    {
        $siteVoucherDescription = "Labour payment for Site: {$site->site_name}";
        $query = JournalVoucher::query()
            ->with($this->journalVoucherHasSiteIdColumn() ? ['site', 'creator'] : ['creator'])
            ->where('project_id', $selectedProjectId);

        if ($this->journalVoucherHasSiteIdColumn()) {
            $query->where(function ($voucherQuery) use ($site, $siteVoucherDescription) {
                $voucherQuery->where('site_id', $site->id)
                    ->orWhere(function ($legacyQuery) use ($site, $siteVoucherDescription) {
                        $legacyQuery->whereNull('site_id')
                            ->where('description', $siteVoucherDescription);

                        if (!empty($site->subhead_accounting_id)) {
                            $legacyQuery->whereHas('details', function ($detailQuery) use ($site) {
                                $detailQuery->where('account_id', $site->subhead_accounting_id)
                                    ->where('credit', '>', 0);
                            });
                        }
                    });
            });
        } else {
            $query->where('description', $siteVoucherDescription);

            if (!empty($site->subhead_accounting_id)) {
                $query->whereHas('details', function ($detailQuery) use ($site) {
                    $detailQuery->where('account_id', $site->subhead_accounting_id)
                        ->where('credit', '>', 0);
                });
            }
        }

        return $query->latest('date')
            ->latest('id')
            ->get();
    }

    private function journalVoucherHasSiteIdColumn(): bool
    {
        if (self::$journalVoucherHasSiteIdColumn === null) {
            self::$journalVoucherHasSiteIdColumn = Schema::hasColumn('journal_vouchers', 'site_id');
        }

        return self::$journalVoucherHasSiteIdColumn;
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
        $request->validate([
            'labour_id'        => 'required|exists:labours,id',
            'daily_wage'       => 'required|numeric|min:0',
            'apply_from_date'  => 'required|date',
        ]);

        try {

            DB::transaction(function () use ($request) {

                $labourId  = $request->labour_id;
                $newRate   = $request->daily_wage;
                $selectedProjectId = getSelectedTown();

                $applyFrom = Carbon::parse($request->apply_from_date)->startOfDay();
                $today     = Carbon::today()->endOfDay();

                $headSubheadId = $this->systemHeadSubheadAccountId(
                    (int) $selectedProjectId,
                    'Labour',
                    'Labour Party',
                    true
                );

                /**
                 * ❌ Block if Journal Voucher already exists
                 */
                $exists = JournalVoucherDetail::where('account_id', $headSubheadId)
                    ->whereHas('journalVoucher', function ($q) use ($applyFrom) {
                        $q->whereDate('date', '>=', $applyFrom);
                    })
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        'Cannot update rate. Journal voucher already created for this date or later.'
                    );
                }

                /**
                 * ✅ Update Attendance
                 */
                LabourAttendance::where('labour_id', $labourId)
                    ->where('status', 'present')
                    ->whereBetween('date', [
                        $applyFrom->toDateString(),
                        $today->toDateString()
                    ])
                    ->chunkById(100, function ($records) use ($newRate) {

                        foreach ($records as $att) {

                            $hourlyRate = $newRate / 8;

                            $normalHours = min($att->hours, 8);
                            $otHours     = $att->ot_hours;

                            $normalAmount = $normalHours * $hourlyRate;
                            $otAmount     = $otHours * $hourlyRate;

                            $att->update([
                                'rate'   => $newRate,
                                'amount' => round($normalAmount + $otAmount, 2),
                            ]);
                        }
                    });

                /**
                 * ✅ Update Master Rate
                 */
                Labour::where('id', $labourId)
                    ->update(['daily_wage' => $newRate]);
            });
            return response()->json([
                'success' => true,
                'message' => 'Labour rate updated from selected date to today'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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
