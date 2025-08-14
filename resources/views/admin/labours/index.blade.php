@extends('admin.layouts.master')
@section('content')
    <div class="content-wrapper pt-4">
        <section class="content">
            <div class="container-fluid">

                <div class="custom_card h-100">
                    <div class="card-body">
                        <div class="mb-3 d-flex align-items-center justify-content-between">
                            <h3>{{ $title }}</h3>
                            <button class="btn btn-sm custom_btn primary" data-toggle="modal" data-target="#addLabourModal">
                                <i class="fas fa-plus mr-1"></i> Add Labour
                            </button>
                        </div>
                        @if ($labours->isNotEmpty())
                            <table class="table table-hover" id="labourTable">
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
                                                <form action="{{ route('labours.updateStatus', $labour->id) }}"
                                                    method="POST" style="display:inline;">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="btn btn-sm {{ $labour->status === 'active' ? 'btn-success' : 'btn-danger' }}">
                                                        {{ ucfirst($labour->status) }}
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm editBtn" data-id="{{ $labour->id }}"
                                                    data-name="{{ $labour->name }}" data-cnic="{{ $labour->cnic }}"
                                                    data-phone="{{ $labour->phone }}"
                                                    data-wage="{{ $labour->daily_wage }}"
                                                    data-date="{{ $labour->join_date }}"
                                                    data-status="{{ $labour->status }}" data-toggle="modal"
                                                    data-target="#editLabourModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <form id="delete-labour-{{ $labour->id }}"
                                                    action="{{ route('labours.destroy', $labour) }}" method="POST"
                                                    style="display:inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="confirmDeleteLabour({{ $labour->id }})">
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
    <div class="modal fade" id="addLabourModal" tabindex="-1" aria-labelledby="addLabourModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form action="{{ route('labours.store') }}" method="POST" class="modal-content shadow-lg border-0 rounded-3">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addLabourModalLabel">
                        <i class="fas fa-plus-circle mr-2"></i> Add Labour
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @include('admin.labours.form')
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
    <div class="modal fade" id="editLabourModal" tabindex="-1" aria-labelledby="editLabourModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" id="editLabourForm" class="modal-content shadow-lg border-0 rounded-3">
                @csrf @method('PUT')
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editLabourModalLabel"> <i class="fas fa-edit mr-2"></i> Edit Labour</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"> <span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @include('admin.labours.form')
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
            $("#labourTable").DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
            })
        });
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
        function confirmDeleteLabour(labourId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This labour will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-labour-' + labourId).submit();
                }
            });
        }
    </script>
@endsection
