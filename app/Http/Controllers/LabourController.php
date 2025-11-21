<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Labour;
use Illuminate\Http\Request;
use App\Models\HeadAccounting;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use App\Http\Controllers\Controller;
use App\Models\LabourAttendance;

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

        // dd($data_list[0]->headAccounting->name);

        $x['headaccounts'] = $data_list;
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
        $data = [
            'site_name' => $request->site_name,
            'site_address' => $request->site_address,
            'head_accounting_id' => $request->accounts_id,
            'subhead_accounting_id' => $request->subaccounts_id,
        ];
        Site::create($data);
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
            'amount'      => 'nullable|numeric|min:0',
            'site_name'   => 'nullable|string|max:255',
            'remarks'     => 'nullable|string|max:255',
            'is_approved' => 'boolean',
            'is_draft'    => 'boolean',
            'town_id'     => 'nullable|integer',
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

        // 🧠 Create or Update logic
        $attendance = LabourAttendance::updateOrCreate(
            [
                'labour_id' => $validated['labour_id'],
                'site_id' => $validated['site_id'],
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
                    ->whereIn('status', ['present', 'leave']); // ✅ include half-days
            }])
            ->whereHas('attendances', function ($query) use ($request) {
                $query->whereBetween('date', [$request->start_date, $request->end_date])
                    ->where('site_id', $request->site_id)
                    ->whereIn('status', ['present', 'leave']); // ✅ same here
            })
            ->get()
            ->map(function ($labour) {
                $totalHours = $labour->attendances->sum('hours');  // ✅ includes half-days now
                $totalOT = $labour->attendances->sum('ot_hours');
                $rate = optional($labour->attendances->first())->rate ?? $labour->daily_wage ?? 0;
                // ✅ calculate half-days correctly
                $days = $totalHours / 8; // don’t round yet — preserve .5 accuracy
                $amount = ($days * $rate) + ($totalOT * ($rate / 8));

                return [
                    'name' => $labour->name,
                    'mobile' => $labour->mobile,
                    'designation' => $labour->designation,
                    'rate' => number_format($rate, 2),
                    'days' => number_format($days, 2),
                    'overtime' => number_format($totalOT, 2),
                    'amount' => number_format($amount, 2),
                ];
            });
        $view = '';
        $view .= view('admin.labours.site-report', compact('reports'))->render();

        return response()->json([
            'success' => true,
            'reports' => $reports,
            'view' => $view
        ]);
    }
    public function checkValidate(Request $request)
    {
        // dd($request->all());
        $query = Labour::query();
        if($request->has('name') && $request->name) $query->where('name', $request->name);
        if($request->has('cnic') && $request->cnic) $query->where('cnic', $request->cnic);
        if($request->has('phone') && $request->phone) $query->where('phone', $request->phone);
        $exist = $query->exists();
        return response()->json(['exists' => $exist]);
    }
}
