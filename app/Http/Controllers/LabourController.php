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


class LabourController extends Controller
{
    public function index(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $x['title'] = 'Labour';
        $x['labours'] = Labour::get(); // You can also filter by $selectedProjectId if needed
        $x['count'] = Labour::count();
        $x['sites'] = Site::get();
        $x['sitecount'] = Site::count();
        $data_list = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $days[] = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
        }
        $x['attendance_labours'] = Labour::whereHas('attendances', function ($query) use ($days) {
            $query->whereIn('date', $days);
        })->get();

        $x['headaccounts'] = $data_list;
        $x['personWiseReports'] = $personWiseReports = Labour::select('labours.id', 'labours.name', 'labours.phone as mobile', 'labours.role as designation')
            ->with('attendances')
            ->whereHas('attendances', function ($query) use ($request) {
                $query->where('voucher_status', 'created');
            })
            ->get()
            ->map(function ($labour) {

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
                    'mobile' => $labour->mobile,
                    'designation' => $labour->designation,
                    'rate' => number_format($rate, 2),
                    'days' => number_format($days, 2),
                    'overtime' => number_format($totalOT, 2),
                    'amount' => number_format($amount, 2),
                    'amount_raw' => $amount,
                    'paid_status' => optional($labour->attendances->first())->paid_status
                ];
            });
        $x['total_amount'] = $personWiseReports->sum('amount_raw');
        if ($request->ajax()) {
            return response()->json($x['labours']);
        }

        return view('admin.labours.index', $x);
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
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_address' => 'required|string',
            'accounts_id' => 'required',
            'subaccounts_id' => 'required',
        ]);
        Site::updateOrCreate(
            ['id' => $request->site_id], // ← condition (update when ID exists)
            [
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
            'labour_id'   => 'required|exists:labours,id',
            'site_id'  => 'nullable|exists:sites,id',
            'date'        => 'required|date',
            'status'      => 'required|in:present,absent,leave,holiday,not-marked',
            'hours'       => 'nullable|numeric|min:0',
            'ot_hours'    => 'nullable|numeric|min:0',
            'rate'        => 'nullable|numeric|min:0',
            // 'amount'      => 'nullable|numeric|min:0',
            // 'site_name'   => 'nullable|string|max:255',
            // 'remarks'     => 'nullable|string|max:255',
            // 'is_approved' => 'boolean',
            // 'is_draft'    => 'boolean',
            // 'town_id'     => 'nullable|integer',
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
        // dd($validated);
        // 🧠 Create or Update logic
        $attendance = LabourAttendance::updateOrCreate(
            [
                'labour_id' => $validated['labour_id'],
                'date' => $validated['date'],
            ],
            $validated
        );

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
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'site_id'    => 'required|integer|exists:sites,id',
        ]);

        $reports = Labour::select('labours.id', 'labours.name', 'labours.phone as mobile', 'labours.role as designation')
            ->with(['attendances' => function ($query) use ($request) {
                $query->whereBetween('date', [$request->start_date, $request->end_date])
                    ->where('site_id', $request->site_id)
                    ->whereIn('status', ['present', 'leave']);
                if ($request->id == 'voucherBtnSiteRun') {
                    $query->where('voucher_status', 'notcreated');
                }
            }])
            ->whereHas('attendances', function ($query) use ($request) {
                $query->whereBetween('date', [$request->start_date, $request->end_date])
                    ->where('site_id', $request->site_id)
                    ->whereIn('status', ['present', 'leave']);
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
                    'mobile' => $labour->mobile,
                    'designation' => $labour->designation,
                    'rate' => number_format($rate, 2),
                    'days' => number_format($days, 2),
                    'overtime' => number_format($totalOT, 2),
                    'amount' => number_format($amount, 2),
                    'amount_raw' => $amount,   // used for grand total
                    'attendance_ids' => $attendanceIds, // ✅ real attendance IDs
                ];
            });

        // ✅ Grand Total
        $total_amount = $reports->sum('amount_raw');

        // Remove helper amount_raw from final report
        $reports = $reports->map(function ($r) {
            unset($r['amount_raw']);
            return $r;
        });

        // Collect ALL attendance IDs for voucher, flatten & unique
        $attendanceIds = $reports
            ->pluck('attendance_ids')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        // Render table partial
        $view = view('admin.labours.site-report', compact('reports'))->render();

        return response()->json([
            'success' => true,
            'site_id' => $request->site_id,
            'attendanceIds' => $attendanceIds, // ✅ perfect output here
            'total_amount' => number_format($total_amount, 2),
            'reports' => $reports,
            'view' => $view
        ]);
    }
    public function personAttendanceReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'search'    => 'required',
        ]);

        $x['personWiseReports'] = $personWiseReports = Labour::select('labours.id', 'labours.name', 'labours.phone as mobile', 'labours.role as designation')
            ->where('name', 'like', '%' . $request->search . '%')
            ->with(['attendances' => function ($query) use ($request) {
                $query->whereBetween('date', [$request->start_date, $request->end_date])
                    ->whereIn('status', ['present', 'leave']);
            }])
            ->whereHas('attendances', function ($query) use ($request) {
                $query->whereBetween('date', [$request->start_date, $request->end_date])
                    ->whereIn('status', ['present', 'leave'])->where('voucher_status', 'created');
            })
            ->get()
            ->map(function ($labour) {
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
                    'mobile' => $labour->mobile,
                    'designation' => $labour->designation,
                    'rate' => number_format($rate, 2),
                    'days' => number_format($days, 2),
                    'overtime' => number_format($totalOT, 2),
                    'amount' => number_format($amount, 2),
                    'amount_raw' => $amount,
                    'paid_status' => optional($labour->attendances->first())->paid_status
                ];
            });
            $x['total_amount'] = $personWiseReports->sum('amount_raw');

        // Render table partial
        $view = view('admin.labours.person-wise-report', $x)->render();

        return response()->json([
            'success' => true,
            'view' => $view
        ]);
    }


    public function checkValidate(Request $request)
    {
        // dd($request->all());
        $query = Labour::query();
        if ($request->has('name') && $request->name) $query->where('name', $request->name);
        if ($request->has('cnic') && $request->cnic) $query->where('cnic', $request->cnic);
        if ($request->has('phone') && $request->phone) $query->where('phone', $request->phone);
        $exist = $query->exists();
        return response()->json(['exists' => $exist]);
    }

    public function loadAttendanceWeek(Request $request)
    {
        $x['week'] = $weekInput = $request->week;
        [$year, $week] = explode('-W', $weekInput);
        $startOfWeek = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
        $endOfWeek   = $startOfWeek->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
        }
        $query = Labour::query();
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        } else {
            $query->whereHas('attendances', function ($query) use ($days, $request) {
                if ($request->has('site_id') && $request->site_id) {
                    $query->where('site_id', $request->site_id);
                } else {
                    $query->whereIn('date', $days);
                }
            });
        }

        $x['attendance_labours'] =  $query->get();
        $view = '';
        $view .= view('admin.labours.attn-board', $x)->render();

        return response()->json([
            'success' => true,
            'view' => $view
        ]);

        return [$startOfWeek, $endOfWeek, $days];
    }

    public function createVoucher(Request $request)
    {
        $project_head_subheads_id = Site::where('id', $request->site_id)->first()->subhead_accounting_id;
        $amount = str_replace(',', '', $request->amount);
        $toLedgerData = Ledger::create([
            'type' => 'JV',
            'type_id' => null,
            'project_head_subheads_id' => 372,
            'reference' => null,
            'amount_in' => $amount ?? 0,
            'amount_out' => 0,
            'detail' => 'From Labour Voucher',
            'create_by' => auth()->id(),
            'is_active' => true,
            'status' => '0',
            'date' => Carbon::now()->format('Y-m-d'),
        ]);
        $fromLedgerData = Ledger::create([
            'type' => 'JV',
            'type_id' => null,
            'project_head_subheads_id' => $project_head_subheads_id,
            'reference' => null,
            'amount_in' => 0,
            'amount_out' => $amount ?? 0,
            'detail' => 'From Labour Voucher',
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
}
