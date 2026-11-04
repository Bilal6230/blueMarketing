@extends('admin.layouts.master')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="mb-1">Labour Contractors</h3><div class="text-muted">Selected project contractors and their ledger accounts</div></div>
        <a href="{{ route('labours.index') }}" class="btn btn-outline-primary">Back to Labour</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card mb-4"><div class="card-header fw-bold">Add Contractor</div><div class="card-body">
        <form method="post" action="{{ route('contractors.store') }}" class="row g-3">@csrf
            <div class="col-md-2"><label class="form-label">Code</label><input name="code" class="form-control" maxlength="40" required></div>
            <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" maxlength="160" required></div>
            <div class="col-md-2"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="50"></div>
            <div class="col-md-3"><label class="form-label">Ledger account</label><select name="project_head_subhead_id" class="form-control" required><option value="">Select active project account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->headAccounting?->name }} / {{ $account->subheadAccounting?->name }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Save contractor</button></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2" maxlength="5000"></textarea></div>
        </form>
    </div></div>

    <div class="card"><div class="card-header fw-bold">Contractors</div><div class="table-responsive"><table class="table table-striped align-middle mb-0">
        <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Ledger account</th><th>Status</th><th>Update</th></tr></thead><tbody>
        @forelse($contractors as $contractor)
            <tr>
                <td><form id="contractor-update-{{ $contractor->id }}" method="post" action="{{ route('contractors.update', $contractor->id) }}">@csrf @method('PUT')</form><input form="contractor-update-{{ $contractor->id }}" name="code" class="form-control form-control-sm" value="{{ $contractor->code }}" required></td>
                <td><input form="contractor-update-{{ $contractor->id }}" name="name" class="form-control form-control-sm" value="{{ $contractor->name }}" required><textarea form="contractor-update-{{ $contractor->id }}" name="address" class="form-control form-control-sm mt-1" placeholder="Address">{{ $contractor->address }}</textarea></td>
                <td><input form="contractor-update-{{ $contractor->id }}" name="phone" class="form-control form-control-sm" value="{{ $contractor->phone }}"></td>
                <td><select form="contractor-update-{{ $contractor->id }}" name="project_head_subhead_id" class="form-control form-control-sm" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($contractor->project_head_subhead_id == $account->id)>{{ $account->headAccounting?->name }} / {{ $account->subheadAccounting?->name }}</option>@endforeach</select><small class="text-muted">Current: {{ $contractor->account?->subheadAccounting?->name ?? 'Account' }}</small></td>
                <td><span class="badge {{ $contractor->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $contractor->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="text-nowrap"><button form="contractor-update-{{ $contractor->id }}" class="btn btn-sm btn-primary">Update</button>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('contractors.ledger', $contractor->id) }}">Ledger</a>
                    <form method="post" action="{{ route('contractors.status', $contractor->id) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $contractor->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                </td>
            </tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">No contractors configured for this project.</td></tr>@endforelse
        </tbody></table></div></div>
</div>
@endsection
