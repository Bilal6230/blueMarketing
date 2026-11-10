<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ProjectHeadSubhead;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChequeIntakeController extends Controller
{
    private function project(): int
    {
        $project = (int) getSelectedTown();
        abort_unless($project > 0 && DB::table('projects')->where('id', $project)->exists(), 403);
        return $project;
    }

    private function ready(): void
    {
        abort_unless(Schema::hasTable('finance_cheque_intakes'), 409, 'Cheque intake migration must be reviewed and applied first.');
    }

    public function index()
    {
        $this->ready();
        $project = $this->project();
        // CR Voucher uses the same active project account mappings.
        $accountMappings = ProjectHeadSubhead::availableForPosting()
            ->with(['headAccounting:id,name','subheadAccounting:id,name'])
            ->where('project_id',$project)->orderBy('head_accounting_id')
            ->orderBy('subhead_accounting_id')->get()
            ->map(fn($account) => [
                'id'=>(int)$account->id,
                'head_id'=>(int)$account->head_accounting_id,
                'head_name'=>(string)($account->headAccounting?->name ?? ''),
                'subhead_id'=>(int)$account->subhead_accounting_id,
                'subhead_name'=>(string)($account->subheadAccounting?->name ?? ''),
            ]);
        $headAccounts = $accountMappings->unique('head_id')->values();
        $bankAccounts = Schema::hasTable('project_bank_accounts')
            ? DB::table('project_bank_accounts')->where('project_id', $project)
                ->where('is_active', 1)->orderBy('bank_name')->get(['id', 'bank_name', 'account_title', 'branch'])
            : collect();
        return view('admin.finance.cheque.intake', compact('accountMappings', 'headAccounts', 'bankAccounts'));
    }

    public function data(Request $request)
    {
        $this->ready();
        $project = $this->project();
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'account_mapping_id' => ['nullable','integer',Rule::exists('project_head_subheads','id')->where('project_id',$project)],
            'search' => ['nullable', 'string', 'max:150'],
            'cheque_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'received_from' => ['nullable', 'string', 'max:150'],
            'purpose' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:received,issued,pending_clearing,cleared,returned,rejected,withdrawn'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = DB::table('finance_cheque_intakes as c')->where('c.project_id', $project);
        if (!empty($filters['from'])) $query->whereDate('c.received_date', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->whereDate('c.received_date', '<=', $filters['to']);
        if (!empty($filters['account_mapping_id'])) $query->where('c.account_mapping_id',(int)$filters['account_mapping_id']);
        if (!empty($filters['status'])) $query->where('c.status', $filters['status']);
        foreach (['cheque_number', 'bank_name', 'received_from', 'purpose'] as $column) {
            if (!empty($filters[$column])) {
                $query->where('c.'.$column, 'like', '%'.$filters[$column].'%');
            }
        }
        if (!empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('c.cheque_number', 'like', $search)
                    ->orWhere('c.bank_name', 'like', $search)
                    ->orWhere('c.received_from', 'like', $search)
                    ->orWhere('c.purpose', 'like', $search)
                    ->orWhere('c.cheque_title','like',$search);
            });
        }
        $page = $query->orderByDesc('c.id')
            ->paginate((int) ($filters['per_page'] ?? 25), ['c.id', 'c.cheque_number', 'c.cheque_date', 'c.received_date',
                'c.amount', 'c.bank_name', 'c.received_from', 'c.purpose', 'c.status', 'c.direction','c.cheque_title'], 'page', (int) ($filters['page'] ?? 1));
        return response()->json([
            'data' => $page->items(),
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ]);
    }

    public function slip(int $id)
    {
        $this->ready();
        $project = $this->project();
        $cheque = DB::table('finance_cheque_intakes')
            ->where('project_id', $project)->where('id', $id)->first();
        abort_unless($cheque, 404);
        $mapping = $cheque->account_mapping_id
            ? ProjectHeadSubhead::with(['headAccounting:id,name','subheadAccounting:id,name'])
                ->where('project_id',$project)->where('id',$cheque->account_mapping_id)->first()
            : null;
        $customer = $cheque->customer_id
            ? DB::table('leads')->where('project_id',$project)->where('id',$cheque->customer_id)
                ->first(['first_name','last_name'])
            : null;
        $receiver = DB::table('users')->where('id', $cheque->created_by)->value('name');
        $slipNumber = 'CHR-'. $project .'-'. str_pad((string) $cheque->id, 6, '0', STR_PAD_LEFT);
        return response()->view('admin.finance.cheque.receiving_slip', compact(
            'cheque', 'customer', 'mapping', 'receiver', 'slipNumber'
        ))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function act(Request $request, int $id)
    {
        abort_unless($request->user()?->can('create voucher'), 403);
        $this->ready();
        abort_unless(Schema::hasTable('finance_cheque_intake_actions'), 409,
            'Cheque action history migration must be applied first.');
        $project = $this->project();
        $data = $request->validate([
            'action' => ['required', Rule::in(['clearing', 'withdrawal'])],
            'action_date' => ['required', 'date_format:Y-m-d'],
            'bank_account_id' => ['required_if:action,clearing', 'nullable', 'integer'],
            'bank_name' => ['required_if:action,withdrawal', 'nullable', 'string', 'max:150'],
            'branch' => ['nullable', 'string', 'max:150'],
            'collector_name' => ['required_if:action,withdrawal', 'nullable', 'string', 'max:150'],
            'collector_cnic' => ['nullable', 'string', 'regex:/^([0-9]{5}-?[0-9]{7}-?[0-9])?$/D'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $action = $data['action'];
        $bankAccount = null;
        if ($action === 'clearing') {
            abort_unless(Schema::hasTable('project_bank_accounts'), 409, 'Project bank configuration is missing.');
            $bankAccount = DB::table('project_bank_accounts')->where('project_id', $project)
                ->where('id', (int) $data['bank_account_id'])->where('is_active', 1)->first();
            if (!$bankAccount) {
                throw ValidationException::withMessages(['bank_account_id' => 'Select an active bank account for this project.']);
            }
        }
        $record = DB::transaction(function () use ($project, $id, $data, $action, $bankAccount, $request) {
            $cheque = DB::table('finance_cheque_intakes')->where('project_id', $project)
                ->where('id', $id)->lockForUpdate()->first();
            abort_unless($cheque, 404);
            $expectedStatus = ($cheque->direction ?? 'in') === 'out' ? 'issued' : 'received';
            if ($cheque->status !== $expectedStatus) {
                throw ValidationException::withMessages(['action' => 'Cheque cannot be processed from its current status.']);
            }
            if (($cheque->direction ?? 'in') === 'out' && $action === 'withdrawal') {
                throw ValidationException::withMessages(['action' => 'Outgoing cheques cannot use direct cash withdrawal.']);
            }
            if ($data['action_date'] < $cheque->received_date) {
                throw ValidationException::withMessages(['action_date' => 'Action date cannot be before the cheque receiving date.']);
            }
            $to = $action === 'clearing' ? 'pending_clearing' : 'withdrawn';
            $bankName = $bankAccount ? $bankAccount->bank_name : trim($data['bank_name']);
            $branch = $bankAccount ? $bankAccount->branch : ($data['branch'] ?? null);
            DB::table('finance_cheque_intake_actions')->insert([
                'project_id' => $project,
                'cheque_intake_id' => (int) $cheque->id,
                'action' => $action,
                'from_status' => $cheque->status,
                'to_status' => $to,
                'action_date' => $data['action_date'],
                'bank_account_id' => $bankAccount?->id,
                'bank_name' => $bankName,
                'branch' => $branch,
                'collector_name' => $action === 'withdrawal' ? $data['collector_name'] : null,
                'collector_cnic' => $action === 'withdrawal' ? ($data['collector_cnic'] ?? null) : null,
                'remarks' => $data['remarks'] ?? null,
                'original_amount' => $cheque->amount,
                'actor_id' => (int) $request->user()->id,
                'created_at' => now(),
            ]);
            DB::table('finance_cheque_intakes')->where('project_id', $project)
                ->where('id', $id)->where('status', 'received')
                ->update(['status' => $to, 'updated_at' => now()]);
            return ['id' => $cheque->id, 'status' => $to, 'amount' => $cheque->amount];
        }, 3);
        return response()->json([
            'status' => 'success',
            'message' => $action === 'clearing'
                ? 'Cheque submitted for bank clearing. No bank receipt voucher was posted.'
                : 'Direct withdrawal recorded. No bank withdrawal voucher was posted.',
            'data' => $record,
        ]);
    }

    public function history(int $id)
    {
        $this->ready();
        abort_unless(Schema::hasTable('finance_cheque_intake_actions'), 409);
        $project = $this->project();
        $cheque = DB::table('finance_cheque_intakes')
            ->where('project_id', $project)->where('id', $id)->first();
        abort_unless($cheque, 404);
        $events = DB::table('finance_cheque_intake_actions as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->where('a.project_id', $project)->where('a.cheque_intake_id', $id)
            ->orderBy('a.id')
            ->get(['a.id','a.action','a.from_status','a.to_status','a.action_date',
                'a.bank_name','a.branch','a.original_amount','a.remarks','a.reason',
                'a.bank_statement_reference','a.verification_confirmed_at',
                'a.memo_path','a.actor_id','a.created_at','u.name as actor_name']);
        return response()->json([
            'cheque' => ['id' => $cheque->id, 'cheque_number' => $cheque->cheque_number,
                'status' => $cheque->status, 'amount' => $cheque->amount,
                'direction' => $cheque->direction],
            'events' => $events->map(function ($event) use ($id) {
                $event->has_memo = (bool) $event->memo_path;
                $event->memo_url = $event->has_memo
                    ? route('finance.cheques.memo', ['id'=>$id,'event'=>$event->id]) : null;
                unset($event->memo_path);
                return $event;
            }),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function memo(int $id, int $event)
    {
        $this->ready();
        $project = $this->project();
        $record = DB::table('finance_cheque_intake_actions as a')
            ->join('finance_cheque_intakes as c', function ($join) {
                $join->on('c.id','=','a.cheque_intake_id')
                    ->on('c.project_id','=','a.project_id');
            })
            ->where('a.project_id',$project)->where('a.cheque_intake_id',$id)
            ->where('a.id',$event)->first(['a.memo_path']);
        abort_unless($record && $record->memo_path,404);
        abort_unless(Storage::disk('local')->exists($record->memo_path),404);
        return Storage::disk('local')->download($record->memo_path);
    }

    public function finalize(Request $request, int $id)
    {
        abort_unless($request->user()?->can('create voucher'),403);
        $this->ready();
        abort_unless(Schema::hasTable('finance_cheque_intake_actions')
            && Schema::hasColumn('finance_cheque_intake_actions','verification_confirmed_at'),
            409,'Cheque finalization migration must be applied first.');
        $project = $this->project();
        $data = $request->validate([
            'decision' => ['required', Rule::in(['cleared','returned','rejected'])],
            'action_date' => ['required','date_format:Y-m-d'],
            'bank_statement_verified' => ['accepted_if:decision,cleared'],
            'bank_statement_reference' => ['required_if:decision,cleared','nullable','string','max:150'],
            'reason' => ['required_if:decision,returned,rejected','nullable','string','max:1000'],
            'remarks' => ['nullable','string','max:2000'],
            'bank_memo' => ['required_if:decision,returned,rejected','nullable','file',
                'mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);
        $decision = $data['decision'];
        // File remains private and accessible only through scoped authenticated download.
        $memoPath = null;
        if ($request->hasFile('bank_memo')) {
            $memoPath = $request->file('bank_memo')
                ->store('finance/cheque-intakes/'.$project.'/memos','local');
        }
        try {
            $result = DB::transaction(function () use ($project,$id,$decision,$data,$memoPath,$request) {
                $cheque = DB::table('finance_cheque_intakes')->where('project_id',$project)
                    ->where('id',$id)->lockForUpdate()->first();
                abort_unless($cheque,404);
                if ($cheque->status !== 'pending_clearing') {
                    throw ValidationException::withMessages([
                        'decision'=>'Only cheques in Pending Clearing can be cleared, returned or rejected.'
                    ]);
                }
                $clearing = DB::table('finance_cheque_intake_actions')
                    ->where('project_id',$project)->where('cheque_intake_id',$id)
                    ->where('action','clearing')->orderByDesc('id')->first();
                abort_unless($clearing && $clearing->bank_account_id,409,
                    'The original clearing bank account could not be verified.');
                if ($data['action_date'] < $clearing->action_date) {
                    throw ValidationException::withMessages([
                        'action_date'=>'Decision date cannot be before bank clearing submission.'
                    ]);
                }
                DB::table('finance_cheque_intake_actions')->insert([
                    'project_id'=>$project,'cheque_intake_id'=>$id,
                    'action'=>$decision,'from_status'=>'pending_clearing',
                    'to_status'=>$decision,'action_date'=>$data['action_date'],
                    'bank_account_id'=>$clearing->bank_account_id,
                    'bank_name'=>$clearing->bank_name,'branch'=>$clearing->branch,
                    'collector_name'=>null,'collector_cnic'=>null,
                    'remarks'=>$data['remarks']??null,
                    'original_amount'=>$cheque->amount,
                    'actor_id'=>(int)$request->user()->id,
                    'verification_confirmed_at'=>$decision==='cleared'?now():null,
                    'bank_statement_reference'=>$decision==='cleared'
                        ?trim($data['bank_statement_reference']):null,
                    'reason'=>$decision==='cleared'?null:trim($data['reason']),
                    'memo_path'=>$decision==='cleared'?null:$memoPath,
                    'created_at'=>now(),
                ]);
                $changed = DB::table('finance_cheque_intakes')->where('project_id',$project)
                    ->where('id',$id)->where('status','pending_clearing')
                    ->update(['status'=>$decision,'updated_at'=>now()]);
                if ($changed !== 1) {
                    throw new \RuntimeException('Cheque status changed during confirmation.');
                }
                return ['id'=>$id,'status'=>$decision,'amount'=>$cheque->amount];
            },3);
        } catch (\Throwable $exception) {
            if ($memoPath) Storage::disk('local')->delete($memoPath);
            throw $exception;
        }
        return response()->json([
            'status'=>'success',
            'message'=>match($decision){
                'cleared'=>'Cheque clearance verified and recorded. No accounting voucher was posted.',
                'returned'=>'Cheque returned / bounced with bank memo recorded.',
                default=>'Cheque rejected with memo recorded.'
            },
            'data'=>$result,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->can('create voucher'), 403);
        $this->ready();
        $project = $this->project();
        $data = $request->validate([
            'cheque_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\\/_-]+$/D'],
            'cheque_title' => ['required','string','max:200'],
            'direction' => ['required',Rule::in(['in','out'])],
            'head_accounting_id' => ['required','integer'],
            'subhead_accounting_id' => ['required','integer'],
            'cheque_date' => ['required', 'date_format:Y-m-d'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^[0-9]{1,13}(?:\\.[0-9]{1,2})?$/D', 'not_in:0,0.0,0.00'],
            'bank_name' => ['required', 'string', 'max:150'],
            'branch' => ['nullable', 'string', 'max:150'],
            'received_from' => ['required', 'string', 'max:150'],
            'purpose' => ['required', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $account = ProjectHeadSubhead::availableForPosting()
            ->where('project_id',$project)
            ->where('head_accounting_id',(int)$data['head_accounting_id'])
            ->where('subhead_accounting_id',(int)$data['subhead_accounting_id'])->first();
        if (!$account) {
            throw ValidationException::withMessages(['subhead_accounting_id'=>'Select a valid Head / Child Account for the current project.']);
        }
        $number = strtoupper(trim($data['cheque_number']));
        $bank = trim($data['bank_name']);
        if (DB::table('finance_cheque_intakes')->where('project_id', $project)
            ->where('bank_name', $bank)->where('cheque_number', $number)->exists()) {
            throw ValidationException::withMessages(['cheque_number' => 'This bank and cheque number already exist for this project.']);
        }
        $path = null;
        try {
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('finance/cheque-intakes/'.$project, 'local');
            }
            $id = DB::table('finance_cheque_intakes')->insertGetId([
                'project_id' => $project,
                'customer_id' => $account->customer_id ? (int)$account->customer_id : null,
                'account_mapping_id' => (int)$account->id,
                'cheque_title' => trim($data['cheque_title']),
                'direction' => $data['direction'],
                'cheque_number' => $number,
                'cheque_date' => $data['cheque_date'],
                'received_date' => $data['received_date'],
                'amount' => $data['amount'],
                'bank_name' => $bank,
                'branch' => $data['branch'] ?? null,
                'received_from' => trim($data['received_from']),
                'purpose' => trim($data['purpose']),
                'attachment_path' => $path,
                'status' => $data['direction'] === 'out' ? 'issued' : 'received',
                'created_by' => (int) $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($path) Storage::disk('local')->delete($path);
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['cheque_number' => 'This bank and cheque number already exist for this project.']);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
        // This receiving record never creates customer_ledger or ledgers entries.
        $record = DB::table('finance_cheque_intakes')->where('project_id', $project)->where('id', $id)
            ->first(['id', 'cheque_number', 'cheque_date', 'received_date', 'amount',
                'bank_name', 'received_from', 'purpose', 'status','direction','cheque_title']);
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $data['direction'] === 'out'
                    ? 'Outgoing cheque issued. No bank or accounting voucher was posted.'
                    : 'Cheque received. No bank or accounting voucher has been posted.',
                'data' => $record,
            ], 201);
        }
        return redirect()->route('finance.cheques.index')
            ->with('success', $data['direction'] === 'out'
                ? 'Outgoing cheque issued. No bank or accounting voucher was posted.'
                : 'Cheque received. No bank or accounting voucher has been posted.');
    }
}
