<?php

namespace App\Http\Controllers;

use App\Models\Dasticash;
use App\Models\Ledger;
use App\Models\Punch;
use App\Models\User;
use App\Models\Work;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Repository\Lead\LeadRepository as lead_repo;
use Carbon\Carbon;
use Termwind\Components\Raw;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $create_by = null;
        $selectedProjectId = getSelectedTown();
        $post_by = Auth::user()->id;
        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Dashboard';
        $x['user'] = User::get();
        $x['role'] = Role::get();
        $x['permission'] = Permission::get();
        $x['display_date'] = true;
        $x['power'] = $power;


        // Build the users_list query
        $usersListQuery = User::where('project_id', $selectedProjectId)
            ->where('status_id', 1);

        // Apply the additional condition if the user is not a superadmin
        if ($power !== 'superadmin') {
            $usersListQuery->where('id', $post_by);
            $x['display_date'] = false;
            $create_by = $post_by;

        }

        // Get the users list
        $x['users_list'] = $usersListQuery->get();
        $data = Ledger::with('projectHeadSubhead.project', 'projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')->where('is_active', 1)->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
            $query->where('project_id', $selectedProjectId);
        })->select(DB::raw('SUM(amount_in) as total_in, SUM(amount_out) as total_out, SUM(amount_in - amount_out) as balance'))
            ->first();

        $Bankaccountdata = Ledger::with([
            'projectHeadSubhead.project',
            'projectHeadSubhead.headAccounting',
            'projectHeadSubhead.subheadAccounting'
        ])
            ->where('is_active', 1)
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->whereHas('projectHeadSubhead.headAccounting', function ($query) {
                $query->where('id', 32); // Filter Head Accounting by ID 32
            })
            ->select(DB::raw('SUM(amount_in) as total_in, SUM(amount_out) as total_out, SUM(amount_in - amount_out) as balance'))
            ->first();
        // Get today's punch records
        $x['today_punches'] = Punch::whereDate('punch_in', now()->toDateString())->where('project_id', $selectedProjectId)->get();
        // Fetch all active leads for a specific project created today
        $dasticash = Dasticash::where('project_id', $selectedProjectId)->get();
        $dasticashtotalcash = Dasticash::where('project_id', $selectedProjectId)
            ->selectRaw("COALESCE(SUM(amount),0) as total")
            ->first();
        $total_hand_balance = ($data->balance ?? 0)-($dasticashtotalcash->total ?? 0);
        $getalllead = lead_repo::getAllLeads($selectedProjectId);
        $lead_response = lead_repo::getLeadsToday($selectedProjectId, 'today', $create_by);
        $x['get_all_lead'] = $getalllead['total_leads'];
        $x['total_blance'] = $total_hand_balance;
        $x['handCashOut'] = $data->total_out;
        $x['handCashIn'] = $data->total_in;
        $x['bankIn'] = $Bankaccountdata->total_in;
        $x['bankOut'] = $Bankaccountdata->total_out;
        $x['dasticashData'] = $dasticash;
        $x['total_bank_account_data'] = $Bankaccountdata->balance;
        $x['today_leads'] = $lead_response['total_leads'];

        return view('admin.dashboard', $x);
    }



    public function punch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required'],
        ]);

        $power = Auth::user()->roles[0]->name;

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Determine punch_time based on user role
        if ($power !== 'superadmin') {
            $punch_time = now(); // Set current date and time
        } else {
            $punch_time = $request->input('punch_time'); // Get punch time from request
        }

        $user_id = $request->input('user_id');
        $selectedProjectId = getSelectedTown();
        $post_by = Auth::user()->id;

        // Check if a punch record exists for today
        $existingPunch = Punch::where('user_id', $user_id)
            ->where('project_id', $selectedProjectId)
            ->whereDate('punch_in', now()->toDateString()) // Check for today's date
            ->first();

        if ($existingPunch) {
            // If both punch_in and punch_out are already set, do not allow an update
            if ($existingPunch->punch_out) {
                Alert::error('Error', 'You have already submitted both punch-in and punch-out for today.')->toToast()->toHtml();
                return redirect()->back();
            }

            // Update only the punch_out field, keep punch_in unchanged
            $existingPunch->update([
                'punch_out' => $punch_time,
                'post_by' => $post_by,
            ]);

            Alert::success('Notification', 'Punch-out time has been updated successfully.')->toToast()->toHtml();
        } else {
            // Create a new record with punch_in
            Punch::create([
                'user_id' => $user_id,
                'punch_in' => $punch_time,
                'project_id' => $selectedProjectId,
                'post_by' => $post_by,
            ]);

            Alert::success('Notification', 'Punch-in time has been saved successfully.')->toToast()->toHtml();
        }

        return redirect()->back(); // Redirects the user back to the previous page
    }


    public function printLeadsByUsersReport2()
    {
        $selectedProjectId = getSelectedTown();

        $post_by = Auth::user()->id;
        $power = Auth::user()->roles[0]->name;
        $create_by = null;

        if ($power !== 'superadmin') {

            $create_by = $post_by;

        }

        // Retrieve leads grouped by users
        $leadsByUsers = lead_repo::getLeadsByUsers($selectedProjectId, 'today', $create_by);
        //dd($leadsByUsers);

        // Pass data to the Blade template
        return view('dashboard.leads_by_users_report', compact('leadsByUsers'));
    }

    public function printLeadsByUsersReport(Request $request)
    {
        $selectedProjectId = getSelectedTown();  // Assuming this function retrieves the selected project ID.
        $post_by = auth()->user()->id;
        $power = auth()->user()->roles[0]->name;
        $create_by = null;

        if ($power !== 'superadmin') {
            $create_by = $post_by;
        }

        // Get the date range from the request (e.g., "2025-01-01 to 2025-01-05")
        $dateRange = $request->input('date_range');

        // Default start and end dates (if no date range is provided)
        $fromDate = Carbon::now()->startOfDay();
        $toDate = Carbon::now()->endOfDay();



        // If date range is provided, parse it
        if ($dateRange) {
            $dates = explode(' to ', $dateRange);
            if (count($dates) == 2) {
                // Parse the start and end dates with the correct format
                $fromDate = Carbon::createFromFormat('Y-m-d', $dates[0])->startOfDay();
                $toDate = Carbon::createFromFormat('Y-m-d', $dates[1])->endOfDay();
            }
        }

        // Retrieve leads grouped by users, within the selected date range
        $leadsByUsers = lead_repo::getLeadsByUsers($selectedProjectId, null, $create_by, $fromDate, $toDate);

        // Pass the data to the Blade template
        return view('dashboard.leads_by_users_report', compact('leadsByUsers', 'fromDate', 'toDate'));
    }




    public function todayLeadWorkReport(Request $request)
    {
        $create_by = null;
        $selectedProjectId = getSelectedTown();
        $post_by = Auth::user()->id;
        $power = Auth::user()->roles[0]->name;

        // Get user_id from the request query parameter (if it exists)
        $userId = $request->get('user_id', null);

        // Apply the additional condition if the user is not a superadmin
        if ($power !== 'superadmin') {
            $userId = $post_by; // Restrict to the current user's data
        }

        // Get date range from the request
        $dateRange = $request->input('date_range', null);
        $fromDate = Carbon::today(); // Default to today if no range is provided
        $toDate = Carbon::today();

        if ($dateRange) {
            $dates = explode(' to ', $dateRange);
            $fromDate = isset($dates[0]) ? Carbon::parse($dates[0]) : $fromDate;
            $toDate = isset($dates[1]) ? Carbon::parse($dates[1]) : $toDate;
        }

        // Fetch the work report data, optionally filtering by user_id and date range
        $todayWorkReport = Work::with(['leads', 'user'])
            ->whereBetween('created_at', [$fromDate->startOfDay(), $toDate->endOfDay()]) // Filter by date range
            ->when($userId, function ($query) use ($userId) {
                $query->where('user_id', $userId); // Filter by user_id if provided
            })
            ->get()
            ->groupBy(['user_id', 'lead_id']); // Group by user_id and lead_id

        // Fetch all users with their works count for the given date range
        $allUsers = User::withCount([
            'works' => function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('created_at', [$fromDate->startOfDay(), $toDate->endOfDay()]);
            }
        ])
            ->having('works_count', '>', 0) // Only include users with works in the date range (count > 0)
            ->get();

        // Pass data to the view
        return view('dashboard.today_lead_work_report', compact('todayWorkReport', 'allUsers'));
    }









}
