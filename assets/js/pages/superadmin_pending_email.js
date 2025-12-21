$(document).ready(function() {

    // ==========================================================================
    // 1. INITIALIZE DATA TABLE
    // ==========================================================================
    var pendingEmailTable = $('#pendingEmailTable').DataTable({
        "processing": true,
        "serverSide": false, 
        "ajax": {
            url: API_ROOT + "/superadmin/pending_emails_action.php?action=fetch_pending",
            "type": "GET", 
            "dataSrc": "data",
            
            // ⭐ HOOK: Use the GLOBAL updateSyncStatus (from footer.php)
            "beforeSend": function() {
                if (typeof updateSyncStatus === "function") updateSyncStatus('loading');
            },
            "complete": function(xhr, status) {
                if (status === 'success') {
                    if (typeof updateSyncStatus === "function") updateSyncStatus('success');
                } else {
                    if (typeof updateSyncStatus === "function") updateSyncStatus('error');
                }
            },
            "error": function (xhr, error, code) {
                console.error("DataTables Error: ", error);
                if (typeof updateSyncStatus === "function") updateSyncStatus('error');
            }
        },
        "columns": [
            { 
                "data": null,
                "title": "User (Email/ID)",
                "render": function(data, type, row) {
                    return `<div>
                                <div class="fw-bold">${row.email}</div>
                                <div class="small text-muted">ID: ${row.employee_id}</div>
                            </div>`;
                }
            },
            { 
                "data": "reason", 
                "title": "Reason",
                "render": function(data) {
                    let text = data === 'DISABLED' ? 'System Disabled' : 'SMTP Failure';
                    let cls = data === 'DISABLED' ? 'bg-warning text-dark' : 'bg-danger';
                    return `<span class="badge ${cls}">${text}</span>`;
                }
            },
            { 
                "data": "attempted_at", 
                "title": "Attempted At",
                "render": function(data) {
                    return new Date(data).toLocaleString(); 
                }
            },
            { 
                "data": "id",
                "title": "Action",
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row) {
                    return `<button class="btn btn-sm btn-primary btn-resend shadow-sm" data-id="${data}" data-email="${row.email}">
                                <i class="fas fa-paper-plane me-1"></i> Re-send
                            </button>`;
                }
            }
        ],
        "order": [[2, "asc"]],
        "pageLength": 10,
        "language": {
            "emptyTable": "No pending emails found."
        }
    });

    // ==========================================================================
    // 2. MASTER REFRESHER HOOK
    // ==========================================================================
    // This function is called automatically by footer.php every 15 seconds
    // or when the user clicks the refresh icon.
    window.refreshPageContent = function(isManual) {
        
        // We simply reload the table. 
        // The 'beforeSend' hook above handles the "Syncing..." text.
        // The Footer handles the Spinning Icon.
        pendingEmailTable.ajax.reload(null, false);
    };

    // ==========================================================================
    // 3. RESEND BUTTON HANDLER
    // ==========================================================================
    $('#pendingEmailTable tbody').on('click', '.btn-resend', function () {
        var button = $(this);
        var pendingId = button.data('id');
        var userEmail = button.data('email');

        Swal.fire({
            title: 'Confirm Re-send?',
            text: `Are you sure you want to resend the reset code to ${userEmail}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4e73df',
            cancelButtonColor: '#858796',
            confirmButtonText: 'Yes, Re-send Code',
        }).then((result) => {
            if (result.isConfirmed) {
                // Manually trigger loading state for this specific action
                if (typeof updateSyncStatus === "function") updateSyncStatus('loading');
                
                $.ajax({
                    url: '../api/superadmin/pending_emails_action.php',
                    type: 'POST',
                    data: { action: 'resend_email', id: pendingId },
                    dataType: 'json',
                    beforeSend: function() {
                        button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending...');
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Sent!', response.message, 'success');
                            pendingEmailTable.ajax.reload(null, false); 
                            if (typeof updateSyncStatus === "function") updateSyncStatus('success');
                        } else if (response.status === 'info') {
                            Swal.fire('Check Settings', response.message, 'info');
                            pendingEmailTable.ajax.reload(null, false); 
                            if (typeof updateSyncStatus === "function") updateSyncStatus('success');
                        } else if (response.status === 'warning') {
                            Swal.fire('Expired', response.message, 'warning');
                            pendingEmailTable.ajax.reload(null, false); 
                            if (typeof updateSyncStatus === "function") updateSyncStatus('success');
                        } else {
                            Swal.fire('Failed', response.message, 'error');
                            if (typeof updateSyncStatus === "function") updateSyncStatus('error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'A network error occurred during resend.', 'error');
                        if (typeof updateSyncStatus === "function") updateSyncStatus('error');
                    },
                    complete: function() {
                        if(button.length) {
                            button.prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i> Re-send');
                        }
                    }
                });
            }
        });
    });
});