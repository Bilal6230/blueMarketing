<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
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
        $customers = DB::table('leads')->where('project_id', $project)
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $bankAccounts = Schema::hasTable('project_bank_accounts')
            ? DB::table('project_bank_accounts')->where('project_id', $project)
                ->where('is_active', 1)->orderBy('bank_name')->get(['id', 'bank_name', 'account_title', 'branch'])
            : collect();
        return view('admin.finance.cheque.intake', compact('customers', 'bankAccounts'));
    }

    public function data(Request $request)
    {
        $this->ready();
        $project = $this->project();
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer', Rule::exists('leads', 'id')->where('project_id', $project)],
            'search' => ['nullable', 'string', 'max:150'],
            'cheque_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'received_from' => ['nullable', 'string', 'max:150'],
            'purpose' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:received,pending_clearing,cleared,returned,rejected,withdrawn'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = DB::table('finance_cheque_intakes as c')->where('c.project_id', $project);
        if (!empty($filters['from'])) $query->whereDate('c.received_date', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->whereDate('c.received_date', '<=', $filters['to']);
        if (!empty($filters['customer_id'])) $query->where('c.customer_id', (int) $filters['customer_id']);
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
                    ->orWhere('c.remarks', 'like', $search);
            });
        }
        $page = $query->orderByDesc('c.id')
            ->paginate((int) ($filters['per_page'] ?? 25), ['c.id', 'c.cheque_number', 'c.cheque_date', 'c.received_date',
                'c.amount', 'c.bank_name', 'c.received_from', 'c.purpose', 'c.status'], 'page', (int) ($filters['page'] ?? 1));
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
        $customer = DB::table('leads')->where('project_id', $project)
            ->where('id', $cheque->customer_id)->first(['first_name','last_name']);
        $receiver = DB::table('users')->where('id', $cheque->created_by)->value('name');
        $slipNumber = 'CHR-'. $project .'-'. str_pad((string) $cheque->id, 6, '0', STR_PAD_LEFT);
        return response()->view('admin.finance.cheque.receiving_slip', compact(
            'cheque', 'customer', 'receiver', 'slipNumber'
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
            if ($cheque->status !== 'received') {
                throw ValidationException::withMessages(['action' => 'Only Received cheques can be submitted for clearing or withdrawal.']);
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

    public function store(Request $request)
    {
        abort_unless($request->user()?->can('create voucher'), 403);
        $this->ready();
        $project = $this->project();
        $data = $request->validate([
            'cheque_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\\/_-]+$/D'],
            'cheque_date' => ['required', 'date_format:Y-m-d'],
            'received_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^[0-9]{1,13}(?:\\.[0-9]{1,2})?$/D', 'not_in:0,0.0,0.00'],
            'bank_name' => ['required', 'string', 'max:150'],
            'branch' => ['nullable', 'string', 'max:150'],
            'received_from' => ['required', 'string', 'max:150'],
            'customer_id' => ['required', 'integer', Rule::exists('leads', 'id')->where('project_id', $project)],
            'purpose' => ['required', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
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
                'customer_id' => (int) $data['customer_id'],
                'cheque_number' => $number,
                'cheque_date' => $data['cheque_date'],
                'received_date' => $data['received_date'],
                'amount' => $data['amount'],
                'bank_name' => $bank,
                'branch' => $data['branch'] ?? null,
                'received_from' => trim($data['received_from']),
                'purpose' => trim($data['purpose']),
                'remarks' => $data['remarks'] ?? null,
                'attachment_path' => $path,
                'status' => 'received',
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
                'bank_name', 'received_from', 'purpose', 'status']);
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Cheque received. No bank or accounting voucher has been posted.',
                'data' => $record,
            ], 201);
        }
        return redirect()->route('finance.cheques.index')
            ->with('success', 'Cheque received. No bank or accounting voucher has been posted.');
    }
}
