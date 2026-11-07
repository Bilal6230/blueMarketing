@extends('admin.layouts.master')
@section('title','Voucher Approvals')
@section('content')
<div class="content-wrapper" style="min-height:100vh;background:#eef2ff;padding:24px">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2>Voucher Approval Workflow</h2><div class="text-muted">Project #{{ (int)getSelectedTown() }} · CR, CP and JV posting is held inactive until approval.</div></div></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card mb-4"><div class="card-header"><strong>Project policies</strong></div><div class="card-body"><div class="row">
      @foreach(['CR'=>'Cash Receipt','CP'=>'Cash Payment','JV'=>'Journal Voucher'] as $type=>$label)
        @php($policy=$policies->get($type))
        <div class="col-lg-4 mb-3"><form method="post" action="{{ route('finance.daily-vouchers.approvals.policy') }}" class="border rounded p-3 bg-white h-100">@csrf
          <input type="hidden" name="voucher_type" value="{{ $type }}"><h5>{{ $label }} <small class="text-muted">({{ $type }})</small></h5>
          <label class="d-block"><input type="checkbox" name="approval_required" value="1" {{ $policy?->approval_required ? 'checked' : '' }}> Require separate approval before posting</label>
          <label class="mt-2">Assigned approver</label><select name="approver_user_id" class="form-control"><option value="">Select project user</option>@foreach($approvers as $user)<option value="{{ $user->id }}" {{ (int)$policy?->approver_user_id === (int)$user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select>
          <button class="btn btn-primary btn-sm mt-3">Save policy</button>
        </form></div>
      @endforeach
    </div><small class="text-muted">Policy changes affect new vouchers only. Existing posted vouchers and history are not changed. Approval and submission must be done by different users.</small></div></div>
    <div class="card"><div class="card-header d-flex justify-content-between"><strong>Requests awaiting your decision</strong><span class="badge badge-primary">{{ $queue->total() }}</span></div><div class="table-responsive" style="max-height:60vh;overflow:auto"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr><th>Type</th><th>Voucher source</th><th>Submitted by</th><th>Assigned approver</th><th>Submitted</th><th style="min-width:320px">Decision</th></tr></thead><tbody>
      @forelse($queue as $item)<tr><td><strong>{{ $item->voucher_type }}</strong></td><td>{{ $item->source_table }} #{{ $item->source_id }}</td><td>{{ $item->submitter?->name ?? 'User #'.$item->submitted_by }}</td><td>{{ $item->assignedApprover?->name ?? 'User #'.$item->assigned_approver_id }}</td><td>{{ $item->created_at?->format('d-M-Y H:i') }}</td><td>
        @if((int)$item->assigned_approver_id === (int)auth()->id() && (int)$item->submitted_by !== (int)auth()->id())
          <form method="post" action="{{ route('finance.daily-vouchers.approvals.decide',[$item,'approve']) }}" class="d-inline">@csrf<button class="btn btn-success btn-sm">Approve &amp; post</button></form>
          <form method="post" action="{{ route('finance.daily-vouchers.approvals.decide',[$item,'reject']) }}" class="mt-2">@csrf<div class="input-group input-group-sm"><input name="reason" required minlength="2" maxlength="1000" class="form-control" placeholder="Required rejection reason"><div class="input-group-append"><button class="btn btn-outline-danger">Reject</button></div></div></form>
        @else <span class="text-muted">Assigned approver only</span> @endif
        @php($events=$item->events)
        <details class="mt-2"><summary>History ({{ $events->count() }})</summary>@foreach($events as $event)<div class="small">{{ $event->created_at }} · {{ ucfirst($event->event) }} · User #{{ $event->actor_id }} {{ $event->remarks ? '· '.$event->remarks : '' }}</div>@endforeach</details>
      </td></tr>@empty<tr><td colspan="6" class="text-center text-muted p-4">No pending voucher approvals.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $queue->links() }}</div></div>
    <div class="card mt-4"><div class="card-header d-flex justify-content-between"><strong>Recent approval history</strong><span class="text-muted">Latest 50 decisions</span></div><div class="table-responsive" style="max-height:45vh;overflow:auto"><table class="table table-sm table-hover mb-0"><thead class="thead-light"><tr><th>Type / source</th><th>Submitted by</th><th>Decision</th><th>Approver</th><th>Reason</th><th>Events</th></tr></thead><tbody>
      @forelse($history as $item)<tr><td>{{ $item->voucher_type }} · {{ $item->source_table }} #{{ $item->source_id }}</td><td>{{ $item->submitter?->name ?? 'User #'.$item->submitted_by }}</td><td><span class="badge badge-{{ $item->status === 'approved' ? 'success' : 'danger' }}">{{ ucfirst($item->status) }}</span></td><td>{{ $item->assignedApprover?->name ?? 'User #'.$item->assigned_approver_id }}</td><td>{{ $item->rejection_reason ?: '—' }}</td><td>@foreach($item->events as $event)<div class="small">{{ $event->created_at?->format('d-M-Y H:i') }} · {{ ucfirst($event->event) }} · User #{{ $event->actor_id }} {{ $event->remarks ? '· '.$event->remarks : '' }}</div>@endforeach</td></tr>@empty<tr><td colspan="6" class="text-center text-muted p-4">No completed approval decisions yet.</td></tr>@endforelse
    </tbody></table></div></div>
  </div>
</div>
@endsection
