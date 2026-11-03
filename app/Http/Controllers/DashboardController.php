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
use App\Models\Permission;
use App\Models\Role;
use App\Repository\Lead\LeadRepository as lead_repo;
use Carbon\Carbon;
use App\Services\Crm\FollowUpStatus;
use App\Services\Dashboard\ModuleMetrics;
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
        // Dashboard New Leads date preset (only this metric is filtered).
        $leadPeriod = $request->query('lead_period', 'today');
        $validLeadPeriods = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'custom'];
        if (!in_array($leadPeriod, $validLeadPeriods, true)) {
            $leadPeriod = 'today';
        }
        $today = Carbon::today();
        switch ($leadPeriod) {
            case 'custom':
                $request->validate([
                    'lead_from' => ['required', 'date_format:Y-m-d'],
                    'lead_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:lead_from'],
                ]);
                $from = Carbon::createFromFormat('Y-m-d', $request->query('lead_from'))->startOfDay();
                $to = Carbon::createFromFormat('Y-m-d', $request->query('lead_to'))->endOfDay();
                if ($from->diffInDays($to) > 366) {
                    abort(422, 'Date range must be within 366 days.');
                }
                break;
            case 'yesterday':
                $from = $today->copy()->subDay();
                $to = $from->copy()->endOfDay();
                break;
            case 'this_week':
                $from = $today->copy()->startOfWeek();
                $to = $today->copy()->endOfWeek();
                break;
            case 'last_week':
                $from = $today->copy()->subWeek()->startOfWeek();
                $to = $from->copy()->endOfWeek();
                break;
            case 'this_month':
                $from = $today->copy()->startOfMonth();
                $to = $today->copy()->endOfMonth();
                break;
            case 'last_month':
                $from = $today->copy()->subMonthNoOverflow()->startOfMonth();
                $to = $from->copy()->endOfMonth();
                break;
            default:
                $from = $today->copy()->startOfDay();
                $to = $today->copy()->endOfDay();
        }
        $x['lead_period'] = $leadPeriod;
        $x['lead_period_from'] = $from->toDateString();
        $x['lead_period_to'] = $to->toDateString();
        $leadPeriodQuery = \App\Models\Lead::query()
            ->where('is_active', 1)
            ->where('project_id', $selectedProjectId)
            ->whereBetween('created_at', [$from, $to]);
        if ($create_by !== null) {
            $leadPeriodQuery->where('create_by', $create_by);
        }
        $lead_response['total_leads'] = $leadPeriodQuery->count();
        $crmScope = \App\Models\Lead::query()->where('is_active', 1)
            ->where('project_id', $selectedProjectId);
        if ($create_by !== null) {
            $crmScope->whereHas('users', fn ($q) => $q->where('users.id', $create_by));
        }
        $openFollowStatuses = FollowUpStatus::OPEN;
        $x['completed_followups'] = Work::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('leads', function ($q) use ($selectedProjectId, $create_by) {
                $q->where('is_active', 1)->where('project_id', $selectedProjectId);
                if ($create_by !== null) {
                    $q->whereHas('users', fn ($assigned) => $assigned->where('users.id', $create_by));
                }
            })->when($create_by !== null, fn ($q) => $q->where('user_id', $create_by))->count();
        $x['today_followups'] = (clone $crmScope)->whereIn('follow_status', $openFollowStatuses)
            ->whereDate('follow_up', Carbon::today())->count();
        $x['pending_overdue_followups'] = (clone $crmScope)->whereIn('follow_status', $openFollowStatuses)
            ->whereNotNull('follow_up')->whereDate('follow_up', '<=', $to->copy()->min(now())->toDateString())->count();
        $x['get_all_lead'] = $getalllead['total_leads'];
        $x['total_blance'] = $total_hand_balance;
        $x['handCashOut'] = $data->total_out;
        $x['handCashIn'] = $data->total_in;
        $x['bankIn'] = $Bankaccountdata->total_in;
        $x['bankOut'] = $Bankaccountdata->total_out;
        $x['dasticashData'] = $dasticash;
        $x['total_bank_account_data'] = $Bankaccountdata->balance;
        $metrics = new ModuleMetrics();
        $x['finance_metrics'] = $metrics->finance($selectedProjectId, (float) $total_hand_balance, (float) -($Bankaccountdata->balance ?? 0));
        $x['plot_metrics'] = $metrics->plots($selectedProjectId);
        $x['stock_metrics'] = $metrics->stock();
        $x['labour_metrics'] = $metrics->labour($selectedProjectId);
        $x['approval_metrics'] = $metrics->approvals($selectedProjectId);
        $x['attendance_metrics'] = $metrics->attendance($selectedProjectId);
        $x['payment_metrics'] = $metrics->paymentInstruments($selectedProjectId);
        $x['recovery_monthly'] = $metrics->monthlyRecovery($selectedProjectId);
        $x['task_metrics'] = $metrics->employeeTasks($selectedProjectId, in_array($power, ['superadmin','admin'], true) ? null : $post_by);
        $x['crm_projects'] = $power === 'superadmin'
            ? \App\Models\Project::query()->orderBy('project')->get(['id', 'project'])
            : \App\Models\Project::query()->whereKey($selectedProjectId)->get(['id', 'project']);
        $x['today_leads'] = $lead_response['total_leads'];
        if ($request->ajax() && $request->expectsJson()) {
            return response()->json([
                'new_leads' => $x['today_leads'],
                'completed_followups' => $x['completed_followups'],
                'today_followups' => $x['today_followups'],
                'pending_overdue_followups' => $x['pending_overdue_followups'],
                'period' => $leadPeriod,
                'from' => $x['lead_period_from'],
                'to' => $x['lead_period_to'],
            ]);
        }
        return view('admin.dashboard', $x);
    }



    /**
     * Read-only, project/role-scoped CRM snapshot for dashboard AJAX.
     * Work entries are labeled logged activities, NOT completed follow-ups.
     */
    public function crmSnapshot(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:all,new,completed,today,pending_overdue'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
        ]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        if ($from->diffInDays($to) > 366) {
            abort(422, 'Date range must not exceed 366 days.');
        }
        $selectedProjectId = getSelectedTown();
        $user = Auth::user();
        $isAdmin = $user->roles->first()?->name === 'superadmin';
        $projectId = $isAdmin && isset($data['project_id']) ? (int) $data['project_id'] : $selectedProjectId;
        abort_unless(\App\Models\Project::query()->whereKey($projectId)->exists(), 404);
        $employeeId = $isAdmin ? ($data['employee_id'] ?? null) : $user->id;
        $statusFilter = $data['status'] ?? 'all';
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 20);
        $openFollowStatuses = FollowUpStatus::OPEN;
        $scope = \App\Models\Lead::query()
            ->where('is_active', 1)
            ->where('project_id', $projectId);
        if ($employeeId) {
            $scope->whereHas('users', fn ($q) => $q->where('users.id', $employeeId));
        }
        $new = (clone $scope)->whereBetween('created_at', [$from, $to])->count();
        $cutoff = $to->copy()->min(now());
        $due = (clone $scope)->whereIn('follow_status', $openFollowStatuses)
            ->whereDate('follow_up', $cutoff->toDateString())->count();
        $overdue = (clone $scope)->whereNotNull('follow_up')
            ->whereIn('follow_status', $openFollowStatuses)
            ->where('follow_up', '<', $cutoff->copy()->startOfDay())->count();
        // Current scheduled follow-up counts. These are current-state records,
        // not an event-sourced historical snapshot of earlier dates.
        $todayDue = (clone $scope)->whereIn('follow_status', $openFollowStatuses)
            ->whereDate('follow_up', Carbon::today()->toDateString())->count();
        $pendingOverdue = (clone $scope)->whereNotNull('follow_up')
            ->whereIn('follow_status', $openFollowStatuses)
            ->whereDate('follow_up', '<=', $cutoff->toDateString())->count();
        $logged = Work::query()->whereHas('leads', function ($q) use ($projectId, $employeeId) {
            $q->where('is_active', 1)->where('project_id', $projectId);
            if ($employeeId) {
                $q->whereHas('users', fn ($assigned) => $assigned->where('users.id', $employeeId));
            }
        })->when($employeeId, fn ($q) => $q->where('user_id', $employeeId))
            ->whereBetween('created_at', [$from, $to])->count();
        $rowQuery = clone $scope;
        if ($statusFilter === 'new') {
            $rowQuery->whereBetween('created_at', [$from, $to]);
        } elseif ($statusFilter === 'completed') {
            $rowQuery->whereHas('comments', function ($q) use ($from, $to, $employeeId) {
                $q->whereBetween('created_at', [$from, $to]);
                if ($employeeId) $q->where('user_id', $employeeId);
            });
        } elseif ($statusFilter === 'today') {
            $rowQuery->whereIn('follow_status', $openFollowStatuses)->whereDate('follow_up', Carbon::today());
        } elseif ($statusFilter === 'pending_overdue') {
            $rowQuery->whereIn('follow_status', $openFollowStatuses)->whereNotNull('follow_up')
                ->whereDate('follow_up', '<=', $cutoff->toDateString());
        } else {
            $rowQuery->where(function ($query) use ($from, $to, $cutoff, $openFollowStatuses) {
                $query->whereBetween('created_at', [$from, $to])
                    ->orWhereHas('comments', fn ($work) => $work->whereBetween('created_at', [$from, $to]))
                    ->orWhere(function ($pending) use ($cutoff, $openFollowStatuses) {
                        $pending->whereIn('follow_status', $openFollowStatuses)
                            ->whereDate('follow_up', '<=', $cutoff->toDateString());
                    });
            });
        }
        $rowTotal = (clone $rowQuery)->count();
        $rows = $rowQuery->with(['project:id,project', 'users:id,name', 'comments' => function ($q) {
            $q->select('id', 'lead_id', 'comment', 'created_at', 'follow_up');
        }])->latest('id')->forPage($page, $perPage)->get()->map(function ($lead) use ($openFollowStatuses) {
            $latest = $lead->comments->first();
            $next = $lead->follow_up ? Carbon::parse($lead->follow_up) : null;
            $status = FollowUpStatus::label((int) $lead->follow_status, $next);
            return [
                'id' => $lead->id,
                'customer' => trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')),
                'mobile' => $lead->phone_number ?: $lead->mobile_number,
                'project' => $lead->project?->project,
                'assigned' => $lead->users->pluck('name')->join(', '),
                'created_at' => optional($lead->created_at)->toDateString(),
                'last_activity' => optional($latest?->created_at)->toDateString(),
                'next_follow_up' => $lead->follow_up,
                'status' => $status,
                'remarks' => $latest?->comment,
            ];
        });
        // Employee-wise activity and assigned lead counts; activities are not treated as completed follow-ups.
        $employees = User::query()->where('project_id', $projectId)
            ->when($employeeId, fn ($q) => $q->whereKey($employeeId))
            ->orderBy('name')->get(['id','name']);
        $employeeSummary = $employees->map(function ($employee) use ($projectId, $from, $to, $cutoff) {
            $leadQuery = \App\Models\Lead::query()->where('is_active', 1)
                ->where('project_id', $projectId)
                ->whereHas('users', fn ($q) => $q->where('users.id', $employee->id));
            $workQuery = Work::query()->where('user_id', $employee->id)
                ->whereHas('leads', fn ($q) => $q->where('is_active', 1)->where('project_id', $projectId));
            return [
                'employee' => $employee->name,
                'assigned_leads' => (clone $leadQuery)->count(),
                'new_leads' => (clone $leadQuery)->whereBetween('created_at', [$from, $to])->count(),
                'completed_followups' => (clone $workQuery)->whereBetween('created_at', [$from, $to])->count(),
                'due_on_period_end' => (clone $leadQuery)->whereIn('follow_status', FollowUpStatus::OPEN)->whereDate('follow_up', $cutoff->toDateString())->count(),
                'overdue_as_of_period_end' => (clone $leadQuery)->whereIn('follow_status', FollowUpStatus::OPEN)->whereNotNull('follow_up')->where('follow_up', '<', $cutoff->copy()->startOfDay())->count(),
            ];
        });
        return response()->json([
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'new_leads' => $new, 'completed_followups' => $logged, 'logged_activities' => $logged,
            'due_on_period_end' => $due, 'overdue_as_of_period_end' => $overdue,
            'today_due' => $todayDue, 'pending_overdue_combined' => $pendingOverdue,
            'rows' => $rows, 'employee_summary' => $employeeSummary,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $rowTotal,
                'last_page' => max(1, (int) ceil($rowTotal / $perPage))],
        ]);
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
        $data = $request->validate(['mode'=>['nullable','in:today,completed,pending_overdue'],
            'user_id'=>['nullable','integer','min:1'], 'date_range'=>['nullable','string','max:30']]);
        $projectId=(int)getSelectedTown(); $mode=$data['mode']??'today';
        $fromDate=Carbon::today()->startOfDay(); $toDate=Carbon::today()->endOfDay();
        if (!empty($data['date_range'])) {
            $dates=explode(' to ',$data['date_range']); abort_unless(count($dates)===2,422,'Invalid date range.');
            try { $fromDate=Carbon::createFromFormat('Y-m-d',trim($dates[0]))->startOfDay(); $toDate=Carbon::createFromFormat('Y-m-d',trim($dates[1]))->endOfDay(); }
            catch (\Throwable) { abort(422,'Invalid date range.'); }
            abort_if($fromDate->gt($toDate),422,'The From date must be before the To date.');
        }
        $toDate=$toDate->min(now()->endOfDay());
        abort_if($fromDate->gt($toDate),422,'Future follow-ups are not available in this report.');
        $requested=isset($data['user_id'])?(int)$data['user_id']:null;
        $userId=Auth::user()->hasRole('superadmin')?$requested:(int)Auth::id();
        if ($userId) abort_unless(User::whereKey($userId)->where('project_id',$projectId)->exists(),404);
        $users=User::where('project_id',$projectId)->where('status_id',1)->when($userId,fn($q)=>$q->whereKey($userId))->orderBy('name')->get(['id','name']);
        $rowsByUser=collect(); $cards=collect();
        foreach ($users as $user) {
            if ($mode==='completed') {
                $rows=Work::with('leads:id,first_name,last_name,phone_number,mobile_number,project_id')->where('user_id',$user->id)
                    ->whereBetween('created_at',[$fromDate,$toDate])->whereHas('leads',fn($q)=>$q->where('is_active',1)->where('project_id',$projectId))
                    ->latest('created_at')->get()->map(fn($work)=>['lead_id'=>$work->lead_id,'name'=>trim(($work->leads?->first_name??'').' '.($work->leads?->last_name??''))?:'N/A',
                        'mobile'=>$work->leads?->phone_number?:($work->leads?->mobile_number?:'N/A'),'comment'=>$work->comment?:'-','follow_up'=>optional($work->created_at)->format('Y-m-d H:i'),
                        'status'=>\Setting::getCallStatus($work->call_status),'late_days'=>null]);
            } else {
                $leadQuery=\App\Models\Lead::with(['comments:id,lead_id,comment'])->where('is_active',1)->where('project_id',$projectId)
                    ->whereHas('users',fn($q)=>$q->where('users.id',$user->id))->whereIn('follow_status',FollowUpStatus::OPEN)->whereNotNull('follow_up');
                $mode==='today'
                    ? $leadQuery->whereBetween('follow_up',[$fromDate,$toDate])
                    : $leadQuery->whereDate('follow_up','<=',$toDate->toDateString());
                $rows=$leadQuery->orderBy('follow_up')->get()->map(function($lead)use($mode){$follow=Carbon::parse($lead->follow_up);return ['lead_id'=>$lead->id,
                    'name'=>trim(($lead->first_name??'').' '.($lead->last_name??''))?:'N/A','mobile'=>$lead->phone_number?:($lead->mobile_number?:'N/A'),
                    'comment'=>$lead->comments->first()?->comment?:'-','follow_up'=>$follow->format('Y-m-d H:i'),'status'=>$follow->isBefore(Carbon::today())?'Overdue':'Pending',
                    'late_days'=>$mode==='pending_overdue'?(int)max(0,$follow->copy()->startOfDay()->diffInDays(Carbon::today(),false)):null];});
            }
            if($rows->isNotEmpty()){$cards->push(['id'=>$user->id,'name'=>$user->name,'count'=>$rows->count()]);$rowsByUser->put($user->id,['user'=>$user,'rows'=>$rows]);}
        }
        if ($request->ajax()) {
            return response()->json([
                'html' => view('dashboard.partials.follow_up_user_tables', compact('mode', 'rowsByUser'))->render(),
                'count' => $rowsByUser->sum(fn ($group) => count($group['rows'])),
            ]);
        }
        return view('dashboard.today_lead_work_report',compact('mode','fromDate','toDate','cards','rowsByUser','projectId','userId'));
    }









}
