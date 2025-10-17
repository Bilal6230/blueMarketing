@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <div class="content">
        <div class="container-fluid mt-1">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">{{ $title }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <a href="{{ route('commision.voucher.create') }}" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add New Voucher
                        </a>
                    </div>
                    <table id="journalTable" class="table table-bordered">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>ID</th>
                                <th>Voucher Number</th>
                                <th>Reference</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                @canany(['edit jv', 'delete jv'])
                                    <th>Actions</th>
                                @endcanany
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vouchers as $voucher)
                            <tr>
                                <td>{{ $voucher->id }}</td>
                                <td>JV-{{ get_jv_number($voucher->voucher_number) }}</td>
                                <td>{{ $voucher->reference }}</td>
                                <td>{{ $voucher->date }}</td>
                                <td>{{ $voucher->description }}</td>
                                <td>{{ $voucher->total_debit }}</td>
                                @canany(['edit jv', 'delete jv','print jv'])
                                    <td>
                                        @can('edit jv')
                                        <a href="{{ route('commision.voucher.edit', $voucher->id) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        @endcan
                                        @can('delete jv')
                                        <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $voucher->id }}">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        @endcan

                                        @can('print jv')
                                        <a href="{{ route('commision.voucher.print', $voucher->id) }}" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                        @endcan

                                        

                                    </td>
                                @endcanany
                                
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('modal')
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this Journal Voucher?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
$(document).ready(function() {
    $('#journalTable').DataTable(); // Initialize DataTable

    // Delete Button Click
    $('.delete-btn').on('click', function () {
        const id = $(this).data('id');
        const deleteUrl = `{{ route('commision.voucher.delete', ':id') }}`.replace(':id', id);
        $('#deleteForm').attr('action', deleteUrl);
        $('#deleteModal').modal('show');
    });
});
</script>
@endsection
