<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Helpers\SettingHelper;
use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use App\Models\Booking;
use App\Models\CustomerLedger;
use App\Models\Lead;
use App\Models\Plot;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MobileReportsController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function inventory(Request $request)
    {
        $this->authorizeInventoryReport($request->user());

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $user = $request->user();
        $projectId = $this->resolveReportProjectId($request, $user);

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

            $rows = $rowsQuery->get()->map(fn ($row) => $this->transformInventoryRow($row))->all();

            return $this->successResponse('Inventory report loaded.', [
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

            return $this->transformInventoryRow($row);
        })->all();

        return $this->successResponse('Inventory report loaded.', [
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

    public function projects(Request $request)
    {
        $this->authorizeProjectReport($request->user());

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Project::query()
            ->select(['id', 'project', 'address'])
            ->where('is_active', 1)
            ->whereIn('id', $this->getAllowedProjectIds($user)->all())
            ->orderBy('project');

        if (!empty($validated['project_id'])) {
            $projectId = (int) $validated['project_id'];
            $this->ensureProjectAccess($projectId, $user);
            $query->where('id', $projectId);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('project', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $projects = $query->paginate($perPage);

        return $this->successResponse(
            'Project reports loaded.',
            collect($projects->items())->map(function (Project $project) {
                return $this->transformProjectSummary($project, $this->buildProjectSummary((int) $project->id));
            })->all(),
            [
                'current_page' => $projects->currentPage(),
                'per_page' => $projects->perPage(),
                'last_page' => $projects->lastPage(),
                'total' => $projects->total(),
            ]
        );
    }

    public function projectDetail(Request $request, int $project)
    {
        $this->authorizeProjectReport($request->user());
        $this->ensureProjectAccess($project, $request->user());

        $projectModel = Project::query()
            ->select(['id', 'project', 'address'])
            ->where('is_active', 1)
            ->find($project);

        if (!$projectModel) {
            return $this->errorResponse('not_found', 'The requested record was not found.', 404);
        }

        return $this->successResponse(
            'Project report loaded.',
            $this->transformProjectSummary($projectModel, $this->buildProjectSummary($project))
        );
    }

    public function projectPlots(Request $request, int $project)
    {
        $this->authorizePlotReport($request->user());
        $this->ensureProjectAccess($project, $request->user());

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['sold', 'available', 'hold', 'all'])],
            'type' => ['nullable'],
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $status = $validated['status'] ?? 'all';

        $query = Plot::query()
            ->with('holdPlots')
            ->where('project_id', $project)
            ->where('is_active', 1)
            ->orderBy('name');

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status === 'sold') {
            $query->where('sold', 1);
        } elseif ($status === 'available') {
            $query->where('sold', 0)->whereDoesntHave('holdPlots');
        } elseif ($status === 'hold') {
            $query->where('sold', 0)->whereHas('holdPlots');
        }

        $plots = $query->paginate($perPage);

        return $this->successResponse(
            'Project plots loaded.',
            collect($plots->items())->map(fn (Plot $plot) => $this->transformPlot($plot))->all(),
            [
                'current_page' => $plots->currentPage(),
                'per_page' => $plots->perPage(),
                'last_page' => $plots->lastPage(),
                'total' => $plots->total(),
                'selected_project_id' => $project,
            ]
        );
    }

    public function projectBookings(Request $request, int $project)
    {
        $this->authorizeBookingReport($request->user());
        $this->ensureProjectAccess($project, $request->user());

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Booking::query()
            ->with(['customer:id,first_name,last_name,nic_number,phone_number,mobile_number', 'plot:id,name,type,size,unit', 'broker:id,name'])
            ->where('project_id', $project)
            ->where('cancel_status', '0')
            ->orderByDesc('booking_date');

        if (!empty($validated['date_from'])) {
            $query->whereDate('booking_date', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('booking_date', '<=', $validated['date_to']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                })->orWhereHas('plot', function ($plotQuery) use ($search) {
                    $plotQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        $bookings = $query->paginate($perPage);

        return $this->successResponse(
            'Project bookings loaded.',
            collect($bookings->items())->map(fn (Booking $booking) => $this->transformBooking($booking))->all(),
            [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
                'selected_project_id' => $project,
            ]
        );
    }

    public function projectRecovery(Request $request, int $project)
    {
        $this->authorizeBookingReport($request->user());
        $this->ensureProjectAccess($project, $request->user());

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'customer_id' => ['nullable', 'integer'],
            'plot_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $summary = $this->buildProjectSummary($project);

        $query = Booking::query()
            ->with(['customer:id,first_name,last_name', 'plot:id,name'])
            ->where('project_id', $project)
            ->where('cancel_status', '0')
            ->orderByDesc('booking_date');

        if (!empty($validated['date_from'])) {
            $query->whereDate('booking_date', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('booking_date', '<=', $validated['date_to']);
        }

        if (!empty($validated['customer_id'])) {
            $query->where('customer_id', (int) $validated['customer_id']);
        }

        if (!empty($validated['plot_id'])) {
            $query->where('plot_id', (int) $validated['plot_id']);
        }

        $bookings = $query->paginate($perPage);

        return $this->successResponse('Project recovery report loaded.', [
            'summary' => [
                'total_sale' => $summary['total_sale'],
                'received_amount' => $summary['received_amount'],
                'due_amount' => $summary['due_amount'],
                'recovery_percentage' => $summary['recovery_percentage'],
            ],
            'rows' => collect($bookings->items())->map(fn (Booking $booking) => $this->transformRecoveryRow($booking))->all(),
        ], [
            'current_page' => $bookings->currentPage(),
            'per_page' => $bookings->perPage(),
            'last_page' => $bookings->lastPage(),
            'total' => $bookings->total(),
            'selected_project_id' => $project,
        ]);
    }

    public function projectCustomers(Request $request, int $project)
    {
        $this->authorizeBookingReport($request->user());
        $this->ensureProjectAccess($project, $request->user());

        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $customerIds = Booking::query()
            ->where('project_id', $project)
            ->where('cancel_status', '0')
            ->pluck('customer_id')
            ->unique()
            ->values();

        $query = Lead::query()
            ->select(['id', 'first_name', 'last_name', 'nic_number', 'phone_number', 'mobile_number'])
            ->whereIn('id', $customerIds)
            ->orderBy('first_name');

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate($perPage);
        $pageCustomerIds = collect($customers->items())->pluck('id')->all();

        $bookingStats = Booking::query()
            ->select(['customer_id', DB::raw('COUNT(*) as booking_count'), DB::raw('SUM(total_price) as total_sale')])
            ->where('project_id', $project)
            ->where('cancel_status', '0')
            ->whereIn('customer_id', $pageCustomerIds)
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        return $this->successResponse(
            'Project customers loaded.',
            collect($customers->items())->map(function (Lead $lead) use ($bookingStats) {
                return $this->transformCustomer($lead, $bookingStats->get($lead->id));
            })->all(),
            [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
                'selected_project_id' => $project,
            ]
        );
    }

    protected function authorizeInventoryReport(User $user): void
    {
        if ($user->can('view inventory') || $user->can('read stock')) {
            return;
        }

        // TODO: Add dedicated inventory permission to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function authorizeProjectReport(User $user): void
    {
        if ($user->can('view project report') || $user->can('read project')) {
            return;
        }

        // TODO: Add dedicated project report permission to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function authorizePlotReport(User $user): void
    {
        if ($user->can('read plot') || $user->can('view project report')) {
            return;
        }

        // TODO: Add dedicated plot/project report permissions to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function authorizeBookingReport(User $user): void
    {
        if ($user->can('view booking report') || $user->can('read plot')) {
            return;
        }

        // TODO: Add dedicated booking report permission to PermissionSeeder when available.
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return;
        }

        throw new AuthorizationException('You do not have permission to perform this action.');
    }

    protected function resolveReportProjectId(Request $request, User $user): ?int
    {
        return $this->resolveSelectedProjectId($request, $user);
    }

    protected function ensureProjectAccess(int $projectId, User $user): void
    {
        if (!$this->getAllowedProjectIds($user)->contains($projectId)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    protected function getAllowedProjectIds(User $user): Collection
    {
        return $this->allowedProjects($user)->pluck('id');
    }

    protected function buildProjectSummary(int $projectId): array
    {
        $plotsBase = Plot::query()
            ->where('project_id', $projectId)
            ->where('is_active', 1);

        $totalPlots = (clone $plotsBase)->count();
        $soldPlots = (clone $plotsBase)->where('sold', 1)->count();
        $holdPlots = (clone $plotsBase)->where('sold', 0)->whereHas('holdPlots')->count();
        $availablePlots = (clone $plotsBase)->where('sold', 0)->whereDoesntHave('holdPlots')->count();

        $bookingsBase = Booking::query()
            ->where('project_id', $projectId)
            ->where('cancel_status', '0');

        $totalSale = (float) (clone $bookingsBase)->sum('total_price');

        // Mirrors web recovery logic: received payments are tracked as amount_out on customer ledger.
        $receivedAmount = (float) CustomerLedger::query()
            ->where('project_id', $projectId)
            ->where('is_active', 1)
            ->whereNotNull('plot_id')
            ->sum('amount_out');

        $dueAmount = max(0, $totalSale - $receivedAmount);

        return [
            'total_plots' => $totalPlots,
            'sold_plots' => $soldPlots,
            'available_plots' => $availablePlots,
            'hold_plots' => $holdPlots,
            'total_sale' => $this->money($totalSale),
            'received_amount' => $this->money($receivedAmount),
            'due_amount' => $this->money($dueAmount),
            'recovery_percentage' => $this->percent($totalSale > 0 ? ($receivedAmount / $totalSale) * 100 : 0),
        ];
    }

    protected function transformProjectSummary(Project $project, array $summary): array
    {
        return [
            'project_id' => (int) $project->id,
            'project_name' => $project->project,
            'address' => $project->address,
            'total_plots' => (int) $summary['total_plots'],
            'sold_plots' => (int) $summary['sold_plots'],
            'available_plots' => (int) $summary['available_plots'],
            'hold_plots' => (int) $summary['hold_plots'],
            'total_sale' => $summary['total_sale'],
            'received_amount' => $summary['received_amount'],
            'due_amount' => $summary['due_amount'],
            'recovery_percentage' => $summary['recovery_percentage'],
        ];
    }

    protected function transformPlot(Plot $plot): array
    {
        return [
            'id' => (int) $plot->id,
            'name' => $plot->name,
            'type' => $this->plotTypeLabel($plot->type),
            'size' => (string) $plot->size,
            'unit' => $this->plotUnitLabel($plot->unit),
            'amount' => $this->money($plot->amount ?? 0),
            'sold' => (bool) ((int) ($plot->sold ?? 0)),
            'hold' => $plot->relationLoaded('holdPlots')
                ? collect($plot->holdPlots ? [$plot->holdPlots] : [])->isNotEmpty()
                : $plot->holdPlots()->exists(),
        ];
    }

    protected function transformBooking(Booking $booking): array
    {
        $customer = $booking->customer;
        $plot = $booking->plot;

        return [
            'id' => (int) $booking->id,
            'booking_date' => $booking->booking_date
                ? Carbon::parse($booking->booking_date)->toDateString()
                : null,
            'customer' => $customer ? [
                'id' => (int) $customer->id,
                'name' => trim($customer->first_name . ' ' . $customer->last_name),
                'phone' => $this->maskPhone($customer->mobile_number ?: $customer->phone_number),
                'nic_masked' => $this->maskSensitiveId($customer->nic_number),
            ] : null,
            'plot' => $plot ? [
                'id' => (int) $plot->id,
                'name' => $plot->name,
                'type' => $this->plotTypeLabel($plot->type),
                'size' => (string) $plot->size,
                'unit' => $this->plotUnitLabel($plot->unit),
            ] : [
                'id' => (int) $booking->plot_id,
                'name' => null,
                'type' => $this->plotTypeLabel($booking->plot_type),
                'size' => (string) $booking->plot_size,
                'unit' => '',
            ],
            'broker' => $booking->broker ? [
                'id' => (int) $booking->broker->id,
                'name' => $booking->broker->name,
            ] : null,
            'total_price' => $this->money($booking->total_price),
            'status' => $booking->status,
        ];
    }

    protected function transformRecoveryRow(Booking $booking): array
    {
        $receivedAmount = (float) CustomerLedger::query()
            ->where('plot_id', $booking->plot_id)
            ->where('is_active', 1)
            ->sum('amount_out');

        $totalPrice = (float) $booking->total_price;
        $dueAmount = max(0, $totalPrice - $receivedAmount);

        return [
            'booking_id' => (int) $booking->id,
            'customer' => $booking->customer ? [
                'id' => (int) $booking->customer->id,
                'name' => trim($booking->customer->first_name . ' ' . $booking->customer->last_name),
            ] : null,
            'plot' => $booking->plot ? [
                'id' => (int) $booking->plot->id,
                'name' => $booking->plot->name,
            ] : [
                'id' => (int) $booking->plot_id,
                'name' => null,
            ],
            'total_price' => $this->money($totalPrice),
            'received_amount' => $this->money($receivedAmount),
            'due_amount' => $this->money($dueAmount),
            'overdue_amount' => $this->money((float) getSumDueAmount($booking->id)),
        ];
    }

    protected function transformCustomer(Lead $lead, $stats = null): array
    {
        return [
            'id' => (int) $lead->id,
            'name' => trim($lead->first_name . ' ' . $lead->last_name),
            'phone' => $this->maskPhone($lead->mobile_number ?: $lead->phone_number),
            'nic_masked' => $this->maskSensitiveId($lead->nic_number),
            'booking_count' => (int) ($stats->booking_count ?? 0),
            'total_sale' => $this->money((float) ($stats->total_sale ?? 0)),
        ];
    }

    protected function transformInventoryRow(object $row): array
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

    protected function maskSensitiveId(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);

        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', max(5, strlen($value) - 4)) . substr($value, -4);
    }

    protected function money($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    protected function percent($value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    protected function formatQty(float $qty): string
    {
        return number_format($qty, 3, '.', '');
    }

    protected function plotTypeLabel($type): string
    {
        if ((string) $type === '1' || (int) $type === 1) {
            return 'Residential';
        }

        if ((string) $type === '2' || (int) $type === 2) {
            return 'Commercial';
        }

        return (string) ($type ?? '');
    }

    protected function plotUnitLabel($unit): string
    {
        if ($unit === null || $unit === '') {
            return '';
        }

        if (is_numeric($unit)) {
            return (string) (SettingHelper::getUnitTypes((int) $unit) ?? $unit);
        }

        return (string) $unit;
    }
}
