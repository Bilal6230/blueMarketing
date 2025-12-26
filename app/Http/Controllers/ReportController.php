<?php

namespace App\Http\Controllers;

use App\Models\CustomerLedger;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\User;
use App\Models\Work;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Repository\Lead\LeadRepository as lead_repo;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\SubheadAccounting;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {

        $power = Auth::user()->roles[0]->name;
        $userID = null;
        $status = true;
        $filter = null;
        if (isset($request->user)) {
            $userID = $request->user;
        } elseif (isset($request->filter) && $request->filter != "all") {
            $userID = null;
        } else {
            $userID = Auth::user()->id;
        }
        if (isset($request->filter)) {
            $filter = $request->filter;
        }


        $x['title'] = 'Lead SetUp';
        $x['data'] = lead_repo::getLeadsList($userID, $filter, $status);
        $x['role'] = Role::get();
        $x['users'] = User::where('status_id', 1)->get(); //User::get();
        $x['power'] = $power;
        $x['filter']['type'] = $filter;
        $x['filter']['user'] = $userID;
        $projects = Project::get();
        $x['projects'] = $projects;


        return view('admin.reports.admin_lead', $x);
    }

    public function users_report(Request $request)
    {
        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Agent Report';
        $x['data'] = lead_repo::getLeadsList(Auth::user()->id, $request->filter);
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        dd($x['users']);
        return view('admin.reports.agent_report', $x);
    }

    public function check_report(Request $request)
    {
        $power = Auth::user()->roles[0]->name;

        $x['title'] = 'Bankers Check Report';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'PPR';
        $x['class'] = 'cash-in';
        $x['bg_voucher'] = 'info-cash-in';
        $old_bank_id = $old_fdate = $old_tdate = null;
        $old_passing_status = 66;

        $query = CustomerLedger::with('customer_list', 'plot_list', 'ledger', 'ledger.projectHeadSubhead.subheadAccounting', 'ledger.projectHeadSubhead.headAccounting')
            ->whereIn('payment_type', [3, 2])
            ->where('is_active', '1')
            ->where('project_id', getSelectedTown())
            ->filterByCustomerId($request->input('customer_id'))
            ->filterByPlotId($request->input('plot_id'))
            ->filterByPassingStatus($request->input('passing_status'))
            ->filterByBankId($request->input('bank_id'))
            ->filterByDateRange($request->input('fdate'), $request->input('tdate'));

        $selectedProjectId = getSelectedTown();

        // Execute the query
        $data = $query->get();

        // Calculate the sum of amount_out
        $totalAmountOut = $data->sum('amount_out');

        $data_list = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();
        $sub_data_list = ProjectHeadSubhead::select('project_id', 'subhead_accounting_id')
            ->distinct()
            ->with('subheadAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();


        $x['data'] = $data;
        $x['old_bank_id'] = $old_bank_id;
        $x['old_fdate'] = $old_fdate;
        $x['old_tdate'] = $old_tdate;
        $x['old_passing_status'] = $old_passing_status;
        $x['head_account_list'] = $data_list;
        $x['subhead_account_list'] = $sub_data_list;
        $x['totalAmountOut'] = $totalAmountOut;  // Add the sum value to the array



        return view('admin.reports.vouchers.check_report', $x);
    }


    public function bank_posting_check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'passing_status' => 'required|numeric',
            'note' => 'required|string',
            'id' => 'required|exists:customer_ledger,id',
            'accounts_id' => 'required',
            'subaccounts_id' => 'required',
        ], [
            'passing_status.required' => 'The passing status field is required.',
            'note.required' => 'The note field is required.',
            'id.required' => 'The ID field is required.',
            'accounts_id.required' => 'Bank Account head must be selected.',
            'subaccounts_id.required' => 'Bank Account must be selected',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $lastId = getLastLedgerIdByType("BR");
            $selectedProjectId = getSelectedTown();

            // Ensure credit account exists
            $creditAccountId = ProjectHeadSubhead::where('head_accounting_id', $request->accounts_id)
                ->where('project_id', $selectedProjectId)
                ->where('subhead_accounting_id', $request->subaccounts_id)
                ->value('id');

            if (!$creditAccountId) {
                throw new \Exception('Credit account ID not found.');
            }

            // Find the CustomerLedger record
            $result = CustomerLedger::find($request->id);
            $amount = $result->amount_out;
            $check_slip = $result->transaction_type . '-' . $result->reference;

            // Get the bank name
            $bankName = getBankNameById($result->bank_id);

            // Update the CustomerLedger record
            $result->update([
                'passing_status' => $request->passing_status,
                'note' => '(' . $check_slip . ') ' . $request->note,
                'bank_post_at' => $request->bank_post_at,
            ]);

            // Add to check history
            $result->addCheckHistory([
                'id' => $request->id,
                'check_number' => $result->t_number,
                'passing_date' => $request->bank_post_at,
                'passing_status' => $request->passing_status,
                'description_note' => '(' . $check_slip . ') ' . $request->note,
                'bank_name' => $bankName, // Add bank name to history
                'credit_account_id' => $creditAccountId, // Add credit account ID to history
            ]);

            // Create Ledger entry only if passing_status == 1
            if ($request->passing_status == 1) {
                $voucherNumber = getVocuherNumber('BR');
                Ledger::create([
                    'customer_ledger_id' => $request->id,
                    'type' => 'BR',
                    'voucher' => $voucherNumber,
                    'type_id' => $lastId + 2,
                    'project_head_subheads_id' => $creditAccountId,
                    'reference' => "Check Pass in Bank",
                    'amount_in' => 0.00, // Adjust this field as per your data
                    'amount_out' => $amount,
                    'is_active' => 1,
                    'date' => $request->bank_post_at, // Adjust this field as per your data
                    'detail' => '(' . $check_slip . ') ' . $request->note,
                    'update_by' => Auth::user()->id,
                    'create_by' => Auth::user()->id,
                    'status' => 0,
                ]);
            }


            DB::commit();
            Alert::success('Notification', 'Data updated successfully.')->toToast()->toHtml();
        } catch (\Throwable $th) {
            DB::rollback();
            Alert::error('Notification', 'An error occurred while updating the record.')->toToast()->toHtml();
        }

        return back();
    }

    public function checkHistory($id)
    {
        $ledger = CustomerLedger::with(['customer_list', 'plot_list'])->findOrFail($id);
        $title = 'Customer Check Report';

        // Retrieve check history from the model
        $checkHistory = $ledger->check_history;

        return view('admin.reports.bookings.check_history', compact('ledger', 'checkHistory', 'title'));
    }




}
