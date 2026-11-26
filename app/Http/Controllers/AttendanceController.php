<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUserMapping;
use App\Models\Punch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    private function projectId(): int
    {
        $projectId = (int) getSelectedTown();
        abort_unless($projectId > 0, 400, 'Select a project first.');
        if (!auth()->user()->hasRole('superadmin')) {
            abort_unless((int) auth()->user()->project_id === $projectId, 403);
        }
        return $projectId;
    }

    private function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'superadmin']);
    }

    public function index(Request $request)
    {
        $projectId = $this->projectId();
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'device_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $query = Punch::with('user:id,name')->where('project_id', $projectId)
            ->whereDate('punch_in', '>=', $from)->whereDate('punch_in', '<=', $to);
        if (!$this->canManage()) {
            $query->where('user_id', auth()->id());
        } elseif (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        $punches = $query->latest('punch_in')->paginate(30)->withQueryString();
        $employees = $this->canManage()
            ? User::where('project_id', $projectId)->where('status_id', 1)->orderBy('name')->get(['id', 'name'])
            : collect();
        return view('admin.attendance.index', compact('punches', 'employees', 'from', 'to', 'projectId') + ['title' => 'Employee Attendance']);
    }

    /**
     * Return only the selected project's device metadata; never expose
     * the encrypted secret or the underlying stored credential value.
     */
    public function deviceSettings(Request $request)
    {
        abort_unless($this->canManage() && $request->user()->can('update setting'), 403);
        $projectId = $this->projectId();
        $device = AttendanceDevice::with(['mappings.user:id,name'])
            ->where('project_id', $projectId)->findOrFail($request->route('device'));
        return response()->json([
            'success' => true,
            'device' => $this->devicePayload($device),
        ]);
    }

    private function devicePayload(AttendanceDevice $device): array
    {
        return [
            'id' => (int) $device->id,
            'name' => (string) $device->name,
            'vendor' => (string) ($device->vendor ?? ''),
            'model' => (string) ($device->model ?? ''),
            'connection_type' => (string) $device->connection_type,
            'host' => (string) ($device->host ?? ''),
            'port' => (int) $device->port,
            'protocol' => (string) ($device->protocol ?? ''),
            'username' => (string) ($device->username ?? ''),
            'has_password' => $device->password !== null && $device->password !== '',
            'mapping_count' => $device->mappings->count(),
            'mappings' => $device->mappings->map(fn ($mapping) => [
                'id' => (int) $mapping->id,
                'user_id' => (int) $mapping->user_id,
                'user_name' => (string) ($mapping->user?->name ?? 'Employee unavailable'),
                'device_user_id' => (string) $mapping->device_user_id,
            ])->values()->all(),
        ];
    }

    public function saveDevice(Request $request)
    {
        abort_unless($this->canManage(), 403);
        $projectId = $this->projectId();
        abort_unless(Schema::hasTable('attendance_devices'), 503, 'Attendance device tables are missing. Run the pending database migrations.');
        $data = $request->validate([
            'device_id' => ['nullable', 'integer', Rule::exists('attendance_devices', 'id')->where('project_id', $projectId)],
            'name' => ['required', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'model' => ['required', 'string', 'max:120'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'connection_type' => ['required', Rule::in(['network', 'cloud', 'webhook', 'sdk', 'other'])],
            'protocol' => ['nullable', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:500'],
        ]);
        $device = !empty($data['device_id'])
            ? AttendanceDevice::where('project_id', $projectId)->findOrFail($data['device_id'])
            : new AttendanceDevice(['project_id' => $projectId]);
        unset($data['device_id']);
        if (($data['password'] ?? '') === '') {
            unset($data['password']);
        }
        $device->fill($data);
        $device->enabled = false; // No device is considered connected until an adapter verifies it.
        $device->save();

        if ($request->expectsJson()) {
            $device->load(['mappings.user:id,name']);
            return response()->json([
                'success' => true,
                'message' => 'Device settings saved. Connection and sync are unavailable until verified.',
                'device' => $this->devicePayload($device),
            ]);
        }
        return redirect()->route('setting.index', ['device_id' => $device->id, 'tab' => 'attendance-machine'])
            ->with('success', 'Device settings saved. Connection and sync will be enabled after the terminal is available for verification.');
    }

    /**
     * Remove only unused setup records. Mapping removal is deliberately
     * separate from a device delete to prevent accidental data loss.
     */
    public function deleteMapping(Request $request, AttendanceDeviceUserMapping $mapping)
    {
        abort_unless($this->canManage() && $request->user()->can('update setting'), 403);
        $projectId = $this->projectId();
        $device = AttendanceDevice::query()->where('project_id', $projectId)
            ->findOrFail($mapping->attendance_device_id);
        $mapping->delete();
        $device->load(['mappings.user:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Employee mapping removed.',
            'device' => $this->devicePayload($device),
        ]);
    }

    public function deleteDevice(Request $request, AttendanceDevice $device)
    {
        abort_unless($this->canManage() && $request->user()->can('update setting'), 403);
        $projectId = $this->projectId();
        abort_unless((int) $device->project_id === $projectId, 404);
        abort_if($device->mappings()->exists(), 409,
            'This device has employee mappings. Remove its mappings before deleting the device.');
        $deviceId = (int) $device->id;
        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unused device removed.',
            'device_id' => $deviceId,
        ]);
    }

    public function saveMapping(Request $request)
    {
        abort_unless($this->canManage(), 403);
        $projectId = $this->projectId();
        abort_unless(Schema::hasTable('attendance_devices') && Schema::hasTable('attendance_device_user_mappings'), 503, 'Attendance device tables are missing. Run the pending database migrations.');
        $device = AttendanceDevice::where('project_id', $projectId)->findOrFail($request->input('device_id'));
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('project_id', $projectId)],
            'device_user_id' => [
                'required', 'string', 'max:100',
                Rule::unique('attendance_device_user_mappings', 'device_user_id')
                    ->where('attendance_device_id', $device->id)
                    ->ignore(AttendanceDeviceUserMapping::where('attendance_device_id', $device->id)
                        ->where('user_id', $request->input('user_id'))->value('id')),
            ],
        ]);
        AttendanceDeviceUserMapping::updateOrCreate(
            ['attendance_device_id' => $device->id, 'user_id' => $data['user_id']],
            ['device_user_id' => $data['device_user_id']]
        );

        if ($request->expectsJson()) {
            $device->load(['mappings.user:id,name']);
            return response()->json([
                'success' => true,
                'message' => 'Employee mapping saved.',
                'device' => $this->devicePayload($device),
            ]);
        }
        return back()->with('success', 'Employee mapping saved.');
    }
}
