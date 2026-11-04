@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center">
        <h1>Plot Definitions</h1><a class="btn btn-outline-primary" href="{{ route('project.plot.index') }}">Back to Plots</a>
    </div></section>
    <section class="content"><div class="container-fluid">
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="alert alert-info">Definitions belong to the selected project. Existing plots have not been auto-mapped; assign links only after confirming each plot's values.</div>
        <div class="row">
            @foreach (['category' => 'Plot Categories', 'type' => 'Plot Types', 'size' => 'Plot Sizes', 'unit' => 'Plot Units'] as $kind => $heading)
                <div class="col-xl-6 mb-3"><div class="card h-100">
                    <div class="card-header"><h3 class="card-title">{{ $heading }}</h3></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('project.plot-definitions.store') }}" class="border rounded p-3 mb-3 definition-form" data-kind="{{ $kind }}">
                            @csrf<input type="hidden" name="kind" value="{{ $kind }}">
                            <div class="form-row">
                                <div class="form-group col-md-4"><label>Code</label><input class="form-control" name="code" maxlength="40" required></div>
                                <div class="form-group col-md-8"><label>Name</label><input class="form-control" name="name" maxlength="120" required></div>
                            </div>
                            @if ($kind === 'type')
                                <div class="form-group"><label>Existing plot type mapping</label><select class="form-control" name="legacy_value" required><option value="">Select</option><option value="1">Residential</option><option value="2">Commercial</option></select><small class="text-muted">Keeps current inventory and reports compatible.</small></div>
                            @elseif ($kind === 'unit')
                                <div class="form-group"><label>Unit symbol</label><input class="form-control" name="unit_code" maxlength="20" placeholder="e.g. Marla, Sq Ft" required></div>
                            @elseif ($kind === 'size')
                                <div class="form-row">
                                    <div class="form-group col-md-4"><label>Size value</label><input class="form-control" name="size_value" type="number" step="0.01" min="0.01" required></div>
                                    <div class="form-group col-md-8"><label>Unit</label><select class="form-control" name="unit_definition_id" required><option value="">Select unit</option>@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->unit_code }})</option>@endforeach</select></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6"><label>Rate per unit</label><input class="form-control" name="rate_per_unit" type="number" step="0.01" min="0" required></div>
                                    <div class="form-group col-md-6"><label>Rate effective from</label><input class="form-control" name="effective_from" type="date" required></div>
                                </div>
                            @endif
                            <button class="btn btn-primary" type="submit">Add {{ rtrim($heading, 's') }}</button>
                        </form>
                        <div class="table-responsive"><table class="table table-sm table-bordered">
                            <thead><tr><th>Code</th><th>Name</th>@if ($kind === 'size')<th>Size / Unit / Rate</th>@endif<th>Status</th><th>Manage</th></tr></thead>
                            <tbody>
                            @forelse ($definitions->get($kind, collect()) as $definition)
                                <tr>
                                    <td>{{ $definition->code }}</td><td>{{ $definition->name }}</td>
                                    @if ($kind === 'size')
                                        <td>{{ $definition->size_value }} {{ optional($units->firstWhere('id', $definition->unit_definition_id))->unit_code }}
                                            @php($currentRate = optional($rates->get($definition->id)?->first())->rate_per_unit)
                                            <br><small>Current rate: {{ $currentRate ?? 'Not effective yet' }}</small>
                                            @if ($definition->is_active)
                                                <form method="POST" action="{{ route('project.plot-definitions.rate', $definition->id) }}" class="mt-2">
                                                    @csrf
                                                    <div class="form-row mt-2"><div class="col"><input class="form-control form-control-sm" name="rate_per_unit" type="number" min="0" step="0.01" placeholder="New rate" required></div><div class="col"><input class="form-control form-control-sm" name="effective_from" type="date" required></div></div>
                                                    <input class="form-control form-control-sm mt-1" name="reason" maxlength="500" placeholder="Reason for rate change" required>
                                                    <button class="btn btn-sm btn-outline-primary mt-1">Schedule rate</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                    <td><span class="badge badge-{{ $definition->is_active ? 'success' : 'secondary' }}">{{ $definition->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td style="min-width:220px">
                                        <form method="POST" action="{{ route('project.plot-definitions.update', $definition->id) }}" class="mb-2">
                                            @csrf @method('PUT')
                                            <input class="form-control form-control-sm mb-1" name="name" value="{{ $definition->name }}" required>
                                            <input class="form-control form-control-sm mb-1" name="reason" maxlength="500" placeholder="Reason for rename" required>
                                            <button class="btn btn-sm btn-outline-primary">Rename</button>
                                        </form>
                                        <form method="POST" action="{{ route('project.plot-definitions.status', $definition->id) }}" class="d-flex">
                                            @csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $definition->is_active ? 0 : 1 }}"><input class="form-control form-control-sm mr-1" name="reason" placeholder="Reason" required><button class="btn btn-sm btn-outline-{{ $definition->is_active ? 'danger' : 'success' }}">{{ $definition->is_active ? 'Disable' : 'Enable' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty<tr><td colspan="{{ $kind === 'size' ? 4 : 3 }}" class="text-center text-muted">No definitions yet.</td></tr>@endforelse
                            </tbody>
                        </table></div>
                    </div>
                </div></div>
            @endforeach
        </div>
    </div></section>
</div>
@endsection
