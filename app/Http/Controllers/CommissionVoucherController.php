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
        // Original browser form may write ONLY in the explicitly isolated
        // local V2 pilot. Never enable this against an existing V1 database.
        \App\Services\Finance\V2IsolatedConnectionGuard::assertAllowed();
        abort_unless(
            in_array(config('app.env'), ['local', 'testing'], true)
                && config('finance.v2_pilot_api_enabled')
                && config('finance.v2_pilot_write_enabled'),
            409, 'Commission voucher creation is temporarily disabled to protect accounting history.'
        );

        $data = $request->validate([
            'voucher_number' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date_format:Y-m-d'],
            'accounts' => ['required', 'array', 'min:2', 'max:100'],
            'accounts.*' => ['required', 'integer', 'min:1'],
            'sub_accounts' => ['required', 'array', 'min:2', 'max:100'],
            'sub_accounts.*' => ['required', 'integer', 'min:1'],
            'debit' => ['required', 'array', 'min:2', 'max:100'],
            'debit.*' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D'],
            'credit' => ['required', 'array', 'min:2', 'max:100'],
            'credit.*' => ['required', 'regex:/^(0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D'],
            'line_description' => ['sometimes', 'array'],
            'line_description.*' => ['nullable', 'string', 'max:1000'],
        ]);
        $keys = array_keys($data['accounts']);
        foreach (['sub_accounts', 'debit', 'credit'] as $field) {
            if (array_keys($data[$field]) !== $keys) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => 'Commission voucher line keys must match.',
                ]);
            }
        }
        $projectId = (int) getSelectedTown();
        if ($projectId < 1) {
            abort(403, 'Select a project before creating a commission voucher.');
        }
        $accounts = ProjectHeadSubhead::query()
            ->where('project_id', $projectId)
            ->whereIn('id', $data['sub_accounts'])
            ->get(['id', 'head_accounting_id'])->keyBy('id');
        $lines = [];
        foreach ($keys as $index) {
            $sub = (int) $data['sub_accounts'][$index];
            if (!$accounts->has($sub)
                || (int) $accounts->get($sub)->head_accounting_id !== (int) $data['accounts'][$index]) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sub_accounts' => 'Account must match the selected project and head.',
                ]);
            }
            $lines[] = [
                'account_id' => $sub,
                'debit' => (string) $data['debit'][$index],
                'credit' => (string) $data['credit'][$index],
                'description' => $data['line_description'][$index] ?? null,
            ];
        }
        try {
            $id = (new \App\Services\Finance\V2CommissionDraftWorkflow())
                ->createDraft($projectId, $data['voucher_number'], $data['date'],
                    $lines, (int) Auth::id());
        } catch (\DomainException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'voucher_number' => 'Commission draft rejected: ' . $e->getMessage(),
            ]);
        }
        return redirect()->route('commision.voucher.edit', ['id' => $id])
            ->with('success', 'Commission draft saved. Approval/posting is still required.');
    }

    public function destroy($id)
    {
        abort(409, 'Commission voucher deletion is temporarily disabled to protect accounting history until reversal posting is implemented.');
    }

    public function edit($id)
    {
        $voucher = CommisionVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail($id);
        $x = $this->getCommonData('Edit Commision Voucher', 'CV');
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

    private function getCommonData($title, $type = 'CV')
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
