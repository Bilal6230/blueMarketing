<?php

namespace App\Http\Controllers;

use App\Models\CommisionVoucher;
use App\Models\CommisionVoucherDetail;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class CommissionVoucherController extends Controller
{
    public function index()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'General Commision Voucher';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CV';
        $x['class']      =   'Commision voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        $x['vouchers']  = CommisionVoucher::latest()->where('project_id',$selectedProjectId)->get();
        return view('admin.reports.vouchers.commision_index', $x);
    }

    public function create()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title']     = 'Create Commision Journal Voucher';
        $x['role']      = Role::get();
        $x['users']      = User::get();
        $x['power']     = $power;
        $x['type']      =   'CV';
        $x['class']      =   'Commision voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();


        $projects = Project::where('id', $selectedProjectId )->get();
        $x['projects'] = $projects;

        $data_list = ProjectHeadSubhead::select('project_id','head_accounting_id')
                        ->distinct()
                        ->with('headAccounting')
                        ->where(['project_id' => $selectedProjectId])
                        ->get();

                        // dd($data_list[0]->headAccounting->name);

        $x['accounts'] = $data_list;


        return view('admin.reports.vouchers.commision_voucher', $x );
    }

    public function store(Request $request)
    {
        $lastVoucherId = getLastJvId();

        $request->validate([
            'voucher_number' => 'required|string',
            'reference' => 'nullable|string',
            'date' => 'required|date',
            'description' => 'required|string',
            'accounts' => 'required|array|min:1',
            'sub_accounts' => 'required|array|min:1',
            'line_description' => 'nullable|array',
            'debit' => 'required|array|min:1',
            'credit' => 'required|array|min:1',
        ]);

        $totalDebit = array_sum($request->debit);
        $totalCredit = array_sum($request->credit);

        if ($totalDebit !== $totalCredit) {
            return redirect()->back()->withErrors(['Total debit and credit must be equal.']);
        }
        $selectedProjectId = getSelectedTown();

        DB::beginTransaction();
        try {
            // Create Journal Voucher Record
            $CommisionVoucher = CommisionVoucher::create([
                'voucher_number' => $lastVoucherId+1,
                'reference' => $request->reference,
                'date' => $request->date,
                'description' => $request->description,
                'total_debit' => $totalDebit, // Add total debit
                'total_credit' => $totalCredit, // Add total credit
                'created_by' => auth()->id(),
                'project_id' => $selectedProjectId,
            ]);


            // Add Ledger Entries
            foreach ($request->accounts as $index => $accountId) {

                $JvDetails = CommisionVoucherDetail::create([
                    'journal_voucher_id' => $CommisionVoucher->id,
                    'account_id' => $request->sub_accounts[$index],
                    'debit' => $request->debit[$index] ?? 0,
                    'credit' => $request->credit[$index] ?? 0,
                    'description' => $request->line_description[$index] ?? '',
                    'created_by' => auth()->id(),
                ]);

                $ledgerData = Ledger::create([
                    'type' => ($request->debit[$index] ?? 0) > 0 ? 'JV' : 'JV',
                    'type_id' => $CommisionVoucher->id,
                    'project_head_subheads_id' => $request->sub_accounts[$index],
                    'reference' => $request->reference,
                    'amount_in' => $request->debit[$index] ?? 0,
                    'amount_out' => $request->credit[$index] ?? 0,
                    'detail' => $request->line_description[$index] ?? '',
                    'create_by' => auth()->id(),
                    'is_active' => true,
                    'status' => '0',
                    'date' => $request->date,
                ]);
            }

            DB::commit();
            return redirect()->route('journal.voucher.index')->with('success', 'Journal Voucher created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // public function edit($id)
    // {
    //     $voucher = CommisionVoucher::with('details')->findOrFail($id);
    //     $x['voucher'] = $voucher;
    //     $power = Auth::user()->roles[0]->name;
    //     $x['title']     = 'Edit General Journal Voucher';
    //     $x['role']      = Role::get();
    //     $x['users']      = User::get();
    //     $x['power']     = $power;
    //     $x['type']      =   'JV';

    //     // Get selected town's project_id
    //     $selectedProjectId = getSelectedTown();

    //     $projects = Project::where('id', $selectedProjectId )->get();
    //     $x['projects'] = $projects;

    //     $Accounts = ProjectHeadSubhead::select('project_id','head_accounting_id')
    //                     ->distinct()
    //                     ->with('headAccounting')
    //                     ->where(['project_id' => $selectedProjectId])
    //                     ->get();

    //     $x['accounts'] = $Accounts;

    //     return view('admin.reports.vouchers.edit_journal_voucher', $x );


    // }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            // Delete Journal Voucher and related details
            $CommisionVoucher = CommisionVoucher::findOrFail($id);
            CommisionVoucherDetail::where('journal_voucher_id', $id)->delete();
            Ledger::where('type_id', $id)->whereIn('type', ['JV', 'JV'])->delete();
            $CommisionVoucher->delete();

            DB::commit();
            return redirect()->route('journal.voucher.index')->with('success', 'Journal Voucher deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $voucher = CommisionVoucher::with('details')->findOrFail($id);
        $x = $this->getCommonData('Edit Journal Voucher');
        $x['voucher'] = $voucher;

        // Get selected town's project_id
        $x['selectedProjectId'] =$selectedProjectId = getSelectedTown();

        // Fetch projects and accounts
        $x['projects'] = Project::where('id', $selectedProjectId)->get();
        $x['accounts'] = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where('project_id', $selectedProjectId)
            ->get();

        return view('admin.reports.vouchers.edit_journal_voucher', $x);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'voucher_number' => 'required|string',
            'reference' => 'nullable|string',
            'date' => 'required|date',
            'description' => 'required|string',
            'accounts' => 'required|array|min:1',
            'sub_accounts' => 'required|array|min:1',
            'line_description' => 'nullable|array',
            'debit' => 'required|array|min:1',
            'credit' => 'required|array|min:1',
        ]);

        $totalDebit = array_sum($request->debit);
        $totalCredit = array_sum($request->credit);

        if ($totalDebit !== $totalCredit) {
            return redirect()->back()->withErrors(['Total debit and credit must be equal.']);
        }

        DB::beginTransaction();
        try {
            // Update Journal Voucher Record
            $CommisionVoucher = CommisionVoucher::findOrFail($id);
            $CommisionVoucher->update([
                'voucher_number' => $request->voucher_number,
                'reference' => $request->reference,
                'date' => $request->date,
                'description' => $request->description,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'updated_by' => auth()->id(),
            ]);

            // Delete existing details and ledger entries
            CommisionVoucherDetail::where('journal_voucher_id', $id)->delete();
            Ledger::where('type_id', $id)->whereIn('type', ['JV', 'JV'])->delete();

            // Add updated Ledger Entries
            foreach ($request->accounts as $index => $accountId) {
                $JvDetails = CommisionVoucherDetail::create([
                    'journal_voucher_id' => $CommisionVoucher->id,
                    'account_id' => $request->sub_accounts[$index],
                    'debit' => $request->debit[$index] ?? 0,
                    'credit' => $request->credit[$index] ?? 0,
                    'description' => $request->line_description[$index] ?? '',
                    'created_by' => auth()->id(),
                ]);

                Ledger::create([
                    'type' => ($request->debit[$index] ?? 0) > 0 ? 'JV' : 'JV',
                    'type_id' => $JvDetails->id,
                    'project_head_subheads_id' => $request->sub_accounts[$index],
                    'reference' => $request->reference,
                    'amount_in' => $request->debit[$index] ?? 0,
                    'amount_out' => $request->credit[$index] ?? 0,
                    'detail' => $request->line_description[$index] ?? '',
                    'create_by' => auth()->id(),
                    'is_active' => true,
                    'status' => '0',
                    'date' => $request->date,
                ]);
            }

            DB::commit();
            return redirect()->route('journal.voucher.index')->with('success', 'Journal Voucher updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function print($id)
    {
        $voucher = CommisionVoucher::with('details.account.headAccounting', 'details.account.subheadAccounting')->findOrFail($id);
        return view('admin.reports.vouchers.print_journal_voucher', compact('voucher'));
    }

    private function getCommonData($title, $type = 'JV')
    {
        $power = Auth::user()->roles[0]->name;
        return [
            'title' => $title,
            'role' => Role::get(),
            'users' => User::get(),
            'power' => $power,
            'type' => $type,
            'class' => 'Journal voucher',
            'bg_voucher' => 'info-cash-in',
        ];
    }




}
