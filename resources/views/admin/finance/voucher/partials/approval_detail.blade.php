@php
    $detail = $voucherDetails[$item->id] ?? [];
    $money = fn($value) => $value === null ? '—' : number_format((float)$value,2);
    $eventReason = $item->events->reverse()->first(fn($event) =>
        in_array($event->event,['same_source_edit','revision_from_approved','revision_from_rejected',
            'resubmitted','legacy_cash_correction'],true));
    $reasonData = $eventReason ? json_decode((string)$eventReason->remarks,true) : null;
    $editReason = $detail['revision_reason'] ?? (is_array($reasonData) ? ($reasonData['revision_reason']??null) : null);
@endphp
<div class="vap-detail">
 <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
  <h5 class="font-weight-bold mb-1">{{ $detail['number'] ?? 'Voucher unavailable' }}</h5>
  <span class="vap-status {{ $item->status }}">{{ ucfirst($item->status) }}</span>
 </div>
 @if(!empty($detail['pending_edit']))
  <div class="alert alert-warning py-2 small">Pending edit: date, description and amount below are <strong>proposed</strong>. Existing posted ledger lines remain unchanged until approval.</div>
 @endif
 <div class="vap-detail-grid mb-3">
  <div><strong>Voucher No</strong><span>{{ $detail['number'] ?? '—' }}</span></div>
  <div><strong>Date</strong><span>{{ $detail['date'] ?? '—' }}</span></div>
  <div><strong>Amount</strong><span>{{ $money($detail['amount'] ?? null) }}</span></div>
  <div><strong>Reference</strong><span>{{ $detail['reference'] ?: '—' }}</span></div>
  <div><strong>Payment Type</strong><span>{{ $detail['payment_type'] ?: '—' }}</span></div>
  @if(!empty($detail['attachment_url']))
  <div class="vap-detail-wide"><strong>Attachment</strong><span><a href="{{ $detail['attachment_url'] }}" target="_blank" rel="noopener">{{ $detail['attachment_name'] ?? 'Download attachment' }}</a></span></div>
  @endif
  <div><strong>Transaction / Cheque No</strong><span>{{ $detail['transaction_reference'] ?: '—' }}</span></div>
  <div><strong>Bank</strong><span>{{ $detail['bank'] ?: '—' }}</span></div>
  <div><strong>Passing Date</strong><span>{{ $detail['passing_date'] ?: '—' }}</span></div>
  <div><strong>Created At</strong><span>{{ $detail['created_at'] ?? '—' }}</span></div>
  <div><strong>Updated At</strong><span>{{ $detail['updated_at'] ?? '—' }}</span></div>
  <div><strong>Submitted By</strong><span>{{ $item->submitter?->name ?? 'User #'.$item->submitted_by }}</span></div>
  <div><strong>Assigned Approver</strong><span>{{ $item->assignedApprover?->name ?? 'User #'.$item->assigned_approver_id }}</span></div>
  <div class="vap-detail-wide"><strong>Purpose / Description</strong><span>{{ $detail['description'] ?: '—' }}</span></div>
  <div class="vap-detail-wide"><strong>Reason for Revision / Edit</strong><span>{{ $editReason ?: '—' }}</span></div>
  <div class="vap-detail-wide"><strong>Rejection Reason</strong><span>{{ $item->rejection_reason ?: '—' }}</span></div>
 </div>
 <h6 class="font-weight-bold">Voucher lines {{ !empty($detail['pending_edit']) ? '(original posting)' : '' }}</h6>
 <div class="table-responsive">
  <table class="table table-sm table-bordered vap-lines">
   <thead><tr><th>Account / Child Account</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
   <tbody>
   @forelse(($detail['lines'] ?? []) as $line)
    <tr><td>{{ $line['account'] ?: '—' }}</td><td>{{ $line['description'] ?: '—' }}</td>
     <td class="text-right">{{ $money($line['debit']) }}</td><td class="text-right">{{ $money($line['credit']) }}</td></tr>
   @empty
    <tr><td colspan="4" class="text-center text-muted">No linked account lines found for this project.</td></tr>
   @endforelse
   </tbody>
  </table>
 </div>
 @if(!empty($detail['pending_edit']))
  <p class="small text-muted mb-3">Proposed account ID: {{ $detail['proposed_account_id'] ?? '—' }}. Proposed amount: {{ $money($detail['proposed_amount'] ?? null) }}. Original accounting values above are unchanged until approval.</p>
 @endif
 @if(!empty($detail['edit_history']))
  <h6 class="font-weight-bold mt-3">Edit / Revision change history</h6>
  @foreach($detail['edit_history'] as $edit)
   <div class="vap-event mb-2">
    <strong>{{ $edit['created_at'] }} · {{ ucfirst($edit['status']) }}</strong>
    <div><strong>Reason:</strong> {{ $edit['reason'] }}</div>
    <div class="table-responsive mt-2">
     <table class="table table-sm table-bordered mb-1">
      <thead><tr><th>Field</th><th>Before Edit</th><th>Proposed / Applied</th></tr></thead>
      <tbody>
       @foreach(['date'=>'Date','description'=>'Narration','account_id'=>'Account ID','amount_in'=>'Amount In','amount_out'=>'Amount Out'] as $key=>$label)
        <tr><td>{{ $label }}</td><td>{{ $edit['before'][$key] ?? '—' }}</td><td>{{ $edit['after'][$key] ?? '—' }}</td></tr>
       @endforeach
      </tbody>
     </table>
    </div>
   </div>
  @endforeach
 @endif
 <h6 class="font-weight-bold">Approval audit timeline</h6>
 @forelse($item->events as $event)
  @php $context=json_decode((string)$event->remarks,true); @endphp
  <div class="vap-event">
   <strong>{{ $event->created_at?->format('d-M-Y H:i:s') }} · {{ ucwords(str_replace('_',' ',$event->event)) }}</strong>
   <div>By {{ $auditActors[$event->actor_id] ?? 'User #'.$event->actor_id }}</div>
   @if(is_array($context))
    @foreach(['revision_reason'=>'Edit / Revision Reason','reason'=>'Reason','previous_status'=>'Previous Status'] as $key=>$label)
     @if(!empty($context[$key]))<div>{{ $label }}: {{ $context[$key] }}</div>@endif
    @endforeach
   @elseif($event->remarks)
    <div>{{ $event->remarks }}</div>
   @endif
  </div>
 @empty
  <p class="text-muted small">No audit events recorded.</p>
 @endforelse
</div>
