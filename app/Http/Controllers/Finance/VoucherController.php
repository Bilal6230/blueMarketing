<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
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


        $projects = Project::where('id', $selectedProjectId )->get();
        $x['projects'] = $projects;
   
        return view('admin.finance.voucher.cash_voucher', $x);
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
                        
        $projects = Project::where('id', $selectedProjectId )->get();
        $x['projects'] = $projects;
   
        return view('admin.finance.voucher.cash_voucher', $x);
    }

    
}
