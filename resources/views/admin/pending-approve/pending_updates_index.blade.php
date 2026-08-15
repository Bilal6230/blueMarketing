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
                                    <th style="background-color: black !important">Current Owner</th>
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
                                        <td>{{ $update->current_owner_name }}</td>
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
                                                data-comments='@json($update->table_name === "leads" ? $leadCommentsByRecord->get($update->record_id, []) : [])'
                                                data-submitted_by="{{ $update->submitted_by }}"
                                                data-record_id="{{ $update->record_id }}"
                                                data-lead='{{ json_encode($update->table_name === "leads" ? array(
                                                    "lead_id" => $update->record_id,
                                                    "lead_name" => $update->lead_name,
                                                    "current_owner_name" => $update->current_owner_name,
                                                    "requested_owner_name" => $update->requested_owner_name,
                                                    "request_type" => $update->request_type,
                                                ) : null) }}'
                                                data-table="{{ $update->table_name }}">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>

                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No pending approvals found</td>
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
                    <div class="modal_table_changes d-none">
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
                    <div id="commentSection"></div>
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
                const table = $(this).data('table');
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
                let comments = $(this).attr('data-comments') || '[]';
                let user = $(this).attr('data-user') || 'Unknown User';
                let tableName = $(this).data('table');
                const record_id = $(this).data('record_id') || $(this).data('id');
                const submittedBy = $(this).data('submitted_by') || 'Unknown User';

                const $tbody = $('#changesTableBody');
                const $modalFooter = $('#viewChangesModal .modal-footer');
                const $commentSection = $('#commentSection');
                const $modalTable = $('.modal_table_changes');

                // Decode HTML entities safely
                newValues = $('<textarea/>').html(newValues).text();
                oldValues = $('<textarea/>').html(oldValues).text();
                comments = $('<textarea/>').html(comments).text();

                try {
                    newValues = JSON.parse(newValues);
                    if (typeof newValues === 'string') newValues = JSON.parse(newValues);

                    oldValues = JSON.parse(oldValues);
                    if (typeof oldValues === 'string') oldValues = JSON.parse(oldValues);
                } catch (e) {
                    console.error('Invalid JSON:', e);
                    newValues = {};
                    oldValues = {};
                }

                try {
                    comments = JSON.parse(comments);
                    if (typeof comments === 'string') comments = JSON.parse(comments);
                } catch (e) {
                    console.error('Invalid JSON:', e);
                    comments = [];
                }

                // Reset modal
                $tbody.empty();
                $modalFooter.find('.btn-approve, .btn-reject').remove();
                $commentSection.hide().empty();

                // Helper function to open modal
                const openModal = (showTable = true) => {
                    if (showTable) {
                        $modalTable.removeClass('d-none');
                    } else {
                        $modalTable.addClass('d-none');
                    }


                    // Open the modal
                    const modal = new bootstrap.Modal($('#viewChangesModal')[0]);
                    modal.show();
                };

                // Build field differences
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

                // 🆕 Handle LEADS case
                if (tableName === 'leads') {
                    let commentsHtml = '';

                    if (Array.isArray(comments) && comments.length > 0) {
                        comments.forEach((item) => {
                            console.log(item);
                            if (item.comment) {
                                commentsHtml += `
                                <div class="border p-2 mb-2 rounded bg-light">
                                    <i class="fas fa-comment-dots text-primary"></i>
                                    <span>${item.comment}</span>
                                    <div class="text-muted small mt-1">
                                        ${item.created_at ? 'at ' + item.created_at : ''}
                                    </div>
                                    <div class="text-muted small">
                                        ${user ? 'by ' + user : ''}
                                    </div>
                                </div>
                            `;
                            }
                        });

                        $commentSection.html(`
                        <h6 class="mt-3 mb-2 text-secondary">
                            <i class="fas fa-comments me-1"></i> Related Comments
                        </h6>
                        ${commentsHtml}
                    `).show();
                    } else {
                        $commentSection.html(`
                        <div class="alert alert-secondary mt-3">
                            <i class="fas fa-info-circle"></i> No comments found for this lead.
                        </div>
                    `).show();
                    }

                    openModal(false);
                } else {
                    // ✅ For all other tables — show modal WITH table
                    openModal(true);
                }
            });

            $(document).off('click', '.btn-view-changes');
            $(document).on('click', '.btn-view-changes', function() {
                let newValues = $(this).attr('data-new') || '{}';
                let oldValues = $(this).attr('data-old') || '{}';
                let comments = $(this).attr('data-comments') || '[]';
                let leadDetails = $(this).attr('data-lead') || 'null';
                let tableName = $(this).data('table');
                const recordId = $(this).data('record_id') || $(this).data('id');
                const submittedBy = $(this).data('submitted_by') || 'Unknown User';

                const $tbody = $('#changesTableBody');
                const $modalFooter = $('#viewChangesModal .modal-footer');
                const $commentSection = $('#commentSection');
                const $modalTable = $('.modal_table_changes');
                const $modalTitle = $('#viewChangesModalLabel');

                newValues = $('<textarea/>').html(newValues).text();
                oldValues = $('<textarea/>').html(oldValues).text();
                comments = $('<textarea/>').html(comments).text();
                leadDetails = $('<textarea/>').html(leadDetails).text();

                try {
                    newValues = JSON.parse(newValues);
                    if (typeof newValues === 'string') newValues = JSON.parse(newValues);

                    oldValues = JSON.parse(oldValues);
                    if (typeof oldValues === 'string') oldValues = JSON.parse(oldValues);
                } catch (e) {
                    console.error('Invalid JSON:', e);
                    newValues = {};
                    oldValues = {};
                }

                try {
                    comments = JSON.parse(comments);
                    if (typeof comments === 'string') comments = JSON.parse(comments);
                } catch (e) {
                    console.error('Invalid JSON:', e);
                    comments = [];
                }

                try {
                    leadDetails = JSON.parse(leadDetails);
                    if (typeof leadDetails === 'string') leadDetails = JSON.parse(leadDetails);
                } catch (e) {
                    console.error('Invalid lead details JSON:', e);
                    leadDetails = null;
                }

                $tbody.empty();
                $modalFooter.find('.btn-approve, .btn-reject').remove();
                $commentSection.hide().empty();
                $modalTitle.html('<i class="fas fa-exchange-alt me-2"></i> Pending Changes');

                const openModal = (showTable = true) => {
                    if (showTable) {
                        $modalTable.removeClass('d-none');
                    } else {
                        $modalTable.addClass('d-none');
                    }

                    const modal = new bootstrap.Modal($('#viewChangesModal')[0]);
                    modal.show();
                };

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

                if (tableName === 'leads') {
                    const safeLeadName = leadDetails?.lead_name || 'Deleted Lead';
                    const safeCurrentOwner = leadDetails?.current_owner_name || 'Unassigned';
                    const safeRequestedOwner = leadDetails?.requested_owner_name || 'Unknown User';
                    const safeRequestType = leadDetails?.request_type || 'Lead Assignment / Ownership Request';
                    const safeLeadId = leadDetails?.lead_id || recordId;
                    let commentsHtml = '';

                    if (Array.isArray(comments) && comments.length > 0) {
                        comments.forEach((item) => {
                            if (item.comment) {
                                commentsHtml += `
                                <div class="border p-2 mb-2 rounded bg-light">
                                    <i class="fas fa-comment-dots text-primary"></i>
                                    <span>${item.comment}</span>
                                    <div class="text-muted small mt-1">
                                        ${item.created_at ? 'at ' + item.created_at : ''}
                                    </div>
                                    <div class="text-muted small">
                                        ${item.user_name ? 'by ' + item.user_name : 'by Unknown User'}
                                    </div>
                                </div>
                            `;
                            }
                        });
                    } else {
                        commentsHtml = `
                            <div class="alert alert-secondary mt-3">
                                <i class="fas fa-info-circle"></i> No comments found for this lead.
                            </div>
                        `;
                    }

                    $modalTitle.html('<i class="fas fa-user-check me-2"></i> Lead Assignment Request');
                    $commentSection.html(`
                        <div class="border rounded p-3 bg-light mb-3">
                            <h6 class="text-dark mb-3">Lead Assignment Request</h6>
                            <div class="mb-2"><strong>Lead:</strong> #${safeLeadId} - ${safeLeadName}</div>
                            <div class="mb-2"><strong>Request Type:</strong> ${safeRequestType}</div>
                            <div class="mb-2"><strong>Current Owner:</strong> ${safeCurrentOwner}</div>
                            <div class="mb-0"><strong>Requested Owner:</strong> ${safeRequestedOwner}</div>
                        </div>
                        <h6 class="mt-3 mb-2 text-secondary">
                            <i class="fas fa-comments me-1"></i> Related Comments
                        </h6>
                        ${commentsHtml}
                    `).show();

                    openModal(false);
                } else {
                    openModal(true);
                }
            });

        });
    </script>
@endsection

