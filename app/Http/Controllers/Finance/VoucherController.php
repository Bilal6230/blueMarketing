<?php

namespace App\Http\Controllers\Finance;

use App\Models\Lead;
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
        $x['title']     = 'Double Entry Voucher';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
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

    public function cash_in()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Cash In Voucher';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CR';
        $x['class']      =   'cash-in';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')->where('is_active', 1)->where('type', 'CR')->get();
        // Modify the query to include the project_id filter
        $data = Ledger::with(['projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project'])
            ->where('is_active', 1)
            ->where('type', 'CR')
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->get();

        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
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
        $x['table_data_route'] = route('voucher.cash_in.data');
        $numbers = Ledger::where('type', 'CR')
            ->where('is_active', 1)
            ->orderBy('voucher_number')
            ->pluck('voucher_number')
            ->toArray();

        $nextNumber = 1; // default starting number

        if (!empty($numbers)) {
            $allNumbers = range(min($numbers), max($numbers));
            $missing = array_diff($allNumbers, $numbers);

            if (!empty($missing)) {
                // Get the smallest missing number
                $nextNumber = min($missing);
            } else {
                // If no missing numbers, continue from max
                $nextNumber = max($numbers) + 1;
            }
        }

        $x['latest_voucher_number'] = $nextNumber;

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
         if ($request->table == 'leads') {
            DB::table('lead_user')->insert([
                'lead_id' => $pending->record_id,
                'user_id' => $pending->submitted_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $pending->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);
            return back()->with('success', 'Record approved successfully.');
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
        $x['title']     = 'Cash Out Voucher';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CP';
        $x['class']      =   'cash-out';
        $x['bg_voucher'] = 'info-cash-out';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();


        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1)
            ->whereIn('type', ['CP', 'BO'])  // Use whereIn to check for either 'CP' or 'BO'
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->get();

        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_out']);
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

        $x['projects'] = Project::query()->select('id', 'project')->where('id', $selectedProjectId)->get();
        $x['table_data_route'] = route('voucher.cash_out.data');
        $numbers = Ledger::where('type', 'CP')
            ->where('is_active', 1)
            ->orderBy('voucher_number')
            ->pluck('voucher_number')
            ->toArray();

        $nextNumber = 1; // default starting number

        if (!empty($numbers)) {
            $allNumbers = range(min($numbers), max($numbers));
            $missing = array_diff($allNumbers, $numbers);

            if (!empty($missing)) {
                // Get the smallest missing number
                $nextNumber = min($missing);
            } else {
                // If no missing numbers, continue from max
                $nextNumber = max($numbers) + 1;
            }
        }

        $x['latest_voucher_number'] = $nextNumber;

        return view('admin.finance.voucher.cash_voucher', $x);
    }
    public function cash_draft()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Draft Vouchers';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CR';
        $x['class']      =   'cash-out';
        $x['bg_voucher'] = 'info-cash-out';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();


        $data = DraftLedger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
            ->where('is_active', 1)  // Use whereIn to check for either 'CP' or 'BO'
            ->get();

        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_out']);
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
