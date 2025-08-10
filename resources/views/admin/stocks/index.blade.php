@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid d-flex justify-content-between">
                <h1 class="m-0">{{ $title }}</h1>
                <button class="btn btn-primary" data-toggle="modal" data-target="#addStockModal">Add Stock</button>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($stocks->isNotEmpty())
                    <table class="table table-bordered">
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
                                            class="badge {{ $stock->type === 'purchase' ? 'badge-success' : 'badge-danger' }}">
                                            {{ ucfirst($stock->type) }}
                                        </span>
                                    </td>
                                    <td>{{ $stock->quantity }}</td>
                                    <td>{{ $stock->total_used }}</td>
                                    <td>{{ $stock->total_remaining }}</td>
                                    <td>{{ $stock->created_at->format('d M, Y') }}</td>
                                    <td>
                                        <button class="btn btn-warning btn-sm editBtn" data-id="{{ $stock->id }}"
                                            data-project="{{ $stock->project_id }}" data-type="{{ $stock->type }}"
                                            data-quantity="{{ $stock->quantity }}" data-used="{{ $stock->total_used }}"
                                            data-remaining="{{ $stock->total_remaining }}" data-toggle="modal"
                                            data-target="#editStockModal">
                                            Edit
                                        </button>

                                        <form action="{{ route('stocks.destroy', $stock) }}" method="POST"
                                            style="display:inline-block">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm"
                                                onclick="return confirm('Delete this stock?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="notfound bg-white p-4 shadow">
                        <div class="d-flex flex-wrap justify-content-center align-items-center">
                            <div class="image-notfound">
                                <img src="{{ asset('dist/images/not-found.png') }}" class="img-fluid">
                            </div>
                            <div class="text-notfound text-center">
                                <p class="mb-0 f-20 text-dark">{{ __('Sorry! No data found.') }}</p>
                                <p class="mb-0 f-16 text-gray-100 mt-2">
                                    {{ __('The requested data does not exist for this feature overview.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
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
                        <i class="fas fa-plus-circle me-2"></i> Add Stock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                @include('admin.stocks.form', ['idPrefix' => 'add'])
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
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
                        <i class="fas fa-edit me-2"></i> Edit Stock
                    </h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                @include('admin.stocks.form', ['idPrefix' => 'edit'])
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check me-1"></i> Update
                    </button>
                    <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('js')
    <script>
        $(document).ready(function() {

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

            $(document).on('click','.editBtn', function() {
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
@endsection
