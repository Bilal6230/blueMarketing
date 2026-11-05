<div class="tab-pane {{ $attendanceTabActive ? 'active' : '' }}" id="attendance-machine">
    @if(!$attendanceTablesAvailable)
        <div class="alert alert-warning">Attendance device tables are not installed. Run <code>php artisan migrate --path=database/migrations/2026_11_03_000001_create_attendance_device_setup_tables.php</code>.</div>
    @elseif(!$canManageAttendanceDevice)
        <div class="alert alert-info">Device settings can be managed by a project administrator.</div>
    @elseif(!$attendanceProjectId)
        <div class="alert alert-info">Select a project to configure its attendance devices.</div>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div><h4 class="mb-1">Attendance Machine</h4><small class="text-muted">Settings are saved separately for the selected project.</small></div>
            <form method="GET" action="{{ route('setting.index') }}" class="d-flex align-items-center">
                <input type="hidden" name="tab" value="attendance-machine">
                <select name="device_id" class="form-control mr-2" onchange="this.form.submit()"><option value="">Select device</option>@foreach($attendanceDevices as $item)<option value="{{ $item->id }}" @selected($attendanceDevice?->id === $item->id)>{{ $item->name }}</option>@endforeach</select>
                <a class="btn btn-outline-primary" href="{{ route('setting.index', ['tab'=>'attendance-machine','new_device'=>1]) }}">Add device</a>
            </form>
        </div>
        <div class="row">
            <div class="col-xl-7">
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title">Device Configuration</h3><span class="badge badge-warning float-right">{{ $attendanceDevice ? 'Saved · Not connected' : 'New device' }}</span></div>
                    <div class="card-body">
                        <p class="text-muted">Configure any supported attendance device. Connection and sync become available after its protocol and event format are implemented and verified.</p>
                        <form data-ajax-form method="POST" action="{{ route('attendance.device.save') }}" class="row">@csrf
                            @if($attendanceDevice)<input type="hidden" name="device_id" value="{{ $attendanceDevice->id }}">@endif
                            <div class="form-group col-md-6"><label>Device name</label><input name="name" class="form-control" maxlength="120" required value="{{ old('name', $attendanceDevice->name ?? '') }}" placeholder="Main office terminal"></div>
                            <div class="form-group col-md-6"><label>Vendor</label><input name="vendor" class="form-control" maxlength="120" value="{{ old('vendor', $attendanceDevice->vendor ?? '') }}" placeholder="Manufacturer"></div>
                            <div class="form-group col-md-6"><label>Model</label><input name="model" class="form-control" maxlength="120" required value="{{ old('model', $attendanceDevice->model ?? '') }}" placeholder="Device model"></div>
                            <div class="form-group col-md-6"><label>Connection type</label><select name="connection_type" class="form-control"><option value="network" @selected(old('connection_type', $attendanceDevice->connection_type ?? 'network') === 'network')>Local network</option><option value="cloud" @selected(old('connection_type', $attendanceDevice->connection_type ?? '') === 'cloud')>Cloud service</option><option value="webhook" @selected(old('connection_type', $attendanceDevice->connection_type ?? '') === 'webhook')>Webhook</option><option value="sdk" @selected(old('connection_type', $attendanceDevice->connection_type ?? '') === 'sdk')>Vendor SDK</option><option value="other" @selected(old('connection_type', $attendanceDevice->connection_type ?? '') === 'other')>Other</option></select></div>
                            <div class="form-group col-md-7"><label>IP address / Hostname / Endpoint</label><input name="host" class="form-control" maxlength="255" value="{{ old('host', $attendanceDevice->host ?? '') }}"></div>
                            <div class="form-group col-md-5"><label>Port</label><input name="port" type="number" min="1" max="65535" class="form-control" required value="{{ old('port', $attendanceDevice->port ?? 80) }}"></div>
                            <div class="form-group col-md-4"><label>Protocol / API version</label><input name="protocol" class="form-control" maxlength="80" value="{{ old('protocol', $attendanceDevice->protocol ?? '') }}"></div>
                            <div class="form-group col-md-4"><label>Username / API key name</label><input name="username" class="form-control" maxlength="120" autocomplete="off" value="{{ old('username', $attendanceDevice->username ?? '') }}"></div>
                            <div class="form-group col-md-4"><label>Password / Secret {{ $attendanceDevice && $attendanceDevice->password ? '(saved; blank keeps current)' : '' }}</label><input name="password" type="password" class="form-control" maxlength="500" autocomplete="new-password"></div>
                            <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save device settings</button><button type="button" class="btn btn-outline-secondary ml-1" disabled>Test connection</button><button type="button" class="btn btn-outline-secondary ml-1" disabled>Sync attendance</button></div>
                        </form>
                        <small class="text-muted d-block mt-3">Credentials are encrypted in the database. No connection attempt is made when saving.</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title">Employee ID Mapping</h3></div>
                    <div class="card-body">
                        @if($attendanceDevice)
                            <p class="text-muted">Link device employee numbers to employees in this project.</p>
                            <form data-ajax-form method="POST" action="{{ route('attendance.mapping.save') }}" class="form-row align-items-end">@csrf<input type="hidden" name="device_id" value="{{ $attendanceDevice->id }}">
                                <div class="form-group col-md-6"><label>Employee</label><select name="user_id" class="form-control" required><option value="">Select employee</option>@foreach($attendanceEmployees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
                                <div class="form-group col-md-4"><label>Device user ID</label><input name="device_user_id" class="form-control" maxlength="100" required></div>
                                <div class="form-group col-md-2"><button class="btn btn-info btn-block">Save</button></div>
                            </form>
                            <div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><th>Employee</th><th>Device ID</th></tr></thead><tbody>@forelse($attendanceDevice->mappings as $mapping)<tr><td>{{ $mapping->user?->name ?? 'Employee unavailable' }}</td><td>{{ $mapping->device_user_id }}</td></tr>@empty<tr><td colspan="2" class="text-center">No mappings added.</td></tr>@endforelse</tbody></table></div>
                        @else
                            <p class="text-muted text-center py-4">Save or select a device to configure employee mappings.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
