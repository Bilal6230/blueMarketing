<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountType;
use App\Models\HeadAccounting;
use App\Models\ProjectHeadSubhead;
use App\Models\SubheadAccounting;
use App\Models\AccountingExpense;
use App\Models\Ledger;
use App\Models\Project;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Carbon;

class AccountingController extends Controller
{


    public function index(Request $request)
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Accounts List';
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


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'price' => ['required', 'numeric', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'detail' => ['required'],
            'accounts_id' => ['required'],
            'subaccounts_id' => ['required'],
            'projects_id' => ['required'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        if (isset($request->cash_in)) {
            if (is_null($request->cash_in)) {
                $request->cash_in = 0;
            } elseif ($request->cash_in === 'on') {
                $request->cash_in = 1;
            }
        }
        try {
            // dd($request->cash_in);

            $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->where('project_id', $request->projects_id)
                ->first();
            $data = AccountingExpense::create([

                'price' => $request->price,
                'detail' => $request->detail,
                'cash_in' => $request->cash_in,
                'project_head_subheads_id' => $projectHeadSubhead->id,
                'create_by' => Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }


    public function get_accounts_by_project(Request $request)
    {
        $data_list = ProjectHeadSubhead::with('headAccounting')->where(['project_id' => $request->projectID])->get();
        // dd($data_list);


        return $data_list;
    }
    public function Accountant(Request $request)
    {
        $x['title'] = 'Accountants List';
        $selectedProjectId = getSelectedTown();
        $accountants = User::query()
            // include users in the project OR users with any ledger in the project
            ->where(function ($q) use ($selectedProjectId) {
                $q->where('users.project_id', $selectedProjectId)
                    ->orWhereHas('ledgers.projectHeadSubhead', function ($qq) use ($selectedProjectId) {
                        $qq->where('project_id', $selectedProjectId);
                    });
            })
            // pick columns first (so withCount isn’t wiped)
            ->select([
                'users.id',
                'users.name',
                'users.phone_number',
                'users.email',
                'users.nic_number',
                'users.project_id',
            ])
            // count only ledgers for this project
            ->withCount([
                'ledgers as matching_ledgers_count' => function ($q) use ($selectedProjectId) {
                    $q->whereHas('projectHeadSubhead', function ($e) use ($selectedProjectId) {
                        $e->where('project_id', $selectedProjectId);
                    });
                }
            ])
            ->orderByDesc('matching_ledgers_count')
            ->orderBy('users.name')
            ->get();
        $x['accountants'] = $accountants;
        return view('admin.finance.accountant.index', $x);
    }
    public function AccountantLedgers(User $user, Request $request)
    {
        $projectId = $request->integer('project_id') ?: getSelectedTown();

        $ledgers = Ledger::query()
            ->where('create_by', $user->id) // or ->where('user_id', $user->id) depending on your schema
            ->whereHas('projectHeadSubhead', fn($q) => $q->where('project_id', $projectId))
            ->with(['projectHeadSubhead:id,project_id,head_accounting_id,subhead_accounting_id,plot_id,customer_id'])
            ->orderBy('date', 'asc')
            ->get([
                'id',
                'type',
                'type_id',
                'project_head_subheads_id',
                'reference',
                'amount_in',
                'amount_out',
                'detail',
                'create_by',
                'date',
                'created_at'
            ]);

        return response()->json(['ledgers' => $ledgers]);
    }

    public function get_account(Request $request)
    {

        $action = $request->input('action');
        $selectedProjectId = getSelectedTown();
        try {

            switch ($action) {

                case 'get_head':

                    $data_list = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
                        ->distinct()
                        ->whereHas('headAccounting', function ($query) use ($request) {
                            $query->where('acct_type', $request->acct_type);
                        })
                        ->where(['project_id' => $selectedProjectId])
                        ->with('headAccounting')
                        ->get();

                    $response = $data_list;
                    break;

                case 'get_child':

                    $data_list = ProjectHeadSubhead::with(['subheadAccounting', 'headAccounting'])
                        ->withSum('ledgers as total_in', 'amount_in')
                        ->withSum('ledgers as total_out', 'amount_out')
                        ->where(['head_accounting_id' => $request->accountID])
                        ->where(['project_id' => $selectedProjectId])
                        ->get();
                    $data_list = $data_list->map(function ($item) {
                        $item->balance = ($item->total_in ?? 0) - ($item->total_out ?? 0);
                        return $item;
                    });


                    $response = $data_list;
                    break;
            }
            return $response;
        } catch (Exception $ex) {
            // $response['msg'] = AppRepo::create_error_log("Tag", "get_slide_html_($action)_by_$app_user_id", $ex, $app_user_id);
        }
    }


    public function get_subaccounts_by_project(Request $request)
    {
        $data_list = ProjectHeadSubhead::with('subheadAccounting')->where(['head_accounting_id' => $request->accountID])->where(['project_id' => $request->projectID])->get();
        // dd($data_list);


        return $data_list;
    }


    public function head_index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Main Accounts';
        $x['data'] = HeadAccounting::get();
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['accountTypes'] = AccountType::where('status', 1)->orderBy('id')->get();
        return view('admin.finance.accounting.head_accounts', $x);
    }

    public function head_store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:25'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }


        try {
            $data = HeadAccounting::create([

                'name' => $request->name,
                'is_active' => $request->is_active,
                'acct_type' => $request->acct_type,
                'create_by' => Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function head_show(Request $request)
    {
        $data_list = HeadAccounting::where(['id' => $request->id])->first();


        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }

    public function head_update(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:25'],
            'acct_type' => ['required'], // validate type as well
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            $result = HeadAccounting::findOrFail($request->id);

            $result->update([
                'name' => $request->name,
                'is_active' => $request->is_active,
                'acct_type' => $request->acct_type, // ✅ added this
            ]);

            DB::commit();

            Alert::success('Notification', 'Data <b>' . $result->name . '</b> updated successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Update failed: <b>' . $th->getMessage() . '</b>')->toToast()->toHtml();
        }

        return back();
    }



    public function head_destroy(Request $request)
    {
    }








    public function subhead_index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Party Accounts';
        $x['data'] = SubheadAccounting::get();
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        return view('admin.finance.accounting.child_accounts', $x);
    }


    public function subhead_store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:25'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }


        try {
            $data = SubheadAccounting::create([
                'name' => $request->name,
                'urdu_name' => $request->urdu_name,
                'is_active' => $request->is_active,
                'cnic' => $request->cnic,
                'phone' => $request->phone,
                'create_by' => Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            dd($th);
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }



    public function subhead_show(Request $request)
    {
        $data_list = SubheadAccounting::where(['id' => $request->id])->first();


        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Data Project by id',
            'data' => $data_list
        ], Response::HTTP_OK);
    }




    public function subhead_update(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:25']
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        $data = [
            'name' => $request->name,
            'urdu_name' => $request->urdu_name,
            'cnic' => $request->cnic,
            'phone' => $request->phone,
            'is_active' => $request->is_active,
        ];

        DB::beginTransaction();
        try {
            $result = SubheadAccounting::find($request->id);
            $result->update($data);
            //$result->syncRoles($request->role);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> berhasil disimpan')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> gagal disimpan : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }


    public function subhead_destroy(Request $request)
    {
    }




    public function details_index(Request $request)
    {
        $x['title'] = 'Party Ledger';
        $power = Auth::user()->roles[0]->name;
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $selectedProjectId = getSelectedTown();

        $query = AccountingExpense::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project');

        if (isset($request->accounts_id)) {
            $query->whereHas('projectHeadSubhead.headAccounting', function ($q) use ($request) {
                $q->where('id', $request->accounts_id);
            });
        }

        if (isset($request->subaccounts_id)) {
            $query->whereHas('projectHeadSubhead.subheadAccounting', function ($q) use ($request) {
                $q->where('id', $request->subaccounts_id);
            });
        }

        if (isset($request->projects_id)) {
            $query->whereHas('projectHeadSubhead.project', function ($q) use ($request) {
                $q->where('id', $request->projects_id);
            });
        }

        $x['data'] = $query->where('is_active', 1)->where('cash_in', 0)->get();

        $totalPrice = 0;

        foreach ($x['data'] as $expense) {
            $totalPrice += $expense->price;
        }

        // dd($totalPrice);
        $x['total_price'] = $totalPrice;

        // $projects = Project::get();
        $projects = Project::where('id', $selectedProjectId)->get();

        $headSubHeads = ProjectHeadSubhead::with(['headAccounting', 'subheadAccounting'])
            ->where('project_id', $selectedProjectId)
            ->get();

        $headaccounts = $headSubHeads->pluck('headAccounting')->unique('id')->values();
        $subheadaccounts = $headSubHeads->pluck('subheadAccounting')->unique('id')->values();

        $x['projects'] = $projects;
        $x['headaccounts'] = $headaccounts;
        $x['subheadaccounts'] = $subheadaccounts;

        return view('admin.finance.reports.details_index', $x);
    }




    public function category_index(Request $request)
    {
        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Category Details';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;

        $x['headaccounts'] = HeadAccounting::get();
        $x['subheadaccounts'] = SubheadAccounting::get();
        $x['projects'] = Project::get();

        $selectedProjectId = getSelectedTown();

        $x['categoryMappings'] = DB::table('project_head_subheads')
            ->leftJoin('projects', 'projects.id', '=', 'project_head_subheads.project_id')
            ->leftJoin('head_accountings', 'head_accountings.id', '=', 'project_head_subheads.head_accounting_id')
            ->leftJoin('subhead_accountings', 'subhead_accountings.id', '=', 'project_head_subheads.subhead_accounting_id')
            ->where('projects.id', $selectedProjectId)
            ->select(
                'project_head_subheads.id',
                'projects.project as project_name',
                'head_accountings.name as head_name',
                'subhead_accountings.name as subhead_name'
            )
            ->orderBy('project_head_subheads.id', 'desc')
            ->get();

        return view('admin.finance.accounting.category_index', $x);
    }




    public function category_store(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'accounts_id' => ['required'],
            'subaccounts_id' => ['required'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }

        try {
            // $data = ProjectHeadSubhead::create([

            //     'project_id' => $request->projects_id,
            //     'head_accounting_id' => $request->accounts_id,
            //     'subhead_accounting_id' => $request->subaccounts_id,

            // ]);



            // Assuming you have fetched the instances of Project, HeadAccounting, and SubHeadAccounting
            $selectedProjectId = getSelectedTown();
            $project = Project::find($selectedProjectId);
            $headAccounting = HeadAccounting::find($request->accounts_id);
            $subheadAccounting = SubheadAccounting::find($request->subaccounts_id);
            $exist = ProjectHeadSubhead::where('subhead_accounting_id', $subheadAccounting->id)->where('head_accounting_id', $request->accounts_id)->first();
            if ($exist) {
                return redirect()->back()->with('error', 'Head Account already exists for this Child Account.');
            }
            // Associating a HeadAccounting with a Project and SubHeadAccounting
            $project->headAccountings()->attach($headAccounting, ['subhead_accounting_id' => $subheadAccounting->id]);


            Alert::success('Notification', 'Data <b>' . '' . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }
    public function category_edit($id)
    {
        // Get the record
        $record = DB::table('project_head_subheads as phs')
            ->leftJoin('subhead_accountings as sh', 'sh.id', '=', 'phs.subhead_accounting_id')
            ->select(
                'phs.id',
                'phs.project_id',
                'phs.subhead_accounting_id',
                'phs.head_accounting_id',
                'sh.name as subhead_name'
            )
            ->where('phs.id', $id)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Record not found'
            ]);
        }

        // All head accounts (so user can change to any)
        $allHeads = DB::table('head_accountings')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $record->id,
                'subhead_name' => $record->subhead_name,
                'selected_head_id' => $record->head_accounting_id, // just the id
            ],
            'heads' => $allHeads // array of {id, name}
        ]);
    }


    public function category_update(Request $request, $id)
    {
        $request->validate([
            'head_ids' => 'required|array|min:1'
        ]);

        // Get the base record
        $baseRecord = DB::table('project_head_subheads')->where('id', $id)->first();
        if (!$baseRecord) {
            return response()->json(['status' => 'error', 'message' => 'Record not found']);
        }

        $projectId = $baseRecord->project_id;
        $subheadId = $baseRecord->subhead_accounting_id;
        $newHeadIds = array_unique($request->head_ids); // Remove duplicates if any

        DB::beginTransaction();
        try {
            // Get existing head IDs for this project + subhead
            $existingHeadIds = DB::table('project_head_subheads')
                ->where('project_id', $projectId)
                ->where('subhead_accounting_id', $subheadId)
                ->pluck('head_accounting_id')
                ->toArray();

            // Filter new heads that don't already exist
            $filteredHeads = array_diff($newHeadIds, $existingHeadIds);

            if (empty($filteredHeads)) {
                // If all already exist, just return success (no change)
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'No new head accounts to update.'
                ]);
            }

            // Use first new head to update existing record
            $firstHeadId = array_shift($filteredHeads);

            DB::table('project_head_subheads')
                ->where('id', $id)
                ->update([
                    'head_accounting_id' => $firstHeadId,
                    'updated_at' => now()
                ]);

            // Insert remaining heads (if any)
            foreach ($filteredHeads as $headId) {
                // Double-check again for duplicates (race condition safety)
                $exists = DB::table('project_head_subheads')
                    ->where('project_id', $projectId)
                    ->where('subhead_accounting_id', $subheadId)
                    ->where('head_accounting_id', $headId)
                    ->exists();

                if (!$exists) {
                    DB::table('project_head_subheads')->insert([
                        'project_id' => $projectId,
                        'subhead_accounting_id' => $subheadId,
                        'head_accounting_id' => $headId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Head Accounts updated successfully!'
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating: ' . $e->getMessage()
            ]);
        }
    }

    public function category_delete($id)
    {
        try {
            $mapping = DB::table('project_head_subheads')->where('id', $id)->first();

            if (!$mapping) {
                return response()->json(['status' => 'error', 'message' => 'Record not found']);
            }

            DB::table('project_head_subheads')->where('id', $id)->delete();

            return response()->json(['status' => 'success', 'message' => 'Record deleted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Something went wrong!']);
        }
    }
    public function details_party_ledger(Request $request)
    {
        $projects_id = getSelectedTown();
        // Get ProjectHeadSubhead ID(s)
        $query = ProjectHeadSubhead::where('project_id', $projects_id);

        // Filter by head_accounting_id if provided
        if ($request->filled('accounts_id')) {
            $query->where('head_accounting_id', $request->accounts_id);
        }

        // Filter by subhead_accounting_id if provided
        if ($request->filled('subaccounts_id')) {
            $query->whereIn('subhead_accounting_id', $request->subaccounts_id);
        }

        // Fetch the matching ProjectHeadSubhead records
        $projectHeadSubheads = $query->pluck('id');
        if ($projectHeadSubheads->isEmpty()) {
            return redirect()->back()->with('error', 'No matching records found for the given criteria.');
        }

        $fdate = Carbon::createFromFormat('d-m-Y', $request->fdate)->format('Y-m-d');
        $tdate = Carbon::createFromFormat('d-m-Y', $request->tdate)->format('Y-m-d');

        // Calculate the opening balance
        $openingBalance = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)
            ->whereDate('date', '<', $fdate)
            ->where('is_active', 1)
            ->selectRaw('COALESCE(SUM(amount_in),0) - COALESCE(SUM(amount_out),0) as opening_balance')
            ->value('opening_balance');
        // Fetch ledger details
        $ledgerDetails = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)
            ->whereBetween('date', [$fdate, $tdate])
            ->where('is_active', 1)
            ->get();

        // Fetch party details
        $partyDetails = ProjectHeadSubhead::whereIn('id', $projectHeadSubheads)
            ->with(['project', 'headAccounting', 'subheadAccounting'])
            ->get()
            ->map(function ($item) {
                return [
                    'project_name' => $item->project->project ?? '',
                    'head_accounting' => $item->headAccounting->name ?? '',
                    'subhead_accounting' => $item->subheadAccounting->name ?? '',
                    'customer_id' => $item->customer_id ?? '',
                ];
            });

        // Get project name for the header
        $projectName = Project::find($projects_id)->project ?? 'N/A';
        // Return the Blade view with data
        return view('admin.finance.reports.party_ledger_report', [
            'reportTitle' => "Ledger Report",
            'fdate' => $request->fdate,
            'tdate' => $request->tdate,
            'projectName' => $projectName,
            'opening_balance' => $openingBalance ?? 0,
            'ledger_details' => $ledgerDetails,
            'party_details' => $partyDetails,
        ]);
    }
}
