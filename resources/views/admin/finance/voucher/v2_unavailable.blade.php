@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div><h1>{{ $title }}</h1><div class="text-muted">Project #{{ $projectId }} · Voucher register</div></div>
            <a class="btn btn-outline-primary" href="{{ route('finance.daily-vouchers.index') }}">Voucher Directory</a>
        </div>
    </section>
    <section class="content"><div class="container-fluid">
        <div class="alert alert-warning">
            <strong>Voucher workspace is not configured.</strong>
            The selected database does not currently meet the finance workspace requirements. No voucher was created or changed.
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ $title }} register</strong>
                <button class="btn btn-primary" type="button" data-toggle="modal" data-target="#finance-setup-modal">New voucher</button>
            </div>
            <div class="table-responsive" style="max-height:65vh">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Date</th><th>Voucher #</th><th>Reference</th><th>Account / Party</th><th>Amount</th><th>Status</th><th>Details</th></tr>
                    <tr>
                        @foreach(['Date','Voucher #','Reference','Account / Party','Amount','Status','Details'] as $column)
                            <th><span class="small text-muted">{{ $column }}</span></th>
                        @endforeach
                    </tr></thead>
                    <tbody><tr><td colspan="7" class="text-center text-muted p-4">This register is unavailable for the selected database.</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="modal fade" id="finance-setup-modal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Setup required</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body">Check the selected database configuration and required finance tables before creating vouchers.</div></div></div></div>
    </div></section>
</div>
@endsection
