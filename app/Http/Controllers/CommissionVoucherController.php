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
    public function index(Request $request)
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'General Commission Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CV';
        $x['class'] = 'Commission voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // Keep pagination counts independent of skip/take; restrict every
        // query to the currently selected project.
        $baseQuery = CommisionVoucher::query()->where('project_id', $selectedProjectId);
        $query = clone $baseQuery;
        if ($request->filled('filter_voucher_number')) {
            $query->where('voucher_number', 'like', '%' . $request->input('filter_voucher_number') . '%');
        }
        if ($request->filled('filter_reference')) {
            $query->where('reference', 'like', '%' . $request->input('filter_reference') . '%');
        }
        if ($request->filled('filter_amount')) {
            $query->where('total_debit', '>=', $request->input('filter_amount'));
        }
        if ($request->filled('filter_date')) {
            $query->whereDate('date', $request->input('filter_date'));
        }

        if ($request->ajax()) {
            $total = (clone $baseQuery)->count();
            $filtered = (clone $query)->count();
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 10)));
            $vouchers = $query->orderByDesc('id')->skip($start)->take($length)->get();
            $data = $vouchers->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => 'CV-' . get_jv_number($voucher->voucher_number),
                    'reference' => $voucher->reference,
                    'date' => $voucher->date,
                    'description' => $voucher->description,
                    'total_debit' => $voucher->total_debit,
                    'actions' => view('admin.reports.vouchers.actions', compact('voucher'))->render(),
                ];
            });
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data,
            ]);
        }

        // For non-AJAX requests, return the view
        $x['vouchers'] = $query->get(); // Fetch all vouchers initially

        return view('admin.reports.vouchers.commision_index', $x);
    }


    public function create()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Create Commision Journal Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'CV';
        $x['class'] = 'Commision voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();


        $projects = Project::where('id', $selectedProjectId)->get();
        $x['projects'] = $projects;

        $data_list = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();

        // dd($data_list[0]->headAccounting->name);

        $x['accounts'] = $data_list;


        return view('admin.reports.vouchers.commision_voucher', $x);
    }

    // Legacy CV posting has no trustworthy source-typed ledger relationship.
    // No dead write path is retained behind these deliberate safety gates.
    public function store(Request $request)
    {
        abort(409, 'Commission voucher creation is temporarily disabled to protect accounting history.');
    }

    public function destroy($id)
    {
        abort(409, 'Commission voucher deletion is temporarily disabled to protect accounting history until reversal posting is implemented.');
    }

    public function edit($id)
    {
        $voucher = CommisionVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail($id);
        $x = $this->getCommonData('Edit Commision Voucher');
        $x['voucher'] = $voucher;

        // Get selected town's project_id
        $x['selectedProjectId'] = $selectedProjectId = getSelectedTown();

        // Fetch projects and accounts
        $x['projects'] = Project::where('id', $selectedProjectId)->get();
        $x['accounts'] = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where('project_id', $selectedProjectId)
            ->get();

        return view('admin.reports.vouchers.edit_commision_voucher', $x);
    }

    public function update(Request $request, $id)
    {
        abort(409, 'Commission voucher updates are temporarily disabled to protect accounting history until reversal posting is implemented.');
    }

    public function print($id)
    {
        $voucher = CommisionVoucher::with('details.account.headAccounting', 'details.account.subheadAccounting')->where('project_id', getSelectedTown())->findOrFail($id);
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
