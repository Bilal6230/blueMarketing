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
        $data_list = ProjectHeadSubhead::select('project_id', 'head_accounting_id')
            ->distinct()
            ->with('headAccounting')
            ->where(['project_id' => $selectedProjectId])
            ->get();
        $x['headaccounts'] = $data_list;
        return view('admin.stock.index', $x);
    }

    /* ============================================================
     * META
     * ============================================================ */
    public function meta()
    {
        $items = StockItem::query()
            ->select(['id', 'name', 'unit', 'default_rate_minor'])
            ->orderBy('name')
            ->get();

        $parties = StockParty::query()
            ->select(['id', 'type', 'name', 'mobile', 'address', 'head_account', 'sub_head_account'])
            ->orderBy('name')
            ->get();

        $items = $items->map(function ($i) {
            return [
                'id' => (int) $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'default_rate' => (int) $i->default_rate_minor,
            ];
        });

        $parties = $parties->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'type' => $p->type,
                'name' => $p->name,
                'mobile' => $p->mobile,
                'address' => $p->address,
                'head' => $p->head_account,
                'subhead' => $p->sub_head_account,
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

        $q = StockItem::query()
            ->select(['id', 'name', 'unit', 'default_rate_minor', 'created_at'])
            ->orderBy('name');

        if ($request->filled('q')) {
            $s = trim((string) $request->query('q'));
            $q->where('name', 'like', "%{$s}%");
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

    public function parties(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['nullable', Rule::in(['Supplier', 'Consumer', 'Site'])],
            'q' => ['nullable', 'string', 'max:190'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $q = StockParty::query()
            ->select(['id', 'type', 'name', 'mobile', 'address', 'head_account', 'sub_head_account', 'created_at'])
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
                'name' => $p->name,
                'mobile' => $p->mobile,
                'address' => $p->address,
                'head' => $p->head_account,
                'subhead' => $p->sub_head_account,
                'created_at' => $p->created_at,
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

    /**
     * Entries list: flattened per line (your table expects item_name/qty/rate/amount)
     * Filters: item_id, flow, q (slip_no / reason / party / item)
     */
    public function entries(Request $request)
    {
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

        // If dates missing => show current balances (works with your JS too)
        if (!$from || !$to) {
            $rowsQ = DB::table('stock_item_balances as b')
                ->join('stock_items as i', 'i.id', '=', 'b.item_id')
                ->select([
                    'i.id as item_id',
                    'i.name as item_name',
                    'i.unit as item_unit',
                    DB::raw('0 as purchase_qty'),
                    DB::raw('0 as sale_qty'),
                    DB::raw('b.on_hand_qty as available'),
                ])
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
            ->whereBetween(DB::raw('DATE(m.movement_date)'), [$from, $to])
            ->groupBy('m.item_id', 'i.name', 'i.unit')
            ->orderBy('i.name');

        if ($itemId)
            $movQ->where('m.item_id', $itemId);

        $rows = $movQ->get();

        // current available (from balances)
        $balances = DB::table('stock_item_balances')->pluck('on_hand_qty', 'item_id');

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
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:190'],
            'unit' => ['required', 'string', 'max:40'],
            'default_rate' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $item = StockItem::create([
            'name' => trim($data['name']),
            'unit' => trim($data['unit']),
            'default_rate_minor' => (int) ($data['default_rate'] ?? 0),
            'created_by' => auth()->id(),
        ]);

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
            'head' => ['nullable'],
            'subhead' => ['nullable'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }
        $data = $validator->validated();
        $party = StockParty::create([
            'type' => $data['type'],
            'name' => trim($data['name']),
            'mobile' => $data['mobile'] ?? null,
            'address' => $data['address'] ?? null,
            'head_account' => $request->head ?? null,
            'sub_head_account' => $request->subhead ?? null,
            'created_by' => auth()->id(),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'party' => [
                    'id' => (int) $party->id,
                    'type' => $party->type,
                    'name' => $party->name,
                    'mobile' => $party->mobile,
                    'address' => $party->address,
                    'head' => $party->head_account,
                    'subhead' => $party->sub_head_account,
                ],
            ],
            'message' => 'Party created.',
        ]);
    }

    public function updateItem(Request $request, $id)
    {
        $item = StockItem::query()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:190'],
            'unit' => ['required', 'string', 'max:40'],
            'default_rate' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $item->update([
            'name' => trim($data['name']),
            'unit' => trim($data['unit']),
            'default_rate_minor' => (int) ($data['default_rate'] ?? 0),
            'updated_by' => auth()->id(),
        ]);

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
            'message' => 'Item updated.',
        ]);
    }

    public function deleteItem(Request $request, $id)
    {
        $item = StockItem::query()->findOrFail($id);

        try {
            DB::transaction(function () use ($item) {
                $entryIds = DB::table('stock_entry_lines')
                    ->where('item_id', $item->id)
                    ->pluck('entry_id')
                    ->unique()
                    ->values();

                $billIds = DB::table('stock_bill_lines')
                    ->where('item_id', $item->id)
                    ->pluck('bill_id')
                    ->unique()
                    ->values();

                $affectedItemIds = collect();
                if ($entryIds->isNotEmpty()) {
                    $affectedItemIds = $affectedItemIds->merge(
                        DB::table('stock_entry_lines')
                            ->whereIn('entry_id', $entryIds)
                            ->pluck('item_id')
                    );
                }
                if ($billIds->isNotEmpty()) {
                    $affectedItemIds = $affectedItemIds->merge(
                        DB::table('stock_bill_lines')
                            ->whereIn('bill_id', $billIds)
                            ->pluck('item_id')
                    );
                }
                $affectedItemIds = $affectedItemIds->unique()->reject(function ($id) use ($item) {
                    return (int) $id === (int) $item->id;
                })->values();

                if ($entryIds->isNotEmpty()) {
                    DB::table('stock_movements')
                        ->where('source_type', 'entry')
                        ->whereIn('source_id', $entryIds)
                        ->delete();
                    DB::table('stock_entry_lines')->whereIn('entry_id', $entryIds)->delete();
                    DB::table('stock_entries')->whereIn('id', $entryIds)->delete();
                }

                if ($billIds->isNotEmpty()) {
                    DB::table('stock_movements')
                        ->where('source_type', 'bill')
                        ->whereIn('source_id', $billIds)
                        ->delete();
                    DB::table('stock_bill_lines')->whereIn('bill_id', $billIds)->delete();
                    DB::table('stock_bills')->whereIn('id', $billIds)->delete();
                }

                DB::table('stock_item_balances')->where('item_id', $item->id)->delete();

                $item->delete();

                $this->rebuildBalances($affectedItemIds->all());
            });
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse('server_error', 'Unable to delete item.', 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Item deleted.',
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
            'head' => ['nullable', 'string', 'max:255'],
            'subhead' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $party->update([
            'type' => $data['type'],
            'name' => trim($data['name']),
            'mobile' => $data['mobile'] ?? null,
            'address' => $data['address'] ?? null,
            'head_account' => $data['head'] ?? null,
            'sub_head_account' => $data['subhead'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'party' => [
                    'id' => (int) $party->id,
                    'type' => $party->type,
                    'name' => $party->name,
                    'mobile' => $party->mobile,
                    'address' => $party->address,
                    'head' => $party->head_account,
                    'subhead' => $party->sub_head_account,
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
                $entryIds = DB::table('stock_entries')
                    ->where('party_id', $party->id)
                    ->pluck('id')
                    ->unique()
                    ->values();

                $billIds = DB::table('stock_bills')
                    ->where('party_id', $party->id)
                    ->pluck('id')
                    ->unique()
                    ->values();

                $affectedItemIds = collect();
                if ($entryIds->isNotEmpty()) {
                    $affectedItemIds = $affectedItemIds->merge(
                        DB::table('stock_entry_lines')
                            ->whereIn('entry_id', $entryIds)
                            ->pluck('item_id')
                    );
                }
                if ($billIds->isNotEmpty()) {
                    $affectedItemIds = $affectedItemIds->merge(
                        DB::table('stock_bill_lines')
                            ->whereIn('bill_id', $billIds)
                            ->pluck('item_id')
                    );
                }
                $affectedItemIds = $affectedItemIds->unique()->values();

                if ($entryIds->isNotEmpty()) {
                    DB::table('stock_movements')
                        ->where('source_type', 'entry')
                        ->whereIn('source_id', $entryIds)
                        ->delete();
                    DB::table('stock_entry_lines')->whereIn('entry_id', $entryIds)->delete();
                    DB::table('stock_entries')->whereIn('id', $entryIds)->delete();
                }

                if ($billIds->isNotEmpty()) {
                    DB::table('stock_movements')
                        ->where('source_type', 'bill')
                        ->whereIn('source_id', $billIds)
                        ->delete();
                    DB::table('stock_bill_lines')->whereIn('bill_id', $billIds)->delete();
                    DB::table('stock_bills')->whereIn('id', $billIds)->delete();
                }

                $party->delete();

                if ($affectedItemIds->isNotEmpty()) {
                    $this->rebuildBalances($affectedItemIds->all());
                }
            });
        } catch (\Throwable $e) {
            report($e);
            return $this->errorResponse('server_error', 'Unable to delete party.', 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Party deleted.',
        ]);
    }

    private function rebuildBalances(array $itemIds): void
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if (!$itemIds) {
            return;
        }

        $rows = DB::table('stock_movements')
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

            $exists = DB::table('stock_item_balances')->where('item_id', $itemId)->exists();
            if ($exists) {
                DB::table('stock_item_balances')
                    ->where('item_id', $itemId)
                    ->update([
                        'on_hand_qty' => $onHand,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('stock_item_balances')
                    ->insert([
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
        $flow = $data['flow'];
        $linesInput = $data['lines'];
        $itemIds = collect($linesInput)->pluck('item_id')->unique()->values()->all();
        $itemMeta = StockItem::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'unit'])
            ->keyBy('id');
        $itemNames = $itemMeta->mapWithKeys(fn($it) => [(int) $it->id => $it->name])->toArray();

        try {
            $result = DB::transaction(function () use ($data, $flow, $linesInput, $itemNames) {
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

                $this->applyStockAdjustments($totalsByItem, $flow, $itemNames);

                $slipNo = $this->nextCounterNumber('slip_no', '', 2);

                $entry = StockEntry::create([
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
                        'entry_id' => $entry->id,
                        'item_id' => $line['item_id'],
                        'qty' => $line['qty_decimal'],
                        'rate_minor' => $line['rate_minor'],
                        'amount_minor' => $line['amount_minor'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('stock_movements')->insert([
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
            'message' => 'Entry saved.',
        ]);
    }

    public function storeBill(Request $request)
    {
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
        $direction = $data['type'] === 'purchase' ? 'IN' : 'OUT';
        $linesInput = $data['lines'];
        $itemIds = collect($linesInput)->pluck('item_id')->unique()->values()->all();
        $itemMeta = StockItem::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'unit'])
            ->keyBy('id');
        $itemNames = $itemMeta->mapWithKeys(fn($it) => [(int) $it->id => $it->name])->toArray();

        $entryId = null;
        if (!empty($data['entry_no'])) {
            $entryId = StockEntry::where('slip_no', $data['entry_no'])->value('id');
            if (!$entryId) {
                return $this->errorResponse('validation_failed', 'Entry not found for the provided entry number.', 422, [
                    'entry_no' => ['Entry not found.'],
                ]);
            }
        }

        try {
            $result = DB::transaction(function () use ($data, $direction, $linesInput, $itemNames, $entryId) {
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

                $this->applyStockAdjustments($totalsByItem, $direction, $itemNames);

                $counterKey = $data['type'] === 'sale' ? 'bill_sale' : 'bill_purchase';
                $billNo = $this->nextCounterNumber($counterKey, 'B-', 5);

                $bill = StockBill::create([
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
                        'bill_id' => $bill->id,
                        'item_id' => $line['item_id'],
                        'qty' => $line['qty_decimal'],
                        'rate_minor' => $line['rate_minor'],
                        'amount_minor' => $line['amount_minor'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('stock_movements')->insert([
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
                    StockEntry::where('id', $entryId)
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
            'message' => 'Bill saved.',
        ]);
    }

    private function postEntryLedger(StockEntry $entry, string $flow, int $totalMinor, string $entryDate, array $lines): void
    {
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

        $existingLegCount = Ledger::query()
            ->where('type', $ledgerType)
            ->where('reference', $reference)
            ->count();

        if ($existingLegCount >= 2) {
            return;
        }

        $projectId = (int) getSelectedTown();
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
        }

        $voucherNumber = getVocuherNumber($ledgerType);
        $typeId = (int) (getLastLedgerIdByType($ledgerType) ?? 0) + 1;
        $userId = auth()->id();

        foreach ($legs as $leg) {
            $alreadyPosted = Ledger::query()
                ->where('type', $ledgerType)
                ->where('reference', $reference)
                ->where('project_head_subheads_id', $leg['project_head_subheads_id'])
                ->exists();

            if ($alreadyPosted) {
                continue;
            }

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
        $projectId = (int) getSelectedTown();
        if ($projectId <= 0) {
            throw new \DomainException('PROJECT_CONTEXT_MISSING');
        }

        $party = StockParty::query()->find($bill->party_id);
        if (!$party) {
            throw new HttpException(422, 'Selected party not found.');
        }

        $partyHead = trim((string) $party->head_account);
        $partySubhead = trim((string) $party->sub_head_account);
        if ($partyHead === '' || $partySubhead === '') {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }

        $partyProjectHeadSubheadId = $this->systemHeadSubheadAccountId($projectId, $partyHead, $partySubhead, true);
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

        $existingLegCount = Ledger::query()
            ->where('type', $ledgerType)
            ->where('reference', $reference)
            ->count();

        if ($existingLegCount >= 2) {
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
        }

        $voucherNumber = getVocuherNumber($ledgerType);
        $typeId = (int) (getLastLedgerIdByType($ledgerType) ?? 0) + 1;
        $userId = auth()->id();

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

        foreach ($legs as $leg) {
            $alreadyPosted = Ledger::query()
                ->where('type', $ledgerType)
                ->where('reference', $reference)
                ->where('project_head_subheads_id', $leg['project_head_subheads_id'])
                ->exists();

            if ($alreadyPosted) {
                continue;
            }

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

        $projectHeadSubhead = ProjectHeadSubhead::firstOrCreate([
            'project_id' => $projectId,
            'head_accounting_id' => $head->id,
            'subhead_accounting_id' => $subhead->id,
            'plot_id' => null,
            'customer_id' => null,
        ]);

        return (int) $projectHeadSubhead->id;
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

    private function applyStockAdjustments(array $totalsByItem, string $direction, $itemNames): void
    {
        $itemIds = array_keys($totalsByItem);
        sort($itemIds);

        $balances = DB::table('stock_item_balances')
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
                DB::table('stock_item_balances')
                    ->where('item_id', $itemId)
                    ->update($payload);
            } else {
                DB::table('stock_item_balances')
                    ->insert(array_merge($payload, [
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
