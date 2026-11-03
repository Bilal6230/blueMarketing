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

        return redirect()->route('setting.index', ['device_id' => $device->id, 'tab' => 'attendance-machine'])
            ->with('success', 'Device settings saved. Connection and sync will be enabled after the terminal is available for verification.');
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

        return back()->with('success', 'Employee mapping saved.');
    }
}
