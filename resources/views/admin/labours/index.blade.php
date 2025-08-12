@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid d-flex justify-content-between">
                <h1 class="m-0">{{ $title }}</h1>
                <button class="btn btn-primary" data-toggle="modal" data-target="#addLabourModal">Add Labour</button>
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

                @if ($labours->isNotEmpty())
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>CNIC</th>
                                <th>Phone</th>
                                <th>Daily Wage</th>
                                <th>Join Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($labours as $labour)
                                <tr>
                                    <td>{{ $labour->id }}</td>
                                    <td>{{ $labour->name }}</td>
                                    <td>{{ $labour->cnic }}</td>
                                    <td>{{ $labour->phone }}</td>
                                    <td>{{ $labour->daily_wage }}</td>
                                    <td>{{ $labour->join_date }}</td>
                                    <td>
                                        <form action="{{ route('labours.updateStatus', $labour->id) }}" method="POST"
                                            style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                class="btn btn-sm {{ $labour->status === 'active' ? 'btn-success' : 'btn-danger' }}">
                                                {{ ucfirst($labour->status) }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm editBtn" data-id="{{ $labour->id }}"
                                            data-name="{{ $labour->name }}" data-cnic="{{ $labour->cnic }}"
                                            data-phone="{{ $labour->phone }}" data-wage="{{ $labour->daily_wage }}"
                                            data-date="{{ $labour->join_date }}" data-status="{{ $labour->status }}"
                                            data-toggle="modal" data-target="#editLabourModal">
                                            Edit
                                        </button>

                                        <form action="{{ route('labours.destroy', $labour) }}" method="POST"
                                            style="display:inline-block">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm"
                                                onclick="return confirm('Delete this labour?')">Delete</button>
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
    <div class="modal fade" id="addLabourModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form action="{{ route('labours.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addStockModalLabel">
                        <i class="fas fa-plus-circle me-2"></i> Add Labour
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('admin.labours.form')
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
    <div class="modal fade" id="editLabourModal" tabindex="-1" tabindex="-1" aria-labelledby="editStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" id="editLabourForm" class="modal-content">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"> <i class="fas fa-edit me-2"> Edit Labour</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    @include('admin.labours.form')
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
        document.addEventListener('DOMContentLoaded', function() {
            $('.editBtn').on('click', function() {
                let id = $(this).data('id');
                let updateUrl = "{{ route('labours.update', ':id') }}".replace(':id', id);
                $('#editLabourForm').attr('action', updateUrl)
                $('input[name="name"]').val($(this).data('name'));
                $('input[name="cnic"]').val($(this).data('cnic'));
                $('input[name="phone"]').val($(this).data('phone'));
                $('input[name="daily_wage"]').val($(this).data('wage'));
                $('input[name="join_date"]').val($(this).data('date'));
                $('select[name="status"]').val($(this).data('status'));
            });
        });
    </script>
@endsection
