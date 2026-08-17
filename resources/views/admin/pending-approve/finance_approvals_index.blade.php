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
                        <table id="financeApprovalTable" class="table table-bordered table-hover">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th style="background-color: black !important">Request ID</th>
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
                                            <span class="badge badge-warning text-dark">{{ ucfirst($update->status) }}</span>
                                        </td>
                                        <td>{{ optional($update->created_at)->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <button class="btn btn-success btn-sm approve-btn"
                                                data-url="{{ route('approvals.finance.approve', $update->id) }}">
                                                Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm reject-btn"
                                                data-url="{{ route('approvals.finance.reject', $update->id) }}">
                                                Reject
                                            </button>
                                            <button class="btn btn-sm btn-outline-info btn-view-details"
                                                data-payload-id="finance-approval-payload-{{ $update->id }}">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <script type="application/json" id="finance-approval-payload-{{ $update->id }}">
                                                {{ json_encode([
                                                    'old' => $update->old_values,
                                                    'new' => $update->new_values,
                                                    'submitted_name' => $update->submittedBy->name ?? 'Unknown User',
                                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}
                                            </script>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No finance change approvals found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="financeApprovalModal" tabindex="-1" aria-labelledby="financeApprovalModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="financeApprovalModalLabel">
                        <i class="fas fa-exchange-alt me-2"></i> Finance Change Details
                    </h5>
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
                        <tbody id="financeChangesTableBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer"></div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function() {
            $('#financeApprovalTable').DataTable({
                responsive: true,
                autoWidth: false
            });

            const csrfToken = '{{ csrf_token() }}';

            function parsePayloadScript(scriptId, fallbackValue) {
                const script = document.getElementById(scriptId);
                if (!script) {
                    return fallbackValue;
                }

                try {
                    return JSON.parse(script.textContent || '');
                } catch (error) {
                    console.error('Invalid JSON payload:', error);
                    return fallbackValue;
                }
            }

            function normalizeDisplayValue(value) {
                if (value === null || value === undefined || value === '') {
                    return 'N/A';
                }

                if (typeof value === 'object') {
                    return JSON.stringify(value);
                }

                return String(value);
            }

            function showActionPrompt(title, text, confirmClass, confirmText, url) {
                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: confirmClass,
                        cancelButton: 'btn btn-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _token: csrfToken
                        },
                        success: function() {
                            window.location.reload();
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Something went wrong.'
                            });
                        }
                    });
                });
            }

            $(document).on('click', '.approve-btn', function(e) {
                e.preventDefault();
                showActionPrompt('Approve Finance Change?', 'Approve this finance change request?', 'btn btn-success me-2',
                    'Yes, Approve', $(this).data('url'));
            });

            $(document).on('click', '.reject-btn', function(e) {
                e.preventDefault();
                showActionPrompt('Reject Finance Change?', 'Reject this finance change request?', 'btn btn-danger me-2',
                    'Yes, Reject', $(this).data('url'));
            });

            $(document).on('click', '.btn-view-details', function() {
                const payload = parsePayloadScript($(this).data('payload-id'), {});
                const newValues = payload.new || {};
                const oldValues = payload.old || {};
                const submittedName = payload.submitted_name || 'Unknown User';
                const $tbody = $('#financeChangesTableBody');

                $tbody.empty();

                if (Object.keys(newValues).length === 1 && String(newValues.is_active) === '0') {
                    const $row = $('<tr></tr>');
                    const $cell = $('<td colspan="3" class="text-center text-danger fw-bold"></td>');
                    $cell.text(`User ${submittedName} has requested to delete the record.`);

                    if (newValues.delete_reason) {
                        $cell.append($('<div class="mt-2"></div>').text(`Reason: ${newValues.delete_reason}`));
                    }

                    $row.append($cell);
                    $tbody.append($row);
                } else {
                    $.each(newValues, function(key, newVal) {
                        const $row = $('<tr></tr>');
                        $row.append($('<td></td>').text(key));
                        $row.append($('<td></td>').text(normalizeDisplayValue(oldValues[key])));
                        $row.append($('<td class="text-primary fw-semibold"></td>').text(normalizeDisplayValue(newVal)));
                        $tbody.append($row);
                    });
                }

                const modal = new bootstrap.Modal($('#financeApprovalModal')[0]);
                modal.show();
            });
        });
    </script>
@endsection
