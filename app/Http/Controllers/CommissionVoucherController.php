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
        $power = Auth::user()->roles->first()?->name ?? '';
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
            // Shared finance column headings: validated, project-scoped filtering/sorting.
            $columnInput = $request->validate([
                'fvd_column_filters' => ['nullable','array'],
                'fvd_column_filters.id' => ['nullable','string','max:100'],
                'fvd_column_filters.voucher_number' => ['nullable','string','max:100'],
                'fvd_column_filters.reference' => ['nullable','string','max:100'],
                'fvd_column_filters.date' => ['nullable','date_format:Y-m-d'],
                'fvd_date_from' => ['nullable','date_format:Y-m-d'],
                'fvd_date_to' => ['nullable','date_format:Y-m-d'],
                'fvd_column_filters.description' => ['nullable','string','max:100'],
                'fvd_column_filters.total_debit' => ['nullable','numeric','min:0'],
                'fvd_sort_column' => ['nullable', \Illuminate\Validation\Rule::in(['id','voucher_number','reference','date','description','total_debit'])],
                'fvd_sort_direction' => ['nullable', \Illuminate\Validation\Rule::in(['asc','desc'])],
            ]);
            $fields = ['id','voucher_number','reference','date','description','total_debit'];
            foreach (($columnInput['fvd_column_filters'] ?? []) as $field => $value) {
                if (!in_array($field,$fields,true) || $value === null || $value === '') continue;
                if ($field === 'date') $query->whereDate('date',$value);
                elseif ($field === 'total_debit' || $field === 'id') $query->where($field,$value);
                elseif ($field === 'voucher_number') {
                    $normalized = preg_replace('/^CV-/i','',(string)$value);
                    $query->where('voucher_number','like','%'.$normalized.'%');
                } else $query->where($field,'like','%'.$value.'%');
            }
            if (!empty($columnInput['fvd_date_from'])) $query->whereDate('date','>=',$columnInput['fvd_date_from']);
            if (!empty($columnInput['fvd_date_to'])) $query->whereDate('date','<=',$columnInput['fvd_date_to']);
            $total = (clone $baseQuery)->count();
            $filtered = (clone $query)->count();
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 10)));
            $sortField = $columnInput['fvd_sort_column'] ?? 'id';
            $sortDir = $columnInput['fvd_sort_direction'] ?? 'desc';
            $query->orderBy($sortField,$sortDir);
            if ($sortField !== 'id') $query->orderByDesc('id');
            $vouchers = $query->skip($start)->take($length)->get();
            $displayNumbers = \Illuminate\Support\Facades\Schema::hasTable('project_voucher_display_numbers')
                ? DB::table('project_voucher_display_numbers')->where('project_id', (int) $selectedProjectId)
                    ->where('source_kind', 'commission_voucher')->whereIn('source_id', $vouchers->pluck('id'))
                    ->pluck('display_number', 'source_id')
                : collect();
            $approvalStatuses=\Illuminate\Support\Facades\Schema::hasTable('daily_voucher_approvals')
                ?DB::table('daily_voucher_approvals')->where('project_id',(int)$selectedProjectId)
                    ->where('voucher_type','CV')->where('source_table','commision_vouchers')
                    ->whereIn('source_id',$vouchers->pluck('id'))
                    ->pluck('status','source_id')
                :collect();
            $data = $vouchers->map(function ($voucher) use ($displayNumbers,$approvalStatuses) {
                $centralStatus=$approvalStatuses[$voucher->id]??null;
                $approvalPending=$centralStatus==='pending';
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $displayNumbers[$voucher->id]
                        ?? \App\Services\Finance\CommissionVoucherNumber::display($voucher->voucher_number),
                    'reference' => $voucher->reference,
                    'date' => $voucher->date,
                    'description' => $voucher->description,
                    'total_debit' => $voucher->total_debit,
                    'approval_status'=>$centralStatus
                        ? ucfirst((string)$centralStatus)
                        : ($voucher->status==='approved'?'Posted':'Draft'),
                    'actions' => view('admin.reports.vouchers.actions',
                        compact('voucher','approvalPending'))->render(),
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
        // The Blade directory is fully AJAX/DataTables-backed. Do not load
        // every voucher into the HTML response before server-side paging.
        $x['vouchers'] = collect();

        return view('admin.reports.vouchers.commision_index', $x);
    }


    public function create()
    {
        $power = Auth::user()->roles->first()?->name ?? '';
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
        $x['commissionPilotCreateAllowed'] =
            (\App\Services\Finance\FinanceWorkflowAccess::enabled()
                || \App\Services\Finance\FinanceWorkflowAccess::mergedWriterAllowed())
            && \App\Services\Finance\FinanceConnectionGuard::isAllowed();


        return view('admin.reports.vouchers.commision_voucher', $x);
    }

    // Legacy CV posting has no trustworthy source-typed ledger relationship.
    // No dead write path is retained behind these deliberate safety gates.
    public function store(Request $request)
    {
        // Original browser form may write ONLY in the explicitly isolated
        // local V2 pilot. Never enable this against an existing V1 database.
        abort_unless(
            \App\Services\Finance\FinanceWorkflowAccess::enabled()
                || \App\Services\Finance\FinanceWorkflowAccess::mergedWriterAllowed(),
            409, 'Commission voucher creation is temporarily disabled to protect accounting history.'
        );
        \App\Services\Finance\FinanceConnectionGuard::assertAllowed();

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
        \App\Services\Finance\FinanceWorkflowAccess::authorize($projectId);
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
            $id = (new \App\Services\Finance\CommissionDraftWorkflow())
                ->createDraft($projectId, $data['voucher_number'], $data['date'],
                    $lines, (int) Auth::id(), [
                        'reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null,
                    ]);
        } catch (\DomainException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'voucher_number' => 'Commission draft rejected: ' . $e->getMessage(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
            // Do not send raw SQL/table/connection details to browser toasts.
            $statusMismatch = in_array((int) ($e->errorInfo[1] ?? 0), [1265, 1264], true);
            return response()->json([
                'message' => $statusMismatch
                    ? 'Commission status storage needs the approved local schema repair. Ask the administrator to run finance:repair-v2-commission-status.'
                    : 'Commission voucher could not be saved. Please contact the administrator.',
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Commission voucher could not be saved. Contact the administrator.',
            ], 500);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'voucher_id' => $id,
                'redirect' => route('commision.voucher.edit', ['id'=>$id] +
                    ($request->boolean('embed') ? ['embed'=>1] : [])),
                'message' => 'Commission draft saved. Approval/posting is still required.',
            ], 201);
        }
        return redirect()->route('commision.voucher.edit', ['id' => $id] + ($request->boolean('embed') ? ['embed'=>1] : []))
            ->with('success', 'Commission draft saved. Approval/posting is still required.');
    }

    public function approve(Request $request, $id)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\FinanceWorkflowAccess::authorize($projectId);
        // A voucher submitted to centralized approval must never be posted
        // through the independent legacy Approve button.
        $centralApproval=\Illuminate\Support\Facades\Schema::hasTable('daily_voucher_approvals')
            ? DB::table('daily_voucher_approvals')
            ->where('project_id',$projectId)->where('voucher_type','CV')
            ->where('source_table','commision_vouchers')
            ->where('source_id',(int)$id)->first() : null;
        if($centralApproval){
            $message='This Commission Voucher belongs to the centralized approval queue.';
            if($request->expectsJson()||$request->ajax())
                return response()->json(['status'=>'error','message'=>$message],409);
            return back()->withErrors(['voucher'=>$message]);
        }
        $data = $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
        ]);
        try {
            (new \App\Services\Finance\CommissionDraftWorkflow())->approveAndPost(
                $projectId, (int) $id, $data['request_key'], (int) Auth::id()
            );
        } catch (\DomainException $e) {
            // Never render Laravel's debug exception inside the iframe.
            // Distinguish expected business conflicts from successful posts;
            // retain the diagnostic code in logs for the developer.
            report($e);
            $reason = match ($e->getMessage()) {
                'V2_CV_SOURCE_NOT_APPROVED', 'V2_CV_NOT_APPROVABLE' =>
                    'Only an active Commission draft can be approved.',
                'V2_CV_DETAILS_REQUIRED' =>
                    'Commission voucher needs at least two active accounting rows.',
                'V2_CV_NOT_BALANCED', 'V2_CV_INVALID_LEG' =>
                    'Commission voucher debit and credit must be balanced and valid.',
                'V2_CV_CROSS_PROJECT_ACCOUNT' =>
                    'A Commission account belongs to another project.',
                'Commission ledger detail foreign key migration is required.',
                'Commission ledger foreign key is not enforced.' =>
                    'Commission accounting relationship is not configured correctly. Contact the administrator.',
                'Commission detail-to-ledger account or amount mismatch.',
                'Duplicate Commission ledger detail foreign key.',
                'Commission ledger references another voucher detail.',
                'Unlinked legacy Commission ledger exists; manual reconciliation required.' =>
                    'Commission ledger links do not match this voucher. Contact the administrator before approving.',
                'Commission voucher requires two active details.' =>
                    'Add at least two balanced accounting rows before approval.',
                'Commission ledger cannot activate without posted financial batch.' =>
                    'Commission financial posting could not be confirmed. No ledger entries were activated.',
                'V2_CV_REPLAY_CONFLICT','V2_CV_REPLAY_TAMPERED' =>
                    'This Commission posting already exists with different accounting details.',
                default => 'Commission approval could not be posted. Please check voucher relationships and contact the administrator.',
            };
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['status'=>'error','message'=>$reason],409);
            }
            return back()->withErrors(['voucher'=>$reason]);
        } catch (\Throwable $e) {
            report($e);
            $reason='Commission approval encountered a server error. No partial posting was saved; contact the administrator.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['status'=>'error','message'=>$reason],500);
            }
            return back()->withErrors(['voucher'=>$reason]);
        }
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status'=>'success','voucher_id'=>(int)$id,
                'message'=>'Commission voucher approved and posted.',
                'redirect'=>route('commision.voucher.index'),
            ]);
        }
        return redirect()->route(
            $request->boolean('embed') ? 'commision.voucher.edit' : 'commision.voucher.index',
            $request->boolean('embed') ? ['id'=>$id,'embed'=>1] : []
        )->with('success', 'Commission voucher approved and posted.');
    }

    /** Keep the typed writer for commission sources created in the old V2 flow. */
    private function isMergedTypedCommission(int $projectId, int $voucherId): bool
    {
        if (!\App\Services\Finance\FinanceWorkflowAccess::mergedWriterAllowed()) {
            return false;
        }
        $voucher = CommisionVoucher::query()->where('project_id', $projectId)
            ->whereKey($voucherId)->first();
        if (!$voucher) {
            return false;
        }
        if ((string) $voucher->status === 'draft') {
            return true;
        }
        return \Illuminate\Support\Facades\Schema::hasTable('finance_posting_commission_sources')
            && DB::table('finance_posting_commission_sources')
                ->where('commision_voucher_id', $voucherId)->exists();
    }

    public function destroy($id)
    {
        $projectId = (int) getSelectedTown();
        abort_if($projectId < 1, 403, 'Select a project.');
        if (request()->boolean('reverse')) {
            abort_unless(auth()->user()?->can('finance cv reverse'),403);
        }
        if ($this->isMergedTypedCommission($projectId,(int)$id)
            && !request()->boolean('reverse')) {
            \App\Services\Finance\FinanceWorkflowAccess::authorize($projectId);
            try {
                app(\App\Services\Finance\PostedVoucherArchiver::class)
                    ->archive($projectId,'commission',(int)$id);
            }catch(\DomainException $e){
                if(request()->expectsJson()||request()->ajax()){
                    return response()->json(['status'=>'error','message'=>$e->getMessage()],409);
                }
                return back()->withErrors(['voucher'=>$e->getMessage()]);
            }
        } elseif (\App\Services\Finance\FinanceWorkflowAccess::selected()
            || $this->isMergedTypedCommission($projectId,(int)$id)) {
            \App\Services\Finance\FinanceWorkflowAccess::authorize($projectId);
            $typedDraft = CommisionVoucher::query()->where('project_id', $projectId)
                ->whereKey((int) $id)->where('status', 'draft')->exists();
            if ($typedDraft) {
                try {
                    (new \App\Services\Finance\CommissionDraftWorkflow())->cancelDraft($projectId, (int) $id);
                } catch (\DomainException $exception) {
                    abort(409, 'Commission draft cancellation rejected to protect accounting history.');
                }
                return redirect()->route('commision.voucher.index')
                    ->with('success', 'Commission draft cancelled.');
            }
            $data = request()->validate([
                'request_key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
                'effective_date' => ['nullable', 'date_format:Y-m-d'],
                'reason' => ['nullable', 'string', 'max:200'],
            ]);
            $data['request_key'] = $data['request_key'] ?? (string) \Illuminate\Support\Str::uuid();
            $data['effective_date'] = $data['effective_date'] ?? now()->toDateString();
            $data['reason'] = trim((string) ($data['reason'] ?? 'Operator void'));
            abort_if($data['reason'] === '', 422, 'A reversal reason is required.');
            try {
                (new \App\Services\Finance\VoucherLifecycleService())->reverse(
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
                // All source-linked ledger legs are retired by exact voucher identity.
                app(\App\Services\Finance\LinkedVoucherLedgerRetirement::class)
                    ->retire($projectId, 'CV', (int)$voucher->id);
                CommisionVoucherDetail::where('commision_voucher_id', $voucher->id)->get()->each->delete();
                $voucher->delete();
            });
        }
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json(['status'=>'success',
                'message'=>'Commission voucher deactivated; linked ledger history preserved.']);
        }
        return redirect()->route('commision.voucher.index')
            ->with('success', 'Commission voucher deactivated; linked ledger history preserved.');
    }

    public function edit($id)
    {
        $voucher = CommisionVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail($id);
        // The register's action opens this route for both Draft and Approved
        // vouchers. Approval must not turn the iframe into an HTTP 409 debug
        // page: show approved posting as read-only while the Update service
        // continues to reject attempts to amend immutable financial history.
        $isPosted=$this->isMergedTypedCommission((int)getSelectedTown(),(int)$id);
        $readOnly=$isPosted
            && !in_array((string)$voucher->status,['draft','approved'],true);
        if ($isPosted && (string)$voucher->status==='approved') {
            $batchIds=DB::table('finance_posting_commission_sources')
                ->where('commision_voucher_id',(int)$id)->pluck('posting_batch_id');
            $readOnly=DB::table('finance_posting_batches')
                ->whereIn('reversal_of_batch_id',$batchIds)->exists();
        }
        $x = $this->getCommonData($readOnly
            ? 'View Approved Commission Voucher' : 'Edit Commision Voucher', 'CV');
        $x['readOnly'] = $readOnly;
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
        // New editor rows have one empty amount input. Normalize it to zero
        // before validation, exactly as Journal Create does.
        foreach (['debit','credit'] as $side) {
            $amounts=$request->input($side);
            if (is_array($amounts)) {
                $request->merge([$side=>array_map(
                    fn($value)=>$value===null||(is_string($value)&&trim($value)==='')
                        ? '0' : $value,$amounts
                )]);
            }
        }
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'detail_ids' => ['required','array','min:2','max:100'],
            'detail_ids.*' => ['nullable','integer','min:1','distinct'],
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
        foreach (['detail_ids','sub_accounts', 'debit', 'credit'] as $field) {
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
                'detail_id' => $data['detail_ids'][$index] ?? null,
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

        if (\App\Services\Finance\FinanceWorkflowAccess::selected()
            || $this->isMergedTypedCommission($projectId, (int) $id)) {
            \App\Services\Finance\FinanceWorkflowAccess::authorize($projectId);
            try {
                $source=CommisionVoucher::query()->where('project_id',$projectId)
                    ->whereKey((int)$id)->firstOrFail();
                $meta=array_intersect_key($data,array_flip(['reference','description']));
                if ((string)$source->status==='approved') {
                    app(\App\Services\Finance\CommissionApprovedVoucherEditor::class)
                        ->update($projectId,(int)$id,$data['date'],$lines,$meta,(int)Auth::id());
                } else {
                    (new \App\Services\Finance\CommissionDraftWorkflow())
                        ->updateDraft($projectId,(int)$id,$data['date'],$lines,$meta);
                }
            } catch (\DomainException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'voucher' => 'Commission update rejected: '.$e->getMessage(),
                ]);
            }
        } else {
            DB::transaction(function () use ($id, $projectId, $data, $lines, $totalDebitCents, $totalCreditCents): void {
                $voucher = CommisionVoucher::whereKey((int) $id)->where('project_id', $projectId)
                    ->lockForUpdate()->firstOrFail();
                $oldDetails = CommisionVoucherDetail::where('commision_voucher_id', $voucher->id)
                    ->lockForUpdate()->get();
                // Identical re-save must not retire/recreate related details
                // and ledger rows. Preserve their IDs and audit history.
                if (app(\App\Services\Finance\VoucherUnchangedUpdateDetector::class)
                    ->same($voucher,$oldDetails,$data,$lines)) {
                    return;
                }
                $typed = app(\App\Services\Finance\FinancePostingSchemaGate::class)->mode() === 'typed';
                // Preserve old detail/ledger history; only retire legs attached to
                // the same source ID, then create corrected details and active legs.
                app(\App\Services\Finance\LinkedVoucherLedgerRetirement::class)
                    ->retire($projectId, 'CV', (int)$voucher->id);

                foreach ($oldDetails as $detail) $detail->delete();
                $voucher->update([
                    'reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null,
                    'date' => $data['date'], 'total_debit' => $this->centsToMoney($totalDebitCents),
                    'total_credit' => $this->centsToMoney($totalCreditCents), 'updated_by' => Auth::id(),
                ]);
                foreach ($lines as $line) {
                    $detail = CommisionVoucherDetail::create([
                        'commision_voucher_id' => $voucher->id, 'account_id' => $line['account_id'],
                        'debit' => $line['debit'], 'credit' => $line['credit'],
                        'description' => $line['description'],
                    ]);
                    $attributes = [
                        'voucher_number' => $voucher->voucher_number, 'type' => 'CV', 'type_id' => $voucher->id,
                        'project_head_subheads_id' => $line['account_id'], 'reference' => $data['reference'] ?? null,
                        'amount_in' => $line['debit'], 'amount_out' => $line['credit'],
                        'detail' => $line['description'], 'create_by' => Auth::id(), 'is_active' => 1,
                        'status' => '0', 'date' => $data['date'],
                    ];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('ledgers','commision_voucher_detail_id')) {
                        $attributes['commision_voucher_detail_id'] = $detail->id;
                    }
                    $ledger = Ledger::create($attributes);
                    if ($typed) app(\App\Services\Finance\LedgerSourceAttributionWriter::class)
                        ->recordNewPosting((int) $ledger->id, $projectId, 'commission_voucher', (int) $voucher->id);
                }
            });
        }
        $redirect=route('commision.voucher.edit', ['id' => $id] + ($request->boolean('embed') ? ['embed'=>1] : []));
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['status'=>'success','redirect'=>$redirect,
                'message'=>'Commission voucher updated; prior linked ledger history preserved.']);
        }
        return redirect()->to($redirect)
            ->with('success', 'Commission voucher updated; prior linked ledger history preserved.');
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
        $power = Auth::user()->roles->first()?->name ?? '';
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
