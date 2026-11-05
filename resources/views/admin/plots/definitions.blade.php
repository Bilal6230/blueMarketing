@extends('admin.layouts.master')

@section('content')
<style>
    .definition-page .definition-card{border:1px solid #e4ebf5;border-radius:16px;box-shadow:0 8px 24px rgba(23,43,77,.06);overflow:hidden}
    .definition-page .card-header{background:linear-gradient(110deg,#f7faff,#fff);padding:14px 18px}
    .definition-page .card-title{font-weight:700;color:#17345b;margin:0}
    .definition-page .table{font-size:.88rem;margin-bottom:0}
    .definition-page .table thead{background:#f0f6ff}
    .definition-page .table-wrap{max-height:46vh;overflow:auto}
    .definition-page .table-wrap thead th{position:sticky;top:0;z-index:1}
    .definition-page .btn{border-radius:9px;font-weight:600}
    .definition-page .action-btn{width:32px;height:32px;padding:0;display:inline-grid;place-items:center}
    .definition-page .modal-content{border:0;border-radius:16px;box-shadow:0 20px 60px #132b4a30}
    .definition-page .modal-header{background:#f5f8fd;border-radius:16px 16px 0 0}
    .definition-page .form-control{border-radius:9px;min-height:39px}
</style>
<div class="content-wrapper definition-page">
    <section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center">
        <div><h1 class="mb-0">Plot Definitions</h1><small class="text-muted">Project scoped categories, types, sizes and units</small></div>
        <a class="btn btn-outline-primary" href="{{ route('project.plot.index') }}"><i class="fas fa-arrow-left mr-1"></i> Back to Plots</a>
    </div></section>
    <section class="content"><div class="container-fluid">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="alert alert-info">Definitions belong to the selected project. Existing plots are not changed automatically; link them only after checking their current values.</div>
        <div class="row">
        @foreach (['category' => 'Plot Categories', 'type' => 'Plot Types', 'size' => 'Plot Sizes', 'unit' => 'Plot Units'] as $kind => $heading)
            @php($items = $definitions->get($kind, collect()))
            <div class="col-xl-6 mb-3">
                <section class="card h-100 definition-card" data-ajax-panel="plot-{{ $kind }}">
                    <header class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">{{ $heading }} <span class="badge badge-light ml-1">{{ $items->count() }}</span></h3>
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#create-{{ $kind }}"><i class="fas fa-plus mr-1"></i> Add</button>
                    </header>
                    <div class="card-body p-3">
                        <div class="table-wrap">
                            <table class="table table-sm table-bordered table-hover">
                                <thead><tr><th>Code</th><th>Name</th>@if($kind === 'size')<th>Size / Unit / Rate</th>@endif<th>Status</th><th class="text-center">Actions</th></tr></thead>
                                <tbody>
                                @forelse($items as $definition)
                                    @php($currentRate = $kind === 'size' ? optional($rates->get($definition->id)?->first())->rate_per_unit : null)
                                    <tr>
                                        <td class="font-weight-bold">{{ $definition->code }}</td>
                                        <td>{{ $definition->name }} @if($kind === 'unit')<small class="text-muted">({{ $definition->unit_code }})</small>@endif</td>
                                        @if($kind === 'size')<td>{{ $definition->size_value }} {{ optional($units->firstWhere('id', $definition->unit_definition_id))->unit_code }}<br><small class="text-muted">Rate: {{ $currentRate ?? 'Not effective yet' }}</small></td>@endif
                                        <td><span class="badge badge-{{ $definition->is_active ? 'success' : 'secondary' }}">{{ $definition->is_active ? 'Active' : 'Inactive' }}</span></td>
                                        <td class="text-center text-nowrap">
                                            <button type="button" class="btn btn-outline-primary btn-sm action-btn" title="Edit name" data-toggle="modal" data-target="#edit-{{ $kind }}-{{ $definition->id }}"><i class="fas fa-pen"></i></button>
                                            @if($kind === 'size' && $definition->is_active)<button type="button" class="btn btn-outline-info btn-sm action-btn" title="Change rate" data-toggle="modal" data-target="#rate-{{ $definition->id }}"><i class="fas fa-coins"></i></button>@endif
                                            <button type="button" class="btn btn-outline-{{ $definition->is_active ? 'danger' : 'success' }} btn-sm action-btn" title="{{ $definition->is_active ? 'Disable' : 'Enable' }}" data-toggle="modal" data-target="#status-{{ $kind }}-{{ $definition->id }}"><i class="fas fa-{{ $definition->is_active ? 'ban' : 'check' }}"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $kind === 'size' ? 5 : 4 }}" class="text-center text-muted py-4">No {{ strtolower($heading) }} configured for this project.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal fade" id="create-{{ $kind }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
                        <form method="POST" action="{{ route('project.plot-definitions.store') }}">@csrf<input type="hidden" name="kind" value="{{ $kind }}">
                            <div class="modal-header"><h5 class="modal-title">Add {{ rtrim($heading, 's') }}</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button></div>
                            <div class="modal-body">
                                <div class="form-row"><div class="form-group col-md-4"><label>Code</label><input class="form-control" name="code" maxlength="40" required></div><div class="form-group col-md-8"><label>Name</label><input class="form-control" name="name" maxlength="120" required></div></div>
                                @if($kind === 'type')<div class="form-group"><label>Existing plot type mapping</label><select class="form-control" name="legacy_value" required><option value="">Select</option><option value="1">Residential</option><option value="2">Commercial</option></select><small class="text-muted">Keeps existing inventory and reports compatible.</small></div>
                                @elseif($kind === 'unit')<div class="form-group"><label>Unit symbol</label><input class="form-control" name="unit_code" maxlength="20" placeholder="e.g. Marla, Sq Ft" required></div>
                                @elseif($kind === 'size')<div class="form-row"><div class="form-group col-md-4"><label>Size value</label><input class="form-control" name="size_value" type="number" step="0.01" min="0.01" required></div><div class="form-group col-md-8"><label>Unit</label><select class="form-control" name="unit_definition_id" required><option value="">Select unit</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->unit_code }})</option>@endforeach</select></div></div><div class="form-row"><div class="form-group col-md-6"><label>Rate per unit</label><input class="form-control" name="rate_per_unit" type="number" step="0.01" min="0" required></div><div class="form-group col-md-6"><label>Rate effective from</label><input class="form-control" name="effective_from" type="date" required></div></div>@endif
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save definition</button></div>
                        </form>
                    </div></div></div>

                    @foreach($items as $definition)
                        <div class="modal fade" id="edit-{{ $kind }}-{{ $definition->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form method="POST" action="{{ route('project.plot-definitions.update', $definition->id) }}">@csrf @method('PUT')
                            <div class="modal-header"><h5 class="modal-title">Edit {{ $definition->code }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><div class="form-group"><label>Name</label><input class="form-control" name="name" value="{{ $definition->name }}" maxlength="120" required></div><div class="form-group mb-0"><label>Reason for change</label><input class="form-control" name="reason" maxlength="500" required></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button class="btn btn-primary">Save changes</button></div>
                        </form></div></div></div>
                        <div class="modal fade" id="status-{{ $kind }}-{{ $definition->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form method="POST" action="{{ route('project.plot-definitions.status', $definition->id) }}">@csrf @method('PATCH')
                            <div class="modal-header"><h5 class="modal-title">{{ $definition->is_active ? 'Disable' : 'Enable' }} {{ $definition->code }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><input type="hidden" name="is_active" value="{{ $definition->is_active ? 0 : 1 }}"><label>Reason</label><input class="form-control" name="reason" maxlength="500" required></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button class="btn btn-{{ $definition->is_active ? 'danger' : 'success' }}">{{ $definition->is_active ? 'Disable' : 'Enable' }}</button></div>
                        </form></div></div></div>
                        @if($kind === 'size' && $definition->is_active)
                            <div class="modal fade" id="rate-{{ $definition->id }}" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form method="POST" action="{{ route('project.plot-definitions.rate', $definition->id) }}">@csrf
                                <div class="modal-header"><h5 class="modal-title">Schedule rate · {{ $definition->name }}</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body"><div class="form-row"><div class="form-group col-md-6"><label>Rate per unit</label><input class="form-control" name="rate_per_unit" type="number" min="0" step="0.01" required></div><div class="form-group col-md-6"><label>Effective from</label><input class="form-control" name="effective_from" type="date" required></div></div><label>Reason</label><input class="form-control" name="reason" maxlength="500" required></div><div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button class="btn btn-primary">Schedule rate</button></div>
                            </form></div></div></div>
                        @endif
                    @endforeach
                </section>
            </div>
        @endforeach
        </div>
    </div></section>
</div>
<script>
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.closest('.definition-page .modal')) return;
    document.addEventListener('ajax:panel-updated', function () {
        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    }, { once: true });
}, true);
document.addEventListener('ajax:panel-updated', function (event) {
    const panel = event.detail && event.detail.panel;
    if (!panel || !panel.matches('.definition-page [data-ajax-panel]')) return;
    const alert = panel.querySelector('.alert-success,.alert-danger,.alert-warning');
    if (!alert) return;
    const message = alert.innerText.trim();
    if (window.toastr) (alert.classList.contains('alert-success') ? toastr.success : toastr.error)(message);
    alert.remove();
});
</script>
@endsection
