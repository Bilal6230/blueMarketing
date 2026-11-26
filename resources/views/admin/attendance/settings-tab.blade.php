<div class="tab-pane {{ $attendanceTabActive ? 'active' : '' }}" id="attendance-machine">
    @if(!$attendanceTablesAvailable)
        <div class="alert alert-warning">Attendance device tables are not installed. Apply the attendance setup migration.</div>
    @elseif(!$canManageAttendanceDevice || !auth()->user()->can('update setting'))
        <div class="alert alert-info">Device settings can be managed by an authorized project administrator.</div>
    @elseif(!$attendanceProjectId)
        <div class="alert alert-info">Select a project to configure its attendance devices.</div>
    @else
        <style>
        #attendance-machine .as-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
        #attendance-machine .as-toolbar h4{font-weight:700;font-size:19px;margin:0;color:#204d78}
        #attendance-machine .as-toolbar-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        #attendance-machine .as-toolbar-actions select{min-width:215px}
        #attendance-machine .as-layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:14px}
        #attendance-machine .as-card{border:1px solid #c2d3e2;border-top:3px solid #2082d7;border-radius:9px;min-width:0;background:white;overflow:hidden}
        #attendance-machine .as-card-header{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 14px;border-bottom:1px solid #c2d3e2;background:#f4f8fc}
        #attendance-machine .as-card-header h5{font-size:15px;margin:0;font-weight:700;color:#174c7b}
        #attendance-machine .as-card-body{padding:14px}
        #attendance-machine .as-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 14px}
        #attendance-machine .as-grid .as-wide{grid-column:1/-1}
        #attendance-machine .as-grid label,#attendance-machine .as-mapping-form label{font-weight:650;font-size:13px;display:block;margin-bottom:5px}
        #attendance-machine .as-grid input,#attendance-machine .as-grid select{width:100%;font-size:14px}
        #attendance-machine .as-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:13px}
        #attendance-machine .as-hint{font-size:12px;color:#6c7988;line-height:1.5}
        #attendance-machine .as-mapping-form{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr) auto;gap:9px;align-items:end}
        #attendance-machine .as-mapping-table{font-size:13px;margin-top:12px;max-height:270px;overflow:auto}
        #attendance-machine .as-mapping-table table{margin:0}
        #attendance-machine .as-mapping-table th{background:#e9f2fa;position:sticky;top:0;border-bottom:2px solid #9abbd5}
        #attendance-machine .as-mapping-table tbody tr:nth-child(even){background:#f2f7fb}
        #attendance-machine .as-mapping-empty{padding:28px 8px;text-align:center;color:#76879a}
        #attendance-machine .as-busy{opacity:.65;pointer-events:none}
        #attendance-machine .as-field-error{font-size:11px;color:#b42318;display:block;min-height:0}
        @media(max-width:1150px){#attendance-machine .as-layout{grid-template-columns:1fr}}
        @media(max-width:650px){#attendance-machine .as-grid,#attendance-machine .as-mapping-form{grid-template-columns:1fr}#attendance-machine .as-grid .as-wide{grid-column:auto}#attendance-machine .as-toolbar-actions select{min-width:160px}}
        </style>
        <div class="as-toolbar">
            <div><h4>Attendance Machine</h4><small class="text-muted">Project-wise device setup and employee mapping · saved via AJAX</small></div>
            <div class="as-toolbar-actions">
                <select id="as-device-select" class="form-control" aria-label="Select attendance device">
                    <option value="">New device</option>
                    @foreach($attendanceDevices as $item)
                        <option value="{{ $item->id }}" @selected((int)($attendanceDevice?->id ?? 0)===(int)$item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
                <button type="button" id="as-new-device" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Add Device</button>
            </div>
        </div>
        <div class="as-layout" id="as-workspace"
             data-device-url="{{ route('attendance.device.settings',['device'=>'987654321']) }}"
             data-project-id="{{ $attendanceProjectId }}">
            <section class="as-card">
                <div class="as-card-header"><h5 id="as-editor-title">Device Configuration</h5><span id="as-device-status" class="badge badge-warning">{{ $attendanceDevice ? 'Saved · Not connected' : 'New device · Not connected' }}</span></div>
                <div class="as-card-body">
                    <p class="as-hint">Add Device starts a blank editor. Selecting a saved device loads its settings here, without reloading the page.</p>
                    <form id="as-device-form" method="POST" action="{{ route('attendance.device.save') }}">
                        @csrf
                        <input type="hidden" name="device_id" value="{{ $attendanceDevice?->id ?? '' }}">
                        <div class="as-grid">
                            <div><label>Device name *</label><input name="name" class="form-control" maxlength="120" required placeholder="Main office terminal" value="{{ $attendanceDevice?->name ?? '' }}"><small class="as-field-error" data-field="name"></small></div>
                            <div><label>Vendor</label><input name="vendor" class="form-control" maxlength="120" placeholder="Manufacturer" value="{{ $attendanceDevice?->vendor ?? '' }}"></div>
                            <div><label>Model *</label><input name="model" class="form-control" maxlength="120" required placeholder="Device model" value="{{ $attendanceDevice?->model ?? '' }}"><small class="as-field-error" data-field="model"></small></div>
                            <div><label>Connection type</label><select name="connection_type" class="form-control">
                                @foreach(['network'=>'Local network','cloud'=>'Cloud service','webhook'=>'Webhook','sdk'=>'Vendor SDK','other'=>'Other'] as $key=>$label)
                                    <option value="{{ $key }}" @selected(($attendanceDevice?->connection_type ?? 'network')===$key)>{{ $label }}</option>
                                @endforeach
                            </select></div>
                            <div class="as-wide"><label>IP address / Hostname / Endpoint</label><input name="host" class="form-control" maxlength="255" value="{{ $attendanceDevice?->host ?? '' }}"></div>
                            <div><label>Port *</label><input type="number" name="port" class="form-control" min="1" max="65535" required value="{{ $attendanceDevice?->port ?? 80 }}"><small class="as-field-error" data-field="port"></small></div>
                            <div><label>Protocol / API version</label><input name="protocol" class="form-control" maxlength="80" value="{{ $attendanceDevice?->protocol ?? '' }}"></div>
                            <div><label>Username / API key name</label><input name="username" class="form-control" maxlength="120" autocomplete="off" value="{{ $attendanceDevice?->username ?? '' }}"></div>
                            <div><label>Password / Secret</label><input type="password" name="password" class="form-control" maxlength="500" autocomplete="new-password" placeholder="{{ $attendanceDevice?->password ? 'Saved secret · blank keeps current' : 'Optional secret' }}"></div>
                        </div>
                        <div class="as-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i><span id="as-save-caption">Save Device</span></button>
                            <button type="button" class="btn btn-outline-secondary" disabled title="A vendor integration must be verified first">Test Connection</button>
                            <button type="button" class="btn btn-outline-secondary" disabled title="A vendor integration must be verified first">Sync Attendance</button>
                        </div>
                    </form>
                    <small class="as-hint d-block mt-2">Passwords are encrypted; saved secrets are never returned in the editor. Saving does not connect or sync the device.</small>
                </div>
            </section>
            <section class="as-card">
                <div class="as-card-header"><h5>Employee ID Mapping</h5><small id="as-mapping-count" class="text-muted">{{ $attendanceDevice?->mappings->count() ?? 0 }} mappings</small></div>
                <div class="as-card-body">
                    <p class="as-hint">Link an employee's device user number to their account in this project. Save a device first to enable mappings.</p>
                    <form id="as-mapping-form" method="POST" action="{{ route('attendance.mapping.save') }}">
                        @csrf
                        <input type="hidden" name="device_id" value="{{ $attendanceDevice?->id ?? '' }}">
                        <div class="as-mapping-form">
                            <div><label>Employee</label><select name="user_id" class="form-control" required @disabled(!$attendanceDevice)><option value="">Select employee</option>
                                @foreach($attendanceEmployees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach
                            </select></div>
                            <div><label>Device user ID</label><input name="device_user_id" class="form-control" maxlength="100" required @disabled(!$attendanceDevice)></div>
                            <button type="submit" class="btn btn-info" @disabled(!$attendanceDevice)>Apply</button>
                        </div>
                        <small class="as-field-error" data-field="device_user_id"></small>
                    </form>
                    <div class="as-mapping-table">
                        <table class="table table-bordered table-sm">
                            <thead><tr><th>Employee</th><th>Device User ID</th><th>Action</th></tr></thead>
                            <tbody id="as-mapping-rows">
                                @forelse($attendanceDevice?->mappings ?? collect() as $mapping)
                                    <tr data-user-id="{{ $mapping->user_id }}"><td>{{ $mapping->user?->name ?? 'Employee unavailable' }}</td><td>{{ $mapping->device_user_id }}</td><td><button type="button" class="btn btn-sm btn-outline-primary as-edit-mapping" data-user="{{ $mapping->user_id }}" data-device-user="{{ $mapping->device_user_id }}">Edit</button></td></tr>
                                @empty
                                    <tr class="as-empty-row"><td colspan="3" class="as-mapping-empty">No employee mappings for this device.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <script>
        $(function(){
            'use strict';
            var $tab=$('#attendance-machine'),$workspace=$('#as-workspace'),$deviceForm=$('#as-device-form'),
                $mappingForm=$('#as-mapping-form'),$selector=$('#as-device-select');
            if(!$workspace.length)return;
            var activeId=$deviceForm.find('[name=device_id]').val()||'';
            var requestId=0;
            function notice(message,error){
                if(window.toastr){if(error)toastr.error(message);else toastr.success(message);}
                else if(window.Swal)Swal.fire({icon:error?'error':'success',text:message});
            }
            function setLoading(busy){
                $workspace.toggleClass('as-busy',busy);
                $('#as-new-device,#as-device-select').prop('disabled',busy);
            }
            function clearErrors(){
                $tab.find('.as-field-error').text('');
                $tab.find('.is-invalid').removeClass('is-invalid');
            }
            function renderErrors(xhr){
                var json=xhr.responseJSON||{},message=json.message||'Could not save settings.';
                if(json.errors){
                    $.each(json.errors,function(field,items){
                        $tab.find('[data-field="'+field+'"]').text(items[0]||'');
                        var $input=$tab.find('#as-device-form [name="'+field+'"], #as-mapping-form [name="'+field+'"]');
                        $input.addClass('is-invalid');
                        if(items.length)message=items[0];
                    });
                }
                notice(message,true);
            }
            function setMappingAvailable(enabled){
                $mappingForm.find('select[name=user_id],input[name=device_user_id],button[type=submit]').prop('disabled',!enabled);
            }
            function renderMappings(items){
                var $rows=$('#as-mapping-rows').empty();
                $('#as-mapping-count').text(items.length+' mappings');
                if(!items.length){
                    $rows.append($('<tr class="as-empty-row">').append($('<td colspan="3" class="as-mapping-empty">').text('No employee mappings for this device.')));
                    return;
                }
                $.each(items,function(_,item){
                    var $button=$('<button>',{type:'button','class':'btn btn-sm btn-outline-primary as-edit-mapping'}).text('Edit')
                        .attr('data-user',item.user_id).attr('data-device-user',item.device_user_id);
                    $rows.append($('<tr>').attr('data-user-id',item.user_id)
                        .append($('<td>').text(item.user_name),$('<td>').text(item.device_user_id),$('<td>').append($button)));
                });
            }
            function setField(name,value){
                $deviceForm.find('[name="'+name+'"]').val(value===undefined||value===null?'':value);
            }
            function blankEditor(){
                ++requestId; activeId='';
                $deviceForm[0].reset();
                setField('device_id','');setField('password','');setField('port',80);setField('connection_type','network');
                $deviceForm.find('[name=password]').attr('placeholder','Optional secret');
                $selector.val('');
                $('#as-device-status').text('New device · Not connected');
                $('#as-save-caption').text('Save New Device');
                $mappingForm[0].reset();$mappingForm.find('[name=device_id]').val('');
                renderMappings([]);setMappingAvailable(false);clearErrors();
                setLoading(false);
            }
            function applyDevice(device){
                activeId=String(device.id);
                $.each(['name','vendor','model','connection_type','host','port','protocol','username'],function(_,name){
                    setField(name,device[name]);
                });
                setField('device_id',activeId);setField('password','');
                $deviceForm.find('[name=password]').attr('placeholder',device.has_password?'Saved secret · blank keeps current':'Optional secret');
                $selector.val(activeId);
                $('#as-device-status').text('Saved · Not connected');
                $('#as-save-caption').text('Update Device');
                $mappingForm[0].reset();$mappingForm.find('[name=device_id]').val(activeId);
                setMappingAvailable(true);renderMappings(device.mappings||[]);clearErrors();
            }
            function fetchDevice(id){
                if(!id){blankEditor();return;}
                var number=++requestId;setLoading(true);
                var url=$workspace.attr('data-device-url').replace('987654321',encodeURIComponent(id));
                $.ajax({url:url,method:'GET',dataType:'json',headers:{Accept:'application/json'}})
                .done(function(data){if(number===requestId)applyDevice(data.device);})
                .fail(function(xhr){if(number===requestId){$selector.val(activeId);renderErrors(xhr);}})
                .always(function(){if(number===requestId)setLoading(false);});
            }
            $('#as-new-device').on('click',blankEditor);
            $selector.on('change',function(){fetchDevice($(this).val());});
            $deviceForm.on('submit',function(event){
                event.preventDefault();event.stopImmediatePropagation();
                if(!$deviceForm[0].checkValidity()){$deviceForm[0].reportValidity();return;}
                function send(){
                    setLoading(true);clearErrors();
                    $.ajax({url:$deviceForm.attr('action'),type:'POST',data:$deviceForm.serialize(),
                        headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},dataType:'json'})
                    .done(function(data){
                        var device=data.device;
                        var $option=$selector.find('option[value="'+device.id+'"]');
                        if(!$option.length){$option=$('<option>').val(device.id).appendTo($selector);}
                        $option.text(device.name);
                        applyDevice(device);notice(data.message||'Device saved.',false);
                    }).fail(renderErrors).always(function(){setLoading(false);});
                }
                if(window.Swal)Swal.fire({icon:'question',title:'Save Attendance Device?',text:'Save device settings for this project?',
                    showCancelButton:true,confirmButtonText:'Yes, Save',cancelButtonText:'No'}).then(function(answer){if(answer.isConfirmed)send();});
                else send();
            });
            $mappingForm.on('submit',function(event){
                event.preventDefault();event.stopImmediatePropagation();
                if(!activeId){notice('Save a device first.',true);return;}
                if(!$mappingForm[0].checkValidity()){$mappingForm[0].reportValidity();return;}
                function send(){
                    setLoading(true);clearErrors();
                    $.ajax({url:$mappingForm.attr('action'),type:'POST',data:$mappingForm.serialize(),
                        headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},dataType:'json'})
                    .done(function(data){
                        applyDevice(data.device);notice(data.message||'Employee mapping saved.',false);
                    }).fail(renderErrors).always(function(){setLoading(false);});
                }
                if(window.Swal)Swal.fire({icon:'question',title:'Save Employee Mapping?',showCancelButton:true,
                    confirmButtonText:'Yes, Apply',cancelButtonText:'No'}).then(function(answer){if(answer.isConfirmed)send();});
                else send();
            });
            $tab.on('click','.as-edit-mapping',function(){
                $mappingForm.find('[name=user_id]').val($(this).attr('data-user'));
                $mappingForm.find('[name=device_user_id]').val($(this).attr('data-device-user')).trigger('focus');
            });
        });
        </script>
    @endif
</div>
