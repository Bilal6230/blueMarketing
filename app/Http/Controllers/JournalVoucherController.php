<?php

namespace App\Http\Controllers;

use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalVoucherController extends Controller
{
    public function index(Request $request)
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'General Journal Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'JV';
        $x['class'] = 'Journal voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // Build base query for vouchers
        $baseQuery = JournalVoucher::latest()
            ->where('project_id', $selectedProjectId)
            ->where(function ($query) {
                $query->where('type', 'JV')
                    ->orWhereNull('type');
            });
        $query = clone $baseQuery;

        // Apply filters if they exist
        $voucherNumberFilter = trim((string) $request->input('filter_voucher_number', ''));
        if ($voucherNumberFilter !== '') {
            $normalizedVoucherNumber = preg_replace('/^[A-Z]+-/i', '', $voucherNumberFilter);
            $normalizedVoucherNumber = ltrim($normalizedVoucherNumber, '0');
            $normalizedVoucherNumber = $normalizedVoucherNumber === '' ? '0' : $normalizedVoucherNumber;

            $query->where(function ($q) use ($voucherNumberFilter, $normalizedVoucherNumber) {
                $q->where('voucher_number', 'like', '%' . $voucherNumberFilter . '%')
                    ->orWhere('voucher_number', 'like', '%' . $normalizedVoucherNumber . '%');
            });
        }

        $minAmount = $request->input('filter_amount_min');
        $maxAmount = $request->input('filter_amount_max');

        if ($minAmount !== null && $minAmount !== '') {
            $query->where('total_debit', '>=', (float) $minAmount);
        }

        if ($maxAmount !== null && $maxAmount !== '') {
            $query->where('total_debit', '<=', (float) $maxAmount);
        }


        if ($request->has('filter_reference') && $request->filter_reference) {
            $query->where('reference', 'like', '%' . $request->filter_reference . '%');
        }

        $filterDate = $this->normalizeFilterDate($request->input('filter_date'));
        if ($filterDate !== null) {
            $query->whereDate('date', '=', $filterDate);
        }

        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $normalizedSearchValue = preg_replace('/^[A-Z]+-/i', '', $searchValue);
            $normalizedSearchValue = ltrim($normalizedSearchValue, '0');
            $normalizedSearchValue = $normalizedSearchValue === '' ? '0' : $normalizedSearchValue;

            $query->where(function ($q) use ($searchValue, $normalizedSearchValue) {
                $q->where('voucher_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('voucher_number', 'like', '%' . $normalizedSearchValue . '%')
                    ->orWhere('reference', 'like', '%' . $searchValue . '%')
                    ->orWhere('description', 'like', '%' . $searchValue . '%')
                    ->orWhere('date', 'like', '%' . $searchValue . '%')
                    ->orWhere('total_debit', 'like', '%' . $searchValue . '%');
            });
        }

        // Excel-style per-column menus share the same scoped SQL query.
        // Never fetch only the first visible DataTables page for filter choices.
        if ($request->ajax()) {
            $allowedExcelColumns=['id','voucher_number','reference','date','description','total_debit'];
            $excelColumn=(string)$request->input('excel_facet','');
            if ($excelColumn==='approval_status') {
                return response()->json(['options'=>['Pending','Approved','Rejected','Posted'],
                    'more_options'=>false]);
            }
            if (in_array($excelColumn,$allowedExcelColumns,true)) {
                $options=(clone $query)->reorder()->select($excelColumn)->distinct()
                    ->orderBy($excelColumn)->limit(501)->pluck($excelColumn)
                    ->map(fn($value)=>(string)$value)->all();
                return response()->json(['options'=>array_slice($options,0,500),
                    'more_options'=>count($options)>500]);
            }
            $excluded=(array)$request->input('excel_excluded',[]);
            foreach($excluded as $field=>$values) {
                if($field==='approval_status' && is_array($values)) {
                    $statuses=array_unique(array_map('strtolower',array_map('strval',$values)));
                    if(count($statuses)>4 || array_diff($statuses,['pending','approved','rejected','posted']))
                        abort(422,'Invalid excluded statuses.');
                    if(\Illuminate\Support\Facades\Schema::hasTable('daily_voucher_approvals')) {
                        if(in_array('posted',$statuses,true)){
                            $query->whereExists(function($sub)use($selectedProjectId) {
                                $sub->selectRaw('1')->from('daily_voucher_approvals as a')
                                    ->whereColumn('a.source_id','journal_vouchers.id')
                                    ->where('a.project_id',$selectedProjectId)->where('a.source_table','journal_vouchers');
                            });
                        }
                        $others=array_diff($statuses,['posted']);
                        if($others){
                            $query->whereNotExists(function($sub)use($selectedProjectId,$others){
                                $sub->selectRaw('1')->from('daily_voucher_approvals as a')
                                    ->whereColumn('a.source_id','journal_vouchers.id')
                                    ->where('a.project_id',$selectedProjectId)
                                    ->where('a.source_table','journal_vouchers')->whereIn('a.status',$others);
                            });
                        }
                    }elseif(in_array('posted',$statuses,true))$query->whereRaw('1=0');
                    continue;
                }
                if(!in_array($field,$allowedExcelColumns,true)||!is_array($values)||
                    count($values)>100)abort(422,'Invalid excluded values.');
                foreach($values as $v)if(!is_scalar($v)||mb_strlen((string)$v)>255)
                    abort(422,'Invalid excluded value.');
                if($values)$query->whereNotIn($field,array_map('strval',$values));
            }
            $selected=(array)$request->input('excel_values',[]);
            foreach($selected as $field=>$values) {
                if($field==='approval_status' && is_array($values)) {
                    $statuses=array_unique(array_map('strtolower',array_map('strval',$values)));
                    if(!$statuses || count($statuses)>4 ||
                        array_diff($statuses,['pending','approved','rejected','posted']))
                        abort(422,'Invalid approval status filter.');
                    if(\Illuminate\Support\Facades\Schema::hasTable('daily_voucher_approvals')) {
                        $query->where(function($scope)use($selectedProjectId,$statuses) {
                            if(in_array('posted',$statuses,true)) {
                                $scope->whereNotExists(function($sub)use($selectedProjectId){
                                    $sub->selectRaw('1')->from('daily_voucher_approvals as a')
                                        ->whereColumn('a.source_id','journal_vouchers.id')
                                        ->where('a.project_id',$selectedProjectId)
                                        ->where('a.source_table','journal_vouchers');
                                });
                            }
                            $other=array_diff($statuses,['posted']);
                            if($other) {
                                $scope->orWhereExists(function($sub)use($selectedProjectId,$other){
                                    $sub->selectRaw('1')->from('daily_voucher_approvals as a')
                                        ->whereColumn('a.source_id','journal_vouchers.id')
                                        ->where('a.project_id',$selectedProjectId)
                                        ->where('a.source_table','journal_vouchers')
                                        ->whereIn('a.status',$other);
                                });
                            }
                        });
                    } elseif(!in_array('posted',$statuses,true)) {
                        $query->whereRaw('1=0');
                    }
                    continue;
                }
                if(!in_array($field,$allowedExcelColumns,true) || !is_array($values) ||
                    !$values || count($values)>50) abort(422,'Invalid column selections.');
                foreach($values as $value) {
                    if(!is_scalar($value) || mb_strlen((string)$value)>255)
                        abort(422,'Invalid filter value.');
                }
                $query->whereIn($field,array_map('strval',$values));
            }
        }
        // Column filters are applied in SQL before paging (not just the first page).
        if ($request->ajax()) {
            $columnTerms = (array) $request->input('columns', []);
            foreach ([0=>'id',1=>'voucher_number',2=>'reference',3=>'date',4=>'description',5=>'total_debit'] as $index=>$column) {
                $needle=trim((string) data_get($columnTerms,$index.'.search.value',''));
                if ($needle==='') continue;
                $needle=mb_substr($needle,0,120);
                if($index===0 && ctype_digit($needle)) $query->where('id',(int)$needle);
                elseif($index===3) $query->whereDate('date',$needle);
                elseif($index===5 && is_numeric($needle)) $query->where('total_debit',(float)$needle);
                else $query->where($column,'like','%'.addcslashes($needle,'%_\\').'%');
            }
            $approvalTerm=strtolower(trim((string)data_get($columnTerms,'6.search.value','')));
            if (in_array($approvalTerm,['pending','approved','rejected'],true) && \Illuminate\Support\Facades\Schema::hasTable('daily_voucher_approvals')) {
                $query->whereExists(function($sub)use($selectedProjectId,$approvalTerm){
                    $sub->selectRaw('1')->from('daily_voucher_approvals as approval')
                        ->whereColumn('approval.source_id','journal_vouchers.id')
                        ->where('approval.project_id',(int)$selectedProjectId)
                        ->where('approval.source_table','journal_vouchers')
                        ->where('approval.status',$approvalTerm);
                });
            } elseif($approvalTerm==='posted' && \Illuminate\Support\Facades\Schema::hasTable('daily_voucher_approvals')) {
                $query->whereNotExists(function($sub)use($selectedProjectId){
                    $sub->selectRaw('1')->from('daily_voucher_approvals as approval')
                        ->whereColumn('approval.source_id','journal_vouchers.id')
                        ->where('approval.project_id',(int)$selectedProjectId)
                        ->where('approval.source_table','journal_vouchers');
                });
            }
            // Pagination parameters
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 10)));

            $recordsTotal = (clone $baseQuery)->count();
            $recordsFiltered = (clone $query)->count();
            $vouchers = $query->skip($start)->take($length)->get();
            $displayNumbers = \Illuminate\Support\Facades\Schema::hasTable('project_voucher_display_numbers')
                ? DB::table('project_voucher_display_numbers')->where('project_id', (int) getSelectedTown())
                    ->where('source_kind', 'journal_voucher')->whereIn('source_id', $vouchers->pluck('id'))
                    ->pluck('display_number', 'source_id')
                : collect();

            $approvalStatuses = app(\App\Services\Finance\VoucherApprovalStatusReader::class)->forSources((int)$selectedProjectId,'journal_vouchers',$vouchers->pluck('id'));
            $data = $vouchers->map(function ($voucher) use ($displayNumbers,$approvalStatuses) {
                $approval=$approvalStatuses[$voucher->id]??null;
                $status=$approval['status']??'posted';
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $displayNumbers[$voucher->id] ?? ('JV-' . get_jv_number($voucher->voucher_number)),
                    'reference' => $voucher->reference,
                    'date' => $voucher->date,
                    'description' => $voucher->description,
                    'total_debit' => $voucher->total_debit,
                    'approval_status' => ucfirst($status),
                    'approval_approver' => $approval['approver']??null,
                    'approval_reason' => $approval['reason']??null,
                    'actions' => view('admin.reports.vouchers.actions', [
                        'voucher'=>$voucher,'approvalTracked'=>$approval !== null,
                        'canResubmit'=>$approval !== null && $approval['status']==='rejected'
                            && (\App\Services\Finance\VoucherApproverAccess::isSuperAdmin(auth()->user())
                                || (int)$voucher->created_by===(int)auth()->id()),
                    ])->render() // Render actions view
                ];
            });

            // Return JSON data
            return response()->json([
                'draw' => $request->input('draw', 1),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);
        }

        // For non-AJAX requests, return the view
        $x['vouchers'] = $query->get(); // Fetch all vouchers initially

        return view('admin.reports.vouchers.index', $x);
    }
    public function salesIndex(Request $request)
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'General Sales Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'SV';
        $x['class'] = 'Journal voucher';
        $x['bg_voucher'] = 'info-cash-in';

        // Get selected town's project_id
        $selectedProjectId = getSelectedTown();

        // Build base query for vouchers
        $baseQuery = JournalVoucher::query()
            ->where('project_id', $selectedProjectId)
            ->where('type', 'SV');
        $query = clone $baseQuery;

        // Apply filters if they exist
        $voucherNumberFilter = trim((string) $request->input('filter_voucher_number', ''));
        if ($voucherNumberFilter !== '') {
            $normalizedVoucherNumber = $this->normalizeVoucherNumberInput($voucherNumberFilter, 'SV');

            $query->where(function ($q) use ($voucherNumberFilter, $normalizedVoucherNumber) {
                if ($normalizedVoucherNumber !== null) {
                    $q->where('voucher_number', (int) $normalizedVoucherNumber);
                } else {
                    $q->where('voucher_number', 'like', '%' . $voucherNumberFilter . '%');
                }
            });
        }

        $minAmount = $request->input('filter_amount_min');
        $maxAmount = $request->input('filter_amount_max');

        if ($minAmount !== null && $minAmount !== '') {
            $query->where('total_debit', '>=', (float) $minAmount);
        }

        if ($maxAmount !== null && $maxAmount !== '') {
            $query->where('total_debit', '<=', (float) $maxAmount);
        }


        if ($request->has('filter_reference') && $request->filter_reference) {
            $query->where('reference', 'like', '%' . $request->filter_reference . '%');
        }

        $filterDate = $this->normalizeFilterDate($request->input('filter_date'));
        if ($filterDate !== null) {
            $query->whereDate('date', '=', $filterDate);
        }

        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $normalizedSearchValue = $this->normalizeVoucherNumberInput($searchValue, 'SV');

            $query->where(function ($q) use ($searchValue, $normalizedSearchValue) {
                $q->where('voucher_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('reference', 'like', '%' . $searchValue . '%')
                    ->orWhere('description', 'like', '%' . $searchValue . '%')
                    ->orWhere('date', 'like', '%' . $searchValue . '%')
                    ->orWhere('total_debit', 'like', '%' . $searchValue . '%')
                    ->orWhere('total_credit', 'like', '%' . $searchValue . '%');

                if ($normalizedSearchValue !== null) {
                    $q->orWhere('voucher_number', (int) $normalizedSearchValue);
                }
            });
        }

        // Shared JV/SV listing uses the same Excel-style column controls.
        if ($request->ajax()) {
            $allowed=['id','voucher_number','reference','date','description','total_debit'];
            $facet=(string)$request->input('excel_facet','');
            if(in_array($facet,$allowed,true)){
                $options=(clone $query)->reorder()->select($facet)->distinct()
                    ->orderBy($facet)->limit(501)->pluck($facet)
                    ->map(fn($v)=>(string)$v)->all();
                return response()->json(['options'=>array_slice($options,0,500),
                    'more_options'=>count($options)>500]);
            }
            foreach(['excel_values'=>true,'excel_excluded'=>false] as $param=>$include){
                foreach((array)$request->input($param,[]) as $field=>$values){
                    if(!in_array($field,$allowed,true)||!is_array($values)||
                        !$values||count($values)>100)abort(422,'Invalid sales voucher filter.');
                    foreach($values as $v)if(!is_scalar($v)||mb_strlen((string)$v)>255)
                        abort(422,'Invalid sales voucher filter value.');
                    $include?$query->whereIn($field,array_map('strval',$values))
                        :$query->whereNotIn($field,array_map('strval',$values));
                }
            }
        }
        // If it's an AJAX request, return JSON data
        if ($request->ajax()) {
            // Pagination parameters
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);

            $recordsTotal = (clone $baseQuery)->count();
            $recordsFiltered = (clone $query)->count();

            $orderableColumns = [
                0 => 'id',
                1 => 'voucher_number',
                2 => 'reference',
                3 => 'date',
                4 => 'description',
                5 => 'total_debit',
            ];
            $orderColumnIndex = (int) $request->input('order.0.column', 0);
            $orderDirection = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
            $orderColumn = $orderableColumns[$orderColumnIndex] ?? 'id';

            $query->orderBy($orderColumn, $orderDirection);
            $vouchers = $query->skip($start)->take($length)->get();
            $displayNumbers = \Illuminate\Support\Facades\Schema::hasTable('project_voucher_display_numbers')
                ? DB::table('project_voucher_display_numbers')
                    ->where('project_id', (int) getSelectedTown())->where('source_kind', 'journal_voucher')
                    ->whereIn('source_id', $vouchers->pluck('id'))->pluck('display_number', 'source_id')
                : collect();

            // Prepare the data, including the "actions" column
            $data = $vouchers->map(function ($voucher) use ($displayNumbers) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $displayNumbers[$voucher->id] ?? ('SV-' . (int) $voucher->voucher_number),
                    'reference' => $voucher->reference,
                    'date' => $voucher->date,
                    'description' => $voucher->description,
                    'total_debit' => $voucher->total_debit,
                    'actions' => view('admin.reports.vouchers.actions', compact('voucher'))->render() // Render actions view
                ];
            });

            // Return JSON data
            return response()->json([
                'draw' => $request->input('draw', 1),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);
        }

        // For non-AJAX requests, return the view
        $x['vouchers'] = $query->orderByDesc('id')->get(); // Fetch all vouchers initially

        return view('admin.reports.vouchers.index', $x);
    }

    private function normalizeVoucherNumberInput(string $value, string $prefix): ?string
    {
        $normalized = strtoupper(trim($value));
        $normalized = preg_replace('/^' . preg_quote(strtoupper($prefix), '/') . '\s*-\s*/', '', $normalized);
        $normalized = ltrim($normalized, '0');

        if ($normalized === '') {
            $normalized = '0';
        }

        return ctype_digit($normalized) ? $normalized : null;
    }

    private function normalizeFilterDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }


    public function create()
    {
        $power = Auth::user()->roles[0]->name;
        $x['title'] = 'Create General Journal Voucher';
        $x['role'] = Role::get();
        $x['users'] = User::get();
        $x['power'] = $power;
        $x['type'] = 'JV';
        $x['class'] = 'Journal voucher';
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


        // Existing production databases may not have the reviewed finance metadata
        // migration. Only issue a durable retry key when both tables are present.
        $x['financeRetryReady'] = \App\Services\Finance\V2OriginalWorkflowAccess::enabled()
            || app(\App\Services\Finance\FinancePostingSchemaGate::class)->isReady();
        $x['financeRetryKey'] = $x['financeRetryReady']
            ? old('idempotency_key', (string) \Illuminate\Support\Str::uuid())
            : null;

        $x['restoredSubAccounts'] = ProjectHeadSubhead::with('subheadAccounting')
            ->where('project_id', $selectedProjectId)
            ->whereIn('id', (array) old('sub_accounts', []))->get()->keyBy('id');

        return view('admin.reports.vouchers.journal_voucher', $x);
    }

    public function store(Request $request)
    {
        // The form leaves the unused side of each line blank. Normalize only
        // submitted blank cells; missing rows and invalid amounts still fail validation.
        foreach (['debit', 'credit'] as $field) {
            $amounts = $request->input($field);
            if (is_array($amounts)) {
                $request->merge([$field => array_map(
                    fn ($amount) => $amount === null || (is_string($amount) && trim($amount) === '') ? '0' : $amount,
                    $amounts
                )]);
            }
        }

        $validated = $request->validate([
            'idempotency_key' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'voucher_number' => 'required|string',
            'reference' => 'nullable|string',
            'date' => 'required|date',
            'description' => 'required|string',
            'accounts' => 'required|array|min:2',
            'accounts.*' => 'required|integer',
            'sub_accounts' => 'required|array|min:2',
            'sub_accounts.*' => 'required|integer',
            'line_description' => 'nullable|array',
            'line_description.*' => 'nullable|string',
            'debit' => 'required|array|min:2',
            'debit.*' => ['required', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'],
            'credit' => 'required|array|min:2',
            'credit.*' => ['required', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/'],
        ]);

        $count = count($validated['accounts']);
        foreach (['sub_accounts', 'debit', 'credit'] as $field) {
            if (count($validated[$field]) !== $count ||
                array_keys($validated[$field]) !== array_keys($validated['accounts'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => 'Voucher line arrays must have identical row keys and lengths.',
                ]);
            }
        }
        if (isset($validated['line_description']) &&
            array_diff(array_keys($validated['line_description']), array_keys($validated['accounts']))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'line_description' => 'Descriptions must belong to existing voucher rows.',
            ]);
        }

        $selectedProjectId = getSelectedTown();
        if (!$selectedProjectId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'project' => 'Select a project before posting a journal voucher.',
            ]);
        }

        $subAccountIds = array_values(array_unique(array_map('intval', $validated['sub_accounts'])));
        $ownedAccounts = ProjectHeadSubhead::availableForPosting()->where('project_id', $selectedProjectId)
            ->whereIn('id', $subAccountIds)->get(['id', 'head_accounting_id']);
        if ($ownedAccounts->count() !== count($subAccountIds)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sub_accounts' => 'Every voucher account must belong to the selected project.',
            ]);
        }
        $headIds = $ownedAccounts->pluck('head_accounting_id', 'id');
        $totalDebitCents = 0;
        $totalCreditCents = 0;
        foreach ($validated['accounts'] as $index => $headId) {
            $subId = $validated['sub_accounts'][$index];
            if ((string) $headIds->get($subId) !== (string) $headId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'accounts' => 'Head and subaccount pairing does not match.',
                ]);
            }

            // Fixed-point addition avoids floating point equality errors.
            $debitParts = explode('.', (string) $validated['debit'][$index]);
            $creditParts = explode('.', (string) $validated['credit'][$index]);
            $debitCents = ((int) $debitParts[0] * 100) + (int) str_pad($debitParts[1] ?? '', 2, '0');
            $creditCents = ((int) $creditParts[0] * 100) + (int) str_pad($creditParts[1] ?? '', 2, '0');
            if (($debitCents === 0 && $creditCents === 0) ||
                ($debitCents > 0 && $creditCents > 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'debit' => 'Each row must have either debit or credit, but not both or neither.',
                ]);
            }
            $totalDebitCents += $debitCents;
            $totalCreditCents += $creditCents;
        }
        if ($totalDebitCents <= 0 || $totalDebitCents !== $totalCreditCents) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'debit' => 'Total debit and credit must be equal and greater than zero.',
            ]);
        }

        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize((int) $selectedProjectId);
            abort_unless(!app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class)->policy((int) $selectedProjectId, 'JV'), 409,
                'This voucher has an approval policy; posting through the isolated pilot is disabled.');
            $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
            try {
                (new \App\Services\Finance\V2OriginalJournalWorkflow())->createAndPost(
                    (int) $selectedProjectId, (string) $request->input('idempotency_key', ''),
                    $validated, (int) Auth::id()
                );
            } catch (\DomainException $e) {
                abort(409, 'Journal posting rejected to protect accounting history.');
            }
            return redirect()->route('journal.voucher.index')->with('success', 'Journal voucher posted successfully.');
        }

        // Guard the REAL posting path, not only an audit CLI: table presence
        // without required columns and unique constraints is not safe.
        $metadataReady = app(\App\Services\Finance\FinancePostingSchemaGate::class)->mode() === 'typed';
        $requestKey = $request->input('idempotency_key');
        // Typed posting must always reserve a durable request identity.
        // Never silently downgrade a metadata-ready install to an unkeyed write.
        if ($metadataReady && $requestKey === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'idempotency_key' => 'A unique request key is required for typed financial posting.',
            ]);
        }
        if ($requestKey !== null && (!$metadataReady || !is_string($requestKey)
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D', $requestKey))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'idempotency_key' => 'Valid request key requires the approved finance metadata schema.',
            ]);
        }

        $post = function () use ($validated, $selectedProjectId, $totalDebitCents, $totalCreditCents, $metadataReady) {
            // Serialize numbering within this project's row (on databases supporting FOR UPDATE).
            Project::whereKey($selectedProjectId)->lockForUpdate()->firstOrFail();
            $nextVoucherNumber = app(\App\Services\Finance\ProjectVoucherAllocator::class)
                ->nextJournal((int) $selectedProjectId);
            $journalVoucher = JournalVoucher::create([
                'voucher_number' => $nextVoucherNumber,
                'type' => 'JV',
                'reference' => $validated['reference'] ?? null,
                'date' => $validated['date'],
                'description' => $validated['description'],
                'total_debit' => intdiv($totalDebitCents, 100) . '.' . str_pad((string) ($totalDebitCents % 100), 2, '0', STR_PAD_LEFT),
                'total_credit' => intdiv($totalCreditCents, 100) . '.' . str_pad((string) ($totalCreditCents % 100), 2, '0', STR_PAD_LEFT),
                'created_by' => auth()->id(),
                'project_id' => $selectedProjectId,
            ]);
            app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)->assign(
                (int) $selectedProjectId, 'journal_voucher', (int) $journalVoucher->id,
                'JV', (string) $validated['date']
            );

            foreach ($validated['accounts'] as $index => $accountId) {
                // Preserve exact validated decimal digits; do not round-trip through float.
                $debitParts = explode('.', (string) $validated['debit'][$index]);
                $creditParts = explode('.', (string) $validated['credit'][$index]);
                $debit = $debitParts[0] . '.' . str_pad($debitParts[1] ?? '', 2, '0');
                $credit = $creditParts[0] . '.' . str_pad($creditParts[1] ?? '', 2, '0');
                $journalDetail = JournalVoucherDetail::create([
                    'journal_voucher_id' => $journalVoucher->id,
                    'account_id' => $validated['sub_accounts'][$index],
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $validated['line_description'][$index] ?? '',
                    'created_by' => auth()->id(),
                ]);
                $ledgerAttributes = [
                    'voucher_number' => $journalVoucher->voucher_number,
                    'type' => 'JV',
                    'type_id' => $journalVoucher->id,
                    'project_head_subheads_id' => $validated['sub_accounts'][$index],
                    'reference' => $validated['reference'] ?? null,
                    'amount_in' => $debit,
                    'amount_out' => $credit,
                    'detail' => $validated['line_description'][$index] ?? '',
                    'create_by' => auth()->id(),
                    'is_active' => true,
                    'status' => '0',
                    'date' => $validated['date'],
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('ledgers', 'journal_voucher_detail_id')) {
                    $ledgerAttributes['journal_voucher_detail_id'] = $journalDetail->id;
                }
                $ledger = Ledger::create($ledgerAttributes);
                if ($metadataReady) {
                    app(\App\Services\Finance\LedgerSourceAttributionWriter::class)
                        ->recordNewPosting((int) $ledger->id, (int) $selectedProjectId,
                            'journal_voucher', (int) $journalVoucher->id);
                }
            }
            app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class)
                ->submitIfRequired((int) $selectedProjectId, 'JV', 'journal_vouchers', (int) $journalVoucher->id, (int) auth()->id());
            if (app(\App\Services\Finance\DailyVoucherApprovalWorkflow::class)->policy((int) $selectedProjectId, 'JV')) {
                DB::table('ledgers')->where('type', 'JV')->where('type_id', $journalVoucher->id)
                    ->whereIn('project_head_subheads_id', DB::table('project_head_subheads')->select('id')->where('project_id', $selectedProjectId))
                    ->update(['is_active' => 0]);
            }
            return (int) $journalVoucher->id;
        };

        $savedVoucherId = null;
        if ($requestKey !== null) {
            // A retry must match metadata AND amounts, not just the debit total.
            $canonical = [
                'reference' => $validated['reference'] ?? null,
                'date' => $validated['date'],
                'description' => $validated['description'],
                'lines' => [],
            ];
            foreach ($validated['accounts'] as $index => $headId) {
                $d = explode('.', (string) $validated['debit'][$index]);
                $c = explode('.', (string) $validated['credit'][$index]);
                $canonical['lines'][] = [
                    (int) $headId, (int) $validated['sub_accounts'][$index],
                    (int) $d[0] * 100 + (int) str_pad($d[1] ?? '', 2, '0'),
                    (int) $c[0] * 100 + (int) str_pad($c[1] ?? '', 2, '0'),
                    $validated['line_description'][$index] ?? '',
                ];
            }
            $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
            $posting = app(\App\Services\Finance\DurableFinancialRequest::class)->execute(
                'journal_voucher', (int) $selectedProjectId, $requestKey, $hash, $post
            );
            $savedVoucherId = (int) $posting['voucher_id'];
            if ($posting['replayed']) {
                app(\App\Services\Finance\JournalVoucherReplayIntegrity::class)
                    ->assertIntact((int) $selectedProjectId, (int) $posting['voucher_id']);
            }
        } else {
            $savedVoucherId = (int) DB::transaction($post);
        }

        $hasPendingApproval=\App\Models\DailyVoucherApproval::query()
            ->where('project_id',(int)$selectedProjectId)->where('source_table','journal_vouchers')
            ->where('source_id',$savedVoucherId)
            ->where('status','pending')->exists();
        return redirect()->route('journal.voucher.index')->with('success',
            $hasPendingApproval?'Journal Voucher saved — Pending Approval. Ledger posting is inactive until approval.':'Journal Voucher created successfully.');
    }

    // public function edit($id)
    // {
    //     $voucher = JournalVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail($id);
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
                    $projectId, 'journal', (int) $id, $data['request_key'],
                    $data['effective_date'], $data['reason'], (int) Auth::id()
                );
            } catch (\DomainException $exception) {
                abort(409, 'Journal reversal rejected to protect accounting history.');
            }
        } else {
            $data = request()->validate([
                'effective_date' => ['nullable', 'date_format:Y-m-d'],
                'reason' => ['nullable', 'string', 'max:200'],
            ]);
            DB::transaction(function () use ($id, $projectId, $data): void {
                $voucher = JournalVoucher::whereKey((int) $id)->where('project_id', $projectId)
                    ->where(fn ($q) => $q->where('type', 'JV')->orWhereNull('type'))
                    ->lockForUpdate()->firstOrFail();
                app(\App\Services\Finance\VoucherApprovalMutationGuard::class)
                    ->assertLegacyMutationAllowed($projectId, 'journal_vouchers', (int) $voucher->id);
                $ledgers = Ledger::query()->withoutGlobalScopes()->where('type', 'JV')
                    ->where('type_id', $voucher->id)->where('is_active', 1)
                    ->whereIn('project_head_subheads_id', ProjectHeadSubhead::where('project_id', $projectId)->select('id'))
                    ->lockForUpdate()->get();
                abort_if($ledgers->isEmpty(), 409, 'JV ledger entries are missing; reversal stopped.');
                $date = $data['effective_date'] ?? now()->toDateString();
                foreach ($ledgers as $old) {
                    Ledger::create([
                        'voucher_number' => $voucher->voucher_number, 'type' => 'JV', 'type_id' => $voucher->id,
                        'project_head_subheads_id' => $old->project_head_subheads_id,
                        'reference' => $voucher->reference, 'amount_in' => $old->amount_out,
                        'amount_out' => $old->amount_in, 'date' => $date, 'is_active' => 1, 'status' => '0',
                        'create_by' => Auth::id(), 'detail' => 'Voucher reversal: '.($data['reason'] ?? $old->detail),
                    ]);
                }
                JournalVoucherDetail::where('journal_voucher_id', $voucher->id)->get()->each->delete();
                $voucher->delete();
            });
        }
        return redirect()->route('journal.voucher.index')
            ->with('success', 'Journal voucher reversed; original accounting history preserved.');
    }

    public function edit($id)
    {
        $voucher = JournalVoucher::with('details')->where('project_id', getSelectedTown())->findOrFail($id);
        $x = $this->getCommonData('Edit Journal Voucher');
        $x['voucher'] = $voucher;
        $x['isRevision']=$isRevision=request()->boolean('resubmit');
        if($isRevision){
            abort_if(\App\Services\Finance\V2OriginalWorkflowAccess::selected(),409,'Rejected JV revision is unavailable in V2 mode.');
            $approval=\App\Models\DailyVoucherApproval::query()
                ->where('project_id',(int)getSelectedTown())->where('voucher_type','JV')
                ->where('source_table','journal_vouchers')->where('source_id',(int)$voucher->id)
                ->where('status','rejected')->firstOrFail();
            abort_unless(\App\Services\Finance\VoucherApproverAccess::isSuperAdmin(auth()->user())
                || (int)$voucher->created_by===(int)auth()->id(),403);
            $x['rejectionReason']=$approval->rejection_reason;
        }else{
            app(\App\Services\Finance\VoucherApprovalMutationGuard::class)
                ->assertLegacyMutationAllowed((int) getSelectedTown(), 'journal_vouchers', (int) $voucher->id);
        }

        // Get selected town's project_id
        $x['selectedProjectId'] = $selectedProjectId = getSelectedTown();

        // Fetch projects and accounts
        $x['projects'] = Project::where('id', $selectedProjectId)->get();
        $x['accounts'] = ProjectHeadSubhead::availableForPosting()->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where('project_id', $selectedProjectId)
            ->get();

        return view('admin.reports.vouchers.edit_journal_voucher', $x);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
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
                    $field => 'Journal line arrays must share identical keys.',
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
                    'sub_accounts' => 'Journal account/head mismatch or foreign project.',
                ]);
            }
            $debitCents = $this->moneyToCents((string) $data['debit'][$index]);
            $creditCents = $this->moneyToCents((string) $data['credit'][$index]);
            if (($debitCents === 0) === ($creditCents === 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'debit' => 'Each journal row must contain either debit or credit, but not both.',
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

        if ($request->routeIs('journal.voucher.resubmit')) {
            abort_if(\App\Services\Finance\V2OriginalWorkflowAccess::selected(),409,'Rejected JV revision is unavailable in V2 mode.');
            $reason=$request->validate(['revision_reason'=>['required','string','min:3','max:500']])['revision_reason'];
            $revisionLines=[];
            foreach($keys as $i){
                $revisionLines[]=[
                    'account_id'=>(int)$data['sub_accounts'][$i],
                    'debit_cents'=>$this->moneyToCents((string)$data['debit'][$i]),
                    'credit_cents'=>$this->moneyToCents((string)$data['credit'][$i]),
                    'description'=>(string)($data['line_description'][$i]??''),
                ];
            }
            try{
                app(\App\Services\Finance\RejectedJournalVoucherResubmission::class)
                    ->resubmit($projectId,(int)$id,(int)Auth::id(),$data,$revisionLines,$reason);
            }catch(\DomainException $exception){
                abort(409,$exception->getMessage());
            }
            return redirect()->route('journal.voucher.index')
                ->with('success','Rejected JV revised and resubmitted for approval. Ledger remains inactive.');
        }

        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
            try {
                (new \App\Services\Finance\V2JournalDraftWorkflow())
                    ->updateUnposted($projectId, (int) $id, $data['date'], $lines);
            } catch (\DomainException $exception) {
                abort(409, 'Journal voucher cannot be edited after posting or legacy ledger attribution.');
            }
        } else {
            DB::transaction(function () use ($id, $projectId, $data, $lines, $totalDebitCents, $totalCreditCents): void {
                $voucher = JournalVoucher::whereKey((int) $id)->where('project_id', $projectId)
                    ->where(function ($query) { $query->where('type', 'JV')->orWhereNull('type'); })
                    ->lockForUpdate()->firstOrFail();
                app(\App\Services\Finance\VoucherApprovalMutationGuard::class)
                    ->assertLegacyMutationAllowed($projectId, 'journal_vouchers', (int) $voucher->id);
                $oldDetails = JournalVoucherDetail::where('journal_voucher_id', $voucher->id)
                    ->lockForUpdate()->get();
                $oldLedgers = Ledger::query()->withoutGlobalScopes()->where('type', 'JV')
                    ->where('type_id', $voucher->id)->where('is_active', 1)
                    ->whereIn('project_head_subheads_id', ProjectHeadSubhead::where('project_id', $projectId)->select('id'))
                    ->lockForUpdate()->get();
                abort_if($oldLedgers->isEmpty(), 409, 'JV ledger entries are missing; update stopped without changes.');
                $typed = app(\App\Services\Finance\FinancePostingSchemaGate::class)->mode() === 'typed';

                // Append exact opposite legs first. The original posted history is
                // retained; reports see original + reversal + corrected posting.
                foreach ($oldLedgers as $old) {
                    $reversal = Ledger::create([
                        'voucher_number' => $voucher->voucher_number, 'type' => 'JV', 'type_id' => $voucher->id,
                        'project_head_subheads_id' => $old->project_head_subheads_id,
                        'reference' => $voucher->reference, 'amount_in' => $old->amount_out,
                        'amount_out' => $old->amount_in,
                        'detail' => 'Correction reversal: '.($old->detail ?: $voucher->description),
                        'create_by' => Auth::id(), 'is_active' => 1, 'status' => '0', 'date' => $data['date'],
                    ]);
                    if ($typed) app(\App\Services\Finance\LedgerSourceAttributionWriter::class)
                        ->recordNewPosting((int) $reversal->id, $projectId, 'journal_voucher', (int) $voucher->id);
                }

                foreach ($oldDetails as $detail) $detail->delete();
                $voucher->update([
                    'reference' => $data['reference'] ?? null, 'description' => $data['description'],
                    'date' => $data['date'], 'total_debit' => $this->centsToMoney($totalDebitCents),
                    'total_credit' => $this->centsToMoney($totalCreditCents), 'updated_by' => Auth::id(),
                ]);
                foreach ($lines as $line) {
                    $detail = JournalVoucherDetail::create([
                        'journal_voucher_id' => $voucher->id, 'account_id' => $line['account_id'],
                        'debit' => $line['debit'], 'credit' => $line['credit'],
                        'description' => $line['description'], 'created_by' => Auth::id(),
                    ]);
                    $attributes = [
                        'voucher_number' => $voucher->voucher_number, 'type' => 'JV', 'type_id' => $voucher->id,
                        'project_head_subheads_id' => $line['account_id'], 'reference' => $data['reference'] ?? null,
                        'amount_in' => $line['debit'], 'amount_out' => $line['credit'],
                        'detail' => $line['description'], 'create_by' => Auth::id(), 'is_active' => 1,
                        'status' => '0', 'date' => $data['date'],
                    ];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('ledgers', 'journal_voucher_detail_id')) {
                        $attributes['journal_voucher_detail_id'] = $detail->id;
                    }
                    $ledger = Ledger::create($attributes);
                    if ($typed) app(\App\Services\Finance\LedgerSourceAttributionWriter::class)
                        ->recordNewPosting((int) $ledger->id, $projectId, 'journal_voucher', (int) $voucher->id);
                }
            });
        }
        return redirect()->route('journal.voucher.edit', ['id' => $id])
            ->with('success', 'Journal voucher corrected successfully; previous posting history was preserved.');
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
        $voucher = JournalVoucher::with('details.account.headAccounting', 'details.account.subheadAccounting')->where('project_id', getSelectedTown())->findOrFail($id);
        $voucherDisplayNumber = app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)
            ->lookup((int) getSelectedTown(), 'journal_voucher', (int) $voucher->id)
            ?: (($voucher->type ?: 'JV') === 'SV'
                ? 'SV-' . (int) $voucher->voucher_number
                : 'JV-' . get_jv_number($voucher->voucher_number));
        return view('admin.reports.vouchers.print_journal_voucher', compact('voucher', 'voucherDisplayNumber'));
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
