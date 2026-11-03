<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use App\Models\AttendanceDevice;
use App\Models\User;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

class SettingController extends Controller
{
    public function index()
    {
        $x['title']     = 'Settings';
        $x['types'] = Ledger::distinct()->pluck('type')->toArray();
        $x['category']  = Setting::where('type', '!=', 'toggle')->select('category')->groupBy('category')->get();
        $projectId = (int) getSelectedTown();
        $isAttendanceAdmin = auth()->user()->hasAnyRole(['admin', 'superadmin']);
        if (!auth()->user()->hasRole('superadmin') && $projectId > 0) {
            abort_unless((int) auth()->user()->project_id === $projectId, 403);
        }
        $x['attendanceTablesAvailable'] = Schema::hasTable('attendance_devices') && Schema::hasTable('attendance_device_user_mappings');
        $x['attendanceDevices'] = $isAttendanceAdmin && $x['attendanceTablesAvailable'] && $projectId > 0
            ? AttendanceDevice::where('project_id', $projectId)->orderBy('name')->get()
            : collect();
        $x['attendanceDevice'] = $isAttendanceAdmin && $x['attendanceTablesAvailable'] && $projectId > 0
            ? AttendanceDevice::with(['mappings.user:id,name'])->where('project_id', $projectId)
                ->when(request()->filled('device_id'), fn ($q) => $q->whereKey(request('device_id')))
                ->when(!request()->filled('device_id') && !request()->boolean('new_device'), fn ($q) => $q->orderBy('id'))
                ->when(request()->boolean('new_device'), fn ($q) => $q->whereRaw('1 = 0'))
                ->first()
            : null;
        $x['attendanceEmployees'] = $isAttendanceAdmin && $projectId > 0
            ? User::where('project_id', $projectId)->where('status_id', 1)->orderBy('name')->get(['id', 'name'])
            : collect();
        $x['attendanceProjectId'] = $projectId;
        $x['canManageAttendanceDevice'] = $isAttendanceAdmin;
        return view('admin.setting', $x);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'  => ['required', 'string'],
            'role'      => ['required']
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput();
        }
        try {
            $setting = Setting::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'password'  => bcrypt($request->password)
            ]);
            $setting->assignRole($request->role);
            Alert::success('Notification', 'Data <b>' . $setting->name . '</b> successfully Save')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' . $setting->name . '</b> failed to Save : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }

    public function show(Request $request)
    {
        $setting = Setting::find($request->id);
        return response()->json([
            'status'    => Response::HTTP_OK,
            'message'   => 'Data setting by id',
            'data'      => $setting
        ], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => ['nullable'],
            'toggles' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            /**
             * Normal settings (text, textarea, file)
             */
            if ($request->filled('key')) {
                foreach ($request->key as $i => $key) {
                    $keyValue = str_replace('_', ' ', $key);
                    $value = $request->value[$i] ?? null;

                    $setting = Setting::firstOrNew(['key' => $key]);

                    if ($setting->exists) {
                        $setting->update([
                            'value' => $value
                        ]);
                    } else {
                        $setting->fill([
                            'key'      => $key,
                            'value'    => $value,
                            'name'     => $keyValue,
                            'type'     => 'text',
                            'category' => 'information',
                        ])->save();
                    }
                }
            }

            /**
             * Toggle settings
             */
            if ($request->filled('toggles')) {
                foreach ($request->toggles as $key => $value) {
                    $keyValue = str_replace('_', ' ', $key);

                    $setting = Setting::firstOrNew(['key' => $key]);

                    if ($setting->exists) {
                        $setting->update([
                            'value' => $value
                        ]);
                    } else {
                        $setting->fill([
                            'key'      => $key,
                            'value'    => $value,
                            'name'     => $keyValue,
                            'type'     => 'toggle',
                            'category' => 'information',
                        ])->save();
                    }
                }
            }

            Alert::success('Notification', 'Settings saved successfully')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Settings failed to save : ' . $th->getMessage())->toToast()->toHtml();
        }

        return back();
    }

    public function destroy(Request $request)
    {
        try {
            $setting = Setting::find($request->id);
            $setting->delete();
            Alert::success('Notification', 'Data <b>' . $setting->name . '</b> successfully deleted')->toToast()->toHtml();
        } catch (\Throwable $th) {
            Alert::error('Notification', 'Data <b>' . $setting->name . '</b> failed to delete : ' . $th->getMessage())->toToast()->toHtml();
        }
        return back();
    }
}
