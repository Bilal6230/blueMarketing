@extends('admin.layouts.master')

@section('content')
    <div class="content-wrapper">
        <div class="content">
            <div class="container-fluid mt-2">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">{{ $title }}</h4>
                    </div>
                    <div class="card-body">
                        <table id="pendingTable" class="table table-bordered table-hover">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th style="background-color: black !important">ID</th>
                                    <th style="background-color: black !important">Table</th>
                                    <th style="background-color: black !important">Record ID</th>
                                    <th style="background-color: black !important">Submitted By</th>
                                    <th style="background-color: black !important">Status</th>
                                    <th style="background-color: black !important">Submitted At</th>
                                    <th style="background-color: black !important">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingUpdates as $update)
                                    <tr>
                                        <td>{{ $update->id }}</td>
                                        <td>{{ $update->table_name }}</td>
                                        <td>{{ $update->record_id }}</td>
                                        <td>{{ $update->submittedBy->name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-warning text-dark">
                                                {{ ucfirst($update->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $update->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <button class="btn btn-success btn-sm approve-btn" data-id="{{ $update->id }}"
                                                data-table="{{ $update->table_name }}">
                                                Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm reject-btn" data-id="{{ $update->id }}">
                                                Reject
                                            </button>
                                            <button class="btn btn-sm btn-outline-info btn-view-changes"
                                                data-old='@json($update->old_values)'
                                                data-new='@json($update->new_values)'
                                                data-submitted_by="{{ $update->submitted_by }}"
                                                data-record_id="{{ $update->record_id }}">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>

                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No pending approvals found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- View Changes Modal -->
    <div class="modal fade" id="viewChangesModal" tabindex="-1" aria-labelledby="viewChangesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewChangesModalLabel">
                        <i class="fas fa-exchange-alt me-2"></i> Pending Changes
                    </h5>
                    <!-- Header close button removed -->
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Old Value</th>
                                <th>New Value</th>
                            </tr>
                        </thead>
                        <tbody id="changesTableBody">
                            <!-- Dynamically filled by JS -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <!-- Footer close button remains -->
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function() {
            $('#pendingTable').DataTable({
                responsive: true,
                autoWidth: false
            });

            // Approve voucher
            $(document).on('click', '.approve-btn', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const container = $(this).closest('.admin_approval'); // full container to remove

                Swal.fire({
                    title: 'Approve Voucher?',
                    text: 'Are you sure you want to approve this voucher?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Approve',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-success me-2',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('finance.voucher.approveadmin', ['id' => 'ID_PLACEHOLDER']) }}"
                                .replace('ID_PLACEHOLDER', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                table: table
                            },
                            success: function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Approved!',
                                    text: 'Voucher approved successfully.',
                                    timer: 100,
                                    showConfirmButton: false
                                });
                                // 🗑 Remove container completely
                                window.location.reload();

                            },
                            error: function(xhr) {
                                const error = xhr.responseJSON?.message ||
                                    'Something went wrong.';
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error
                                });
                            }
                        });
                    }
                });
            });
            // Reject voucher
            $(document).on('click', '.reject-btn', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const table = $(this).data('table');
                const container = $(this).closest('.admin_approval');

                Swal.fire({
                    title: 'Reject Voucher?',
                    text: 'Are you sure you want to reject this voucher?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Reject',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-danger me-2',
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "{{ route('finance.voucher.rejectadmin', ['id' => 'ID_PLACEHOLDER']) }}"
                                .replace('ID_PLACEHOLDER', id),
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                table: table
                            },
                            success: function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Rejected!',
                                    text: 'Voucher rejected successfully.',
                                    timer: 100,
                                    showConfirmButton: false
                                });
                                // 🗑 Remove container completely
                                window.location.reload();
                            },
                            error: function(xhr) {
                                const error = xhr.responseJSON?.message ||
                                    'Something went wrong.';
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error
                                });
                            }
                        });
                    }
                });
            });

            $(document).on('click', '.btn-view-changes', function() {
                let newValues = $(this).attr('data-new') || '{}';
                let oldValues = $(this).attr('data-old') || '{}';

                // Decode HTML entities first
                newValues = $('<textarea/>').html(newValues).text();
                oldValues = $('<textarea/>').html(oldValues).text();

                try {
                    newValues = JSON.parse(newValues);
                    if (typeof newValues === 'string') newValues = JSON.parse(
                    newValues); // Handle double encoding

                    oldValues = JSON.parse(oldValues);
                    if (typeof oldValues === 'string') oldValues = JSON.parse(oldValues);
                } catch (e) {
                    console.error('Invalid JSON:', e);
                    newValues = {};
                    oldValues = {};
                }

                const submittedBy = $(this).data('submitted_by') || 'Unknown User';
                const record_id = $(this).data('record_id') || $(this).data('id');
                const $tbody = $('#changesTableBody');
                const $modalFooter = $('#viewChangesModal .modal-footer');

                $tbody.empty();
                $modalFooter.find('.btn-approve, .btn-reject').remove(); // Remove old buttons

                // 🛑 If it's a delete request (only is_active = 0)
                if (Object.keys(newValues).length === 1 && newValues.is_active == 0) {
                    $tbody.html(`
            <tr>
                <td colspan="3" class="text-center text-danger fw-bold">
                    <i class="fas fa-trash-alt me-2"></i>
                    User <span class="text-primary">${submittedBy}</span> has requested to <strong>delete</strong> the ledger.
                    ${newValues.delete_reason ? `<br><strong>Reason:</strong> ${newValues.delete_reason}` : ''}
                </td>
            </tr>
        `);
                } else {
                    // 📝 Normal field differences
                    $.each(newValues, function(key, newVal) {
                        const oldVal = oldValues[key] ?? '<em class="text-muted">N/A</em>';
                        const safeNewVal = newVal ?? '<em class="text-muted">N/A</em>';
                        $tbody.append(`
                <tr>
                    <td><strong>${key}</strong></td>
                    <td>${oldVal}</td>
                    <td class="text-primary fw-semibold">${safeNewVal}</td>
                </tr>
            `);
                    });
                }

                // ✅ Approve & Reject buttons
                const approveBtn = $(`
        <button class="btn btn-outline-success btn-approve" data-id="${record_id}" data-table="draft_ledgers">
            <i class="fas fa-check"></i> Approve
        </button>
    `);
                const rejectBtn = $(`
        <button class="btn btn-outline-danger btn-reject" data-id="${record_id}" data-table="draft_ledgers">
            <i class="fas fa-times"></i> Reject
        </button>
    `);

                $modalFooter.prepend(approveBtn, rejectBtn);

                const modal = new bootstrap.Modal($('#viewChangesModal')[0]);
                modal.show();
            });

        });
    </script>
@endsection
