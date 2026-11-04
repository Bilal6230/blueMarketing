@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <div class="content-header"><div class="container-fluid"><h1>{{ $title }}</h1></div></div>
    <section class="content"><div class="container-fluid">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @unless($storageReady)
            <div class="alert alert-warning">Account Group storage is not installed yet. Apply the reviewed Account Group migrations before creating groups or assigning Heads.</div>
        @else

        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Create Account Group</h3></div>
            <form method="POST" action="{{ route('accounting.group_store') }}">
                @csrf
                <div class="card-body"><div class="row">
                    <div class="col-md-2"><label>Code</label><input class="form-control" name="code" maxlength="50" required value="{{ old('code') }}"></div>
                    <div class="col-md-3"><label>Name</label><input class="form-control" name="name" maxlength="150" required value="{{ old('name') }}"></div>
                    <div class="col-md-3"><label>Parent group</label><select class="form-control" name="parent_id"><option value="">Top-level group</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label>Description</label><input class="form-control" name="description" maxlength="5000" value="{{ old('description') }}"></div>
                </div></div>
                <div class="card-footer"><button class="btn btn-primary" type="submit">Create group</button></div>
            </form>
        </div>

        <div class="card"><div class="card-header"><h3 class="card-title">Group tree and Head assignments</h3></div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-hover">
                    <thead><tr><th>Group</th><th>Parent</th><th>Status</th><th>Assigned Heads</th><th>Assign ungrouped Head</th><th>Update</th></tr></thead>
                    <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td><strong>{{ $group->code }} - {{ $group->name }}</strong><br><small>{{ $group->description }}</small></td>
                            <td>{{ $group->parent ? $group->parent->code.' - '.$group->parent->name : 'Top-level' }}</td>
                            <td>{{ $group->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>@forelse($group->heads as $head)<span class="badge badge-info mr-1">{{ $head->name }}</span>@empty<span class="text-muted">None</span>@endforelse</td>
                            <td>
                                @if($group->is_active && $unassignedHeads->isNotEmpty())
                                    <form method="POST" action="{{ route('accounting.group_assign_head', $group) }}" class="d-flex">
                                        @csrf
                                        <select class="form-control form-control-sm mr-1" name="head_id" required><option value="">Select Head</option>@foreach($unassignedHeads as $head)<option value="{{ $head->id }}">{{ $head->name }}</option>@endforeach</select>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Assign</button>
                                    </form>
                                @else<span class="text-muted">No unassigned Heads</span>@endif
                            </td>
                            <td style="min-width:330px">
                                <form method="POST" action="{{ route('accounting.group_update', $group) }}">
                                    @csrf @method('PUT')
                                    <input class="form-control form-control-sm mb-1" name="code" maxlength="50" required value="{{ $group->code }}">
                                    <input class="form-control form-control-sm mb-1" name="name" maxlength="150" required value="{{ $group->name }}">
                                    <select class="form-control form-control-sm mb-1" name="parent_id"><option value="">Top-level group</option>@foreach($parents as $parent)@if((int) $parent->id !== (int) $group->id)<option value="{{ $parent->id }}" @selected((int) $group->parent_id === (int) $parent->id)>{{ $parent->code }} - {{ $parent->name }}</option>@endif @endforeach</select>
                                    <input class="form-control form-control-sm mb-1" name="description" maxlength="5000" value="{{ $group->description }}" placeholder="Description">
                                    <select class="form-control form-control-sm mb-1" name="is_active"><option value="1" @selected($group->is_active)>Active</option><option value="0" @selected(!$group->is_active)>Inactive</option></select>
                                    <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty<tr><td colspan="6" class="text-center text-muted">No account groups configured. Existing Heads stay unchanged until assigned.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="alert alert-info">Assigned Heads can only be reclassified through the audited, project-scoped merge flow below.</div>

        <div class="card card-warning">
            <div class="card-header"><h3 class="card-title">Group Merge Preview &amp; Confirmation</h3></div>
            <div class="card-body">
                <p>Select source and target to inspect affected Heads, project mappings, and active ledger totals. This preview never changes data.</p>
                <form method="GET" action="{{ route('accounting.groups') }}" class="form-row align-items-end">
                    <div class="form-group col-md-4"><label>Source group</label><select name="source_group_id" class="form-control" required><option value="">Select source</option>@foreach($groups->where('is_active', true) as $group)<option value="{{ $group->id }}" @selected(request('source_group_id') == $group->id)>{{ $group->code }} - {{ $group->name }}</option>@endforeach</select></div>
                    <div class="form-group col-md-4"><label>Target group</label><select name="target_group_id" class="form-control" required><option value="">Select target</option>@foreach($groups->where('is_active', true) as $group)<option value="{{ $group->id }}" @selected(request('target_group_id') == $group->id)>{{ $group->code }} - {{ $group->name }}</option>@endforeach</select></div>
                    <div class="form-group col-md-4"><button class="btn btn-warning" type="submit">Build preview</button></div>
                </form>
                @if($preview)
                    <hr>
                    <h5>{{ $preview['source']->name }} → {{ $preview['target']->name }}</h5>
                    <div class="alert alert-info">Project-scoped preview. It lists the selected project's mapped records; building this preview does not change data.</div>
                    @if(count($preview['reasons']) > 0)
                        <ul>@foreach($preview['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                    @endif
                    <div class="table-responsive"><table class="table table-sm table-bordered">
                        <thead><tr><th>Selected project</th><th>Source Heads</th><th>Target Heads</th><th>Mappings</th><th>Active ledger rows</th><th>Amount In</th><th>Amount Out</th></tr></thead>
                        <tbody>@forelse($preview['projects'] as $project)
                            <tr><td>{{ $project['project_name'] }}</td><td>{{ $project['source_heads'] }}</td><td>{{ $project['target_heads'] }}</td><td>{{ $project['mappings'] }}</td><td>{{ $project['ledger_rows'] }}</td><td>{{ number_format($project['amount_in_cents'] / 100, 2) }}</td><td>{{ number_format($project['amount_out_cents'] / 100, 2) }}</td></tr>
                        @empty<tr><td colspan="7" class="text-center">No project mappings are attached to these Heads.</td></tr>@endforelse</tbody>
                        <tfoot><tr><th>Project {{ $projectId }}</th><th>{{ $preview['source_heads']->count() }} source</th><th>{{ $preview['target_heads']->count() }} target</th><th>{{ $preview['project_totals']['mappings'] }}</th><th>{{ $preview['project_totals']['ledger_rows'] }}</th><th>{{ number_format($preview['project_totals']['amount_in_cents'] / 100, 2) }}</th><th>{{ number_format($preview['project_totals']['amount_out_cents'] / 100, 2) }}</th></tr></tfoot>
                    </table></div>
                    @if($preview['duplicates']->isNotEmpty())
                        <div class="alert alert-danger">{{ $preview['duplicates']->count() }} duplicate mapping group(s) found among the affected Heads. Review these before any classification change.</div>
                    @endif
                    @if($preview['can_apply'])
                        <form method="POST" action="{{ route('accounting.group_merge_apply', $preview['source']) }}" class="border rounded p-3 bg-light">
                            @csrf<input type="hidden" name="target_group_id" value="{{ $preview['target']->id }}">
                            <div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="confirmGroupMerge" name="confirm_merge" value="1" required><label class="custom-control-label" for="confirmGroupMerge">I reviewed this project-only reclassification. Ledger rows and amounts must remain unchanged.</label></div>
                            <button class="btn btn-danger" type="submit">Merge Heads into {{ $preview['target']->name }}</button>
                        </form>
                    @else
                        <div class="alert alert-secondary mb-0">Merge is blocked until the findings above are resolved.</div>
                    @endif
                @endif
            </div>
        </div>
        @endunless
    </div></section>
</div>
@endsection
