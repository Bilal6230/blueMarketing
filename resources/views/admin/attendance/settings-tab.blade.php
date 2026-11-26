<div class="tab-pane {{ $attendanceTabActive ? 'active' : '' }}" id="attendance-machine">
@if(!$attendanceTablesAvailable)
    <div class="alert alert-warning">Attendance device tables are not installed. Contact your administrator.</div>
@elseif(!$canManageAttendanceDevice || !auth()->user()->can('update setting'))
    <div class="alert alert-info">Attendance devices can be managed by an authorized project administrator.</div>
@elseif(!$attendanceProjectId)
    <div class="alert alert-info">Select a project to configure attendance devices.</div>
@else
<style>
#attendance-machine .as-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap}
#attendance-machine .as-toolbar h4{font-size:19px;font-weight:700;color:#204d78;margin:0}
#attendance-machine .as-layout{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:14px}
#attendance-machine .as-card{border:1px solid #c2d3e2;border-top:3px solid #2082d7;border-radius:9px;background:#fff;min-width:0;overflow:hidden}
#attendance-machine .as-card-head{background:#f4f8fc;border-bottom:1px solid #c2d3e2;padding:10px 12px;display:flex;justify-content:space-between;align-items:center}
#attendance-machine .as-card-head h5{font-size:15px;font-weight:700;color:#174c7b;margin:0}
#attendance-machine .as-card-body{padding:12px}
#attendance-machine .as-table-scroll{max-height:calc(100dvh - 385px);overflow:auto;min-height:130px;overscroll-behavior:contain}
#attendance-machine .as-table{width:100%;font-size:14px;margin:0}
#attendance-machine .as-table th{position:sticky;top:0;z-index:1;background:#eaf2f9;border-bottom:2px solid #9abbd5;white-space:nowrap}
#attendance-machine .as-table td,#attendance-machine .as-table th{padding:9px 8px;vertical-align:middle}
#attendance-machine .as-table tbody tr:nth-child(even){background:#f1f6fa}
#attendance-machine .as-table tbody tr:hover{background:#e6f2fb}
#attendance-machine .as-actions{display:flex;gap:5px;flex-wrap:wrap}
#attendance-machine .as-note{font-size:12px;color:#65798b}
#attendance-machine .as-mapping-grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr) auto;gap:8px;align-items:end}
#attendance-machine .as-mapping-grid label,#as-device-modal label{font-weight:650;font-size:14px;display:block;margin-bottom:5px}
#attendance-machine .as-input-error,#as-device-modal .as-input-error{color:#b42318;font-size:12px}
#attendance-machine .as-table-empty{text-align:center;color:#698197;padding:22px!important}
#attendance-machine .as-busy{opacity:.65;pointer-events:none}
#as-device-modal .modal-content{border-radius:12px;overflow:hidden}
#as-device-modal .modal-header{background:#f3f8fd;border-bottom:1px solid #bfd0de}
#as-device-modal .as-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}
#as-device-modal .as-form-wide{grid-column:1/-1}
#as-device-modal .as-form-grid input,#as-device-modal .as-form-grid select{width:100%;font-size:14px}
#as-device-modal .as-readonly-note{font-size:12px;color:#697c90}
@media(max-width:1100px){#attendance-machine .as-layout{grid-template-columns:1fr}}
@media(max-width:650px){#attendance-machine .as-mapping-grid,#as-device-modal .as-form-grid{grid-template-columns:1fr}#as-device-modal .as-form-wide{grid-column:auto}}
</style>
<div id="as-workspace"
    data-device-url="{{ route('attendance.device.settings',['device'=>987654321]) }}"
    data-delete-url="{{ route('attendance.device.delete',['device'=>987654321]) }}">
    <div class="as-toolbar">
        <div><h4>Attendance Machine</h4><small class="text-muted">Manage devices and employee mappings for the selected project</small></div>
        <button type="button" class="btn btn-primary" id="as-new-device"><i class="fas fa-plus mr-1"></i> Add Device</button>
    </div>
    <div class="as-layout">
        <section class="as-card">
            <header class="as-card-head"><h5>Device Configuration</h5><small id="as-device-count" class="text-muted">{{ $attendanceDevices->count() }} devices</small></header>
            <div class="as-table-scroll">
                <table class="table table-bordered as-table">
                    <thead><tr><th>Device</th><th>Connection</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="as-device-rows">
                    @forelse($attendanceDevices as $item)
                        <tr data-id="{{ $item->id }}">
                            <td><strong class="as-device-name">{{ $item->name }}</strong><div class="as-note">{{ $item->vendor }} {{ $item->model }}</div></td>
                            <td><span class="as-device-connection">{{ ucfirst($item->connection_type) }}</span><div class="as-note as-device-host">{{ $item->host ?: 'Not provided' }}</div></td>
                            <td><span class="badge badge-warning">Not connected</span></td>
                            <td><div class="as-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary as-edit-device" data-id="{{ $item->id }}" aria-label="Edit device">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-info as-select-device" data-id="{{ $item->id }}">Map IDs</button>
                                <button type="button" class="btn btn-sm btn-outline-danger as-delete-device" data-id="{{ $item->id }}">Delete</button>
                            </div></td>
                        </tr>
                    @empty
                        <tr class="as-empty-device"><td colspan="4" class="as-table-empty">No devices configured. Click Add Device to begin.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-2 border-top as-note">Deletion is blocked when employee mappings exist. No attendance sync happens until integration is verified.</div>
        </section>
        <section class="as-card">
            <header class="as-card-head"><h5>Employee ID Mapping</h5><small class="text-muted" id="as-mapping-count">0 mappings</small></header>
            <div class="as-card-body">
                <label for="as-map-device" style="font-weight:650;font-size:14px">Select device</label>
                <select id="as-map-device" class="form-control mb-3">
                    <option value="">Select saved device</option>
                    @foreach($attendanceDevices as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
                <form id="as-mapping-form" method="POST" action="{{ route('attendance.mapping.save') }}" data-ajax="off">
                    @csrf
                    <input type="hidden" name="device_id" value="">
                    <div class="as-mapping-grid">
                        <div><label>Employee</label><select name="user_id" class="form-control" required disabled><option value="">Select employee</option>@foreach($attendanceEmployees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select></div>
                        <div><label>Device user ID</label><input name="device_user_id" class="form-control" maxlength="100" required disabled placeholder="e.g. 1205"></div>
                        <button type="submit" class="btn btn-info" disabled>Apply</button>
                    </div>
                    <small class="as-input-error" data-field="device_user_id"></small>
                </form>
                <div class="as-table-scroll mt-3">
                    <table class="table table-bordered as-table">
                        <thead><tr><th>Employee</th><th>Device ID</th><th>Action</th></tr></thead>
                        <tbody id="as-mapping-rows"><tr><td colspan="3" class="as-table-empty">Select a saved device to view employee mappings.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
<div class="modal fade" id="as-device-modal" tabindex="-1" role="dialog" aria-labelledby="as-device-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="as-device-modal-title">Add Attendance Device</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form id="as-device-form" method="POST" action="{{ route('attendance.device.save') }}" data-ajax="off">
                @csrf
                <input type="hidden" name="device_id" value="">
                <div class="modal-body">
                    <div class="as-form-grid">
                        <div><label>Device Name *</label><input name="name" class="form-control" required maxlength="120" placeholder="Office Hikvision"><small class="as-input-error" data-field="name"></small></div>
                        <div><label>Vendor</label><input name="vendor" class="form-control" maxlength="120" placeholder="Hikvision"></div>
                        <div><label>Model *</label><input name="model" class="form-control" required maxlength="120" placeholder="Device model"><small class="as-input-error" data-field="model"></small></div>
                        <div><label>Connection Type</label><select name="connection_type" class="form-control">@foreach(['network'=>'Local network','cloud'=>'Cloud service','webhook'=>'Webhook','sdk'=>'Vendor SDK','other'=>'Other'] as $type=>$label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="as-form-wide"><label>IP Address / Hostname / Endpoint</label><input name="host" class="form-control" maxlength="255" placeholder="192.168.0.195"></div>
                        <div><label>Port *</label><input name="port" class="form-control" type="number" min="1" max="65535" required value="80"><small class="as-input-error" data-field="port"></small></div>
                        <div><label>Protocol / API Version</label><input name="protocol" class="form-control" maxlength="80" placeholder="ISAPI"></div>
                        <div><label>Username / API Key Name</label><input name="username" class="form-control" maxlength="120" autocomplete="off"></div>
                        <div><label>Password / Secret</label><input name="password" type="password" class="form-control" maxlength="500" autocomplete="new-password" placeholder="Optional"></div>
                    </div>
                    <p class="as-readonly-note mt-3 mb-0"><i class="fas fa-lock mr-1"></i> Secrets are encrypted. Saving does not connect to or synchronize the device. Test Connection and Sync remain unavailable until vendor integration is verified.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Save Device</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
</div>
@push('script')
@if($attendanceTablesAvailable && $canManageAttendanceDevice && $attendanceProjectId && auth()->user()->can('update setting'))
<script>
$(function(){
    'use strict';
    var $workspace=$('#as-workspace'),$modal=$('#as-device-modal'),$deviceForm=$('#as-device-form'),
        $mapForm=$('#as-mapping-form'),$mapSelect=$('#as-map-device');
    if(!$workspace.length || !$modal.length) return;
    // The ERP's shared submit interceptor is registered on document. These
    // forms explicitly opt out so only this AJAX flow owns their response.
    var selectedId='',selectionRequest=0,busy=false;
    function notify(message,error){
        if(window.toastr){if(error)window.toastr.error(message);else window.toastr.success(message);}
        else if(window.Swal)window.Swal.fire({icon:error?'error':'success',text:message});
    }
    function errorMessage(xhr){
        var json=xhr.responseJSON||{},message=json.message||'Unable to save. Please retry.';
        if(json.errors)$.each(json.errors,function(field,issues){
            var $target=$modal.find('[data-field="'+field+'"]').add($mapForm.find('[data-field="'+field+'"]'));
            $target.text(issues[0]||'Invalid value');
            if(issues.length)message=issues[0];
        });
        return message;
    }
    function setBusy(value){
        busy=value;
        $workspace.toggleClass('as-busy',value);
        $modal.find('button[type=submit]').prop('disabled',value);
        $('#as-new-device').prop('disabled',value);
    }
    function mappingEnabled(value){
        $mapForm.find('[name=user_id],[name=device_user_id],button[type=submit]').prop('disabled',!value);
    }
    function resetMappings(message){
        $('#as-mapping-rows').empty().append($('<tr>').append($('<td>',{colspan:3,'class':'as-table-empty'}).text(message)));
        $('#as-mapping-count').text('0 mappings');
        $mapForm[0].reset();$mapForm.find('[name=device_id]').val('');
        mappingEnabled(false);
    }
    function populateMappings(items,id){
        var $rows=$('#as-mapping-rows').empty();
        $mapForm[0].reset();$mapForm.find('[name=device_id]').val(id);
        mappingEnabled(true);
        $('#as-mapping-count').text(items.length+' mappings');
        if(!items.length){$rows.append($('<tr>').append($('<td>',{colspan:3,'class':'as-table-empty'}).text('No employee mappings for this device.')));return;}
        $.each(items,function(_,item){
            $rows.append($('<tr>')
                .append($('<td>').text(item.user_name),$('<td>').text(item.device_user_id),
                    $('<td>').append($('<button>',{type:'button','class':'btn btn-sm btn-outline-primary as-edit-mapping'}).text('Edit')
                        .attr('data-user-id',item.user_id).attr('data-device-user-id',item.device_user_id))));
        });
    }
    function deviceUrl(id,action){
        return $workspace.attr(action==='delete'?'data-delete-url':'data-device-url').replace('987654321',encodeURIComponent(id));
    }
    function chooseDevice(id){
        var token=++selectionRequest;
        if(!id){
            selectedId='';$mapSelect.val('');resetMappings('Select a saved device to view employee mappings.');
            return $.Deferred().resolve(null).promise();
        }
        $mapSelect.val(id);
        return $.ajax({url:deviceUrl(id),method:'GET',headers:{Accept:'application/json'},dataType:'json'})
            .done(function(data){
                if(token!==selectionRequest)return;
                selectedId=String(data.device.id);$mapSelect.val(selectedId);
                populateMappings(data.device.mappings||[],selectedId);
            }).fail(function(xhr){
                if(token!==selectionRequest)return;
                $mapSelect.val(selectedId);notify(errorMessage(xhr),true);
            });
    }
    function fillDevice(device){
        $deviceForm[0].reset();
        $deviceForm.find('.as-input-error').text('');
        $.each(['id','name','vendor','model','connection_type','host','port','protocol','username'],function(_,name){
            var inputName=name==='id'?'device_id':name;
            $deviceForm.find('[name="'+inputName+'"]').val(device[inputName]===undefined ? (name==='id'?device.id:'') : device[inputName]);
        });
        $deviceForm.find('[name=password]').val('').attr('placeholder',device.has_password?'Saved secret · leave blank to keep':'Optional secret');
        $('#as-device-modal-title').text('Edit Attendance Device');
        $modal.modal('show');
    }
    function updateRow(device){
        var $rows=$('#as-device-rows'),$row=$rows.find('tr[data-id="'+device.id+'"]');
        var $new=$('<tr>').attr('data-id',device.id);
        var $actions=$('<div>',{'class':'as-actions'})
          .append($('<button>',{type:'button','class':'btn btn-sm btn-outline-primary as-edit-device'}).text('Edit').attr('data-id',device.id),
                  $('<button>',{type:'button','class':'btn btn-sm btn-outline-info as-select-device'}).text('Map IDs').attr('data-id',device.id),
                  $('<button>',{type:'button','class':'btn btn-sm btn-outline-danger as-delete-device'}).text('Delete').attr('data-id',device.id));
        $new.append($('<td>').append($('<strong>',{'class':'as-device-name'}).text(device.name),$('<div>',{'class':'as-note'}).text((device.vendor||'')+' '+(device.model||''))),
            $('<td>').append($('<span>').text(device.connection_type),$('<div>',{'class':'as-note'}).text(device.host||'Not provided')),
            $('<td>').append($('<span>',{'class':'badge badge-warning'}).text('Not connected')),
            $('<td>').append($actions));
        $rows.find('.as-empty-device').remove();
        if($row.length)$row.replaceWith($new);else $rows.append($new);
        var $option=$mapSelect.find('option[value="'+device.id+'"]');
        if(!$option.length)$option=$('<option>').val(device.id).appendTo($mapSelect);
        $option.text(device.name);
        $('#as-device-count').text($rows.find('tr[data-id]').length+' devices');
    }
    $('#as-new-device').on('click',function(){
        $deviceForm[0].reset();
        $deviceForm.find('[name=device_id]').val('');
        $deviceForm.find('[name=port]').val('80');
        $deviceForm.find('.as-input-error').text('');
        $('#as-device-modal-title').text('Add Attendance Device');
        $modal.modal('show');
    });
    $workspace.on('click','.as-edit-device',function(){
        var id=$(this).attr('data-id');
        if(busy)return;
        setBusy(true);
        $.ajax({url:deviceUrl(id),method:'GET',headers:{Accept:'application/json'},dataType:'json'})
            .done(function(data){fillDevice(data.device);})
            .fail(function(xhr){notify(errorMessage(xhr),true);})
            .always(function(){setBusy(false);});
    }).on('click','.as-select-device',function(){
        chooseDevice($(this).attr('data-id'));
    }).on('click','.as-delete-device',function(){
        var id=$(this).attr('data-id'),$row=$(this).closest('tr');
        function remove(){
            setBusy(true);
            $.ajax({url:deviceUrl(id,'delete'),type:'POST',
                data:{_method:'DELETE',_token:$deviceForm.find('[name=_token]').val()},
                headers:{Accept:'application/json'},dataType:'json'})
              .done(function(data){
                  $row.remove();$mapSelect.find('option[value="'+id+'"]').remove();
                  if(selectedId===String(id))chooseDevice('');
                  var n=$('#as-device-rows tr[data-id]').length;
                  $('#as-device-count').text(n+' devices');
                  if(!n)$('#as-device-rows').append($('<tr>',{'class':'as-empty-device'}).append($('<td>',{colspan:4,'class':'as-table-empty'}).text('No devices configured. Click Add Device to begin.')));
                  notify(data.message||'Device removed.',false);
              }).fail(function(xhr){notify(errorMessage(xhr),true);})
              .always(function(){setBusy(false);});
        }
        if(window.Swal)Swal.fire({icon:'warning',title:'Delete Attendance Device?',text:'Deletion is blocked if employee mappings exist.',showCancelButton:true,confirmButtonText:'Yes, Delete',confirmButtonColor:'#d33'}).then(function(result){if(result.isConfirmed)remove();});
        else if(window.confirm('Delete this unused device?'))remove();
    });
    $mapSelect.on('change',function(){chooseDevice($(this).val());});
    $deviceForm.on('submit',function(e){
        e.preventDefault();e.stopImmediatePropagation();
        if(busy)return;
        if(!this.checkValidity()){this.reportValidity();return;}
        var send=function(){
            setBusy(true);$deviceForm.find('.as-input-error').text('');
            $.ajax({url:$deviceForm.attr('action'),method:'POST',data:$deviceForm.serialize(),
                headers:{Accept:'application/json'},dataType:'json'})
              .done(function(data){
                  updateRow(data.device);$modal.modal('hide');
                  if(selectedId===String(data.device.id))populateMappings(data.device.mappings||[],selectedId);
                  notify(data.message||'Device saved.',false);
              }).fail(function(xhr){notify(errorMessage(xhr),true);})
              .always(function(){setBusy(false);});
        };
        if(window.Swal)Swal.fire({icon:'question',title:'Save Attendance Device?',showCancelButton:true,confirmButtonText:'Yes, Save',cancelButtonText:'No'}).then(function(result){if(result.isConfirmed)send();});
        else send();
    });
    $mapForm.on('submit',function(e){
        e.preventDefault();e.stopImmediatePropagation();
        if(busy||!selectedId)return;
        if(!this.checkValidity()){this.reportValidity();return;}
        function send(){
            setBusy(true);$mapForm.find('.as-input-error').text('');
            $.ajax({url:$mapForm.attr('action'),method:'POST',data:$mapForm.serialize(),
                headers:{Accept:'application/json'},dataType:'json'})
              .done(function(data){
                  if(selectedId===String(data.device.id))populateMappings(data.device.mappings||[],selectedId);
                  notify(data.message||'Employee mapping saved.',false);
              }).fail(function(xhr){notify(errorMessage(xhr),true);})
              .always(function(){setBusy(false);});
        }
        if(window.Swal)Swal.fire({icon:'question',title:'Apply Employee Mapping?',showCancelButton:true,confirmButtonText:'Yes, Apply',cancelButtonText:'No'}).then(function(result){if(result.isConfirmed)send();});
        else send();
    });
    $workspace.on('click','.as-edit-mapping',function(){
        $mapForm.find('[name=user_id]').val($(this).attr('data-user-id'));
        $mapForm.find('[name=device_user_id]').val($(this).attr('data-device-user-id')).trigger('focus');
    });
});
</script>
@endif
@endpush
