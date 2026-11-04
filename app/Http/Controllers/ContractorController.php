<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\ProjectHeadSubhead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Ledger;

class ContractorController extends Controller
{
    public function index()
    {
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 404);

        return view('admin.contractors.index', [
            'contractors' => Contractor::where('project_id', $projectId)->with('account')->orderBy('name')->get(),
            'accounts' => ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)->with(['headAccounting', 'subheadAccounting'])->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 404);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:5000'],
            'project_head_subhead_id' => ['required', 'integer', 'exists:project_head_subheads,id'],
        ]);
        abort_if(Contractor::where('project_id', $projectId)->where('code', $data['code'])->exists(), 422, 'Contractor code already exists in this project.');

        DB::transaction(function () use ($data, $projectId): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            abort_if(Contractor::where('project_id', $projectId)->where('code', $data['code'])->exists(), 422, 'Contractor code already exists in this project.');
            abort_unless(ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
                ->whereKey($data['project_head_subhead_id'])->lockForUpdate()->exists(), 422, 'Select an active account from this project.');
            $contractor = Contractor::create($data + ['project_id' => $projectId, 'changed_by' => auth()->id(), 'is_active' => true]);
            $this->audit($contractor, 'created', null, $contractor->only(['code', 'name', 'phone', 'address', 'project_head_subhead_id', 'is_active']));
        });
        return back()->with('success', 'Contractor saved.');
    }

    public function update(Request $request, int $contractor)
    {
        $projectId = (int) getSelectedTown();
        $record = Contractor::where('project_id', $projectId)->findOrFail($contractor);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:5000'],
            'project_head_subhead_id' => ['required', 'integer', 'exists:project_head_subheads,id'],
        ]);
        abort_if(Contractor::where('project_id', $projectId)->where('code', $data['code'])->where('id', '<>', $record->id)->exists(), 422, 'Contractor code already exists in this project.');

        DB::transaction(function () use ($record, $data, $projectId): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $locked = Contractor::where('project_id', $projectId)->lockForUpdate()->findOrFail($record->id);
            abort_if(Contractor::where('project_id', $projectId)->where('code', $data['code'])
                ->where('id', '<>', $locked->id)->exists(), 422, 'Contractor code already exists in this project.');
            abort_unless(ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
                ->whereKey($data['project_head_subhead_id'])->lockForUpdate()->exists(), 422, 'Select an active account from this project.');
            $accountChanged = (int) $locked->project_head_subhead_id !== (int) $data['project_head_subhead_id'];
            if ($accountChanged) {
                $hasTaggedHistory = (Schema::hasTable('journal_vouchers') && Schema::hasColumn('journal_vouchers', 'contractor_id') &&
                        DB::table('journal_vouchers')->where('project_id', $projectId)->where('contractor_id', $locked->id)->exists()) ||
                    (Schema::hasTable('customer_ledger') && Schema::hasColumn('customer_ledger', 'contractor_id') &&
                        DB::table('customer_ledger')->where('project_id', $projectId)->where('contractor_id', $locked->id)->exists());
                abort_if($hasTaggedHistory, 409, 'A contractor account cannot be reassigned after linked vouchers exist. Keep the existing account for history and create a new contractor definition for future postings.');
            }
            $before = $locked->only(['code', 'name', 'phone', 'address', 'project_head_subhead_id', 'is_active']);
            $locked->fill($data + ['changed_by' => auth()->id()])->save();
            $this->audit($locked, 'updated', $before, $locked->only(array_keys($before)));
        });
        return back()->with('success', 'Contractor updated.');
    }

    public function toggleStatus(int $contractor)
    {
        $projectId = (int) getSelectedTown();
        DB::transaction(function () use ($contractor, $projectId): void {
            $record = Contractor::where('project_id', $projectId)->lockForUpdate()->findOrFail($contractor);
            $before = ['is_active' => (bool) $record->is_active];
            $record->is_active = !(bool) $record->is_active;
            $record->changed_by = auth()->id();
            $record->save();
            $this->audit($record, 'status_changed', $before, ['is_active' => (bool) $record->is_active]);
        });
        return back()->with('success', 'Contractor status updated.');
    }

    public function ledger(int $contractor)
    {
        $projectId = (int) getSelectedTown();
        $record = Contractor::where('project_id', $projectId)->with('account.subheadAccounting')->findOrFail($contractor);
        $journalIds = DB::table('journal_vouchers')->where('project_id', $projectId)->where('contractor_id', $record->id)->pluck('id');
        $customerLedgerIds = DB::table('customer_ledger')->where('project_id', $projectId)->where('contractor_id', $record->id)->pluck('id');
        // A parent voucher ID is not a substitute for tenant scoping: legacy
        // ledger rows do not all have a reliable parent FK, so constrain both.
        $ledgerQuery = Ledger::query()->join('project_head_subheads as mapping', 'mapping.id', '=', 'ledgers.project_head_subheads_id')
            ->where('mapping.project_id', $projectId)->where('ledgers.is_active', 1);
        if ($journalIds->isEmpty() && $customerLedgerIds->isEmpty()) {
            $ledgerQuery->whereRaw('1 = 0');
        } else {
            $ledgerQuery->where(function ($query) use ($journalIds, $customerLedgerIds): void {
                if ($journalIds->isNotEmpty()) $query->where(fn ($q) => $q->where('type', 'JV')->whereIn('type_id', $journalIds));
                if ($customerLedgerIds->isNotEmpty()) {
                    $method = $journalIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('customer_ledger_id', $customerLedgerIds);
                }
            });
        }
        $rows = $ledgerQuery->orderBy('ledgers.date')->orderBy('ledgers.id')->get([
                'ledgers.id', 'ledgers.date', 'ledgers.type', 'ledgers.voucher_number', 'ledgers.detail', 'ledgers.amount_in', 'ledgers.amount_out',
            ]);
        $running = 0;
        $entries = $rows->map(function ($row) use (&$running): array {
            $in = $this->cents($row->amount_in);
            $out = $this->cents($row->amount_out);
            $running += $in - $out;
            return [
                'date' => $row->date, 'voucher' => trim(($row->type ?? '').'-'.($row->voucher_number ?? ''), '-'),
                'detail' => $row->detail, 'in' => $in, 'out' => $out, 'balance' => $running,
            ];
        });
        return view('admin.contractors.ledger', ['contractor' => $record, 'entries' => $entries, 'balance' => $running]);
    }

    private function cents($value): int
    {
        $value = (string) ($value ?? '0');
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $value)) return 0;
        $negative = str_starts_with($value, '-');
        [$whole, $decimal] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
        return $negative ? -$cents : $cents;
    }

    private function audit(Contractor $record, string $action, ?array $before, array $after): void
    {
        DB::table('contractor_audits')->insert([
            'project_id' => $record->project_id, 'contractor_id' => $record->id,
            'action' => $action, 'before_data' => $before ? json_encode($before) : null,
            'after_data' => json_encode($after), 'actor_id' => auth()->id(), 'created_at' => now(),
        ]);
    }
}
