<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use App\Models\CustomerLedger;
use App\Models\HeadAccounting;
use App\Models\Ledger;
use App\Models\ProjectHeadSubhead;
use App\Models\StockEntry;
use App\Models\StockEntryLine;
use App\Models\StockItem;
use App\Models\StockParty;
use App\Models\SubheadAccounting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MobileStockController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function meta(Request $request)
    {
        $this->authorizeStockRead($request->user());

        $request->validate([
            'project_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $projectId = $this->resolveStockProjectId($request, $user);

        $balances = DB::table('stock_item_balances')->pluck('on_hand_qty', 'item_id');

        $items = StockItem::query()
            ->select(['id', 'name', 'unit', 'default_rate_minor'])
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->map(fn (StockItem $item) => $this->transformStockItem($item, $balances->get($item->id)))
            ->all();

        $parties = StockParty::query()
            ->select(['id', 'type', 'name', 'mobile', 'address'])
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->map(fn (StockParty $party) => $this->transformStockParty($party))
            ->all();

        $units = collect($items)->pluck('unit')->unique()->values()->all();

        return $this->successResponse('Stock meta loaded.', [
            'items' => $items,
            'parties' => $parties,
            'next_slip_no' => $this->peekSlipNumber(),
            'units' => $units,
        ], [
            'selected_project_id' => $projectId,
        ]);
    }

    public function items(Request $request)
    {
        $this->authorizeStockRead($request->user());

        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);
        $balances = DB::table('stock_item_balances')->pluck('on_hand_qty', 'item_id');

        $query = StockItem::query()
            ->select(['id', 'name', 'unit', 'default_rate_minor'])
            ->where('is_active', 1)
            ->orderBy('name');

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->paginate($perPage);

        return $this->successResponse(
            'Stock items loaded.',
            collect($items->items())->map(fn (StockItem $item) => $this->transformStockItem($item, $balances->get($item->id)))->all(),
            [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ]
        );
    }

    public function parties(Request $request)
    {
        $this->authorizeStockRead($request->user());

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['Supplier', 'Consumer', 'Site'])],
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = StockParty::query()
            ->select(['id', 'type', 'name', 'mobile', 'address'])
            ->where('is_active', 1)
            ->orderBy('name');

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $parties = $query->paginate($perPage);

        return $this->successResponse(
            'Stock parties loaded.',
            collect($parties->items())->map(fn (StockParty $party) => $this->transformStockParty($party))->all(),
            [
                'current_page' => $parties->currentPage(),
                'per_page' => $parties->perPage(),
                'last_page' => $parties->lastPage(),
                'total' => $parties->total(),
            ]
        );
    }

    public function entries(Request $request)
    {
        $this->authorizeStockRead($request->user());

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'flow' => ['nullable', Rule::in(['IN', 'OUT'])],
            'item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'party_id' => ['nullable', 'integer', 'exists:stock_parties,id'],
            'search' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $projectId = $this->resolveStockProjectId($request, $user);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = trim((string) ($validated['search'] ?? ''));

        // TODO: stock_entries has no project_id column; entries are global until schema supports project scoping.
        $query = DB::table('stock_entry_lines as l')
            ->join('stock_entries as e', 'e.id', '=', 'l.entry_id')
            ->join('stock_items as i', 'i.id', '=', 'l.item_id')
            ->join('stock_parties as p', 'p.id', '=', 'e.party_id')
            ->select([
                'e.id as id',
                'e.slip_no',
                'e.entry_date',
                'e.flow',
                'e.reason',
                'e.created_at',
                'p.id as party_id',
                'p.name as party_name',
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

        if (!empty($validated['date_from'])) {
            $query->whereDate('e.entry_date', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('e.entry_date', '<=', $validated['date_to']);
        }
        if (!empty($validated['flow'])) {
            $query->where('e.flow', $validated['flow']);
        }
        if (!empty($validated['item_id'])) {
            $query->where('l.item_id', (int) $validated['item_id']);
        }
        if (!empty($validated['party_id'])) {
            $query->where('e.party_id', (int) $validated['party_id']);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('e.slip_no', 'like', "%{$search}%")
                    ->orWhere('e.reason', 'like', "%{$search}%")
                    ->orWhere('p.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate($perPage);

        $kpiQuery = clone $query;
        $kpi = DB::query()
            ->fromSub(
                $kpiQuery->cloneWithout(['orders'])->cloneWithoutBindings(['order']),
                't'
            )
            ->selectRaw("
                COALESCE(SUM(CASE WHEN flow='IN'  THEN qty ELSE 0 END),0) as in_qty,
                COALESCE(SUM(CASE WHEN flow='OUT' THEN qty ELSE 0 END),0) as out_qty,
                COUNT(*) as line_count
            ")
            ->first();

        return $this->successResponse(
            'Stock entries loaded.',
            collect($page->items())->map(fn ($row) => $this->transformEntryListRow($row))->all(),
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'selected_project_id' => $projectId,
                'kpi' => [
                    'in_qty' => $this->formatQty((float) ($kpi->in_qty ?? 0)),
                    'out_qty' => $this->formatQty((float) ($kpi->out_qty ?? 0)),
                    'line_count' => (int) ($kpi->line_count ?? 0),
                ],
            ]
        );
    }

    public function entryDetail(Request $request, int $entry)
    {
        $this->authorizeStockRead($request->user());

        $request->validate([
            'project_id' => ['nullable', 'integer'],
        ]);

        $projectId = $this->resolveStockProjectId($request, $request->user());

        $stockEntry = StockEntry::query()
            ->with([
                'party:id,type,name,mobile',
                'lines.item:id,name,unit',
            ])
            ->whereNull('deleted_at')
            ->find($entry);

        if (!$stockEntry) {
            return $this->errorResponse('not_found', 'The requested record was not found.', 404);
        }

        return $this->successResponse(
            'Stock entry loaded.',
            $this->transformEntryDetail($stockEntry),
            [
                'selected_project_id' => $projectId,
            ]
        );
    }

    public function storeIn(Request $request)
    {
        return $this->storeStockEntry($request, 'IN');
    }

    public function storeOut(Request $request)
    {
        return $this->storeStockEntry($request, 'OUT');
    }

    public function report(Request $request)
    {
        $this->authorizeStockRead($request->user());

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user = $request->user();
        $projectId = $this->resolveStockProjectId($request, $user);

        $from = $validated['date_from'] ?? null;
        $to = $validated['date_to'] ?? null;
        $itemId = !empty($validated['item_id']) ? (int) $validated['item_id'] : null;

        // TODO: stock balances/movements are global; project_id is used for access context only.
        if (!$from || !$to) {
            $rowsQuery = DB::table('stock_item_balances as b')
                ->join('stock_items as i', 'i.id', '=', 'b.item_id')
                ->select([
                    'i.id as item_id',
                    'i.name as item_name',
                    'i.unit as item_unit',
                    DB::raw('0 as in_qty'),
                    DB::raw('0 as out_qty'),
                    'b.on_hand_qty as available_qty',
                ])
                ->where('i.is_active', 1)
                ->orderBy('i.name');

            if ($itemId) {
                $rowsQuery->where('i.id', $itemId);
            }

            $rows = $rowsQuery->get()->map(fn ($row) => $this->transformStockReportRow($row))->all();

            return $this->successResponse('Stock report loaded.', [
                'rows' => $rows,
                'kpi' => [
                    'items' => count($rows),
                    'in_qty' => $this->formatQty(0),
                    'out_qty' => $this->formatQty(0),
                    'available_qty' => $this->formatQty(collect($rows)->sum(fn ($row) => (float) $row['available_qty'])),
                ],
            ], [
                'selected_project_id' => $projectId,
            ]);
        }

        $movQuery = DB::table('stock_movements as m')
            ->join('stock_items as i', 'i.id', '=', 'm.item_id')
            ->select([
                'm.item_id',
                'i.name as item_name',
                'i.unit as item_unit',
                DB::raw("SUM(CASE WHEN m.direction='IN'  THEN m.qty ELSE 0 END) as in_qty"),
                DB::raw("SUM(CASE WHEN m.direction='OUT' THEN m.qty ELSE 0 END) as out_qty"),
            ])
            ->whereBetween(DB::raw('DATE(m.movement_date)'), [$from, $to])
            ->where('i.is_active', 1)
            ->groupBy('m.item_id', 'i.name', 'i.unit')
            ->orderBy('i.name');

        if ($itemId) {
            $movQuery->where('m.item_id', $itemId);
        }

        $balances = DB::table('stock_item_balances')->pluck('on_hand_qty', 'item_id');
        $rows = $movQuery->get()->map(function ($row) use ($balances) {
            $row->available_qty = $balances[$row->item_id] ?? 0;

            return $this->transformStockReportRow($row);
        })->all();

        return $this->successResponse('Stock report loaded.', [
            'rows' => $rows,
            'kpi' => [
                'items' => count($rows),
                'in_qty' => $this->formatQty(collect($rows)->sum(fn ($row) => (float) $row['in_qty'])),
                'out_qty' => $this->formatQty(collect($rows)->sum(fn ($row) => (float) $row['out_qty'])),
                'available_qty' => $this->formatQty(collect($rows)->sum(fn ($row) => (float) $row['available_qty'])),
            ],
        ], [
            'selected_project_id' => $projectId,
        ]);
    }

    protected function storeStockEntry(Request $request, string $flow): JsonResponse
    {
        $user = $request->user();
        $this->authorizeStockWrite($user);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'party_id' => ['required', 'integer', 'exists:stock_parties,id'],
            'vehicle' => ['nullable', 'string', 'max:80'],
            'driver' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'lines.*.qty' => ['required', 'string', 'regex:/^\d+(\.\d{1,3})?$/'],
            'lines.*.rate' => ['required', 'numeric', 'min:0'],
        ]);

        $projectId = $this->resolveStockProjectId($request, $user, true);
        $linesInput = $validated['lines'];
        $itemIds = collect($linesInput)->pluck('item_id')->unique()->values()->all();
        $itemMeta = StockItem::query()
            ->whereIn('id', $itemIds)
            ->where('is_active', 1)
            ->get(['id', 'name', 'unit'])
            ->keyBy('id');

        if ($itemMeta->count() !== count($itemIds)) {
            return $this->errorResponse('validation_error', 'Please check the submitted fields.', 422, [
                'lines' => ['One or more items are invalid or inactive.'],
            ]);
        }

        $party = StockParty::query()
            ->where('id', $validated['party_id'])
            ->where('is_active', 1)
            ->first();

        if (!$party) {
            return $this->errorResponse('validation_error', 'Please check the submitted fields.', 422, [
                'party_id' => ['Selected party is invalid or inactive.'],
            ]);
        }

        $itemNames = $itemMeta->mapWithKeys(fn ($item) => [(int) $item->id => $item->name])->toArray();

        try {
            $result = DB::transaction(function () use ($validated, $flow, $linesInput, $itemMeta, $itemNames, $user, $projectId) {
                $now = now();
                $lines = [];
                $totalsByItem = [];
                $totalMinor = 0;

                foreach ($linesInput as $idx => $line) {
                    $qtyInt = $this->parseQtyToInt($line['qty']);
                    if ($qtyInt === null || $qtyInt <= 0) {
                        throw new HttpException(422, 'Invalid quantity on line ' . ($idx + 1));
                    }

                    $rateMinor = $this->parseMoneyToMinor($line['rate']);
                    $amountMinor = $this->calculateAmountMinor($qtyInt, $rateMinor);
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

                $this->applyStockMovements($totalsByItem, $flow, $itemNames);

                $slipNo = $this->nextSlipNumber();

                $entry = StockEntry::create([
                    'slip_no' => $slipNo,
                    'entry_date' => $validated['date'],
                    'flow' => $flow,
                    'party_id' => (int) $validated['party_id'],
                    'vehicle_no' => $validated['vehicle'] ?? null,
                    'driver_name' => $validated['driver'] ?? null,
                    'reason' => $validated['reason'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
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
                        'movement_date' => $validated['date'],
                        'direction' => $flow,
                        'item_id' => $line['item_id'],
                        'qty' => $line['qty_decimal'],
                        'rate_minor' => $line['rate_minor'],
                        'amount_minor' => $line['amount_minor'],
                        'source_type' => 'entry',
                        'source_id' => $entry->id,
                        'source_line_id' => $lineRow->id,
                        'party_id' => (int) $validated['party_id'],
                        'created_by' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->postEntryLedger($entry, $flow, $totalMinor, $validated['date'], $lines, $projectId, $user->id);

                return [
                    'id' => (int) $entry->id,
                    'slip_no' => $slipNo,
                ];
            });
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return $this->errorResponse('insufficient_stock', $e->getMessage(), 409);
            }

            return $this->errorResponse('validation_error', $e->getMessage(), 422, [
                'lines' => [$e->getMessage()],
            ]);
        } catch (\DomainException $e) {
            $key = $e->getMessage();
            Log::warning('mobile.stock.entry.ledger.domain_error', [
                'key' => $key,
                'flow' => $flow,
                'party_id' => $validated['party_id'] ?? null,
            ]);

            if (in_array($key, ['PARTY_ACCOUNT_MAPPING_MISSING', 'PROJECT_CONTEXT_MISSING'], true)) {
                return $this->errorResponse(
                    'stock_configuration_missing',
                    'Stock ledger configuration is missing for the selected project.',
                    422
                );
            }

            return $this->errorResponse('server_error', 'Unable to save stock entry.', 500);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('server_error', 'Unable to save stock entry.', 500);
        }

        $message = $flow === 'IN'
            ? 'Stock in entry saved successfully.'
            : 'Stock out entry saved successfully.';

        return $this->successResponse($message, $result, [
            'selected_project_id' => $projectId,
        ]);
    }

    protected function authorizeStockRead(User $user): void
    {
        if ($user->can('read stock') || $user->can('view inventory')) {
            return;
        }

        // TODO: Add dedicated stock/inventory permissions to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function authorizeStockWrite(User $user): void
    {
        if ($user->can('create stock')) {
            return;
        }

        // TODO: Add dedicated stock/inventory permissions to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function resolveStockProjectId(Request $request, User $user, bool $requiredForWrite = false): ?int
    {
        $projectId = $this->resolveSelectedProjectId($request, $user);

        if ($projectId === null && $requiredForWrite) {
            $request->validate([
                'project_id' => ['required'],
            ]);
        }

        return $projectId;
    }

    protected function transformStockItem(StockItem $item, $availableQty = null): array
    {
        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'unit' => $item->unit,
            'default_rate' => $this->minorToMoney($item->default_rate_minor),
            'available_qty' => $availableQty !== null ? $this->formatQty((float) $availableQty) : $this->formatQty(0),
        ];
    }

    protected function transformStockParty(StockParty $party): array
    {
        return [
            'id' => (int) $party->id,
            'type' => $party->type,
            'name' => $party->name,
            'mobile' => $this->maskPhone($party->mobile),
            'address' => $party->address,
        ];
    }

    protected function transformEntryListRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'line_id' => (int) $row->line_id,
            'slip_no' => $row->slip_no,
            'entry_date' => $row->entry_date,
            'flow' => $row->flow,
            'party' => [
                'id' => (int) $row->party_id,
                'name' => $row->party_name,
            ],
            'item' => [
                'id' => (int) $row->item_id,
                'name' => $row->item_name,
                'unit' => $row->item_unit,
            ],
            'qty' => $this->formatQty((float) $row->qty),
            'rate' => $this->minorToMoney((int) $row->rate_minor),
            'amount' => $this->minorToMoney((int) $row->amount_minor),
            'reason' => $row->reason,
            'created_at' => $row->created_at,
        ];
    }

    protected function transformEntryDetail(StockEntry $entry): array
    {
        return [
            'id' => (int) $entry->id,
            'slip_no' => $entry->slip_no,
            'entry_date' => $entry->entry_date?->toDateString(),
            'flow' => $entry->flow,
            'party' => $entry->party ? [
                'id' => (int) $entry->party->id,
                'type' => $entry->party->type,
                'name' => $entry->party->name,
                'mobile' => $this->maskPhone($entry->party->mobile),
            ] : null,
            'vehicle_no' => $entry->vehicle_no,
            'driver_name' => $entry->driver_name,
            'reason' => $entry->reason,
            'lines' => $entry->lines->map(function (StockEntryLine $line) {
                return [
                    'line_id' => (int) $line->id,
                    'item' => $line->item ? [
                        'id' => (int) $line->item->id,
                        'name' => $line->item->name,
                        'unit' => $line->item->unit,
                    ] : null,
                    'qty' => $this->formatQty((float) $line->qty),
                    'rate' => $this->minorToMoney((int) $line->rate_minor),
                    'amount' => $this->minorToMoney((int) $line->amount_minor),
                ];
            })->all(),
        ];
    }

    protected function transformStockReportRow(object $row): array
    {
        return [
            'item_id' => (int) $row->item_id,
            'item_name' => $row->item_name,
            'unit' => $row->item_unit,
            'in_qty' => $this->formatQty((float) ($row->in_qty ?? 0)),
            'out_qty' => $this->formatQty((float) ($row->out_qty ?? 0)),
            'available_qty' => $this->formatQty((float) ($row->available_qty ?? 0)),
        ];
    }

    protected function maskPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phone = trim($phone);

        if (strlen($phone) <= 4) {
            return $phone;
        }

        return substr($phone, 0, 2)
            . str_repeat('*', max(0, strlen($phone) - 4))
            . substr($phone, -2);
    }

    // Mirrors StockController quantity helpers.
    protected function parseQtyToInt($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);
        if ($v === '' || !preg_match('/^\d+(\.\d{1,3})?$/', $v)) {
            return null;
        }

        $parts = explode('.', $v, 2);
        $whole = (int) $parts[0];
        $frac = $parts[1] ?? '';
        $frac = str_pad(substr($frac, 0, 3), 3, '0');

        return ($whole * 1000) + (int) $frac;
    }

    protected function qtyIntToDecimal(int $qtyInt): string
    {
        $whole = intdiv($qtyInt, 1000);
        $frac = $qtyInt % 1000;

        return $whole . '.' . str_pad((string) $frac, 3, '0', STR_PAD_LEFT);
    }

    protected function parseMoneyToMinor($value): int
    {
        return (int) round((float) $value);
    }

    protected function minorToMoney(int $amountMinor): string
    {
        return number_format($amountMinor, 2, '.', '');
    }

    protected function calculateAmountMinor(int $qtyInt, int $rateMinor): int
    {
        return (int) intdiv($qtyInt * $rateMinor, 1000);
    }

    protected function formatQty(float $qty): string
    {
        return number_format($qty, 3, '.', '');
    }

    protected function peekSlipNumber(): string
    {
        $maxSlip = DB::table('stock_entries')->max('slip_no');
        $maxInt = $this->parseSlipNumber($maxSlip);
        $counterNext = DB::table('stock_counters')->where('key', 'slip_no')->value('next_number');
        $next = max((int) ($counterNext ?? 1), $maxInt + 1);

        return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    protected function nextSlipNumber(): string
    {
        return DB::transaction(function () {
            return $this->nextCounterNumber('slip_no', '', 2);
        });
    }

    protected function nextCounterNumber(string $key, string $prefix = '', int $pad = 2): string
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

    protected function parseSlipNumber(?string $slip): int
    {
        if (!$slip) {
            return 0;
        }

        $digits = preg_replace('/\D+/', '', $slip);

        return $digits === '' ? 0 : (int) $digits;
    }

    protected function ensureAvailableStock(array $totalsByItem, array $itemNames): void
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

            if (!$currentRow || $currentInt < $delta) {
                $name = $itemNames[$itemId] ?? ('Item #' . $itemId);
                throw new HttpException(409, 'Insufficient stock for ' . $name);
            }
        }
    }

    protected function applyStockMovements(array $totalsByItem, string $direction, array $itemNames): void
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
                DB::table('stock_item_balances')->insert(array_merge($payload, [
                    'item_id' => $itemId,
                    'created_at' => now(),
                ]));
            }
        }
    }

    // Mirrors StockController ledger posting with explicit mobile project context.
    protected function postEntryLedger(
        StockEntry $entry,
        string $flow,
        int $totalMinor,
        string $entryDate,
        array $lines,
        int $projectId,
        int $userId
    ): void {
        if ($projectId <= 0) {
            throw new \DomainException('PROJECT_CONTEXT_MISSING');
        }

        $reference = 'STOCK-ENTRY:' . $entry->id;
        $ledgerType = 'JV';
        $amount = $this->minorToMoney($totalMinor);
        $party = StockParty::query()->find((int) $entry->party_id);

        if (!$party) {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }

        $partyProjectHeadSubheadId = $this->resolvePartyPhsId((int) $entry->party_id, $projectId, $userId);
        $stockProjectHeadSubheadId = $this->resolveStockKhataPhsId($projectId, $userId);
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

    protected function resolvePartyPhsId(int $partyId, int $projectId, int $userId): int
    {
        $party = StockParty::query()->find($partyId);
        if (!$party) {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }

        $partyHead = trim((string) $party->head_account);
        $partySubhead = trim((string) $party->sub_head_account);
        if ($partyHead === '' || $partySubhead === '') {
            throw new \DomainException('PARTY_ACCOUNT_MAPPING_MISSING');
        }

        return $this->systemHeadSubheadAccountId($projectId, $partyHead, $partySubhead, true, $userId);
    }

    protected function resolveStockKhataPhsId(int $projectId, int $userId): int
    {
        return $this->systemHeadSubheadAccountId($projectId, 'Stock', 'Stock Khata', true, $userId);
    }

    protected function systemHeadSubheadAccountId(
        int $projectId,
        string $headName,
        string $subheadName,
        bool $createIfMissing = false,
        ?int $userId = null
    ): int {
        $head = HeadAccounting::where('id', $headName)->first();
        $subhead = SubheadAccounting::where('id', $subheadName)->first();

        if (!$head && $createIfMissing) {
            $head = HeadAccounting::create([
                'name' => $headName,
                'is_active' => 1,
                'acct_type' => 0,
                'create_by' => $userId,
            ]);
        }

        if (!$subhead && $createIfMissing) {
            $subhead = SubheadAccounting::create([
                'name' => $subheadName,
                'is_active' => 1,
                'create_by' => $userId,
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

    protected function buildStockLedgerDetail(array $meta, array $lines): string
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
        } else {
            $lead = "Stock movement for {$partyName}";
        }

        $lineText = collect($lines)->map(function ($line) {
            $item = (string) ($line['item_name'] ?? 'Item');
            $qty = (string) ($line['qty_decimal'] ?? '0.000');
            $unit = trim((string) ($line['unit'] ?? ''));
            $rate = $this->minorToMoney((int) ($line['rate_minor'] ?? 0));
            $amount = $this->minorToMoney((int) ($line['amount_minor'] ?? 0));
            $qtyWithUnit = trim($qty . ' ' . $unit);

            return "{$item}: {$qtyWithUnit} @ {$rate} = {$amount}";
        })->implode('; ');

        $dateLabel = $date !== '' ? date('d M Y', strtotime($date)) : '';
        $total = $this->minorToMoney($totalMinor);
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
}
