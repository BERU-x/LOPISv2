/**
 * Global Email Queue Controller
 * Manages monitoring and manual re-processing of system emails.
 */

$(document).ready(function() {

    // ==========================================================================
    // 1. INITIALIZE DATA TABLE
    // ==========================================================================
    var pendingEmailTable = $('#pendingEmailTable').DataTable({
        "processing": true,
        "serverSide": false, // Handled client-side for better search/filter of queue
        "ajax": {
            "url": API_ROOT + "/superadmin/pending_emails_action.php",
            "type": "POST", // Use POST to match API expectation
            "data": { action: 'fetch_pending' },
            "dataSrc": "data",
            "beforeSend": function() {
                if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');
            },
            "error": function (xhr, error, code) {
                if (error === 'abort') return;
                console.error("DataTables Error:", error);
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        "columns": [
            { 
                "data": null,
                "render": function(data, type, row) {
                    return `<div>
                                <div class="fw-bold text-dark">${row.recipient_email}</div>
                                <div class="text-xs text-muted">
                                    <i class="fas fa-id-badge me-1"></i>ID: ${row.employee_id || 'System'}
                                </div>
                            </div>`;
                }
            },
            { 
                "data": "subject", 
                "render": function(data, type, row) {
                    let typeLabel = row.email_type ? row.email_type.replace('_', ' ') : 'General';
                    return `<div class="d-flex flex-column">
                                <span class="fw-bold text-gray-800 mb-1" style="font-size:0.85rem;">${data}</span>
                                <div><span class="badge bg-light text-secondary border border-secondary-subtle rounded-pill px-2 text-uppercase" style="font-size:0.65rem;">${typeLabel}</span></div>
                            </div>`;
                }
            },
            { 
                "data": "error_message", 
                "render": function(data, type, row) {
                    // Logic: If is_sent is 2, it's a hard failure. If 0 and no error, it's just waiting.
                    if (row.is_sent == 0 && !data) {
                        return `<span class="badge bg-soft-warning text-warning border border-warning px-2"><i class="fas fa-clock me-1"></i>Waiting</span>`;
                    }
                    
                    let errorText = data || "Unknown Connection Failure";
                    return `<div class="text-danger x-small font-monospace p-1 bg-soft-danger rounded" 
                                 style="max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
                                 title="${errorText}">
                                <i class="fas fa-exclamation-triangle me-1"></i>${errorText}
                            </div>`;
                }
            },
            { 
                "data": "attempted_at", 
                "render": function(data) {
                    const dateObj = new Date(data);
                    return `<div class="text-xs">
                                <div class="fw-bold text-dark">${dateObj.toLocaleDateString()}</div>
                                <div class="text-muted">${dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                            </div>`;
                }
            },
            { 
                "data": "id",
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row) {
                    return `<button class="btn btn-sm btn-outline-primary btn-resend shadow-sm fw-bold" 
                                    data-id="${data}" 
                                    data-email="${row.recipient_email}">
                                <i class="fas fa-paper-plane me-1"></i> Force Send
                            </button>`;
                }
            }
        ],
        "order": [[3, "desc"]],
        "drawCallback": function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
        },
        "language": {
            "emptyTable": "<div class='py-5 text-center'><i class='fas fa-check-circle text-success fa-3x mb-3'></i><p class='text-gray-500 mb-0'>Queue is empty. No pending emails found.</p></div>",
            "processing": "<div class='spinner-border text-primary spinner-border-sm' role='status'></div> Loading queue..."
        }
    });

    // ==========================================================================
    // 2. MASTER REFRESHER HOOK
    // ==========================================================================
    window.refreshPageContent = function(isManual) {
        if (isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        pendingEmailTable.ajax.reload(null, false);
    };

    $('#btn-refresh-queue').on('click', function() {
        window.refreshPageContent(true);
    });

    // ==========================================================================
    // 3. RESEND / RETRY HANDLER
    // ==========================================================================
    $('#pendingEmailTable tbody').on('click', '.btn-resend', function () {
        const button = $(this);
        const pendingId = button.data('id');
        const userEmail = button.data('email');

        Swal.fire({
            title: 'Force Send?',
            text: `Attempt immediate delivery to ${userEmail}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send Now',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: API_ROOT + '/superadmin/pending_emails_action.php',
                    type: 'POST',
                    data: { action: 'resend_email', id: pendingId },
                    dataType: 'json'
                }).then(response => {
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error.statusText}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                const response = result.value;

                if (response.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Sent!', text: response.message, timer: 1500, showConfirmButton: false });
                    pendingEmailTable.ajax.reload(null, false);
                } else {
                    Swal.fire('Failed', response.message, 'error');
                }
            }
        });
    });
});