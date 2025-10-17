<?php

namespace App\Http\Controllers\Finance;

use App\Models\Lead;
use App\Models\PendingUpdate;
use App\Models\Plot;
use App\Models\User;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\DraftLedger;
use Illuminate\Http\Request;
use App\Models\HeadAccounting;
use App\Models\AccountingExpense;
use App\Models\SubheadAccounting;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Double Entry Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        // $x['data']      = AccountingExpense::get();


        // $x['data'] = AccountingExpense::where(['is_active' => 1])->get();


        $x['data'] = AccountingExpense::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')->where('is_active', 1)->get();
        // dd($x['data']);


        $projects = Project::get();
        // $headaccounts = HeadAccounting::get();
        // $subheadaccounts = SubheadAccounting::get();

        $x['projects'] = $projects;
        // $x['headaccounts'] = $headaccounts;
        // $x['subheadaccounts'] = $subheadaccounts;
        return view('admin.finance.voucher.accounting_index', $x);
    }
    public function pendingIndex()
    {
        $user = Auth::user();
        $power = $user->roles[0]->name ?? 'User';

        $x['title'] = 'Pending Admin Approvals';
        $x['power'] = $power;
        $x['users'] = User::get();

        // Fetch only pending updates (you can show approved/rejected with filters later)
        $x['pendingUpdates'] = PendingUpdate::with(['submittedBy', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->where('status', 'pending')
            ->get();

        return view('admin.pending-approve.pending_updates_index', $x);
    }


    public function cash_in()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Cash In Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['class'] = 'cash-in';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')->where('is_active', 1)->where('type', 'CR')->get();
        // Modify the query to include the project_id filter
        // $data = Ledger::with(['projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project'])
        //     ->where('is_active', 1)
        //     ->where('type', 'CR')
        //     ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
        //         $query->where('project_id', $selectedProjectId);
        //     })
        //     ->get();

        // // Attach status to each ledger record
        // $data = $data->map(function ($item) {
        //     $item['amount'] = floatval($item['amount_in']);

        //     $pending = PendingUpdate::where('table_name', 'ledgers')
        //         ->where('record_id', $item->id)
        //         ->latest()
        //         ->first();
        //     $item['submitted_by'] = $pending ? $pending->submittedBy?->name : '';
        //     $item['new_values'] = $pending ? json_decode($pending->new_values, true) : '';
        //     $item['old_values'] = $pending ? json_decode($pending->old_values, true) : '';
        //     $item['status'] = $pending ? ucfirst($pending->status) : 'Approved';
        //     return $item;
        // });
        // $x['data'] = $data;
        $x['data'] = [];

        $x['headaccounts'] = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();
        $x['partyaccounts'] = ProjectHeadSubhead::with('subheadAccounting')
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->whereIn('head_accounting_id', $x['headaccounts']->pluck('head_accounting_id'))
            ->where('project_id', $selectedProjectId)
            ->get()
            ->map(function ($item) {
                $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                return $item;
            });
        $x['customers'] = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $selectedProjectId)
            ->whereNull('bookings.deleted_at') // Exclude soft-deleted bookings
            ->select('leads.*') // Select all columns from the leads table
            ->distinct() // Ensure uniqueness by lead id
            ->get();
        $x['plots'] = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
            ->where('plots.project_id', $selectedProjectId)
            ->get();
        $x['selectedProjectId'] = $selectedProjectId;
        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;
        $x['table_data_route'] = route('voucher.cash_in.data');

        return view('admin.finance.voucher.cash_voucher', $x);
    }
    public function approve($id, Request $request)
    {
        // Whitelist allowed tables to prevent SQL injection
        // $allowedTables = ['ledgers', 'customers', 'projects']; // add all tables you want to allow
        // if (!in_array($request->table, $allowedTables)) {
        //     abort(400, 'Invalid table name.');
        // }

        // Fetch pending update record
        $pending = PendingUpdate::where('record_id', $id)
            ->where('table_name', $request->table)
            ->where('status', 'pending')
            ->firstOrFail();

        // Authorization check
        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }


        $pendingNewChanges = json_decode($pending->new_values, true);
        if (isset($pendingNewChanges['is_active']) && $pending->new_values == $pendingNewChanges['is_active']) {
            DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
                $result = DB::table($request->table)::findOrFail($pending->record_id);
                if ($request->table == 'ledgers') {
                    $customerLedger = $result->customerLedger;
                    if ($customerLedger) {
                        $customerLedger->update($pendingNewChanges);
                    }
                }
                $result->update($pendingNewChanges);
            });
        }

        DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
            // Check if record exists
            $record = DB::table($request->table)
                ->where('id', $pending->record_id)
                ->first();

            if (!$record) {
                abort(404, 'Record not found.');
            }

            // Update record dynamically
            DB::table($request->table)
                ->where('id', $pending->record_id)
                ->update($pendingNewChanges);

            // Mark pending as approved
            $pending->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Record approved successfully.');
    }


    public function reject($id, Request $request)
    {
        $pending = PendingUpdate::where('record_id', $id)
            ->where('table_name', $request->table)
            ->where('status', 'pending')
            ->firstOrFail();

        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }

        $pending->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Voucher rejected.');
    }
    public function approveAdmin($id, Request $request)
    {
        // Whitelist allowed tables to prevent SQL injection
        // $allowedTables = ['ledgers', 'customers', 'projects']; // add all tables you want to allow
        // if (!in_array($request->table, $allowedTables)) {
        //     abort(400, 'Invalid table name.');
        // }

        // Fetch pending update record
        $pending = PendingUpdate::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        // Authorization check
        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }


        $pendingNewChanges = json_decode($pending->new_values, true);
        if (isset($pendingNewChanges['is_active']) && $pending->new_values == $pendingNewChanges['is_active']) {
            DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
                $result = DB::table($request->table)::findOrFail($pending->record_id);
                if ($request->table == 'ledgers') {
                    $customerLedger = $result->customerLedger;
                    if ($customerLedger) {
                        $customerLedger->update($pendingNewChanges);
                    }
                }
                $result->update($pendingNewChanges);
            });
        }

        DB::transaction(function () use ($pending, $pendingNewChanges, $request) {
            // Check if record exists
            $record = DB::table($request->table)
                ->where('id', $pending->record_id)
                ->first();

            if (!$record) {
                abort(404, 'Record not found.');
            }

            // Update record dynamically
            DB::table($request->table)
                ->where('id', $pending->record_id)
                ->update($pendingNewChanges);

            // Mark pending as approved
            $pending->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Record approved successfully.');
    }


    public function rejectAdmin($id, Request $request)
    {
        $pending = PendingUpdate::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        if (!Auth::user()->hasRole('super-admin') && !Auth::user()->can('direct-update')) {
            abort(403, 'Unauthorized action.');
        }

        $pending->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Voucher rejected.');
    }


    public function cash_out()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Cash Out Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CP';
        $x['class'] = 'cash-out';
        $x['bg_voucher'] = 'info-cash-out';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();


        // $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
        //     ->where('is_active', 1)
        //     ->whereIn('type', ['CP', 'BO'])  // Use whereIn to check for either 'CP' or 'BO'
        //     ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
        //         $query->where('project_id', $selectedProjectId);
        //     })
        //     ->get();

        // $data = $data->map(function ($item) {
        //     $item['amount'] = floatval($item['amount_in']);

        //     $pending = PendingUpdate::where('table_name', 'ledgers')
        //         ->where('record_id', $item->id)
        //         ->latest()
        //         ->first();
        //     $item['submitted_by'] = $pending ? $pending->submittedBy?->name : '';
        //     $item['new_values'] = $pending ? json_decode($pending->new_values, true) : '';
        //     $item['old_values'] = $pending ? json_decode($pending->old_values, true) : '';
        //     $item['status'] = $pending ? ucfirst($pending->status) : 'Approved';
        //     return $item;
        // });

        // $x['data'] = $data;
        $x['data'] = [];
        $x['headaccounts'] = ProjectHeadSubhead::query()->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with(['headAccounting:id,name'])
            ->where('project_id', $selectedProjectId)
            ->get();
        $x['partyaccounts'] = ProjectHeadSubhead::query()
            ->select('project_id', 'head_accounting_id', 'subhead_accounting_id')
            ->with(['subheadAccounting:id,name'])
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->where('project_id', $selectedProjectId)
            ->whereIn('head_accounting_id', $x['headaccounts']->pluck('head_accounting_id'))
            ->get()
            ->map(function ($item) {
                $item->balance = (float) ($item->total_in ?? 0) - (float) ($item->total_out ?? 0);
                return $item;
            });
        $x['customers'] = Lead::query()
            ->select('id', 'first_name', 'last_name', 'relate', 'father_name', 'phone_number', 'mobile_number', 'nic_number', 'home_address')
            ->where('project_id', $selectedProjectId)
            ->whereExists(function ($q) {
                $q->from('bookings')
                    ->whereColumn('bookings.customer_id', 'leads.id')
                    ->whereNull('bookings.deleted_at');
            })->get();
        $x['plots'] = Plot::query()
            ->select('plots.id as plot_id', 'plots.name', 'plots.type')
            ->where('plots.project_id', $selectedProjectId)
            ->whereExists(function ($q) {
                $q->from('bookings')->whereColumn('bookings.plot_id', 'plots.id')->whereNull('bookings.deleted_at');
            })->get();
        $x['selectedProjectId'] = $selectedProjectId;

        $x['projects'] = Project::query()->select('id', 'project')->where('id', $selectedProjectId)->get();
        $x['table_data_route'] = route('voucher.cash_out.data');

        return view('admin.finance.voucher.cash_voucher', $x);
    }
    public function cash_out_data()
    {
        $selectedProjectId = getSelectedTown();

        // Build base query with only the necessary relationships and columns
        $q = Ledger::with([
            'projectHeadSubhead.headAccounting:id,name',
            'projectHeadSubhead.subheadAccounting:id,name',
            'projectHeadSubhead.project:id,project'
        ])
            ->where('is_active', 1)
            ->whereIn('type', ['CP', 'BO'])
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId));

        // Get total count before pagination
        $total = (clone $q)->count();

        // Pagination parameters
        $start = (int) request('start', 0);
        $length = (int) request('length', 10);
        $draw = (int) request('draw', 1);

        // Fetch only paginated rows, ordered by latest
        $rows = $q->orderByDesc('id')->skip($start)->take($length)->get();

        // Transform the result set
        $data = $rows->map(function ($i) {
            $amount = $i->type === 'CP' ? (float) $i->amount_out : (float) $i->amount_in;

            // Use preloaded relation if available or define relation in Ledger for latestPendingUpdate
            $p = $i->latestPendingUpdate ?? PendingUpdate::where('table_name', 'ledgers')
                ->where('record_id', $i->id)
                ->latest()
                ->first();

            return [
                'date' => $i->date,
                'project' => $i->projectHeadSubhead->project->project ?? '',
                'head' => $i->projectHeadSubhead->headAccounting->name ?? '',
                'subhead' => $i->projectHeadSubhead->subheadAccounting->name ?? '',
                'detail' => $i->detail,
                'amount' => $amount,
                'status' => $p ? ucfirst($p->status) : 'Approved',
                'submitted_by' => $p?->submittedBy?->name ?? '',
                'old_values' => json_decode($p?->old_values, true) ?? [],
                'new_values' => json_decode($p?->new_values, true) ?? [],
                'id' => $i->id,
                'type' => $i->type,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }
    public function cash_in_data()
    {
        $selectedProjectId = getSelectedTown();
        // Build base query with only the necessary relationships and columns
        $q = Ledger::with([
            'projectHeadSubhead.headAccounting:id,name',
            'projectHeadSubhead.subheadAccounting:id,name',
            'projectHeadSubhead.project:id,project'
        ])
            ->where('is_active', 1)
            ->where('type', 'CR')
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $selectedProjectId));

        // Get total count before pagination
        $total = (clone $q)->count();

        // Pagination parameters
        $start = (int) request('start', 0);
        $length = (int) request('length', 10);
        $draw = (int) request('draw', 1);

        // Fetch only paginated rows, ordered by latest
        $rows = $q->orderByDesc('id')->skip($start)->take($length)->get();

        // Transform the result set
        $data = $rows->map(function ($i) {
            $amount = $i->type === 'CP' ? (float) $i->amount_out : (float) $i->amount_in;

            // Use preloaded relation if available or define relation in Ledger for latestPendingUpdate
            $p = $i->latestPendingUpdate ?? PendingUpdate::where('table_name', 'ledgers')
                ->where('record_id', $i->id)
                ->latest()
                ->first();

            return [
                'date' => $i->date,
                'project' => $i->projectHeadSubhead->project->project ?? '',
                'head' => $i->projectHeadSubhead->headAccounting->name ?? '',
                'subhead' => $i->projectHeadSubhead->subheadAccounting->name ?? '',
                'detail' => $i->detail,
                'amount' => $amount,
                'status' => $p ? ucfirst($p->status) : 'Approved',
                'submitted_by' => $p?->submittedBy?->name ?? '',
                'old_values' => json_decode($p?->old_values, true) ?? [],
                'new_values' => json_decode($p?->new_values, true) ?? [],
                'id' => $i->id,
                'type' => $i->type,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    public function cash_draft()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Draft Vouchers';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CR';
        $x['class'] = 'cash-out';
        $x['bg_voucher'] = 'info-cash-out';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();


        $data = DraftLedger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('project_id', $selectedProjectId)
            ->where('is_active', 1)  // Use whereIn to check for either 'CP' or 'BO'
            ->get();

        $data = $data->map(function ($item) {
            $item['amount'] = $item['transaction_type'] === 'CR' ? floatval($item['amount_in']) : floatval($item['amount_out']);
            $pending = PendingUpdate::where('table_name', 'draft_ledgers')
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

        $x['headaccounts'] = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();
        $x['partyaccounts'] = ProjectHeadSubhead::with('subheadAccounting')
            ->withSum('ledgers as total_in', 'amount_in')
            ->withSum('ledgers as total_out', 'amount_out')
            ->whereIn('head_accounting_id', $x['headaccounts']->pluck('head_accounting_id'))
            ->where('project_id', $selectedProjectId)
            ->get()
            ->map(function ($item) {
                $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                return $item;
            });
        $x['customers'] = Lead::join('bookings', 'leads.id', '=', 'bookings.customer_id')
            ->where('leads.project_id', $selectedProjectId)
            ->whereNull('bookings.deleted_at') // Exclude soft-deleted bookings
            ->select('leads.*') // Select all columns from the leads table
            ->distinct() // Ensure uniqueness by lead id
            ->get();
        $x['plots'] = Plot::join('bookings', 'plots.id', '=', 'bookings.plot_id')
            ->where('plots.project_id', $selectedProjectId)
            ->get();
        $x['selectedProjectId'] = $selectedProjectId;

        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;

        return view('admin.finance.voucher.draft_voucher', $x);
    }
}
