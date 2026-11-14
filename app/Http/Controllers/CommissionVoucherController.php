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
use App\Models\Role;
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
            $displayNumbers = \Illuminate\Support\Facades\Schema::hasTable('project_voucher_display_numbers')
                ? DB::table('project_voucher_display_numbers')->where('project_id', (int) $selectedProjectId)
                    ->where('source_kind', 'commission_voucher')->whereIn('source_id', $vouchers->pluck('id'))
                    ->pluck('display_number', 'source_id')
                : collect();
            $data = $vouchers->map(function ($voucher) use ($displayNumbers) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $displayNumbers[$voucher->id]
                        ?? \App\Services\Finance\CommissionVoucherNumber::display($voucher->voucher_number),
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

        $data_list = ProjectHeadSubhead::availableForPosting()->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();

        // dd($data_list[0]->headAccounting->name);

        $x['accounts'] = $data_list;
        $x['nextCommissionNumber'] = \App\Services\Finance\CommissionVoucherNumber::next((int) $selectedProjectId);


        return view('admin.reports.vouchers.commision_voucher', $x);
    }

    // Legacy CV posting has no trustworthy source-typed ledger relationship.
    // No dead write path is retained behind these deliberate safety gates.
    public function store(Request $request)
    {
        // Original browser form may write ONLY in the explicitly isolated
        // local V2 pilot. Never enable this against an existing V1 database.
        abort_unless(
            in_array(config('app.env'), ['local', 'testing'], true)
                && config('finance.v2_pilot_api_enabled')
                && config('finance.v2_pilot_write_enabled'),
            409, 'Commission voucher creation is temporarily disabled to protect accounting history.'
        );
        \App\Services\Finance\V2IsolatedConnectionGuard::assertAllowed();

        $data = $request->validate([
            'voucher_number' => ['required', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
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
        if (preg_match('/^CV-([0-9]+)$/D', $data['voucher_number'], $match)) {
            $data['voucher_number'] = (string) (int) $match[1];
        }
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
        \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
        $accounts = ProjectHeadSubhead::availableForPosting()
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
                    $lines, (int) Auth::id(), [
                        'reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null,
                    ]);
        } catch (\DomainException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'voucher_number' => 'Commission draft rejected: ' . $e->getMessage(),
            ]);
        }
        return redirect()->route('commision.voucher.edit', ['id' => $id] + ($request->boolean('embed') ? ['embed'=>1] : []))
            ->with('success', 'Commission draft saved. Approval/posting is still required.');
    }

    public function approve(Request $request, $id)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
        $data = $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
        ]);
        try {
            (new \App\Services\Finance\V2CommissionDraftWorkflow())->approveAndPost(
                $projectId, (int) $id, $data['request_key'], (int) Auth::id()
            );
        } catch (\DomainException $e) {
            abort(409, 'Commission approval rejected to protect accounting history.');
        }
        return redirect()->route(
            $request->boolean('embed') ? 'commision.voucher.edit' : 'commision.voucher.index',
            $request->boolean('embed') ? ['id'=>$id,'embed'=>1] : []
        )->with('success', 'Commission voucher approved and posted.');
    }

    public function destroy($id)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project.');
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
            $data = request()->validate([
                'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
                'effective_date' => ['required', 'date_format:Y-m-d'],
                'reason' => ['required', 'string', 'min:1', 'max:200'],
            ]);
            try {
                (new \App\Services\Finance\V2VoucherLifecycleService())->reverse(
                    $projectId, 'commission', (int) $id, $data['request_key'],
                    $data['effective_date'], $data['reason'], (int) Auth::id()
                );
            } catch (\DomainException $exception) {
                abort(409, 'Commission voucher reversal rejected to protect accounting history.');
            }
        } else {
            $data = request()->validate([
                'effective_date' => ['nullable', 'date_format:Y-m-d'],
                'reason' => ['nullable', 'string', 'max:200'],
            ]);
            DB::transaction(function () use ($id, $projectId, $data): void {
                $voucher = CommisionVoucher::whereKey((int) $id)->where('project_id', $projectId)
                    ->lockForUpdate()->firstOrFail();
                $ledgers = Ledger::query()->withoutGlobalScopes()->where('type', 'CV')
                    ->where('type_id', $voucher->id)->where('is_active', 1)
                    ->whereIn('project_head_subheads_id', ProjectHeadSubhead::where('project_id', $projectId)->select('id'))
                    ->lockForUpdate()->get();
                abort_if($ledgers->isEmpty(), 409, 'CV ledger entries are missing; reversal stopped.');
                $date = $data['effective_date'] ?? now()->toDateString();
                foreach ($ledgers as $old) {
                    Ledger::create([
                        'voucher_number' => $voucher->voucher_number, 'type' => 'CV', 'type_id' => $voucher->id,
                        'project_head_subheads_id' => $old->project_head_subheads_id,
                        'reference' => $voucher->reference, 'amount_in' => $old->amount_out,
                        'amount_out' => $old->amount_in, 'date' => $date, 'is_active' => 1, 'status' => '0',
                        'create_by' => Auth::id(), 'detail' => 'Voucher reversal: '.($data['reason'] ?? $old->detail),
                    ]);
                }
                CommisionVoucherDetail::where('commision_voucher_id', $voucher->id)->get()->each->delete();
                $voucher->delete();
            });
        }
        return redirect()->route('commision.voucher.index')
            ->with('success', 'Commission voucher reversed; original accounting history preserved.');
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
        $x['accounts'] = ProjectHeadSubhead::availableForPosting()->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where('project_id', $selectedProjectId)
            ->get();

        return view('admin.reports.vouchers.edit_commision_voucher', $x);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
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
        abort_if($projectId < 1, 403, 'Select a project.');
        $accounts = ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
            ->whereIn('id', $data['sub_accounts'])
            ->get(['id', 'head_accounting_id'])->keyBy('id');
        $lines = []; $totalDebitCents = 0; $totalCreditCents = 0;
        foreach ($keys as $index) {
            $sub = (int) $data['sub_accounts'][$index];
            if (!$accounts->has($sub)
                || (int) $accounts->get($sub)->head_accounting_id !== (int) $data['accounts'][$index]) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sub_accounts' => 'Account must match the selected project and head.',
                ]);
            }
            $debitCents = $this->moneyToCents((string) $data['debit'][$index]);
            $creditCents = $this->moneyToCents((string) $data['credit'][$index]);
            if (($debitCents === 0) === ($creditCents === 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'debit' => 'Each commission row must contain either debit or credit, but not both.',
                ]);
            }
            $totalDebitCents += $debitCents; $totalCreditCents += $creditCents;
            $lines[] = [
                'account_id' => $sub,
                'debit' => $this->centsToMoney($debitCents),
                'credit' => $this->centsToMoney($creditCents),
                'description' => $data['line_description'][$index] ?? '',
            ];
        }
        if ($totalDebitCents <= 0 || $totalDebitCents !== $totalCreditCents) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'debit' => 'Total debit and credit must be equal and greater than zero.',
            ]);
        }

        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
            try {
                (new \App\Services\Finance\V2CommissionDraftWorkflow())
                    ->updateDraft($projectId, (int) $id, $data['date'], $lines,
                        array_intersect_key($data, array_flip(['reference', 'description'])));
            } catch (\DomainException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'voucher' => 'Commission draft update rejected: ' . $e->getMessage(),
                ]);
            }
        } else {
            DB::transaction(function () use ($id, $projectId, $data, $lines, $totalDebitCents, $totalCreditCents): void {
                $voucher = CommisionVoucher::whereKey((int) $id)->where('project_id', $projectId)
                    ->lockForUpdate()->firstOrFail();
                $oldDetails = CommisionVoucherDetail::where('commision_voucher_id', $voucher->id)
                    ->lockForUpdate()->get();
                $oldLedgers = Ledger::query()->withoutGlobalScopes()->where('type', 'CV')
                    ->where('type_id', $voucher->id)->where('is_active', 1)
                    ->whereIn('project_head_subheads_id', ProjectHeadSubhead::where('project_id', $projectId)->select('id'))
                    ->lockForUpdate()->get();
                abort_if($oldLedgers->isEmpty(), 409, 'CV ledger entries are missing; update stopped without changes.');
                $typed = app(\App\Services\Finance\FinancePostingSchemaGate::class)->mode() === 'typed';

                foreach ($oldLedgers as $old) {
                    $reversal = Ledger::create([
                        'voucher_number' => $voucher->voucher_number, 'type' => 'CV', 'type_id' => $voucher->id,
                        'project_head_subheads_id' => $old->project_head_subheads_id,
                        'reference' => $voucher->reference, 'amount_in' => $old->amount_out,
                        'amount_out' => $old->amount_in,
                        'detail' => 'Correction reversal: '.($old->detail ?: $voucher->description),
                        'create_by' => Auth::id(), 'is_active' => 1, 'status' => '0', 'date' => $data['date'],
                    ]);
                    if ($typed) app(\App\Services\Finance\LedgerSourceAttributionWriter::class)
                        ->recordNewPosting((int) $reversal->id, $projectId, 'commission_voucher', (int) $voucher->id);
                }

                foreach ($oldDetails as $detail) $detail->delete();
                $voucher->update([
                    'reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null,
                    'date' => $data['date'], 'total_debit' => $this->centsToMoney($totalDebitCents),
                    'total_credit' => $this->centsToMoney($totalCreditCents), 'updated_by' => Auth::id(),
                ]);
                foreach ($lines as $line) {
                    CommisionVoucherDetail::create([
                        'commision_voucher_id' => $voucher->id, 'account_id' => $line['account_id'],
                        'debit' => $line['debit'], 'credit' => $line['credit'],
                        'description' => $line['description'],
                    ]);
                    $ledger = Ledger::create([
                        'voucher_number' => $voucher->voucher_number, 'type' => 'CV', 'type_id' => $voucher->id,
                        'project_head_subheads_id' => $line['account_id'], 'reference' => $data['reference'] ?? null,
                        'amount_in' => $line['debit'], 'amount_out' => $line['credit'],
                        'detail' => $line['description'], 'create_by' => Auth::id(), 'is_active' => 1,
                        'status' => '0', 'date' => $data['date'],
                    ]);
                    if ($typed) app(\App\Services\Finance\LedgerSourceAttributionWriter::class)
                        ->recordNewPosting((int) $ledger->id, $projectId, 'commission_voucher', (int) $voucher->id);
                }
            });
        }
        return redirect()->route('commision.voucher.edit', ['id' => $id] + ($request->boolean('embed') ? ['embed'=>1] : []))
            ->with('success', 'Commission voucher corrected successfully; previous posting history was preserved.');
    }

    private function moneyToCents(string $value): int
    {
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function print($id)
    {
        $voucher = CommisionVoucher::with('details.account.headAccounting', 'details.account.subheadAccounting')->where('project_id', getSelectedTown())->findOrFail($id);
        $voucherDisplayNumber = app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
            ->lookup((int) getSelectedTown(), 'commission_voucher', (int) $voucher->id)
            ?: \App\Services\Finance\CommissionVoucherNumber::display($voucher->voucher_number);
        return view('admin.reports.vouchers.print_journal_voucher', compact('voucher', 'voucherDisplayNumber') + ['sourceVoucherType' => 'CV']);
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
