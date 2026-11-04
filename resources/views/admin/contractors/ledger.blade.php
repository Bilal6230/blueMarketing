@extends('admin.layouts.master')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div><h3 class="mb-1">Contractor Ledger</h3><div>{{ $contractor->code }} — {{ $contractor->name }} · {{ $contractor->account?->subheadAccounting?->name }}</div></div>
        <a href="{{ route('contractors.index') }}" class="btn btn-outline-primary">Back to Contractors</a>
    </div>
    <div class="card"><div class="card-header d-flex justify-content-between"><strong>Posted account history</strong><strong>Net balance: {{ number_format($balance / 100, 2) }}</strong></div>
        <div class="table-responsive"><table class="table table-striped table-bordered mb-0"><thead><tr><th>Date</th><th>Voucher</th><th>Details</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Running balance</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr><td>{{ $entry['date'] }}</td><td>{{ $entry['voucher'] }}</td><td>{{ $entry['detail'] }}</td><td class="text-end">{{ number_format($entry['in'] / 100, 2) }}</td><td class="text-end">{{ number_format($entry['out'] / 100, 2) }}</td><td class="text-end">{{ number_format($entry['balance'] / 100, 2) }}</td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">No posted transactions on this contractor account yet.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>
@endsection
