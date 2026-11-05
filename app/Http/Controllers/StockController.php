<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerLedger;
use App\Models\HeadAccounting;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use App\Models\Stock;
use App\Models\StockBill;
use App\Models\StockBillLine;
use App\Models\StockEntry;
use App\Models\StockEntryLine;
use App\Models\StockItem;
use App\Models\StockParty;
use App\Models\SubheadAccounting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StockController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $selectedProjectId = getSelectedTown();
        $x['title'] = 'Stock';
        $x['stocks'] = Stock::where('project_id', $selectedProjectId)->get();
        $projectStockSchemaReady = Schema::hasColumn('stock_entries', 'project_id')
            && Schema::hasColumn('stock_bills', 'project_id')
            && Schema::hasColumn('stock_movements', 'project_id')
            && Schema::hasColumn('stock_entry_lines', 'project_id')
            && Schema::hasColumn('stock_bill_lines', 'project_id');
        $x['canReconcileLegacyStock'] = $projectStockSchemaReady
            && auth()->user()->hasAnyRole(['admin', 'superadmin'])
            && Schema::hasTable('stock_legacy_reconciliation_audits');
        $x['legacyStockCandidates'] = $x['canReconcileLegacyStock']
            ? $this->legacyStockCandidates((int) $selectedProjectId)
            : ['entries' => collect(), 'bills' => collect(), 'movements' => collect()];
        $x['legacyStockAudits'] = $x['canReconcileLegacyStock']
            ? DB::table('stock_legacy_reconciliation_audits as audit')->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
                ->where('audit.project_id', (int) $selectedProjectId)->orderByDesc('audit.id')->limit(30)
                ->get(['audit.record_type', 'audit.record_id', 'audit.reason', 'audit.created_at', 'actor.name as actor_name'])
            : collect();
        $x['legacyStockStatus'] = [
            'unassigned_entries' => Schema::hasColumn('stock_entries', 'project_id')
                ? DB::table('stock_entries')->whereNull('project_id')->count() : null,
            'unassigned_bills' => Schema::hasColumn('stock_bills', 'project_id')
                ? DB::table('stock_bills')->whereNull('project_id')->count() : null,
            'unassigned_movements' => Schema::hasColumn('stock_movements', 'project_id')
                ? DB::table('stock_movements')->whereNull('project_id')->count() : null,
            'legacy_balance_rows' => DB::table('stock_item_balances')->count(),
        ];
        $data_list = ProjectHeadSubhead::availableForPosting()->select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();
        $x['headaccounts'] = $data_list;
        $x['partyAccountOptions'] = ProjectHeadSubhead::availableForPosting()
            ->where('project_id', (int) $selectedProjectId)->whereNull('plot_id')->whereNull('customer_id')
            ->with(['headAccounting:id,name', 'subheadAccounting:id,name'])->get()
            ->map(fn ($account) => [
                'id' => (int) $account->id,
                'label' => ($account->headAccounting->name ?? 'Head').' / '.($account->subheadAccounting->name ?? 'Sub-account'),
            ])->values();
        return view('admin.stock.index', $x);
    }

    public function reconcileLegacyStock(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'superadmin']), 403, 'Only project administrators can reconcile legacy stock history.');
        abort_unless(Schema::hasTable('stock_legacy_reconciliation_audits')
            && Schema::hasTable('stock_project_item_balances')
            && Schema::hasColumn('stock_entries', 'project_id')
            && Schema::hasColumn('stock_bills', 'project_id')
            && Schema::hasColumn('stock_movements', 'project_id')
            && Schema::hasColumn('stock_entry_lines', 'project_id')
            && Schema::hasColumn('stock_bill_lines', 'project_id'),
            409, 'Project stock reconciliation migrations must be installed first.');

        $data = $request->validate([
            'kind' => ['required', Rule::in(['entry', 'bill', 'movement'])],
            'record_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 409, 'Select a project before reconciling stock history.');
        if (!auth()->user()->hasRole('superadmin')) {
            abort_unless((int) auth()->user()->project_id === $projectId, 403);
        }

        $result = DB::transaction(function () use ($data, $projectId): array {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $changed = [];
            $movementIds = [];
            $itemIds = [];
            $kind = $data['kind'];
            $id = (int) $data['record_id'];

            if ($kind === 'entry') {
                $entry = DB::table('stock_entries')->where('id', $id)->whereNull('project_id')->lockForUpdate()->first();
                abort_unless($entry, 409, 'This stock entry is already assigned or unavailable.');
                $this->assertLegacyStockPartyMapping($entry->party_id, $projectId);
                $this->assertLegacyStockScopeClear('stock_entry_lines', 'entry_id', $id, $projectId);
                $entryLineIds = DB::table('stock_entry_lines')->where('entry_id', $id)->whereNull('project_id')->pluck('id');
                $bills = DB::table('stock_bills')->where('entry_id', $id)->lockForUpdate()->get();
                foreach ($bills as $bill) {
                    abort_if($bill->project_id !== null && (int) $bill->project_id !== $projectId, 409, 'A linked bill belongs to another project.');
                    abort_unless((int) $bill->party_id === (int) $entry->party_id, 409, 'A linked bill uses a different party; reconcile it separately after review.');
                    $this->assertLegacyStockPartyMapping($bill->party_id, $projectId);
                    $this->assertLegacyStockScopeClear('stock_bill_lines', 'bill_id', (int) $bill->id, $projectId);
                }
                $billLineIds = [];
                foreach ($bills as $bill) {
                    $billLineIds = array_merge($billLineIds, DB::table('stock_bill_lines')->where('bill_id', $bill->id)->whereNull('project_id')->pluck('id')->map(fn ($value) => (int) $value)->all());
                }
                $billIds = $bills->pluck('id')->map(fn ($value) => (int) $value)->all();
                $this->assertLegacyMovementScopeClear('entry', [$id], $projectId);
                if ($billIds) $this->assertLegacyMovementScopeClear('bill', $billIds, $projectId);
                DB::table('stock_entries')->where('id', $id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                $changed[] = ['stock_entry', $id];
                DB::table('stock_entry_lines')->where('entry_id', $id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                foreach ($entryLineIds as $lineId) $changed[] = ['stock_entry_line', (int) $lineId];
                foreach ($bills as $bill) {
                    if ($bill->project_id === null) {
                        DB::table('stock_bills')->where('id', $bill->id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                        $changed[] = ['stock_bill', (int) $bill->id];
                    }
                    DB::table('stock_bill_lines')->where('bill_id', $bill->id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                }
                foreach ($billLineIds as $lineId) $changed[] = ['stock_bill_line', $lineId];
                $movementQuery = DB::table('stock_movements')->whereNull('project_id')->where(function ($query) use ($id, $billIds): void {
                    $query->where(fn ($q) => $q->where('source_type', 'entry')->where('source_id', $id));
                    if ($billIds) $query->orWhere(fn ($q) => $q->where('source_type', 'bill')->whereIn('source_id', $billIds));
                })->lockForUpdate();
                foreach ($movementQuery->get() as $movement) {
                    abort_if($movement->party_id !== null && (int) $movement->party_id !== (int) $entry->party_id, 409, 'A linked stock movement uses a different party.');
                    $movementIds[] = (int) $movement->id;
                    $itemIds[] = (int) $movement->item_id;
                    $changed[] = ['stock_movement', (int) $movement->id];
                }
            } elseif ($kind === 'bill') {
                $bill = DB::table('stock_bills')->where('id', $id)->whereNull('project_id')->lockForUpdate()->first();
                abort_unless($bill, 409, 'This stock bill is already assigned or unavailable.');
                $this->assertLegacyStockPartyMapping($bill->party_id, $projectId);
                $this->assertLegacyStockScopeClear('stock_bill_lines', 'bill_id', $id, $projectId);
                $billLineIds = DB::table('stock_bill_lines')->where('bill_id', $id)->whereNull('project_id')->pluck('id');
                $entry = $bill->entry_id ? DB::table('stock_entries')->where('id', $bill->entry_id)->lockForUpdate()->first() : null;
                if ($entry) {
                    abort_if($entry->project_id !== null && (int) $entry->project_id !== $projectId, 409, 'The linked entry belongs to another project.');
                    abort_unless((int) $entry->party_id === (int) $bill->party_id, 409, 'The linked entry uses a different party.');
                    $this->assertLegacyStockPartyMapping($entry->party_id, $projectId);
                    $this->assertLegacyStockScopeClear('stock_entry_lines', 'entry_id', (int) $entry->id, $projectId);
                    $entryLineIds = DB::table('stock_entry_lines')->where('entry_id', $entry->id)->whereNull('project_id')->pluck('id');
                    $this->assertLegacyMovementScopeClear('entry', [(int) $entry->id], $projectId);
                    if ($entry->project_id === null) {
                        DB::table('stock_entries')->where('id', $entry->id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                        $changed[] = ['stock_entry', (int) $entry->id];
                    }
                    DB::table('stock_entry_lines')->where('entry_id', $entry->id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                    foreach ($entryLineIds as $lineId) $changed[] = ['stock_entry_line', (int) $lineId];
                }
                $this->assertLegacyMovementScopeClear('bill', [$id], $projectId);
                DB::table('stock_bills')->where('id', $id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                DB::table('stock_bill_lines')->where('bill_id', $id)->whereNull('project_id')->update(['project_id' => $projectId, 'updated_at' => now()]);
                $changed[] = ['stock_bill', $id];
                foreach ($billLineIds as $lineId) $changed[] = ['stock_bill_line', (int) $lineId];
                $sourceIds = [$id];
                $entryId = $entry ? (int) $entry->id : null;
                $movementQuery = DB::table('stock_movements')->whereNull('project_id')->where(function ($query) use ($sourceIds, $entryId): void {
                    $query->where(fn ($q) => $q->where('source_type', 'bill')->whereIn('source_id', $sourceIds));
                    if ($entryId) $query->orWhere(fn ($q) => $q->where('source_type', 'entry')->where('source_id', $entryId));
                })->lockForUpdate();
                foreach ($movementQuery->get() as $movement) {
                    abort_if($movement->party_id !== null && (int) $movement->party_id !== (int) $bill->party_id, 409, 'A linked stock movement uses a different party.');
                    $movementIds[] = (int) $movement->id;
                    $itemIds[] = (int) $movement->item_id;
                    $changed[] = ['stock_movement', (int) $movement->id];
                }
            } else {
                $movement = DB::table('stock_movements')->where('id', $id)->whereNull('project_id')->lockForUpdate()->first();
                abort_unless($movement, 409, 'This stock movement is already assigned or unavailable.');
                abort_unless($movement->party_id !== null, 422, 'A movement without a party cannot be safely assigned.');
                $this->assertLegacyStockPartyMapping($movement->party_id, $projectId, true);
                if ($movement->source_type === 'entry') {
                    abort_if(DB::table('stock_entries')->where('id', $movement->source_id)->exists(), 409, 'Reconcile the linked stock entry instead of assigning this movement alone.');
                } elseif ($movement->source_type === 'bill') {
                    abort_if(DB::table('stock_bills')->where('id', $movement->source_id)->exists(), 409, 'Reconcile the linked stock bill instead of assigning this movement alone.');
                }
                $movementIds[] = (int) $movement->id;
                $itemIds[] = (int) $movement->item_id;
                $changed[] = ['stock_movement', (int) $movement->id];
            }

            foreach ($movementIds as $movementId) {
                DB::table('stock_movements')->where('id', $movementId)->whereNull('project_id')
                    ->update(['project_id' => $projectId, 'updated_at' => now()]);
            }
            $changed = collect($changed)->unique(fn ($row) => $row[0].':'.$row[1])->values();
            foreach ($changed as [$recordType, $recordId]) {
                DB::table('stock_legacy_reconciliation_audits')->insert([
                    'record_type' => $recordType, 'record_id' => $recordId, 'project_id' => $projectId,
                    'reason' => trim($data['reason']), 'actor_id' => auth()->id(), 'created_at' => now(),
                ]);
            }

            foreach (array_unique($itemIds) as $itemId) {
                $balance = DB::table('stock_movements')->where('project_id', $projectId)->where('item_id', $itemId)
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN qty ELSE -qty END), 0) as on_hand_qty")
                    ->value('on_hand_qty');
                DB::table('stock_project_item_balances')->upsert([[
                    'project_id' => $projectId, 'item_id' => $itemId, 'on_hand_qty' => $balance,
                    'created_at' => now(), 'updated_at' => now(),
                ]], ['project_id', 'item_id'], ['on_hand_qty', 'updated_at']);
            }

            return ['count' => $changed->count(), 'kind' => $kind];
        }, 3);

        return redirect()->route('stocks.index')->with('success', $result['count'].' legacy stock record(s) assigned to this project and audit logged. No movement amounts were changed.');
    }

    private function assertLegacyStockPartyMapping(int|string|null $partyId, int $projectId, bool $mustBeUnique = false): void
    {
        $mapping = $partyId
            ? DB::table('stock_party_project_accounts')->where('project_id', $projectId)
                ->where('stock_party_id', $partyId)->lockForUpdate()->first()
            : null;
        abort_unless($mapping, 422, 'The stock party must have an account mapping in the selected project.');
        if ($mustBeUnique) {
            $projectCount = DB::table('stock_party_project_accounts')->where('stock_party_id', $partyId)->distinct('project_id')->count('project_id');
            abort_unless($projectCount === 1, 422, 'This stock party is mapped to multiple projects; a standalone movement needs manual review.');
        }
    }

    private function assertLegacyStockScopeClear(string $table, string $parentColumn, int $parentId, int $projectId): void
    {
        abort_if(DB::table($table)->where($parentColumn, $parentId)->whereNotNull('project_id')->where('project_id', '<>', $projectId)->exists(),
            409, 'A linked stock line is already assigned to another project.');
    }

    private function assertLegacyMovementScopeClear(string $sourceType, array $sourceIds, int $projectId): void
    {
        abort_if(DB::table('stock_movements')->where('source_type', $sourceType)->whereIn('source_id', $sourceIds)
            ->whereNotNull('project_id')->where('project_id', '<>', $projectId)->exists(),
            409, 'A linked stock movement is already assigned to another project.');
    }

    private function legacyStockCandidates(int $projectId): array
    {
        $entries = DB::table('stock_entries as e')->join('stock_parties as p', 'p.id', '=', 'e.party_id')
            ->whereNull('e.project_id')->whereExists(fn ($q) => $q->selectRaw('1')->from('stock_party_project_accounts as map')
                ->whereColumn('map.stock_party_id', 'e.party_id')->where('map.project_id', $projectId))
            ->orderBy('e.id')->limit(50)->get(['e.id', 'e.slip_no', 'e.entry_date', 'e.flow', 'e.deleted_at', 'p.name as party_name']);
        $bills = DB::table('stock_bills as b')->join('stock_parties as p', 'p.id', '=', 'b.party_id')
            ->whereNull('b.project_id')->whereExists(fn ($q) => $q->selectRaw('1')->from('stock_party_project_accounts as map')
                ->whereColumn('map.stock_party_id', 'b.party_id')->where('map.project_id', $projectId))
            ->orderBy('b.id')->limit(50)->get(['b.id', 'b.bill_no', 'b.bill_date', 'b.type', 'b.entry_id', 'b.deleted_at', 'p.name as party_name']);
        $movements = DB::table('stock_movements as m')->join('stock_parties as p', 'p.id', '=', 'm.party_id')
            ->whereNull('m.project_id')->whereExists(fn ($q) => $q->selectRaw('1')->from('stock_party_project_accounts as map')
                ->whereColumn('map.stock_party_id', 'm.party_id')->where('map.project_id', $projectId))
            ->whereIn('m.party_id', DB::table('stock_party_project_accounts')->select('stock_party_id')->groupBy('stock_party_id')->havingRaw('COUNT(DISTINCT project_id) = 1'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_entries as e')->whereColumn('e.id', 'm.source_id')->whereRaw("m.source_type = 'entry'"))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_bills as b')->whereColumn('b.id', 'm.source_id')->whereRaw("m.source_type = 'bill'"))
            ->orderBy('m.id')->limit(50)->get(['m.id', 'm.movement_date', 'm.direction', 'm.item_id', 'm.qty', 'm.source_type', 'm.source_id', 'p.name as party_name']);
        return compact('entries', 'bills', 'movements');
    }

    public function approveFinance(Request $request, string $kind, int $id)
    {
        $projectId = (int) getSelectedTown();
        \App\Services\Finance\V2OriginalWorkflowAccess::authorize($projectId);
        $data = $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'evidence_reference' => ['required', 'string', 'min:12', 'max:128'],
            'posting_lines' => ['required', 'array', 'min:2', 'max:100'],
            'posting_lines.*.account_id' => ['required', 'integer', 'min:1'],
            'posting_lines.*.debit' => ['required', 'regex:/^\d{1,9}(?:\.[0-9]{1,2})?$/D'],
            'posting_lines.*.credit' => ['required', 'regex:/^\d{1,9}(?:\.[0-9]{1,2})?$/D'],
        ]);
        try {
            $batch = (new \App\Services\Finance\V2StockApprovalWorkflow())->approveAndPost(
                $projectId, $kind, $id, $data['request_key'], $data['evidence_reference'],
                $data['posting_lines'], (int) Auth::id()
            );
        } catch (\DomainException $e) {
            abort(409, 'Stock approval rejected to protect accounting history.');
        }
        return response()->json(['status' => 'success', 'posting_batch_id' => $batch]);
    }

    private function lockStockMasters(int $projectId, int $partyId, array $itemIds)
    {
        $partyAccount = $projectId > 0
            ? DB::table('stock_party_project_accounts')->where('project_id', $projectId)->where('stock_party_id', $partyId)->lockForUpdate()->first()
            : null;
        if (!$partyAccount) {
            throw new HttpException(422, 'Map this stock party to an account in the selected project before posting stock.');
        }
        if (!ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
            ->whereKey((int) $partyAccount->project_head_subhead_id)->lockForUpdate()->exists()) {
            throw new HttpException(422, 'The stock party accounting account is inactive or unavailable in this project.');
        }
        if (!StockParty::query()->whereKey($partyId)->where('is_active', true)->lockForUpdate()->first(['id'])) {
            throw new HttpException(422, 'Stock party is inactive or archived.');
        }
        abort_unless(Schema::hasTable('stock_project_item_definitions'), 409, 'Project stock item definitions migration is required.');
        $items = StockItem::query()->join('stock_project_item_definitions as definition', 'definition.item_id', '=', 'stock_items.id')
            ->where('definition.project_id', $projectId)->where('definition.is_active', 1)->whereNull('definition.deleted_at')
            ->whereIn('stock_items.id', $itemIds)->orderBy('stock_items.id')->lockForUpdate()
            ->get(['stock_items.id', 'definition.name', 'definition.unit'])->keyBy('id');
        if ($items->count() !== count(array_unique($itemIds))) {
            throw new HttpException(422, 'Stock item is missing or archived.');
        }
        return $items;
    }

    private function projectItemDefinitions(int $projectId): \Illuminate\Database\Query\Builder
    {
        abort_unless($projectId > 0 && Schema::hasTable('stock_project_item_definitions'), 409,
            'Project stock item definitions migration is required.');
        return DB::table('stock_project_item_definitions as definition')
            ->join('stock_items as item', 'item.id', '=', 'definition.item_id')
            ->where('definition.project_id', $projectId)->whereNull('definition.deleted_at')
            ->whereNull('item.deleted_at');
    }

    /* ============================================================
     * META
     * ============================================================ */
    public function meta()
    {
        $projectId = (int) getSelectedTown();
        $items = $this->projectItemDefinitions($projectId)->where('definition.is_active', 1)
            ->select(['item.id', 'definition.name', 'definition.unit', 'definition.default_rate_minor'])
            ->orderBy('definition.name')
            ->get();

        $partyColumns = ['id', 'type', 'name', 'mobile', 'address', 'head_account', 'sub_head_account'];
        if (Schema::hasColumn('stock_parties', 'vendor_code')) {
            $partyColumns[] = 'vendor_code';
        }
        $mappedPartyIds = DB::table('stock_party_project_accounts')->where('project_id', $projectId)->pluck('stock_party_id');
        $parties = StockParty::query()->where('is_active', true)->whereIn('id', $mappedPartyIds)
            ->select($partyColumns)
            ->orderBy('name')
            ->get();
        $accountMap = $this->projectPartyAccountMap($projectId, $parties->pluck('id')->all());

        $items = $items->map(function ($i) {
            return [
                'id' => (int) $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'default_rate' => (int) $i->default_rate_minor,
            ];
        });

        $parties = $parties->map(function ($p) use ($accountMap) {
            return [
                'id' => (int) $p->id,
                'type' => $p->type,
                'vendor_code' => $p->vendor_code,
                'name' => $p->name,
                'mobile' => $p->mobile,
                'address' => $p->address,
                'head' => $accountMap->get((int) $p->id)['head'] ?? $p->head_account,
                'subhead' => $accountMap->get((int) $p->id)['subhead'] ?? $p->sub_head_account,
                'account_id' => $accountMap->get((int) $p->id)['account_id'] ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => compact('items', 'parties'),
        ]);
    }

    /* ============================================================
     * COUNTERS (safe numbering)
     * ============================================================ */
    public function nextSlip()
    {
        $slip = $this->peekSlipNumber();
        return response()->json(['status' => 'success', 'data' => ['slip_no' => $slip]]);
    }

    public function nextBill(Request $request)
    {
        $type = $request->query('type', 'purchase');
        if (!in_array($type, ['purchase', 'sale'], true)) {
            $type = 'purchase';
        }

        $bill = DB::transaction(function () use ($type) {
            $key = $type === 'sale' ? 'bill_sale' : 'bill_purchase';
            return $this->nextCounterNumber($key, 'B-', 5);
        });

        return response()->json(['status' => 'success', 'data' => ['bill_no' => $bill, 'type' => $type]]);
    }

    private function nextCounterNumber(string $key, string $prefix = '', int $pad = 5): string
    {
        $row = DB::table('stock_counters')
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            DB::table('stock_counters')->insert([
                'key' => $key,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = (object) ['next_number' => 1];
        }

        $minNext = null;
        if ($key === 'slip_no') {
            $maxSlip = DB::table('stock_entries')->max('slip_no');
            $minNext = $this->parseSlipNumber($maxSlip) + 1;
        }

        if ($minNext !== null && $row->next_number < $minNext) {
            DB::table('stock_counters')
                ->where('key', $key)
                ->update([
                    'next_number' => $minNext,
                    'updated_at' => now(),
                ]);
            $row->next_number = $minNext;
        }

        $next = (int) $row->next_number;

        DB::table('stock_counters')
            ->where('key', $key)
            ->update([
                'next_number' => $next + 1,
                'updated_at' => now(),
            ]);

        return $prefix . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    private function parseSlipNumber(?string $slip): int
    {
        if (!$slip) {
            return 0;
        }
        $digits = preg_replace('/\D+/', '', $slip);
        return $digits === '' ? 0 : (int) $digits;
    }

    private function peekSlipNumber(): string
    {
        $maxSlip = DB::table('stock_entries')->max('slip_no');
        $maxInt = $this->parseSlipNumber($maxSlip);
        $counterNext = DB::table('stock_counters')->where('key', 'slip_no')->value('next_number');
        $next = max((int) ($counterNext ?? 1), $maxInt + 1);
        return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    /* ============================================================
     * LISTS (AJAX) — return shape:
     * { status:'success', data: { rows:[], total:int, kpi:{} } }
     * ============================================================ */

    public function items(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => ['nullable', 'string', 'max:190'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $q = $this->projectItemDefinitions((int) getSelectedTown())->where('definition.is_active', 1)
            ->select(['item.id', 'definition.name', 'definition.unit', 'definition.default_rate_minor', 'definition.created_at'])
            ->orderBy('definition.name');

        if ($request->filled('q')) {
            $s = trim((string) $request->query('q'));
            $q->where('definition.name', 'like', "%{$s}%");
        }

        $rows = $q->limit(500)->get()->map(function ($i) {
            return [
                'id' => (int) $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'default_rate' => (int) $i->default_rate_minor,
                'created_at' => $i->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'rows' => $rows,
                'total' => $rows->count(),
            ],
        ]);
    }

    public function exportItems(Request $request)
    {
        $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx'])]]);
        $ids = collect((array) $request->query('item_ids', []))->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
        $query = $this->projectItemDefinitions((int) getSelectedTown())->where('definition.is_active', 1)->orderBy('item.id');
        if ($ids->isNotEmpty()) $query->whereIn('item.id', $ids);
        $query->select(['item.id', 'definition.name', 'definition.unit', 'definition.default_rate_minor']);

        if ($request->query('format') === 'xlsx') {
            $path = (new \App\Services\Stock\StockItemExcel())->export($query->cursor());
            return response()->download($path, 'stock-items-'.now()->format('Ymd-His').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['item_id', 'name', 'unit', 'default_rate_minor']);
            foreach ($query->cursor() as $item) {
                fputcsv($out, [
                    (int) $item->id,
                    $this->spreadsheetSafeCsvText($item->name),
                    $this->spreadsheetSafeCsvText($item->unit),
                    (int) $item->default_rate_minor,
                ]);
            }
            fclose($out);
        }, 'stock-items-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function stageItemImport(Request $request)
    {
        $this->authorizeStockImportAccess();
        abort_unless(Schema::hasTable('stock_item_import_batches') && Schema::hasTable('stock_item_import_rows'), 409, 'Stock import history migration is not installed yet.');
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120']]);
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 409, 'Select a project before staging a stock import.');

        $convertedPath = null;
        if (strtolower((string) $data['file']->getClientOriginalExtension()) === 'xlsx') {
            try {
                $convertedPath = (new \App\Services\Stock\StockItemExcel())->toCsv($data['file']->getRealPath());
            } catch (\InvalidArgumentException $exception) {
                return $this->errorResponse('import_file_invalid', $exception->getMessage(), 422);
            }
        }
        $handle = fopen($convertedPath ?? $data['file']->getRealPath(), 'rb');
        if (!$handle) return $this->errorResponse('import_file_unreadable', 'Unable to read the uploaded CSV file.', 422);
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers) { fclose($handle); if ($convertedPath) @unlink($convertedPath); return $this->errorResponse('import_header_invalid', 'Import header is missing.', 422); }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
        if (array_slice($headers, 0, 4) !== ['item_id', 'name', 'unit', 'default_rate_minor']) {
            fclose($handle);
            if ($convertedPath) @unlink($convertedPath);
            return $this->errorResponse('import_header_invalid', 'Required columns: item_id, name, unit, default_rate_minor.', 422);
        }

        $rows = [];
        $seenIds = [];
        $seenNewDefinitions = [];
        $lineNumber = 1;
        while (($cells = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if (count($rows) >= 10000) { fclose($handle); if ($convertedPath) @unlink($convertedPath); return $this->errorResponse('import_too_large', 'A single import can contain at most 10,000 data rows.', 422); }
            $cells = array_pad(array_slice($cells, 0, count($headers)), count($headers), '');
            $row = array_combine($headers, array_map(fn ($cell) => trim((string) $cell), $cells));
            if (implode('', $row) === '') continue;

            $errors = [];
            $rawId = trim((string) $row['item_id']);
            $itemId = $rawId === '' ? null : (ctype_digit($rawId) && (int) $rawId > 0 ? (int) $rawId : -1);
            $originalHash = null;
            $validItemId = null;
            if ($itemId === -1) $errors[] = 'item_id must be a positive ID or empty for a new item.';
            if ($itemId && isset($seenIds[$itemId])) $errors[] = 'item_id is duplicated in this file.';
            if ($itemId) $seenIds[$itemId] = true;
            if ($itemId > 0) {
                $currentItem = $this->projectItemDefinitions($projectId)->where('item.id', $itemId)
                    ->first(['item.id', 'definition.name', 'definition.unit', 'definition.default_rate_minor']);
                if (!$currentItem) $errors[] = 'item_id does not identify an active stock item.';
                else {
                    $validItemId = $itemId;
                    $originalHash = $this->stockItemDefinitionHash($currentItem);
                }
            }
            if ($row['name'] === '' || mb_strlen($row['name']) > 255) $errors[] = 'name is required and must be at most 255 characters.';
            if ($row['unit'] === '' || mb_strlen($row['unit']) > 40) $errors[] = 'unit is required and must be at most 40 characters.';
            if (!$itemId && $row['name'] !== '' && $row['unit'] !== '') {
                $newDefinitionKey = mb_strtolower(trim($row['name'])).'|'.mb_strtolower(trim($row['unit']));
                if (isset($seenNewDefinitions[$newDefinitionKey])) $errors[] = 'A new item with the same name and unit is duplicated in this file.';
                $seenNewDefinitions[$newDefinitionKey] = true;
            }
            if ($row['default_rate_minor'] !== '' && (!ctype_digit($row['default_rate_minor']) || strlen($row['default_rate_minor']) > 14)) $errors[] = 'default_rate_minor must be a non-negative integer with at most 14 digits.';

            $rows[] = [
                'row_number' => $lineNumber,
                'stock_item_id' => $validItemId,
                'action' => $errors ? 'failed' : ($itemId ? 'updated' : 'added'),
                'payload' => json_encode([
                    'item_id' => $itemId > 0 ? $itemId : null,
                    'name' => $row['name'], 'unit' => $row['unit'],
                    'default_rate_minor' => $row['default_rate_minor'] === '' ? '0' : $row['default_rate_minor'],
                    'original_hash' => $originalHash,
                ], JSON_THROW_ON_ERROR),
                'errors' => $errors ? json_encode($errors, JSON_THROW_ON_ERROR) : null,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        fclose($handle);
        if ($convertedPath) @unlink($convertedPath);
        if (!$rows) return $this->errorResponse('import_empty', 'The CSV contains no item rows.', 422);

        $hasNewItems = collect($rows)->contains(fn ($row) => $row['action'] === 'added');
        if ($hasNewItems && !auth()->user()->can('create stock') && !auth()->user()->hasAnyRole(['admin', 'superadmin'])) {
            abort(403, 'You do not have permission to create stock items through import.');
        }
        $batchId = DB::transaction(function () use ($rows, $projectId): int {
            $batchId = DB::table('stock_item_import_batches')->insertGetId([
                'project_id' => $projectId, 'created_by' => auth()->id(), 'status' => 'staged',
                'total_rows' => count($rows),
                'added_rows' => collect($rows)->where('action', 'added')->count(),
                'updated_rows' => collect($rows)->where('action', 'updated')->count(),
                'skipped_rows' => collect($rows)->where('action', 'skipped')->count(),
                'failed_rows' => collect($rows)->where('action', 'failed')->count(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_chunk($rows, 500) as $chunk) {
                foreach ($chunk as &$row) $row['batch_id'] = $batchId;
                unset($row);
                DB::table('stock_item_import_rows')->insert($chunk);
            }
            return $batchId;
        });

        return response()->json(['status' => 'success', 'data' => $this->stockImportPreview($batchId, $projectId)]);
    }

    public function previewItemImport(int $batch)
    {
        $this->authorizeStockImportAccess();
        $projectId = (int) getSelectedTown();
        abort_unless(Schema::hasTable('stock_item_import_batches'), 409, 'Stock import history migration is not installed yet.');
        return response()->json(['status' => 'success', 'data' => $this->stockImportPreview($batch, $projectId)]);
    }

    public function itemImportHistory()
    {
        $this->authorizeStockImportAccess();
        abort_unless(Schema::hasTable('stock_item_import_batches'), 409, 'Stock import history migration is not installed yet.');
        $projectId = (int) getSelectedTown();
        $rows = DB::table('stock_item_import_batches as batch')
            ->leftJoin('users as actor', 'actor.id', '=', 'batch.created_by')
            ->where('batch.project_id', $projectId)
            ->orderByDesc('batch.id')->limit(25)
            ->get(['batch.id', 'batch.status', 'batch.total_rows', 'batch.added_rows', 'batch.updated_rows', 'batch.skipped_rows', 'batch.failed_rows', 'batch.created_at', 'batch.applied_at', 'actor.name as created_by_name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id, 'status' => $row->status,
                'total' => (int) $row->total_rows, 'added' => (int) $row->added_rows,
                'updated' => (int) $row->updated_rows, 'skipped' => (int) $row->skipped_rows,
                'failed' => (int) $row->failed_rows, 'created_at' => $row->created_at,
                'applied_at' => $row->applied_at, 'created_by' => $row->created_by_name,
            ]);
        return response()->json(['status' => 'success', 'data' => ['rows' => $rows]]);
    }

    public function applyItemImport(Request $request, int $batch)
    {
        $this->authorizeStockImportAccess();
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $projectId = (int) getSelectedTown();
        abort_unless(Schema::hasTable('stock_item_import_batches'), 409, 'Stock import history migration is not installed yet.');
        $result = DB::transaction(function () use ($batch, $projectId) {
            $import = DB::table('stock_item_import_batches')->where('id', $batch)->where('project_id', $projectId)->lockForUpdate()->first();
            abort_unless($import, 404, 'Import batch not found.');
            if ($import->status === 'applied') return ['already_applied' => true, 'batch_id' => $batch];
            abort_unless($import->status === 'staged', 409, 'This import batch is not available to apply.');
            $rows = DB::table('stock_item_import_rows')->where('batch_id', $batch)->orderBy('row_number')->lockForUpdate()->get();
            abort_unless($rows->isNotEmpty() && $rows->every(fn ($row) => $row->action !== 'failed'), 422, 'Fix all row errors before applying this import.');
            $added = 0; $updated = 0;
            foreach ($rows as $row) {
                if ($row->action === 'skipped') continue;
                $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                if ($row->action === 'added') {
                    if (!auth()->user()->can('create stock') && !auth()->user()->hasAnyRole(['admin', 'superadmin'])) abort(403, 'You do not have permission to create stock items through import.');
                    $item = StockItem::create(['name' => $payload['name'], 'unit' => $payload['unit'], 'default_rate_minor' => (int) $payload['default_rate_minor'], 'created_by' => auth()->id()]);
                    DB::table('stock_project_item_definitions')->insert([
                        'project_id' => $projectId, 'item_id' => $item->id, 'name' => $payload['name'],
                        'unit' => $payload['unit'], 'default_rate_minor' => (int) $payload['default_rate_minor'],
                        'is_active' => 1, 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('stock_item_import_rows')->where('id', $row->id)->update(['stock_item_id' => $item->id, 'updated_at' => now()]);
                    $added++;
                } else {
                    if (!auth()->user()->can('edit stock') && !auth()->user()->can('create stock') && !auth()->user()->hasAnyRole(['admin', 'superadmin'])) abort(403, 'You do not have permission to update stock items through import.');
                    $item = $this->projectItemDefinitions($projectId)->where('item.id', (int) $row->stock_item_id)
                        ->lockForUpdate()->first(['item.id', 'definition.name', 'definition.unit', 'definition.default_rate_minor']);
                    abort_unless($item, 409, 'An item in this import was archived after preview.');
                    abort_unless(isset($payload['original_hash'])
                        && hash_equals((string) $payload['original_hash'], $this->stockItemDefinitionHash($item)),
                        409, 'A stock item changed after this import was staged. Restage the file to review the latest values.');
                    DB::table('stock_project_item_definitions')->where('project_id', $projectId)->where('item_id', $item->id)
                        ->update(['name' => $payload['name'], 'unit' => $payload['unit'],
                            'default_rate_minor' => (int) $payload['default_rate_minor'], 'updated_by' => auth()->id(), 'updated_at' => now()]);
                    $updated++;
                }
            }
            DB::table('stock_item_import_batches')->where('id', $batch)->where('project_id', $projectId)->update([
                'status' => 'applied', 'added_rows' => $added, 'updated_rows' => $updated,
                'applied_at' => now(), 'updated_at' => now(),
            ]);
            return ['already_applied' => false, 'batch_id' => $batch, 'added' => $added, 'updated' => $updated, 'skipped' => (int) $import->skipped_rows];
        });
        return response()->json(['status' => 'success', 'data' => $result]);
    }

    public function downloadItemImportErrors(int $batch)
    {
        $this->authorizeStockImportAccess();
        $projectId = (int) getSelectedTown();
        abort_unless(DB::table('stock_item_import_batches')->where('id', $batch)->where('project_id', $projectId)->exists(), 404);
        $rows = DB::table('stock_item_import_rows')->where('batch_id', $batch)->whereNotNull('errors')->orderBy('row_number')->get();
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb'); fputcsv($out, ['row_number', 'item_id', 'name', 'unit', 'default_rate_minor', 'errors']);
            foreach ($rows as $row) {
                $payload = json_decode($row->payload, true) ?: [];
                fputcsv($out, [
                    (int) $row->row_number,
                    $payload['item_id'] ?? '',
                    $this->spreadsheetSafeCsvText($payload['name'] ?? ''),
                    $this->spreadsheetSafeCsvText($payload['unit'] ?? ''),
                    $payload['default_rate_minor'] ?? '',
                    $this->spreadsheetSafeCsvText(implode('; ', json_decode($row->errors, true) ?: [])),
                ]);
            }
            fclose($out);
        }, 'stock-item-import-errors-'.$batch.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Prevent spreadsheet applications from interpreting untrusted text cells as formulas. */
    private function spreadsheetSafeCsvText(mixed $value): string
    {
        $text = (string) $value;
        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $text)) {
            return "'".$text;
        }

        return $text;
    }

    private function stockItemDefinitionHash(object $item): string
    {
        return hash('sha256', json_encode([
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'unit' => (string) $item->unit,
            'default_rate_minor' => (int) $item->default_rate_minor,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function stockImportPreview(int $batchId, int $projectId): array
    {
        $batch = DB::table('stock_item_import_batches')->where('id', $batchId)->where('project_id', $projectId)->first();
        abort_unless($batch, 404, 'Import batch not found.');
        $rows = DB::table('stock_item_import_rows')->where('batch_id', $batchId)->orderBy('row_number')->get()->map(fn ($row) => [
            'row_number' => (int) $row->row_number, 'action' => $row->action,
            'item' => json_decode($row->payload, true), 'errors' => json_decode($row->errors ?? '[]', true),
        ]);
        return ['batch_id' => $batchId, 'status' => $batch->status, 'counts' => [
            'total' => (int) $batch->total_rows, 'added' => (int) $batch->added_rows,
            'updated' => (int) $batch->updated_rows, 'skipped' => (int) $batch->skipped_rows,
            'failed' => (int) $batch->failed_rows,
        ], 'rows' => $rows];
    }

    private function authorizeStockImportAccess(): void
    {
        abort_unless(auth()->user()->can('create stock') || auth()->user()->can('edit stock') || auth()->user()->hasAnyRole(['admin', 'superadmin']), 403, 'You do not have permission to manage stock item imports.');
    }

    public function parties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['nullable', Rule::in(['Supplier', 'Consumer', 'Site'])],
            'q' => ['nullable', 'string', 'max:190'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $partyColumns = ['id', 'type', 'name', 'mobile', 'address', 'head_account', 'sub_head_account', 'created_at'];
        if (Schema::hasColumn('stock_parties', 'vendor_code')) {
            $partyColumns[] = 'vendor_code';
        }
        $partyColumns[] = 'is_active';
        $mappedPartyIds = DB::table('stock_party_project_accounts')->where('project_id', (int) getSelectedTown())->pluck('stock_party_id');
        $q = StockParty::query()->whereIn('id', $mappedPartyIds)
            ->select($partyColumns)
            ->orderBy('name');

        if ($request->filled('type')) {
            $q->where('type', $request->query('type'));
        }

        if ($request->filled('q')) {
            $s = trim((string) $request->query('q'));
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%")
                    ->orWhere('address', 'like', "%{$s}%");
            });
        }

        $rows = $q->limit(500)->get()->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'type' => $p->type,
                'vendor_code' => $p->vendor_code,
                'is_active' => (bool) $p->is_active,
                'name' => $p->name,
                'mobile' => $p->mobile,
                'address' => $p->address,
                'head' => $p->head_account,
                'subhead' => $p->sub_head_account,
                'created_at' => $p->created_at,
            ];
        });

        $accountMap = $this->projectPartyAccountMap((int) getSelectedTown(), $rows->pluck('id')->all());
        $rows = $rows->map(function ($row) use ($accountMap) {
            $mapping = $accountMap->get((int) $row['id']);
            if ($mapping) {
                $row['head'] = $mapping['head'];
                $row['subhead'] = $mapping['subhead'];
                $row['account_id'] = $mapping['account_id'];
            }
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'rows' => $rows,
                'total' => $rows->count(),
            ],
        ]);
    }

    /**
     * Entries list: flattened per line (your table expects item_name/qty/rate/amount)
     * Filters: item_id, flow, q (slip_no / reason / party / item)
     */
    public function entries(Request $request)
    {
        abort_unless(Schema::hasColumn('stock_entries', 'project_id') && Schema::hasColumn('stock_entry_lines', 'project_id'), 409, 'Project stock schema is not installed yet.');
        $validator = Validator::make($request->all(), [
            'item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'flow' => ['nullable', Rule::in(['IN', 'OUT'])],
            'q' => ['nullable', 'string', 'max:190'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }
        $data = $validator->validated();

        $perPage = (int) ($data['per_page'] ?? 20);
        $search = trim((string) ($data['q'] ?? ''));

        // Base: join entry_lines + entries + items + parties
        $q = DB::table('stock_entry_lines as l')
            ->join('stock_entries as e', 'e.id', '=', 'l.entry_id')
            ->join('stock_items as i', 'i.id', '=', 'l.item_id')
            ->join('stock_parties as p', 'p.id', '=', 'e.party_id')
            ->select([
                'e.id as id',
                'e.slip_no',
                'e.entry_date',
                'e.flow',
                'e.reason',
                'e.billed_at',

                'p.id as party_id',
                'p.name as party_name',
                'p.type as party_type',

                'i.id as item_id',
                'i.name as item_name',
                'i.unit as item_unit',

                'l.id as line_id',
                'l.qty',
                'l.rate_minor',
                'l.amount_minor',
            ])
            ->whereNull('e.deleted_at')
            ->where('e.project_id', (int) getSelectedTown())
            ->orderByDesc('e.id')
            ->orderByDesc('l.id');

        if (!empty($data['from'])) {
            $q->whereDate('e.entry_date', '>=', $data['from']);
        }
        if (!empty($data['to'])) {
            $q->whereDate('e.entry_date', '<=', $data['to']);
        }
        if (!empty($data['flow'])) {
            $q->where('e.flow', $data['flow']);
        }
        if (!empty($data['item_id'])) {
            $q->where('l.item_id', (int) $data['item_id']);
        }

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('e.slip_no', 'like', "%{$search}%")
                    ->orWhere('e.reason', 'like', "%{$search}%")
                    ->orWhere('p.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");
            });
        }

        $page = $q->paginate($perPage);

        // KPI from the same filters (no pagination)
        $kpiQ = clone $q;
        // remove selects/order for KPI
        $kpi = DB::query()
            ->fromSub(
                $kpiQ->cloneWithout(['orders'])->cloneWithoutBindings(['order']),
                't'
            )
            ->selectRaw("
            COALESCE(SUM(CASE WHEN flow='IN'  THEN qty ELSE 0 END),0) as in_qty,
            COALESCE(SUM(CASE WHEN flow='OUT' THEN qty ELSE 0 END),0) as out_qty,
            COUNT(*) as line_count
        ")
            ->first();

        $rows = collect($page->items())->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'line_id' => (int) $r->line_id,
                'slip_no' => $r->slip_no,
                'entry_date' => $r->entry_date,
                'flow' => $r->flow,
                'reason' => $r->reason,
                'billed_at' => $r->billed_at,

                'party_id' => (int) $r->party_id,
                'party_name' => $r->party_name,

                'item_id' => (int) $r->item_id,
                'item_name' => $r->item_name,
                'qty' => $r->qty,
                'rate' => (int) $r->rate_minor,
                'amount' => (int) $r->amount_minor,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'rows' => $rows,
                'total' => (int) $page->total(),
                'page' => (int) $page->currentPage(),
                'per_page' => (int) $page->perPage(),
                'kpi' => [
                    'in_qty' => (int) ($kpi->in_qty ?? 0),
                    'out_qty' => (int) ($kpi->out_qty ?? 0),
                    'line_count' => (int) ($kpi->line_count ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Bills list: one row per bill (with totals)
     * Filters: type, party_id, q, from, to
     */
    public function bills(Request $request)
    {
        abort_unless(Schema::hasColumn('stock_bills', 'project_id') && Schema::hasColumn('stock_bill_lines', 'project_id'), 409, 'Project stock schema is not installed yet.');
        $validator = Validator::make($request->all(), [
            'type' => ['nullable', Rule::in(['purchase', 'sale'])],
            'party_id' => ['nullable', 'integer', 'exists:stock_parties,id'],
            'q' => ['nullable', 'string', 'max:190'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }
        $data = $validator->validated();

        $perPage = (int) ($data['per_page'] ?? 20);
        $search = trim((string) ($data['q'] ?? ''));

        $q = DB::table('stock_bills as b')
            ->join('stock_parties as p', 'p.id', '=', 'b.party_id')
            ->leftJoin('stock_entries as e', 'e.id', '=', 'b.entry_id')
            ->leftJoin('stock_bill_lines as l', 'l.bill_id', '=', 'b.id')
            ->leftJoin('stock_items as i', 'i.id', '=', 'l.item_id')
            ->select([
                'b.id',
                'b.bill_no',
                'b.bill_date',
                'b.type',
                'e.slip_no as entry_no',
                'p.name as party_name',
                'b.total_minor as total_amount',
            ])
            ->whereNull('b.deleted_at')
            ->where('b.project_id', (int) getSelectedTown())
            ->groupBy('b.id', 'b.bill_no', 'b.bill_date', 'b.type', 'e.slip_no', 'p.name', 'b.total_minor')
            ->orderByDesc('b.id');

        if (!empty($data['from'])) {
            $q->whereDate('b.bill_date', '>=', $data['from']);
        }
        if (!empty($data['to'])) {
            $q->whereDate('b.bill_date', '<=', $data['to']);
        }
        if (!empty($data['type'])) {
            $q->where('b.type', $data['type']);
        }
        if (!empty($data['party_id'])) {
            $q->where('b.party_id', (int) $data['party_id']);
        }
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('b.bill_no', 'like', "%{$search}%")
                    ->orWhere('e.slip_no', 'like', "%{$search}%")
                    ->orWhere('p.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");
            });
        }

        $page = $q->paginate($perPage);

        // KPI (counts + sum) from same filtered query but without pagination
        $kpi = DB::query()
            ->fromSub(
                $q->cloneWithout(['orders'])->cloneWithoutBindings(['order']),
                't'
            )
            ->selectRaw("
            COALESCE(SUM(CASE WHEN type='purchase' THEN 1 ELSE 0 END),0) as purchase_count,
            COALESCE(SUM(CASE WHEN type='sale' THEN 1 ELSE 0 END),0) as sale_count,
            COALESCE(SUM(total_amount),0) as total_amount
        ")
            ->first();

        $rows = collect($page->items())->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'bill_no' => $r->bill_no,
                'date' => $r->bill_date,
                'type' => $r->type,
                'party_name' => $r->party_name,
                'entry_no' => $r->entry_no,
                'total' => (int) $r->total_amount,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'rows' => $rows,
                'total' => (int) $page->total(),
                'page' => (int) $page->currentPage(),
                'per_page' => (int) $page->perPage(),
                'kpi' => [
                    'purchase_count' => (int) ($kpi->purchase_count ?? 0),
                    'sale_count' => (int) ($kpi->sale_count ?? 0),
                    'total_amount' => (int) ($kpi->total_amount ?? 0),
                ],
            ],
        ]);
    }

    /* ============================================================
     * REPORT (range + optional item filter) — shape:
     * { rows:[{item_name,purchase_qty,sale_qty,available}], kpi:{} }
     * ============================================================ */
    public function report(Request $request)
    {
        if (!$request->ajax() && !$request->expectsJson()) {
            return redirect()->route('stocks.index');
        }

        $validator = Validator::make($request->all(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }
        $data = $validator->validated();

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        $itemId = !empty($data['item_id']) ? (int) $data['item_id'] : null;
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0 && Schema::hasTable('stock_project_item_balances') && Schema::hasColumn('stock_movements', 'project_id'), 409, 'Project stock balances are not installed yet.');

        // If dates missing => show current balances (works with your JS too)
        if (!$from || !$to) {
            $rowsQ = DB::table('stock_project_item_balances as b')
                ->join('stock_items as i', 'i.id', '=', 'b.item_id')
                ->select([
                    'i.id as item_id',
                    'i.name as item_name',
                    'i.unit as item_unit',
                    DB::raw('0 as purchase_qty'),
                    DB::raw('0 as sale_qty'),
                    DB::raw('b.on_hand_qty as available'),
                ])->where('b.project_id', $projectId)
                ->orderBy('i.name');

            if ($itemId)
                $rowsQ->where('i.id', $itemId);

            $rows = $rowsQ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'rows' => $rows,
                    'kpi' => [
                        'items' => $rows->count(),
                        'in_qty' => 0,
                        'out_qty' => 0,
                        'available' => (int) $rows->sum('available'),
                    ],
                ],
            ]);
        }

        // IN/OUT in range from movements
        $movQ = DB::table('stock_movements as m')
            ->join('stock_items as i', 'i.id', '=', 'm.item_id')
            ->select([
                'm.item_id',
                'i.name as item_name',
                'i.unit as item_unit',
                DB::raw("SUM(CASE WHEN m.direction='IN'  THEN m.qty ELSE 0 END) as purchase_qty"),
                DB::raw("SUM(CASE WHEN m.direction='OUT' THEN m.qty ELSE 0 END) as sale_qty"),
            ])
            ->where('m.project_id', $projectId)
            ->whereBetween(DB::raw('DATE(m.movement_date)'), [$from, $to])
            ->groupBy('m.item_id', 'i.name', 'i.unit')
            ->orderBy('i.name');

        if ($itemId)
            $movQ->where('m.item_id', $itemId);

        $rows = $movQ->get();

        // current available (from balances)
        $balances = DB::table('stock_project_item_balances')->where('project_id', $projectId)->pluck('on_hand_qty', 'item_id');

        $rows = $rows->map(function ($r) use ($balances) {
            $avail = (int) ($balances[$r->item_id] ?? 0);
            return [
                'item_id' => (int) $r->item_id,
                'item_name' => $r->item_name,
                'item_unit' => $r->item_unit,
                'purchase_qty' => (int) $r->purchase_qty,
                'sale_qty' => (int) $r->sale_qty,
                'available' => $avail,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'rows' => $rows,
                'kpi' => [
                    'items' => $rows->count(),
                    'in_qty' => (int) $rows->sum('purchase_qty'),
                    'out_qty' => (int) $rows->sum('sale_qty'),
                    'available' => (int) $rows->sum('available'),
                ],
            ],
        ]);
    }

    /* ============================================================
     * STORE: ITEMS / PARTIES / ENTRIES / BILLS
     * ============================================================ */
    public function storeItem(Request $request)
    {
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0 && Schema::hasTable('stock_project_item_definitions'), 409, 'Project stock item definitions migration is required.');
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:190'],
            'unit' => ['required', 'string', 'max:40'],
            'default_rate' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $item = DB::transaction(function () use ($data, $projectId): StockItem {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $item = StockItem::create([
                'name' => trim($data['name']), 'unit' => trim($data['unit']),
                'default_rate_minor' => (int) ($data['default_rate'] ?? 0), 'created_by' => auth()->id(),
            ]);
            DB::table('stock_project_item_definitions')->insert([
                'project_id' => $projectId, 'item_id' => $item->id,
                'name' => $item->name, 'unit' => $item->unit, 'default_rate_minor' => $item->default_rate_minor,
                'is_active' => 1, 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'item' => [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'default_rate' => (int) $item->default_rate_minor,
                ],
            ],
            'message' => 'Item created.',
        ]);
    }

    public function storeParty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['Supplier', 'Consumer', 'Site'])],
            'name' => ['required', 'string', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'head' => ['nullable', 'integer', 'min:1'],
            'subhead' => ['nullable', 'integer', 'min:1'],
            'account_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }
        $data = $validator->validated();
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 409, 'Select a project before creating a stock party.');
        [$party, $account] = DB::transaction(function () use ($data, $projectId) {
            $account = $this->resolveSubmittedPartyAccount($projectId, $data['head'] ?? null, $data['subhead'] ?? null, $data['account_id'] ?? null);
            $party = StockParty::create([
            'type' => $data['type'],
            'name' => trim($data['name']),
            'mobile' => $data['mobile'] ?? null,
            'address' => $data['address'] ?? null,
            'created_by' => auth()->id(),
            ]);
            if ($account) $this->savePartyProjectAccount($projectId, (int) $party->id, (int) $account->id);
            if ($data['type'] === 'Supplier' && Schema::hasColumn('stock_parties', 'vendor_code')) {
                $party->vendor_code = sprintf('SUP-%06d', $party->id);
                $party->save();
            }
            return [$party, $account];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'party' => [
                    'id' => (int) $party->id,
                    'type' => $party->type,
                    'vendor_code' => $party->vendor_code,
                    'name' => $party->name,
                    'mobile' => $party->mobile,
                    'address' => $party->address,
                    'head' => $account?->head_accounting_id,
                    'subhead' => $account?->subhead_accounting_id,
                    'account_id' => $account?->id,
                ],
            ],
            'message' => 'Party created.',
        ]);
    }

    public function updateItem(Request $request, $id)
    {
        $projectId = (int) getSelectedTown();
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:190'],
            'unit' => ['required', 'string', 'max:40'],
            'default_rate' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        DB::transaction(function () use ($projectId, $id, $data): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $item = $this->projectItemDefinitions($projectId)->where('item.id', $id)
                ->where('definition.is_active', 1)->lockForUpdate()->first(['item.id']);
            abort_unless($item, 404, 'Stock item not found in this project.');
            DB::table('stock_project_item_definitions')->where('project_id', $projectId)->where('item_id', $id)
                ->update(['name' => trim($data['name']), 'unit' => trim($data['unit']),
                    'default_rate_minor' => (int) ($data['default_rate'] ?? 0),
                    'updated_by' => auth()->id(), 'updated_at' => now()]);
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'item' => [
                    'id' => (int) $id,
                    'name' => trim($data['name']),
                    'unit' => trim($data['unit']),
                    'default_rate' => (int) ($data['default_rate'] ?? 0),
                ],
            ],
            'message' => 'Item updated.',
        ]);
    }

    public function deleteItem(Request $request, $id)
    {
        $projectId = (int) getSelectedTown();
        DB::transaction(function () use ($projectId, $id): void {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->firstOrFail();
            $item = $this->projectItemDefinitions($projectId)->where('item.id', $id)->lockForUpdate()->first(['item.id']);
            abort_unless($item, 404, 'Stock item not found in this project.');
            DB::table('stock_project_item_definitions')->where('project_id', $projectId)->where('item_id', $id)
                ->update(['is_active' => 0, 'deleted_at' => now(), 'updated_by' => auth()->id(), 'updated_at' => now()]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Item archived for this project.',
        ]);
    }

    public function updateParty(Request $request, $id)
    {
        $party = StockParty::query()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['Supplier', 'Consumer', 'Site'])],
            'name' => ['required', 'string', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'head' => ['nullable', 'integer', 'min:1'],
            'subhead' => ['nullable', 'integer', 'min:1'],
            'account_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 409, 'Select a project before updating a stock party.');
        $account = DB::transaction(function () use ($data, $party, $projectId) {
            $account = $this->resolveSubmittedPartyAccount($projectId, $data['head'] ?? null, $data['subhead'] ?? null, $data['account_id'] ?? null);
            $party->update([
                'type' => $data['type'],
                'name' => trim($data['name']),
                'mobile' => $data['mobile'] ?? null,
                'address' => $data['address'] ?? null,
                'updated_by' => auth()->id(),
            ]);
            if ($account) $this->savePartyProjectAccount($projectId, (int) $party->id, (int) $account->id);
            return $account;
        });
        if ($data['type'] === 'Supplier' && Schema::hasColumn('stock_parties', 'vendor_code') && empty($party->vendor_code)) {
            $party->vendor_code = sprintf('SUP-%06d', $party->id);
            $party->save();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'party' => [
                    'id' => (int) $party->id,
                    'type' => $party->type,
                    'vendor_code' => $party->vendor_code,
                    'name' => $party->name,
                    'mobile' => $party->mobile,
                    'address' => $party->address,
                    'head' => $account?->head_accounting_id,
                    'subhead' => $account?->subhead_accounting_id,
                    'account_id' => $account?->id,
                ],
            ],
            'message' => 'Party updated.',
        ]);
    }

    public function deleteParty(Request $request, $id)
    {
        $party = StockParty::query()->findOrFail($id);

        try {
            DB::transaction(function () use ($party) {
                StockParty::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();
                if (DB::table('stock_entries')->where('party_id', $party->id)->exists()
                    || DB::table('stock_bills')->where('party_id', $party->id)->exists()
                    || DB::table('stock_movements')->where('party_id', $party->id)->exists()) {
                    throw new \DomainException('STOCK_PARTY_HAS_HISTORY');
                }
                $party->delete();
            });
        } catch (\DomainException $e) {
            return $this->errorResponse('stock_history_exists', 'Party has stock history and cannot be deleted.', 409);
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse('server_error', 'Unable to delete party.', 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Party deleted.',
        ]);
    }

    public function updatePartyStatus(Request $request, $id)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        abort_unless(Schema::hasTable('account_definition_status_audits'), 409, 'Install the account status audit migration before changing party status.');

        $party = DB::transaction(function () use ($id, $data) {
            $party = StockParty::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $oldStatus = (int) $party->is_active;
            $newStatus = (int) $data['is_active'];
            if ($oldStatus !== $newStatus) {
                app(\App\Services\Finance\AccountDefinitionStatusAudit::class)->record(
                    'stock_party', (int) $party->id, $oldStatus, $newStatus, $data['reason'], Auth::id()
                );
                $party->is_active = $newStatus;
                $party->updated_by = Auth::id();
                $party->save();
            }
            return $party;
        });

        return response()->json([
            'status' => 'success',
            'data' => ['party' => ['id' => (int) $party->id, 'is_active' => (bool) $party->is_active]],
            'message' => 'Party status updated.',
        ]);
    }

    private function rebuildBalances(int $projectId, array $itemIds): void
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if (!$itemIds) {
            return;
        }

        $rows = DB::table('stock_movements')->where('project_id', $projectId)
            ->select([
                'item_id',
                DB::raw("SUM(CASE WHEN direction='IN' THEN qty ELSE 0 END) as in_qty"),
                DB::raw("SUM(CASE WHEN direction='OUT' THEN qty ELSE 0 END) as out_qty"),
            ])
            ->whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        foreach ($itemIds as $itemId) {
            $row = $rows->get($itemId);
            $inQty = $row ? (string) $row->in_qty : '0';
            $outQty = $row ? (string) $row->out_qty : '0';
            $inInt = $this->parseQtyToInt($inQty) ?? 0;
            $outInt = $this->parseQtyToInt($outQty) ?? 0;
            $onHand = $this->qtyIntToDecimal($inInt - $outInt);

            $exists = DB::table('stock_project_item_balances')->where('project_id', $projectId)->where('item_id', $itemId)->exists();
            if ($exists) {
                DB::table('stock_project_item_balances')
                    ->where('project_id', $projectId)->where('item_id', $itemId)
                    ->update([
                        'on_hand_qty' => $onHand,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_project_item_balances')
                    ->insert([
                        'project_id' => $projectId,
                        'item_id' => $itemId,
                        'on_hand_qty' => $onHand,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function storeEntry(Request $request)
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize((int) getSelectedTown());
        }
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date'],
            'party_id' => ['required', 'integer', 'exists:stock_parties,id'],
            'flow' => ['required', Rule::in(['IN', 'OUT'])],
            'vehicle' => ['nullable', 'string', 'max:80'],
            'driver' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'lines.*.qty' => ['required', 'string', 'regex:/^\\d+(\\.\\d{1,3})?$/'],
            'lines.*.rate' => ['required', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0 && Schema::hasTable('stock_project_item_balances'), 409, 'Project stock balances are not installed yet.');
        $flow = $data['flow'];
        $linesInput = $data['lines'];
        $itemIds = collect($linesInput)->pluck('item_id')->unique()->values()->all();
        $itemMeta = $this->projectItemDefinitions($projectId)->whereIn('item.id', $itemIds)
            ->get(['item.id', 'definition.name', 'definition.unit'])
            ->keyBy('id');
        $itemNames = $itemMeta->mapWithKeys(fn($it) => [(int) $it->id => $it->name])->toArray();

        try {
            $result = DB::transaction(function () use ($data, $flow, $linesInput, $itemNames, $itemMeta, $projectId) {
                $itemMeta = $this->lockStockMasters($projectId, (int) $data['party_id'], array_column($linesInput, 'item_id'));
                $now = now();

                $lines = [];
                $totalsByItem = [];
                $totalMinor = 0;
                foreach ($linesInput as $idx => $line) {
                    $qtyInt = $this->parseQtyToInt($line['qty']);
                    if ($qtyInt === null || $qtyInt <= 0) {
                        throw new HttpException(422, 'Invalid quantity on line ' . ($idx + 1));
                    }
                    $rateMinor = (int) $line['rate'];
                    $amountMinor = $this->calcAmountMinor($qtyInt, $rateMinor);
                    $totalMinor += $amountMinor;

                    $lines[] = [
                        'item_id' => (int) $line['item_id'],
                        'qty_int' => $qtyInt,
                        'qty_decimal' => $this->qtyIntToDecimal($qtyInt),
                        'rate_minor' => $rateMinor,
                        'amount_minor' => $amountMinor,
                        'item_name' => $itemMeta[(int) $line['item_id']]->name ?? ('Item #' . (int) $line['item_id']),
                        'unit' => $itemMeta[(int) $line['item_id']]->unit ?? '',
                    ];

                    $totalsByItem[$line['item_id']] = ($totalsByItem[$line['item_id']] ?? 0) + $qtyInt;
                }

                $this->applyStockAdjustments($projectId, $totalsByItem, $flow, $itemNames);

                $slipNo = $this->nextCounterNumber('slip_no', '', 2);

                $entry = StockEntry::create([
                    'project_id' => $projectId,
                    'slip_no' => $slipNo,
                    'entry_date' => $data['date'],
                    'flow' => $flow,
                    'party_id' => (int) $data['party_id'],
                    'vehicle_no' => $data['vehicle'] ?? null,
                    'driver_name' => $data['driver'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($lines as $line) {
                    $lineRow = StockEntryLine::create([
                        'project_id' => $projectId,
                        'entry_id' => $entry->id,
                        'item_id' => $line['item_id'],
                        'qty' => $line['qty_decimal'],
                        'rate_minor' => $line['rate_minor'],
                        'amount_minor' => $line['amount_minor'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('stock_movements')->insert([
                        'project_id' => $projectId,
                        'movement_date' => $data['date'],
                        'direction' => $flow,
                        'item_id' => $line['item_id'],
                        'qty' => $line['qty_decimal'],
                        'rate_minor' => $line['rate_minor'],
                        'amount_minor' => $line['amount_minor'],
                        'source_type' => 'entry',
                        'source_id' => $entry->id,
                        'source_line_id' => $lineRow->id,
                        'party_id' => (int) $data['party_id'],
                        'created_by' => auth()->id(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->postEntryLedger($entry, $flow, $totalMinor, $data['date'], $lines);

                return [
                    'id' => (int) $entry->id,
                    'slip_no' => $slipNo,
                ];
            });
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return $this->errorResponse('insufficient_stock', $e->getMessage(), 409);
            }
            if ($e->getStatusCode() === 422) {
                return $this->errorResponse('validation_failed', $e->getMessage(), 422, [
                    'lines' => [$e->getMessage()],
                ]);
            }
            throw $e;
        } catch (\DomainException $e) {
            $key = $e->getMessage();
            Log::warning('stock.entry.ledger.domain_error', [
                'key' => $key,
                'flow' => $flow,
                'party_id' => $data['party_id'] ?? null,
            ]);
            if ($key === 'PARTY_ACCOUNT_MAPPING_MISSING') {
                return $this->errorResponse('PARTY_ACCOUNT_MAPPING_MISSING', 'Party accounting mapping missing.', 422);
            }
            if ($key === 'PROJECT_CONTEXT_MISSING') {
                return $this->errorResponse('PROJECT_CONTEXT_MISSING', 'Project context missing.', 422);
            }
            return $this->errorResponse('server_error', 'Unable to save entry.', 500);
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse('server_error', 'Unable to save entry.', 500);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
            'message' => \App\Services\Finance\V2OriginalWorkflowAccess::selected()
                ? 'Entry saved. Finance approval is required.' : 'Entry saved.',
        ]);
    }

    public function storeBill(Request $request)
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize((int) getSelectedTown());
        }
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['purchase', 'sale'])],
            'date' => ['required', 'date'],
            'party_id' => ['required', 'integer', Rule::exists('stock_parties', 'id')->where(fn($q) => $q->where('is_active', 1))],
            'entry_no' => ['nullable', 'string', 'max:30'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'lines.*.qty' => ['required', 'string', 'regex:/^\\d+(\\.\\d{1,3})?$/'],
            'lines.*.rate' => ['required', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0 && Schema::hasTable('stock_project_item_balances'), 409, 'Project stock balances are not installed yet.');
        $direction = $data['type'] === 'purchase' ? 'IN' : 'OUT';
        $linesInput = $data['lines'];
        $itemIds = collect($linesInput)->pluck('item_id')->unique()->values()->all();
        $itemMeta = $this->projectItemDefinitions($projectId)->whereIn('item.id', $itemIds)
            ->get(['item.id', 'definition.name', 'definition.unit'])
            ->keyBy('id');
        $itemNames = $itemMeta->mapWithKeys(fn($it) => [(int) $it->id => $it->name])->toArray();

        $entryId = null;
        if (!empty($data['entry_no'])) {
            $entryId = StockEntry::where('project_id', $projectId)->where('slip_no', $data['entry_no'])->value('id');
            if (!$entryId) {
                return $this->errorResponse('validation_failed', 'Entry not found for the provided entry number.', 422, [
                    'entry_no' => ['Entry not found.'],
                ]);
            }
        }

        try {
            $result = DB::transaction(function () use ($data, $direction, $linesInput, $itemNames, $itemMeta, $entryId, $projectId) {
                $itemMeta = $this->lockStockMasters($projectId, (int) $data['party_id'], array_column($linesInput, 'item_id'));
                $now = now();

                $lines = [];
                $totalsByItem = [];
                $totalMinor = 0;
                foreach ($linesInput as $idx => $line) {
                    $qtyInt = $this->parseQtyToInt($line['qty']);
                    if ($qtyInt === null || $qtyInt <= 0) {
                        throw new HttpException(422, 'Invalid quantity on line ' . ($idx + 1));
                    }
                    $rateMinor = (int) $line['rate'];
                    $amountMinor = $this->calcAmountMinor($qtyInt, $rateMinor);
                    $totalMinor += $amountMinor;

                    $lines[] = [
                        'item_id' => (int) $line['item_id'],
                        'qty_int' => $qtyInt,
                        'qty_decimal' => $this->qtyIntToDecimal($qtyInt),
                        'rate_minor' => $rateMinor,
                        'amount_minor' => $amountMinor,
                        'item_name' => $itemMeta[(int) $line['item_id']]->name ?? ('Item #' . (int) $line['item_id']),
                        'unit' => $itemMeta[(int) $line['item_id']]->unit ?? '',
                    ];

                    $totalsByItem[$line['item_id']] = ($totalsByItem[$line['item_id']] ?? 0) + $qtyInt;
                }

                $this->applyStockAdjustments($projectId, $totalsByItem, $direction, $itemNames);

                $counterKey = $data['type'] === 'sale' ? 'bill_sale' : 'bill_purchase';
                $billNo = $this->nextCounterNumber($counterKey, 'B-', 5);

                $bill = StockBill::create([
                    'project_id' => $projectId,
                    'bill_no' => $billNo,
                    'bill_date' => $data['date'],
                    'type' => $data['type'],
                    'party_id' => (int) $data['party_id'],
                    'entry_id' => $entryId,
                    'total_minor' => $totalMinor,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($lines as $line) {
                    $lineRow = StockBillLine::create([
                        'project_id' => $projectId,
                        'bill_id' => $bill->id,
                        'item_id' => $line['item_id'],
                        'qty' => $line['qty_decimal'],
                        'rate_minor' => $line['rate_minor'],
                        'amount_minor' => $line['amount_minor'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('stock_movements')->insert([
                        'project_id' => $projectId,
                        'movement_date' => $data['date'],
                        'direction' => $direction,
                        'item_id' => $line['item_id'],
                        'qty' => $line['qty_decimal'],
                        'rate_minor' => $line['rate_minor'],
                        'amount_minor' => $line['amount_minor'],
                        'source_type' => 'bill',
                        'source_id' => $bill->id,
                        'source_line_id' => $lineRow->id,
                        'party_id' => (int) $data['party_id'],
                        'created_by' => auth()->id(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->postBillLedger($bill, $data['type'], $totalMinor, $data['date'], $lines);

                if ($entryId) {
                    StockEntry::where('id', $entryId)->where('project_id', $projectId)
                        ->update(['billed_at' => now(), 'updated_at' => $now]);
                }

                return [
                    'id' => (int) $bill->id,
                    'bill_no' => $billNo,
                ];
            });
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return $this->errorResponse('insufficient_stock', $e->getMessage(), 409);
            }
            if ($e->getStatusCode() === 422) {
                return $this->errorResponse('validation_failed', $e->getMessage(), 422, [
                    'lines' => [$e->getMessage()],
                ]);
            }
            throw $e;
        } catch (\DomainException $e) {
            $key = $e->getMessage();
            Log::warning('stock.bill.ledger.domain_error', [
                'key' => $key,
                'party_id' => $data['party_id'] ?? null,
                'type' => $data['type'] ?? null,
            ]);
            if ($key === 'PARTY_ACCOUNT_MAPPING_MISSING') {
                return $this->errorResponse('PARTY_ACCOUNT_MAPPING_MISSING', 'Party accounting mapping missing.', 422);
            }
            if ($key === 'PROJECT_CONTEXT_MISSING') {
                return $this->errorResponse('PROJECT_CONTEXT_MISSING', 'Project context is missing.', 422);
            }
            return $this->errorResponse('server_error', 'Unable to save bill.', 500);
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse('server_error', 'Unable to save bill.', 500);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
            'message' => \App\Services\Finance\V2OriginalWorkflowAccess::selected()
                ? 'Bill saved. Finance approval is required.' : 'Bill saved.',
        ]);
    }

    private function postEntryLedger(StockEntry $entry, string $flow, int $totalMinor, string $entryDate, array $lines): void
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize((int) getSelectedTown());
            return; // Inventory source is saved; finance needs separate evidence-backed approval.
        }
        $reference = 'STOCK-ENTRY:' . $entry->id;
        $ledgerType = 'JV';
        $amount = $this->minorToLedgerAmount($totalMinor);
        $party = StockParty::query()->find((int) $entry->party_id);
        if (!$party) {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }
        $partyProjectHeadSubheadId = $this->resolvePartyPhsId((int) $entry->party_id);
        $stockProjectHeadSubheadId = $this->resolveStockKhataPhsId();
        $detail = $this->buildStockLedgerDetail([
            'kind' => 'ENTRY',
            'number' => $entry->slip_no,
            'date' => $entryDate,
            'direction' => $flow,
            'party_name' => $party->name,
            'party_type' => $party->type,
            'total_minor' => $totalMinor,
        ], $lines);

        if ($flow === 'IN') {
            $debitPhsId = $stockProjectHeadSubheadId;
            $creditPhsId = $partyProjectHeadSubheadId;
        } else {
            $debitPhsId = $partyProjectHeadSubheadId;
            $creditPhsId = $stockProjectHeadSubheadId;
        }

        $legs = [
            [
                'project_head_subheads_id' => $debitPhsId,
                'amount_in' => $amount,
                'amount_out' => 0,
                'detail' => $detail,
            ],
            [
                'project_head_subheads_id' => $creditPhsId,
                'amount_in' => 0,
                'amount_out' => $amount,
                'detail' => $detail,
            ],
        ];

        $projectId = (int) getSelectedTown();
        if (app(\App\Services\Finance\StockLedgerPostingGuard::class)
            ->alreadyPosted($reference, $projectId, $entryDate, $legs)) {
            return;
        }

        $customerLedger = CustomerLedger::query()
            ->where('transaction_type', $ledgerType)
            ->where('reference', $reference)
            ->where('project_id', $projectId)
            ->first();

        if (!$customerLedger) {
            $customerLedger = CustomerLedger::create([
                'transaction_type' => $ledgerType,
                'type_id' => get_new_typeID($ledgerType),
                'reference' => $reference,
                'project_id' => $projectId,
                'customer_id' => null,
                'plot_id' => null,
                'amount_in' => $flow === 'OUT' ? $amount : 0,
                'amount_out' => $flow === 'IN' ? $amount : 0,
                'description' => $detail,
                'date' => $entryDate,
                'payment_type' => 1,
                'is_active' => 1,
                'is_approve' => 0,
            ]);
            app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)->assign(
                $projectId, 'customer_ledger', (int) $customerLedger->id, $ledgerType, $entryDate
            );
        }

        $voucherNumber = app(\App\Services\Finance\ProjectVoucherAllocator::class)
            ->next($projectId, $ledgerType);
        $typeId = app(\App\Services\Finance\ProjectVoucherAllocator::class)
            ->nextLedgerId($projectId, $ledgerType);
        $userId = auth()->id();

        foreach ($legs as $leg) {
            Ledger::create([
                'customer_ledger_id' => $customerLedger->id,
                'voucher_number' => $voucherNumber,
                'type' => $ledgerType,
                'type_id' => $typeId,
                'project_head_subheads_id' => $leg['project_head_subheads_id'],
                'reference' => $reference,
                'amount_in' => $leg['amount_in'],
                'amount_out' => $leg['amount_out'],
                'is_active' => 1,
                'date' => $entryDate,
                'detail' => $leg['detail'],
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }
    }

    private function resolvePartyPhsId(int $partyId): int
    {
        $projectId = (int) getSelectedTown();
        if ($projectId <= 0) {
            throw new \DomainException('PROJECT_CONTEXT_MISSING');
        }

        $party = StockParty::query()->find($partyId);
        if (!$party) {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }

        if (Schema::hasTable('stock_party_project_accounts')) {
            $mappedId = DB::table('stock_party_project_accounts')
                ->where('project_id', $projectId)->where('stock_party_id', $partyId)
                ->value('project_head_subhead_id');
            if ($mappedId !== null) {
                $account = ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
                    ->whereKey((int) $mappedId)->first();
                if (!$account) throw new \DomainException('PARTY_ACCOUNT_MAPPING_INACTIVE');
                return (int) $account->id;
            }
        }

        $partyHead = trim((string) $party->head_account);
        $partySubhead = trim((string) $party->sub_head_account);
        if ($partyHead === '' || $partySubhead === '') {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }

        return $this->systemHeadSubheadAccountId($projectId, $partyHead, $partySubhead, true);
    }

    private function resolveStockKhataPhsId(): int
    {
        $projectId = (int) getSelectedTown();
        if ($projectId <= 0) {
            throw new \DomainException('PROJECT_CONTEXT_MISSING');
        }

        return $this->systemHeadSubheadAccountId($projectId, 'Stock', 'Stock Khata', true);
    }

    private function postBillLedger(StockBill $bill, string $billType, int $totalMinor, string $billDate, array $lines): void
    {
        if (\App\Services\Finance\V2OriginalWorkflowAccess::selected()) {
            \App\Services\Finance\V2OriginalWorkflowAccess::authorize((int) getSelectedTown());
            return;
        }
        $projectId = (int) getSelectedTown();
        if ($projectId <= 0) {
            throw new \DomainException('PROJECT_CONTEXT_MISSING');
        }

        $party = StockParty::query()->find($bill->party_id);
        if (!$party) {
            throw new HttpException(422, 'Selected party not found.');
        }

        $partyProjectHeadSubheadId = $this->resolvePartyPhsId((int) $party->id);
        $stockProjectHeadSubheadId = $this->systemHeadSubheadAccountId($projectId, 'Stock', 'Stock Khata', true);
        $detail = $this->buildStockLedgerDetail([
            'kind' => 'BILL',
            'number' => $bill->bill_no,
            'date' => $billDate,
            'direction' => $billType,
            'party_name' => $party->name,
            'party_type' => $party->type,
            'total_minor' => $totalMinor,
        ], $lines);

        $reference = 'STOCK-BILL:' . $bill->id;
        $ledgerType = 'JV';
        $amount = $this->minorToLedgerAmount($totalMinor);

        if ($billType === 'purchase') {
            $legs = [
                [
                    'project_head_subheads_id' => $stockProjectHeadSubheadId,
                    'amount_in' => $amount,
                    'amount_out' => 0,
                    'detail' => $detail,
                ],
                [
                    'project_head_subheads_id' => $partyProjectHeadSubheadId,
                    'amount_in' => 0,
                    'amount_out' => $amount,
                    'detail' => $detail,
                ],
            ];
        } else {
            $legs = [
                [
                    'project_head_subheads_id' => $partyProjectHeadSubheadId,
                    'amount_in' => $amount,
                    'amount_out' => 0,
                    'detail' => $detail,
                ],
                [
                    'project_head_subheads_id' => $stockProjectHeadSubheadId,
                    'amount_in' => 0,
                    'amount_out' => $amount,
                    'detail' => $detail,
                ],
            ];
        }

        if (app(\App\Services\Finance\StockLedgerPostingGuard::class)
            ->alreadyPosted($reference, $projectId, $billDate, $legs)) {
            return;
        }

        $customerLedger = CustomerLedger::query()
            ->where('transaction_type', $ledgerType)
            ->where('reference', $reference)
            ->where('project_id', $projectId)
            ->first();

        if (!$customerLedger) {
            $customerLedger = CustomerLedger::create([
                'transaction_type' => $ledgerType,
                'type_id' => get_new_typeID($ledgerType),
                'reference' => $reference,
                'project_id' => $projectId,
                'customer_id' => null,
                'plot_id' => null,
                'amount_in' => $billType === 'sale' ? $amount : 0,
                'amount_out' => $billType === 'purchase' ? $amount : 0,
                'description' => $detail,
                'date' => $billDate,
                'payment_type' => 1,
                'is_active' => 1,
                'is_approve' => 0,
            ]);
            app(\App\Services\Finance\ProjectVoucherDisplayIdentity::class)->assign(
                $projectId, 'customer_ledger', (int) $customerLedger->id, $ledgerType, $billDate
            );
        }

        $voucherNumber = app(\App\Services\Finance\ProjectVoucherAllocator::class)
            ->next($projectId, $ledgerType);
        $typeId = app(\App\Services\Finance\ProjectVoucherAllocator::class)
            ->nextLedgerId($projectId, $ledgerType);
        $userId = auth()->id();

        foreach ($legs as $leg) {
            Ledger::create([
                'customer_ledger_id' => $customerLedger->id,
                'voucher_number' => $voucherNumber,
                'type' => $ledgerType,
                'type_id' => $typeId,
                'project_head_subheads_id' => $leg['project_head_subheads_id'],
                'reference' => $reference,
                'amount_in' => $leg['amount_in'],
                'amount_out' => $leg['amount_out'],
                'is_active' => 1,
                'date' => $billDate,
                'detail' => $leg['detail'],
                'update_by' => $userId,
                'create_by' => $userId,
                'status' => 0,
            ]);
        }
    }

    private function minorToLedgerAmount(int $amountMinor): string
    {
        return number_format($amountMinor, 2, '.', '');
    }

    private function buildStockLedgerDetail(array $meta, array $lines): string
    {
        $direction = strtolower((string) ($meta['direction'] ?? ''));
        $partyName = (string) ($meta['party_name'] ?? 'Unknown Party');
        $number = (string) ($meta['number'] ?? '-');
        $kind = strtoupper((string) ($meta['kind'] ?? 'ENTRY'));
        $date = (string) ($meta['date'] ?? '');
        $totalMinor = (int) ($meta['total_minor'] ?? 0);

        if ($kind === 'ENTRY' && $direction === 'in') {
            $lead = "Stock purchase from {$partyName}";
        } elseif ($kind === 'ENTRY' && $direction === 'out') {
            $lead = "Stock issued to {$partyName}";
        } elseif ($kind === 'BILL' && $direction === 'purchase') {
            $lead = "Stock purchase bill from {$partyName}";
        } else {
            $lead = "Stock sale/issue bill for {$partyName}";
        }

        $lineText = collect($lines)->map(function ($line) {
            $item = (string) ($line['item_name'] ?? 'Item');
            $qty = (string) ($line['qty_decimal'] ?? '0.000');
            $unit = trim((string) ($line['unit'] ?? ''));
            $rate = $this->minorToLedgerAmount((int) ($line['rate_minor'] ?? 0));
            $amount = $this->minorToLedgerAmount((int) ($line['amount_minor'] ?? 0));
            $qtyWithUnit = trim($qty . ' ' . $unit);
            return "{$item}: {$qtyWithUnit} @ {$rate} = {$amount}";
        })->implode('; ');

        $dateLabel = $date !== '' ? date('d M Y', strtotime($date)) : '';
        $total = $this->minorToLedgerAmount($totalMinor);
        $directionLabel = strtoupper((string) ($meta['direction'] ?? ''));

        $parts = [
            $lead,
            "{$kind} #{$number} ({$directionLabel})",
        ];
        if ($lineText !== '') {
            $parts[] = $lineText;
        }
        $parts[] = "Total: {$total}";
        if ($dateLabel !== '') {
            $parts[] = "({$dateLabel})";
        }

        return implode(' - ', $parts);
    }

    private function systemHeadSubheadAccountId(int $projectId, string $headName, string $subheadName, bool $createIfMissing = false): int
    {
        $head = HeadAccounting::where('id', $headName)->first();
        $subhead = SubheadAccounting::where('id', $subheadName)->first();

        if (!$head && $createIfMissing) {
            $head = HeadAccounting::create([
                'name' => $headName,
                'is_active' => 1,
                'acct_type' => 0,
                'create_by' => Auth::id(),
            ]);
        }

        if (!$subhead && $createIfMissing) {
            $subhead = SubheadAccounting::create([
                'name' => $subheadName,
                'is_active' => 1,
                'create_by' => Auth::id(),
            ]);
        }

        if (!$head || !$subhead) {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }
        if ((int) $head->is_active !== 1 || (int) $subhead->is_active !== 1) {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_INACTIVE');
        }

        $projectHeadSubhead = ProjectHeadSubhead::firstOrCreate([
            'project_id' => $projectId,
            'head_accounting_id' => $head->id,
            'subhead_accounting_id' => $subhead->id,
            'plot_id' => null,
            'customer_id' => null,
        ]);

        if (!ProjectHeadSubhead::availableForPosting()->whereKey($projectHeadSubhead->id)->exists()) {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_INACTIVE');
        }

        return (int) $projectHeadSubhead->id;
    }

    private function projectPartyAccountMap(int $projectId, array $partyIds)
    {
        if ($projectId <= 0 || !$partyIds || !Schema::hasTable('stock_party_project_accounts')) {
            return collect();
        }

        return DB::table('stock_party_project_accounts as link')
            ->join('project_head_subheads as account', function ($join) {
                $join->on('account.id', '=', 'link.project_head_subhead_id')
                    ->on('account.project_id', '=', 'link.project_id');
            })
            ->where('link.project_id', $projectId)->whereIn('link.stock_party_id', $partyIds)
            ->get(['link.stock_party_id', 'account.id as account_id', 'account.head_accounting_id', 'account.subhead_accounting_id'])
            ->keyBy('stock_party_id')->map(fn ($row) => [
                'head' => (int) $row->head_accounting_id,
                'subhead' => (int) $row->subhead_accounting_id,
                'account_id' => (int) $row->account_id,
            ]);
    }

    private function resolveSubmittedPartyAccount(int $projectId, $headId, $subheadId, $accountId = null): ?ProjectHeadSubhead
    {
        if ($accountId !== null) {
            $account = ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
                ->whereKey((int) $accountId)->whereNull('plot_id')->whereNull('customer_id')
                ->lockForUpdate()->first();
            if (!$account) throw new HttpException(422, 'The selected account does not belong to this project or is inactive.');
            return $account;
        }
        if ($headId === null && $subheadId === null) return null;
        if (!$headId || !$subheadId) {
            throw new HttpException(422, 'Select both a head account and its sub-account.');
        }

        $account = ProjectHeadSubhead::availableForPosting()->where('project_id', $projectId)
            ->where('head_accounting_id', (int) $headId)
            ->where('subhead_accounting_id', (int) $subheadId)
            ->whereNull('plot_id')->whereNull('customer_id')->lockForUpdate()->first();
        if (!$account) throw new HttpException(422, 'The selected account does not belong to this project or is inactive.');
        return $account;
    }

    private function savePartyProjectAccount(int $projectId, int $partyId, int $accountId): void
    {
        if (!Schema::hasTable('stock_party_project_accounts')) {
            throw new HttpException(409, 'Run the stock party account-link migration before assigning accounting accounts.');
        }

        $link = DB::table('stock_party_project_accounts')->where('project_id', $projectId)
            ->where('stock_party_id', $partyId)->first();
        if ($link) {
            if ((int) $link->project_head_subhead_id === $accountId) return;
            DB::table('stock_party_project_accounts')->where('id', $link->id)->where('project_id', $projectId)->update([
                'project_head_subhead_id' => $accountId, 'changed_by' => Auth::id(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('stock_party_project_accounts')->insert([
                'project_id' => $projectId, 'stock_party_id' => $partyId,
                'project_head_subhead_id' => $accountId, 'changed_by' => Auth::id(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (Schema::hasTable('stock_party_project_account_audits')) {
            DB::table('stock_party_project_account_audits')->insert([
                'project_id' => $projectId, 'stock_party_id' => $partyId,
                'previous_account_id' => $link->project_head_subhead_id ?? null,
                'new_account_id' => $accountId, 'actor_id' => Auth::id(), 'created_at' => now(),
            ]);
        }
    }

    private function parseQtyToInt($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        if ($v === '' || !preg_match('/^\\d+(\\.\\d{1,3})?$/', $v)) {
            return null;
        }
        $parts = explode('.', $v, 2);
        $whole = (int) $parts[0];
        $frac = $parts[1] ?? '';
        $frac = str_pad(substr($frac, 0, 3), 3, '0');
        return ($whole * 1000) + (int) $frac;
    }

    private function qtyIntToDecimal(int $qtyInt): string
    {
        $whole = intdiv($qtyInt, 1000);
        $frac = $qtyInt % 1000;
        return $whole . '.' . str_pad((string) $frac, 3, '0', STR_PAD_LEFT);
    }

    private function calcAmountMinor(int $qtyInt, int $rateMinor): int
    {
        return (int) intdiv($qtyInt * $rateMinor, 1000);
    }

    private function applyStockAdjustments(int $projectId, array $totalsByItem, string $direction, $itemNames): void
    {
        $itemIds = array_keys($totalsByItem);
        sort($itemIds);

        $balances = DB::table('stock_project_item_balances')->where('project_id', $projectId)
            ->whereIn('item_id', $itemIds)
            ->orderBy('item_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('item_id');

        foreach ($itemIds as $itemId) {
            $delta = (int) $totalsByItem[$itemId];
            $currentRow = $balances->get($itemId);
            $currentInt = $currentRow ? $this->parseQtyToInt($currentRow->on_hand_qty) : 0;
            if ($currentInt === null) {
                $currentInt = 0;
            }

            if ($direction === 'OUT' && (!$currentRow || $currentInt < $delta)) {
                $name = $itemNames[$itemId] ?? ('Item #' . $itemId);
                throw new HttpException(409, 'Insufficient stock for ' . $name);
            }

            $newInt = $direction === 'IN' ? ($currentInt + $delta) : ($currentInt - $delta);
            $payload = [
                'on_hand_qty' => $this->qtyIntToDecimal($newInt),
                'updated_at' => now(),
            ];

            if ($currentRow) {
                DB::table('stock_project_item_balances')
                    ->where('project_id', $projectId)->where('item_id', $itemId)
                    ->update($payload);
            } else {
                DB::table('stock_project_item_balances')
                    ->insert(array_merge($payload, [
                        'project_id' => $projectId,
                        'item_id' => $itemId,
                        'created_at' => now(),
                    ]));
            }
        }
    }

    private function validationError($validator)
    {
        return response()->json([
            'status' => 'error',
            'key' => 'validation_failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    private function errorResponse(string $key, ?string $message, int $status = 400, ?array $errors = null)
    {
        $payload = [
            'status' => 'error',
            'key' => $key,
        ];
        if ($message) {
            $payload['message'] = $message;
        }
        if ($errors) {
            $payload['errors'] = $errors;
        }
        return response()->json($payload, $status);
    }

}
