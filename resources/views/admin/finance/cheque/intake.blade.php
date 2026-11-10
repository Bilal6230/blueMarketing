@extends('admin.layouts.master')
@section('content')
<div class="content-wrapper">
    <section class="content pt-3">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Cheque Management — Receiving Register</h4>
                    @can('create voucher')
                        <button type="button" class="btn btn-primary" id="cheque-add-toggle">Add Cheque</button>
                    @endcan
                </div>
                <div class="card-body">
                    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
                    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
                    @can('create voucher')
                    <form id="cheque-intake-form" class="border rounded p-3 mb-4" method="POST"
                          action="{{ route('finance.cheques.store') }}" enctype="multipart/form-data"
                          style="{{ $errors->any() ? '' : 'display:none' }}">
                        @csrf
                        <h5>Receive New Cheque</h5>
                        <div class="row">
                            <div class="form-group col-md-4"><label>Cheque Number *</label><input class="form-control" name="cheque_number" maxlength="100" required value="{{ old('cheque_number') }}"></div>
                            <div class="form-group col-md-4"><label>Cheque Date *</label><input class="form-control" type="date" name="cheque_date" required value="{{ old('cheque_date') }}"></div>
                            <div class="form-group col-md-4"><label>Receiving Date *</label><input class="form-control" type="date" name="received_date" required value="{{ old('received_date', date('Y-m-d')) }}"></div>
                            <div class="form-group col-md-4"><label>Amount (Rs.) *</label><input class="form-control" name="amount" inputmode="decimal" required value="{{ old('amount') }}"></div>
                            <div class="form-group col-md-4"><label>Bank Name *</label><input class="form-control" name="bank_name" maxlength="150" required value="{{ old('bank_name') }}"></div>
                            <div class="form-group col-md-4"><label>Branch</label><input class="form-control" name="branch" maxlength="150" value="{{ old('branch') }}"></div>
                            <div class="form-group col-md-4"><label>Received From *</label><input class="form-control" name="received_from" maxlength="150" required value="{{ old('received_from') }}"></div>
                            <div class="form-group col-md-8"><label>Related Customer *</label>
                                <select class="form-control" name="customer_id" required>
                                    <option value="">Select Customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->first_name }} {{ $customer->last_name }} (#{{ $customer->id }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-12"><label>Purpose / Description *</label><textarea class="form-control" name="purpose" maxlength="500" required>{{ old('purpose') }}</textarea></div>
                            <div class="form-group col-md-8"><label>Remarks</label><textarea class="form-control" name="remarks" maxlength="2000">{{ old('remarks') }}</textarea></div>
                            <div class="form-group col-md-4"><label>Cheque Image / PDF</label><input type="file" class="form-control-file" name="attachment" accept=".jpg,.jpeg,.png,.pdf"></div>
                        </div>
                        <p class="text-muted">Saving creates a receiving record only. It does not confirm bank clearance or generate an accounting voucher.</p>
                        <button type="submit" class="btn btn-success">Save Cheque</button>
                    </form>
                    @endcan
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>Cheque #</th><th>Bank</th><th>Received From</th><th>Amount</th><th>Received</th><th>Purpose</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($rows as $row)
                                <tr><td>{{ $row->cheque_number }}</td><td>{{ $row->bank_name }}</td><td>{{ $row->received_from }}</td>
                                    <td>{{ number_format((float)$row->amount, 2) }}</td><td>{{ $row->received_date }}</td>
                                    <td>{{ $row->purpose }}</td><td><span class="badge badge-secondary">{{ ucfirst($row->status) }}</span></td></tr>
                                @empty <tr><td colspan="7">No received cheques yet.</td></tr> @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
@push('script')
<script>
$(function () {
    $('#cheque-add-toggle').on('click', function () {
        $('#cheque-intake-form').slideToggle(150);
    });
});
</script>
@endpush
