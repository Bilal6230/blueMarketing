@extends('admin.layouts.master')
@section('content')
<div class="content-wrapper"><section class="content pt-3"><div class="container-fluid">
    <div class="mb-3 d-flex justify-content-between">
        <a href="{{ route('finance.reports.trial_balance') }}" class="btn btn-light">← Change Dates</a>
        <a target="_blank" href="{{ route('finance.reports.trial_balance.print', ['from'=>$from,'to'=>$to]) }}" class="btn btn-dark">Print</a>
    </div>
    <div class="card"><div class="card-body table-responsive">
        <div class="report-heading"><h3>{{ $projectName }}</h3><h4>Trial Balance</h4><div>{{ $from }} to {{ $to }}</div></div>
        @include('admin.finance.reports._trial_balance_table')
        @if($paginator->hasPages())
            <nav class="trial-pagination" aria-label="Trial Balance pages">
                <a class="btn btn-sm btn-outline-primary {{ $paginator->onFirstPage() ? 'disabled' : '' }}"
                   href="{{ $paginator->onFirstPage() ? '#' : $paginator->previousPageUrl() }}">&larr; Previous</a>
                <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
                <a class="btn btn-sm btn-outline-primary {{ $paginator->hasMorePages() ? '' : 'disabled' }}"
                   href="{{ $paginator->hasMorePages() ? $paginator->nextPageUrl() : '#' }}">Next &rarr;</a>
            </nav>
        @endif
    </div></div>
</div></section></div>
@endsection
@push('style')
<style>
.report-heading{text-align:center;margin-bottom:18px;font-family:Arial,sans-serif}.report-heading h3,.report-heading h4{margin:2px}.trial-balance{font-family:Arial,sans-serif;font-size:12px;margin-bottom:0}.trial-balance th,.trial-balance td{vertical-align:middle;white-space:nowrap;padding:5px}.trial-balance thead th{text-align:center;background:#3498d4;color:#fff}.trial-balance .account-title{text-align:left}.head-row th{text-align:center;font-size:18px;font-weight:800;background:#dce6ee;padding:10px}.subhead-row th{text-align:center;font-size:14px;font-weight:700;background:#f0f3f5;padding:7px}.account-label th{text-align:left;font-size:12px;background:#f8f9fa;padding-left:14px}.account-row td:nth-child(3){white-space:normal;min-width:360px}.entry-meta{display:inline-block;min-width:155px;font-weight:600;color:#34495e}.code-col{text-align:center;width:58px}.number{text-align:right}.subtotal,.head-total{font-weight:700}.head-total{background:#e8eef2}.trial-balance tfoot{font-weight:800;background:#3498d4;color:#fff}.trial-pagination{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:16px;font-family:Arial,sans-serif}.trial-pagination span{font-size:13px;font-weight:600;color:#34495e}.trial-pagination .disabled{pointer-events:none;opacity:.5}
</style>
@endpush
