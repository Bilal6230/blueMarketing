@extends('admin.layouts.master')
@section('title', 'Activity Audit')

@section('content')
<div class="content-wrapper">
 <section class="content pt-3"><div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
   <div><h3 class="mb-0">Activity Audit</h3><small class="text-muted">Read-only history of authenticated write requests. No sensitive form values are stored.</small></div>
   <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
  </div>
  <div class="card"><div class="card-body">
   <form method="GET" action="{{ route('activity-audit.index') }}" class="form-row mb-3">
    <div class="col-md-2 form-group"><label>Actor ID</label><input class="form-control form-control-sm" type="number" min="1" name="actor_id" value="{{ $filters['actor_id'] ?? '' }}"></div>
    <div class="col-md-2 form-group"><label>Action</label><select name="method" class="form-control form-control-sm"><option value="">All</option>@foreach(['POST','PUT','PATCH','DELETE'] as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '')===$method)>{{ $method }}</option>@endforeach</select></div>
    <div class="col-md-2 form-group"><label>From</label><input type="date" class="form-control form-control-sm" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div class="col-md-2 form-group"><label>To</label><input type="date" class="form-control form-control-sm" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="col-md-4 form-group d-flex align-items-end"><button class="btn btn-primary btn-sm mr-2">Apply Filters</button><a href="{{ route('activity-audit.index') }}" class="btn btn-light btn-sm">Reset</a></div>
   </form>
   <div class="table-responsive">
    <table class="table table-sm table-hover table-bordered" style="font-size:12px">
     <thead class="thead-light"><tr><th>ID</th><th>When</th><th>Actor</th><th>Action</th><th>Route</th><th>Path</th><th>Status</th><th>IP</th></tr></thead>
     <tbody>
      @forelse($events as $event)
       <tr><td>{{ $event->id }}</td><td class="text-nowrap">{{ $event->created_at }}</td>
        <td>{{ $event->actor_name ?? 'Former user' }} (#{{ $event->actor_id }})</td><td>{{ $event->action }}</td>
        <td>{{ $event->route_name ?? '—' }}</td><td>{{ $event->request_path }}</td>
        <td>{{ $event->response_status }}</td><td>{{ $event->ip_address ?? '—' }}</td></tr>
      @empty
       <tr><td colspan="8" class="text-center text-muted py-4">No activity records found.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
   <div class="d-flex justify-content-end">{{ $events->links() }}</div>
  </div></div>
 </div></section>
</div>
@endsection
