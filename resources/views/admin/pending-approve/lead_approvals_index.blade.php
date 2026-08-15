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
                        <table id="leadApprovalTable" class="table table-bordered table-hover">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th style="background-color: black !important">Request ID</th>
                                    <th style="background-color: black !important">Lead</th>
                                    <th style="background-color: black !important">Current Assigned Users</th>
                                    <th style="background-color: black !important">Requested User</th>
                                    <th style="background-color: black !important">Submitted At</th>
                                    <th style="background-color: black !important">Status</th>
                                    <th style="background-color: black !important">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingUpdates as $update)
                                    <tr>
                                        <td>{{ $update->id }}</td>
                                        <td>#{{ $update->record_id }} - {{ $update->lead_name }}</td>
                                        <td>{{ $update->current_assigned_users_label }}</td>
                                        <td>{{ $update->requested_user_name }}</td>
                                        <td>{{ optional($update->created_at)->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <span class="badge badge-warning text-dark">{{ ucfirst($update->status) }}</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-sm approve-btn"
                                                data-url="{{ route('approvals.leads.approve', $update->id) }}">
                                                Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm reject-btn"
                                                data-url="{{ route('approvals.leads.reject', $update->id) }}">
                                                Reject
                                            </button>
                                            <button class="btn btn-sm btn-outline-info btn-view-details"
                                                data-lead='{{ json_encode([
                                                    "lead_id" => $update->record_id,
                                                    "lead_name" => $update->lead_name,
                                                    "current_assigned_users" => $update->current_assigned_users,
                                                    "requested_user_name" => $update->requested_user_name,
                                                    "request_type" => $update->request_type,
                                                ]) }}'
                                                data-comments='@json($update->comments_for_modal)'>
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No lead assignment requests found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="leadApprovalModal" tabindex="-1" aria-labelledby="leadApprovalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="leadApprovalModalLabel">
                        <i class="fas fa-user-check me-2"></i> Lead Assignment Request
                    </h5>
                </div>
                <div class="modal-body" id="leadApprovalModalBody"></div>
                <div class="modal-footer"></div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function() {
            $('#leadApprovalTable').DataTable({
                responsive: true,
                autoWidth: false
            });

            const csrfToken = '{{ csrf_token() }}';

            function parseJsonAttribute(rawValue, fallbackValue) {
                const decoded = $('<textarea/>').html(rawValue || '').text();

                try {
                    const parsed = JSON.parse(decoded);
                    return typeof parsed === 'string' ? JSON.parse(parsed) : parsed;
                } catch (error) {
                    console.error('Invalid JSON payload:', error);
                    return fallbackValue;
                }
            }

            function appendLabeledText($container, label, value) {
                const $row = $('<div class="mb-2"></div>');
                $row.append($('<strong></strong>').text(label + ': '));
                $row.append(document.createTextNode(value || ''));
                $container.append($row);
            }

            function buildCommentCard(comment) {
                const $card = $('<div class="border p-2 mb-2 rounded bg-light"></div>');
                const $icon = $('<i class="fas fa-comment-dots text-primary me-2"></i>');
                const $commentText = $('<span></span>').text(comment.comment || '');
                const $date = $('<div class="text-muted small mt-1"></div>').text(comment.created_at || '');
                const $author = $('<div class="text-muted small"></div>').text(`by ${comment.user_name || 'Unknown User'}`);

                $card.append($icon, $commentText, $date, $author);

                return $card;
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
                showActionPrompt('Approve Lead Request?', 'Approve this lead assignment request?', 'btn btn-success me-2',
                    'Yes, Approve', $(this).data('url'));
            });

            $(document).on('click', '.reject-btn', function(e) {
                e.preventDefault();
                showActionPrompt('Reject Lead Request?', 'Reject this lead assignment request?', 'btn btn-danger me-2',
                    'Yes, Reject', $(this).data('url'));
            });

            $(document).on('click', '.btn-view-details', function() {
                const leadDetails = parseJsonAttribute($(this).attr('data-lead'), {});
                const comments = parseJsonAttribute($(this).attr('data-comments'), []);
                const $body = $('#leadApprovalModalBody');
                const $summary = $('<div class="border rounded p-3 bg-light mb-3"></div>');
                const $commentsSection = $('<div></div>');

                $body.empty();

                $summary.append($('<h6 class="text-dark mb-3"></h6>').text('Lead Assignment Request'));
                appendLabeledText($summary, 'Lead',
                    `#${leadDetails.lead_id || ''} - ${leadDetails.lead_name || 'Deleted Lead'}`);
                appendLabeledText($summary, 'Request Type', leadDetails.request_type ||
                    'Lead Assignment / Ownership Request');
                appendLabeledText($summary, 'Current Assigned Users', Array.isArray(leadDetails.current_assigned_users) ?
                    leadDetails.current_assigned_users.join(', ') : 'Unassigned');
                appendLabeledText($summary, 'Requested User', leadDetails.requested_user_name || 'Unknown User');

                $commentsSection.append($('<h6 class="mt-3 mb-2 text-secondary"></h6>').text('Related Comments'));

                if (Array.isArray(comments) && comments.length > 0) {
                    comments.forEach((comment) => {
                        $commentsSection.append(buildCommentCard(comment));
                    });
                } else {
                    $commentsSection.append(
                        $('<div class="alert alert-secondary mt-3"></div>').text('No comments found for this lead.')
                    );
                }

                $body.append($summary, $commentsSection);

                const modal = new bootstrap.Modal($('#leadApprovalModal')[0]);
                modal.show();
            });
        });
    </script>
@endsection
