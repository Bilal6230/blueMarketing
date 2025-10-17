@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">{{ $title }}</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{ $title }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">

                    {{-- Create Form --}}
                    <div class="card-header">
                        <form action="{{ route('accounting.category_store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-sm-4">
                                    <label>Head Accounts</label>
                                    <select class="form-control select2" name="accounts_id" id="accounts_id">
                                        <option value="">Select Head</option>
                                        @foreach ($headaccounts as $head)
                                            <option value="{{ $head->id }}">{{ $head->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('accounts_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-sm-4">
                                    <label>Sub Head Accounts</label>
                                    <select class="form-control select2" name="subaccounts_id" id="subaccounts_id">
                                        <option value="">Select Sub Head</option>
                                        @foreach ($subheadaccounts as $sub)
                                            <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('subaccounts_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>

                    {{-- Data Table --}}
                    <div class="card-body table-responsive">
                        <table id="categoryTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Head Account</th>
                                    <th>Sub Head Account</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categoryMappings as $index => $map)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $map->head_names ?? 'N/A' }}</td>
                                        <td>{{ $map->subhead_name ?? 'N/A' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm editBtn"
                                                data-id="{{ $map->id }}">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <button type="button" class="btn btn-danger btn-sm deleteBtn"
                                                data-id="{{ $map->id }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </section>

        {{-- Edit Modal --}}
        {{-- Put this in your category_index blade (replace current edit modal & js) --}}

        {{-- Edit Modal --}}
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Head Accounts</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>

                    <form id="editForm">
                        @csrf
                        <div class="modal-body">
                            <input type="hidden" id="edit_id">

                            <div class="form-group">
                                <label>Head Accounts</label>
                                <select id="edit_heads" name="head_ids[]" class="form-control select2" multiple="multiple"
                                    style="width:100%;">
                                    <!-- options populated via JS -->
                                </select>
                                <small class="form-text text-muted">Select one or more head accounts for this
                                    subhead.</small>
                            </div>

                            <div class="form-group">
                                <label>Sub Head Account</label>
                                <input type="text" id="edit_subhead" class="form-control" readonly>
                            </div>

                            <!-- Optional: show existing pivot rows for debugging/visibility (you can remove) -->
                            <div class="form-group">
                                <label>Existing Pivot IDs</label>
                                <div id="pivotList" class="small text-muted"></div>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>



    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: `{!! session('error') !!}`
            });
        </script>
    @endif
    <script>
        $(function() {
            // initialize Select2 (ensure select2 CSS/JS loaded in layout)
            function initSelect2() {
                if ($.fn.select2) {
                    $('#edit_heads').select2({
                        dropdownParent: $('#editModal'),
                        width: '100%'
                    });
                }
            }
            initSelect2();

            // Open Edit Modal
            $(document).on('click', '.editBtn', function() {
                let id = $(this).data('id');

                $.ajax({
                    url: "{{ url('admin/finance/accounting/category/edit') }}/" + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (res.status !== 'success') {
                            Swal.fire('Error', res.message || 'Could not fetch data', 'error');
                            return;
                        }

                        // expected response: data: { id, subhead_name, selected_heads, pivots }, heads: [...]
                        let data = res.data;
                        let heads = res.heads || [];

                        $('#edit_id').val(data.id);
                        $('#edit_subhead').val(data.subhead_name || '');

                        // populate select options and set selected ones
                        let $select = $('#edit_heads');
                        $select.empty();

                        $.each(heads, function(i, head) {
                            let isSelected = (data.selected_heads || []).indexOf(head
                                .id) !== -1;
                            let opt = $('<option>', {
                                value: head.id,
                                text: head.name,
                                selected: isSelected
                            });
                            $select.append(opt);
                        });

                        // refresh/select2
                        if ($.fn.select2) {
                            $select.trigger('change');
                        }

                        // Optional: display pivot rows (pivot_id -> head_id) for clarity
                        if (data.pivots && data.pivots.length) {
                            let html = data.pivots.map(function(p) {
                                return 'pivot_id: ' + p.pivot_id + ' → head_id: ' + p
                                    .head_id;
                            }).join('<br>');
                            $('#pivotList').html(html).show();
                        } else {
                            $('#pivotList').html('No pivot rows found').show();
                        }

                        $('#editModal').modal('show');
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Server error while fetching record', 'error');
                    }
                });
            });

            // Submit Update
            $('#editForm').on('submit', function(e) {
                e.preventDefault();
                let id = $('#edit_id').val();
                let head_ids = $('#edit_heads').val() || []; // array of selected head ids

                if (head_ids.length === 0) {
                    Swal.fire('Validation', 'Please select at least one Head Account', 'warning');
                    return;
                }

                $.ajax({
                    url: "{{ url('admin/finance/accounting/category/update') }}/" + id,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: "{{ csrf_token() }}",
                        head_ids: head_ids
                    },
                    success: function(res) {
                        if (res.status === 'success') {
                            $('#editModal').modal('hide');
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated',
                                text: res.message || 'Saved',
                                timer: 1400,
                                showConfirmButton: false
                            });
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else if (res.status === 'warning') {
                            Swal.fire('Warning', res.message, 'warning');
                        } else {
                            Swal.fire('Error', res.message || 'Update failed', 'error');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Server error';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON
                            .message;
                        Swal.fire('Error', msg, 'error');
                    }
                });
            });

            // Delete button (unchanged)
            $(document).on('click', '.deleteBtn', function() {
                let id = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This record will be deleted permanently!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!'
                }).then((res) => {
                    if (!res.isConfirmed) return;
                    $.ajax({
                        url: "{{ url('admin/finance/accounting/category/delete') }}/" + id,
                        type: 'DELETE',
                        data: {
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(r) {
                            if (r.status === 'success') {
                                Swal.fire('Deleted', r.message, 'success');
                                setTimeout(() => location.reload(), 1200);
                            } else {
                                Swal.fire('Error', r.message || 'Delete failed',
                                    'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Server error', 'error');
                        }
                    });
                });
            });
            $('#categoryTable').DataTable({
                responsive: true,
                autoWidth: false,
                pageLength: 10
            });

        });
    </script>
@endsection
