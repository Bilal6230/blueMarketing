<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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


class AccountingController extends Controller
{


    public function index(Request $request)
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Accounts List';
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
        
        if(isset($request->cash_in)){
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
                'create_by' =>Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }


    public function get_accounts_by_project(Request $request)
    {
        $data_list = ProjectHeadSubhead::with('headAccounting')->where(['project_id' => $request->projectID])->get();
        // dd($data_list);


        return $data_list;
    }

    public function get_account(Request $request) {

        $action = $request->input('action');

        try {

            switch ($action) {

                case 'get_head':

                    $data_list = ProjectHeadSubhead::select('project_id','head_accounting_id')
                        ->distinct()
                        ->with('headAccounting')
                        ->where(['project_id' => $request->projectID])
                        ->get();

                    $response = $data_list;
                break;

                case 'get_child':

                    $data_list = ProjectHeadSubhead::with('subheadAccounting')
                        ->where(['head_accounting_id' => $request->accountID])
                        ->where(['project_id' => $request->projectID])
                        ->get();


                    $response = $data_list;
                break;
            }
            return $response;

        } catch (Exception $ex) {
            dd("asdas");
            //$response['msg'] = AppRepo::create_error_log("Tag", "get_slide_html_($action)_by_$app_user_id", $ex, $app_user_id);
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

        $x['title']     = 'Main Accounts';
        $x['data']      = HeadAccounting::get();
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        return view('admin.finance.accounting.head_accounts', $x);
    }


    public function head_store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:25'],
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
                'create_by' =>Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }


    public function head_show(Request $request)
    {
        $data_list = HeadAccounting::where(['id' => $request->id])->first();


        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Project by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }




    public function head_update(Request $request)
    {
        $rules = [
            'name'      => ['required', 'string', 'max:25']
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)
            ->withInput();
        }
        $data = [
            'name' => $request->name,
            'is_active' => $request->is_active,
        ];

        DB::beginTransaction();
        try {
            $result = HeadAccounting::find($request->id);
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


    public function head_destroy(Request $request)
    {
        dd("asasas");
    }








    public function subhead_index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;

        $x['title']     = 'Child Accounts';
        $x['data']      = SubheadAccounting::get();
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        return view('admin.finance.accounting.child_accounts', $x);
    }


    public function subhead_store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:25'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }


        try {
            $data = SubheadAccounting::create([

                'name' => $request->name,
                'is_active' => $request->is_active,
                'cnic' => $request->cnic,
                'phone' => $request->phone,
                'create_by' =>Auth::user()->id

            ]);

            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }



    public function subhead_show(Request $request)
    {
        $data_list = SubheadAccounting::where(['id' => $request->id])->first();


        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Project by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }




    public function subhead_update(Request $request)
    {
        $rules = [
            'name'      => ['required', 'string', 'max:25']
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)
            ->withInput();
        }
        $data = [
            'name' => $request->name,
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
        dd("asasas");
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

        if(isset($request->accounts_id)){
            $query->whereHas('projectHeadSubhead.headAccounting', function($q) use ($request) {
                $q->where('id', $request->accounts_id);
            });
        }

        if(isset($request->subaccounts_id)){
            $query->whereHas('projectHeadSubhead.subheadAccounting', function($q) use ($request) {
                $q->where('id', $request->subaccounts_id);
            });
        }

        if(isset($request->projects_id)){
            $query->whereHas('projectHeadSubhead.project', function($q) use ($request) {
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
        $projects = Project::where('id', $selectedProjectId )->get();
        // $headaccounts = HeadAccounting::get();
        // $subheadaccounts = SubheadAccounting::get();

        $x['projects'] = $projects;
        // $x['headaccounts'] = $headaccounts;
        // $x['subheadaccounts'] = $subheadaccounts;

        return view('admin.finance.reports.details_index', $x);
    }




    public function category_index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Category Details';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $headaccounts = HeadAccounting::get();
        $subheadaccounts = SubheadAccounting::get();
        $projects = Project::get();

        $x['headaccounts'] = $headaccounts;
        $x['subheadaccounts'] = $subheadaccounts;
        $x['projects'] = $projects;

        return view('admin.finance.accounting.category_index', $x);
    }
    public function category_store(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'projects_id'      => ['required'],
            'accounts_id'      => ['required'],
            'subaccounts_id'      => ['required'],

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
            $project = Project::find($request->projects_id);
            $headAccounting = HeadAccounting::find($request->accounts_id);
            $subheadAccounting = SubheadAccounting::find($request->subaccounts_id);

            // Associating a HeadAccounting with a Project and SubHeadAccounting
            $project->headAccountings()->attach($headAccounting, ['subhead_accounting_id' => $subheadAccounting->id]);


            Alert::success('Notification', 'Data <b>' . '' . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function details_party_ledger(Request $request)
    {
        // Validate the request
        $request->validate([
            'fdate' => 'required|date',
            'tdate' => 'required|date',
            'projects_id' => 'required|integer|exists:projects,id',
            'accounts_id' => 'nullable|integer|exists:head_accountings,id',
            'subaccounts_id' => 'nullable|array',
            'subaccounts_id.*' => 'integer|exists:subhead_accountings,id',
        ]);

        // Get ProjectHeadSubhead ID(s)
        $query = ProjectHeadSubhead::where('project_id', $request->projects_id);

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

        // Calculate the opening balance
        $openingBalance = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)
            ->where('date', '<', $request->fdate)->where('is_active',1)
            ->selectRaw('SUM(amount_in) - SUM(amount_out) as opening_balance')
            ->value('opening_balance');

        // Fetch ledger details
        $ledgerDetails = Ledger::whereIn('project_head_subheads_id', $projectHeadSubheads)
            ->whereBetween('date', [$request->fdate, $request->tdate])->where('is_active',1)
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
        $projectName = Project::find($request->projects_id)->project ?? 'N/A';
        // Return the Blade view with data
        return view('admin.finance.reports.party_ledger_report', [
            'reportTitle'=> "Ledger Report",
            'fdate' => $request->fdate,
            'tdate' => $request->tdate,
            'projectName' => $projectName,
            'opening_balance' => $openingBalance ?? 0,
            'ledger_details' => $ledgerDetails,
            'party_details' => $partyDetails,
        ]);
    }






}
