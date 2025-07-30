<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HeadAccounting;
use App\Models\ProjectHeadSubhead;
use App\Models\SubheadAccounting;
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
use Yajra\DataTables\Facades\DataTables;


class LedgerController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required'],
            'detail' => ['required'],
            'accounts_id' => ['required'],
            'subaccounts_id' => ['required'],
            'projects_id' => ['required'],
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors($validator)
            ->withInput();
        }
        $cleanAmount = str_replace(',', '', $request->amount);
        $voucherValue = $request->input('voucher');
        $firstTwoDigits = substr($voucherValue, 0, 2);
        $amount_in = $amount_out = 0;
        if ($firstTwoDigits === 'CR') {
            $amount_in = $cleanAmount;
        } elseif($firstTwoDigits === 'CP') {
            $amount_out = $cleanAmount;
        }

        try {

            $lastId = getLastLedgerIdByType($firstTwoDigits);
            

            $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
            ->where('subhead_accounting_id', $request->subaccounts_id)
            ->where('project_id', $request->projects_id)
            ->first();
            $data = Ledger::create([

                'type' => $firstTwoDigits,
                'type_id' => $lastId+1,
                'project_head_subheads_id' => $projectHeadSubhead->id,
                'reference' => $request->reference,
                'amount_in' => $amount_in,
                'amount_out' => $amount_out,
                'is_active' => 1,
                'date' => $request->date,
                'detail' => $request->detail,
                'update_by' =>Auth::user()->id,
                'create_by' =>Auth::user()->id,

                'status' => 0,

            ]);
            $lastSubmitDate = $request->date; // Adjust this according to your actual form field
            session(['last_submit_date' => $lastSubmitDate]);


            Alert::success('Notification', 'Data <b>' . $data->project . '</b> Save successfully ')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' .  $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function getSubaccountDetails(Request $request)
    {
        $subaccountId = $request->input('subaccounts_id');

        $subaccount = SubheadAccounting::find($subaccountId);

        return response()->json([
            'cnic' => $subaccount->cnic,
            'phone' => $subaccount->phone,
        ]);
    }

    public function show(Request $request)
    {
        $data_list = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')->where(['id' => $request->id])->first();
        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data Project by id',
            'data'      => $data_list
        ], Response::HTTP_OK);
    }

    public function show_ledger()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Cash Book';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CR';
        $x['class']      =   'cash-in';

        $selectedProjectId = getSelectedTown();

        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
        ->where('is_active', 1) // Add this condition to filter by is_active
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


        $projects = Project::where('id', $selectedProjectId )->get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.show_ledger', $x);
    }

    public function destroy(Request $request)
    {
        $data = [
            'is_active' => "0",
        ];
        DB::beginTransaction();
        try {
            $result = Ledger::find($request->id);
            $result->update($data);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> Deleted')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> failed to delete: ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function update(Request $request)
    {
        $rules = [
            'amount' => ['required'],
            'detail' => ['required'],
            'accounts_id' => ['required'],
            'subaccounts_id' => ['required'],
            'projects_id' => ['required'],

        ];

        
        
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)
            ->withInput();
        }

        $cleanAmount = str_replace(',', '', $request->amount);
        $voucherValue = $request->input('voucher');
        $firstTwoDigits = substr($voucherValue, 0, 2);
        $amount_in = $amount_out = 0;
        if ($firstTwoDigits === 'CR') {
            $amount_in = $cleanAmount;
        } elseif($firstTwoDigits === 'CP') {
            $amount_out = $cleanAmount;
        }

        $projectHeadSubhead = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
            ->where('subhead_accounting_id', $request->subaccounts_id)
            ->where('project_id', $request->projects_id)
            ->first();

        $data = [
            'project_head_subheads_id' => $projectHeadSubhead->id,
            'reference' => $request->reference,
            'amount_in' => $amount_in,
            'amount_out' => $amount_out,
            'is_active' => 1,
            'date' => $request->date,
            'detail' => $request->detail,
            'update_by' =>Auth::user()->id,

        ];

        DB::beginTransaction();
        try {
            $result = Ledger::find($request->id);
            $result->update($data);
            //$result->syncRoles($request->role);
            DB::commit();
            Alert::success('Notification', 'Data <b>' . $result->name . '</b> berhasil disimpan')->toToast()->toHtml();
        } catch (\Throwable $th) {
            // dd($th);
            DB::rollback();
            Alert::error('Notification', 'Data <b>' . $result->name . '</b> gagal disimpan : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function fetch_data_url(Request $request)
    {
        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        $data = Ledger::with('projectHeadSubhead.project','projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')->where('is_active',1)->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
            $query->where('project_id', $selectedProjectId);
        });

        return DataTables::eloquent($data)
            ->addColumn('project_name', function ($ledger) {
                // Access the project name from the relationship
                return $ledger->projectHeadSubhead->project->project ?? '';
            })
            ->addColumn('head_account_name', function ($ledger) {
                // Access the head account name from the relationship
                return $ledger->projectHeadSubhead->headAccounting->name ?? '';
            })
            ->addColumn('subhead_account_name', function ($ledger) {
                // Access the sub account name from the relationship
                return $ledger->projectHeadSubhead->subheadAccounting->name ?? '';
            })
            ->addColumn('action', function ($ledger) {
                // Add any additional columns or custom data here
                return '<button class="btn btn-info">Edit</button>';
            })
            ->toJson();
    }

    public function show_ledger_party()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Party wise ledger';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CR';
        $x['class']      =   'cash-in';
        

        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
        ->where('is_active', 1) // Add this condition to filter by is_active
        ->where('type', 'CR')
        ->get();
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.show_ledger_party', $x);
    }

    public function show_ledger_head()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Head wise ledger';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CR';
        $x['class']      =   'cash-in';
        

        $data = Ledger::with('projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting', 'projectHeadSubhead.project')
        ->where('is_active', 1) // Add this condition to filter by is_active
        ->where('type', 'CR')
        ->get();
        $data = $data->map(function ($item) {
            $item['amount'] = floatval($item['amount_in']);
            return $item;
        });
        $x['data'] = $data;


        $projects = Project::get();
        $x['projects'] = $projects;

        return view('admin.finance.reports.head_ledger', $x);
    }

    public function fetch_data_by_party(Request $request)
    {
        $selectedProjectId = getSelectedTown();
        $data = Ledger::with('projectHeadSubhead.project', 'projectHeadSubhead.headAccounting', 'projectHeadSubhead.subheadAccounting')
            ->where('is_active', 1)
            ->whereHas('projectHeadSubhead', function ($query) use ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            })
            ->selectRaw('project_head_subheads_id, sum(amount_in) as total_amount_in, sum(amount_out) as total_amount_out')
            ->groupBy('project_head_subheads_id');

        return DataTables::eloquent($data)
            ->addColumn('project_name', function ($ledger) {
                // Access the project name from the relationship
                return $ledger->projectHeadSubhead->project->project ?? '';
            })
            ->addColumn('head_account_name', function ($ledger) {
                // Access the head account name from the relationship
                return $ledger->projectHeadSubhead->headAccounting->name ?? '';
            })
            ->addColumn('subhead_account_name', function ($ledger) {
                // Access the sub account name from the relationship
                return $ledger->projectHeadSubhead->subheadAccounting->name ?? '';
            })
            ->addColumn('total_amount_in', function ($ledger) {
                // Access the total amount in for the project_head_subheads_id
                return $ledger->total_amount_in ?? '';
            })
            ->addColumn('total_amount_out', function ($ledger) {
                // Access the total amount out for the project_head_subheads_id
                return $ledger->total_amount_out ?? '';
            })
            ->addColumn('balance_amount', function ($ledger) {
                // Calculate the balance by subtracting total_amount_out from total_amount_in
                return ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0);
            })
            ->toJson();
    }

    public function fetch_data_by_head(Request $request)
{
    $selectedProjectId = getSelectedTown();

    $data = Ledger::query()
        ->join('project_head_subheads', 'ledgers.project_head_subheads_id', '=', 'project_head_subheads.id')
        ->join('head_accountings', 'project_head_subheads.head_accounting_id', '=', 'head_accountings.id')
        ->where('ledgers.is_active', 1)
        ->where('project_head_subheads.project_id', $selectedProjectId)
        ->selectRaw('
            project_head_subheads.head_accounting_id, 
            head_accountings.name as head_account_name, 
            SUM(ledgers.amount_in) as total_amount_in, 
            SUM(ledgers.amount_out) as total_amount_out
        ')
        ->groupBy('project_head_subheads.head_accounting_id', 'head_accountings.name'); // Ensure name is grouped too

    return DataTables::of($data)
        ->addColumn('total_amount_in', function ($ledger) {
            return $ledger->total_amount_in ?? '';
        })
        ->addColumn('total_amount_out', function ($ledger) {
            return $ledger->total_amount_out ?? '';
        })
        ->addColumn('balance_amount', function ($ledger) {
            return ($ledger->total_amount_in ?? 0) - ($ledger->total_amount_out ?? 0);
        })
        ->toJson();
}



}
