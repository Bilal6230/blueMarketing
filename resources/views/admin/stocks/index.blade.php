@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper pt-4">
        <section class="content">
            <div class="container-fluid">
                <div class="custom_card h-100">
                    <div class="card-body">
                        <div class="mb-3 d-flex align-items-center justify-content-between">
                            <h3>{{ $title }}</h3>
                            <button class="btn btn-sm custom_btn primary" data-toggle="modal" data-target="#addStockModal">
                                <i class="fas fa-plus mr-1"></i> Add Stock
                            </button>
                        </div>
                        @if ($stocks->isNotEmpty())
                            <table class="table table-hover" id="stockTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Total Used</th>
                                        <th>Total Remaining</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($stocks as $stock)
                                        <tr>
                                            <td>{{ $stock->id }}</td>
                                            <td>{{ $stock->stock_name ?? 'N/A' }}</td>
                                            <td>
                                                <span
                                                    class="badge {{ $stock->type === 'purchase' ? 'badge-success' : 'badge-danger' }}"
                                                    style="min-width: 60px;">
                                                    {{ ucfirst($stock->type) }}
                                                </span>
                                            </td>
                                            <td>{{ $stock->quantity }}</td>
                                            <td>{{ $stock->total_used }}</td>
                                            <td>{{ $stock->total_remaining }}</td>
                                            <td>{{ $stock->created_at->format('d M, Y') }}</td>
                                            <td>
                                                <button class="btn btn-info btn-sm editBtn" data-id="{{ $stock->id }}"
                                                    data-project="{{ $stock->project_id }}"
                                                    data-type="{{ $stock->type }}" data-quantity="{{ $stock->quantity }}"
                                                    data-used="{{ $stock->total_used }}"
                                                    data-remaining="{{ $stock->total_remaining }}" data-toggle="modal"
                                                    data-target="#editStockModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <form id="delete-form-{{ $stock->id }}"
                                                    action="{{ route('stocks.destroy', $stock) }}" method="POST"
                                                    style="display:inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="confirmDelete({{ $stock->id }})">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="notfound bg-white p-3">
                                <div class="d-flex flex-wrap justify-content-center align-items-center">
                                    <div class="image-notfound mr-3">
                                        <img src="{{ asset('dist/images/not-found.png') }}" class="img-fluid">
                                    </div>
                                    <div class="text-notfound text-center">
                                        <h4 class="mb-0 f-20 text-dark">{{ __('Sorry! No data found.') }}</h4>
                                        <p class="mb-0 f-16 text-gray-100 mt-2">
                                            {{ __('The requested data does not exist for this feature overview.') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> {{-- Larger modal --}}
            <form action="{{ route('stocks.store') }}" method="POST" class="modal-content shadow-lg border-0 rounded-3">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addStockModalLabel">
                        <i class="fas fa-plus-circle mr-2"></i> Add Stock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @include('admin.stocks.form', ['idPrefix' => 'add'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editStockModal" tabindex="-1" aria-labelledby="editStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> {{-- Larger modal --}}
            <form method="POST" id="editStockForm" class="modal-content shadow-lg border-0 rounded-3">
                @csrf @method('PUT')
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editStockModalLabel">
                        <i class="fas fa-edit mr-2"></i> Edit Stock
                    </h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @include('admin.stocks.form', ['idPrefix' => 'edit'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check mr-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $("#stockTable").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            })

            function calculateRemaining(prefix) {
                let quantity = parseInt($('#' + prefix + '_quantity').val()) || 0;
                let used = parseInt($('#' + prefix + '_total_used').val()) || 0;
                $('#' + prefix + '_total_remaining').val(quantity - used);
            }

            $('#add_quantity, #add_total_used').on('input', function() {
                calculateRemaining('add');
            });

            $('#edit_quantity, #edit_total_used').on('input', function() {
                calculateRemaining('edit');
            });

            $(document).on('click', '.editBtn', function() {
                let id = $(this).data('id');
                let updateUrl = "{{ route('stocks.update', ':id') }}".replace(':id', id);
                $('#editStockForm').attr('action', updateUrl);

                $('#edit_quantity').val($(this).data('quantity'));
                $('#edit_total_used').val($(this).data('used'));
                $('#edit_total_remaining').val($(this).data('remaining'));
                $('#edit_type').val($(this).data('type'));

                calculateRemaining('edit'); // recalc when opening
            });

        });
    </script>
    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end', // top right
                    icon: 'error',
                    title: `{!! implode('<br>', $errors->all()) !!}`,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif

    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: '{{ session('success') }}',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            });
        </script>
    @endif
    <script>
        function confirmDelete(stockId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This stock will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form-' + stockId).submit();
                }
            });
        }
    </script>
@endsection
