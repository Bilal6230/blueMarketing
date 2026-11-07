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
            This page needs the isolated local V2 finance database and its migrations. No voucher was created or changed.
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ $title }} register</strong>
                <button class="btn btn-primary" type="button" data-toggle="modal" data-target="#v2-setup-modal">New voucher</button>
            </div>
            <div class="table-responsive" style="max-height:65vh">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Date</th><th>Voucher #</th><th>Reference</th><th>Account / Party</th><th>Amount</th><th>Status</th><th>Details</th></tr>
                    <tr>
                        @foreach(['Date','Voucher #','Reference','Account / Party','Amount','Status','Details'] as $column)
                            <th><span class="small text-muted">{{ $column }}</span></th>
                        @endforeach
                    </tr></thead>
                    <tbody><tr><td colspan="7" class="text-center text-muted p-4">Register data will appear after the isolated V2 database is configured.</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="modal fade" id="v2-setup-modal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Setup required</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body">Configure the isolated local V2 finance database and run its migrations before creating vouchers.</div></div></div></div>
    </div></section>
</div>
@endsection
