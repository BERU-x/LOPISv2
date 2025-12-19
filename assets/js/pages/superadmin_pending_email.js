$(document).ready(function() {

    // ==========================================================================
    // 1. UI HELPER: UPDATE SYNC STATUS (Topbar)
    // ==========================================================================
    function updateSyncStatus(state) {
        const $dot = $('.live-dot');
        const $text = $('#last-updated-time');
        const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    
        $dot.removeClass('text-success text-warning text-danger');
    
        if (state === 'loading') {
            $text.text('Syncing...');
            $dot.addClass('text-warning'); 
        } 
        else if (state === 'success') {
            $text.text(`Synced: ${time}`);
            $dot.addClass('text-success'); 
        } 
        else {
            $text.text(`Failed: ${time}`);
            $dot.addClass('text-danger');  
        }
    }

    // ==========================================================================
    // 2. INITIALIZE DATA TABLE
    // ==========================================================================
    var pendingEmailTable = $('#pendingEmailTable').DataTable({
        "processing": true,
        "serverSide": false, 
        "ajax": {
            "url": "../api/superadmin/pending_emails_action.php?action=fetch_pending",
            "type": "GET", 
            "dataSrc": "data",
            // ⭐ HOOK: Update Sync Status on every request start/end
            "beforeSend": function() {
                updateSyncStatus('loading');
            },
            "complete": function(xhr, status) {
                if (status === 'success') {
                    updateSyncStatus('success');
                } else {
                    updateSyncStatus('error');
                }
            },
            "error": function (xhr, error, code) {
                console.error("DataTables Error: ", error);
                updateSyncStatus('error');
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
    // 3. MASTER REFRESHER HOOK
    // ==========================================================================
    window.refreshPageContent = function(isManual) {
        if (isManual) {
            $('#refreshIcon').addClass('fa-spin');
        }

        // We don't need to call updateSyncStatus here manually because
        // pendingEmailTable.ajax.reload() triggers the 'beforeSend' hook above automatically.
        pendingEmailTable.ajax.reload(function() {
            if (isManual) {
                $('#refreshIcon').removeClass('fa-spin');
            }
        }, false);
    };

    // ==========================================================================
    // 4. RESEND BUTTON HANDLER
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
                updateSyncStatus('loading');
                
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
                            updateSyncStatus('success');
                        } else if (response.status === 'info') {
                            Swal.fire('Check Settings', response.message, 'info');
                            pendingEmailTable.ajax.reload(null, false); 
                            updateSyncStatus('success');
                        } else if (response.status === 'warning') {
                            Swal.fire('Expired', response.message, 'warning');
                            pendingEmailTable.ajax.reload(null, false); 
                            updateSyncStatus('success');
                        } else {
                            Swal.fire('Failed', response.message, 'error');
                            updateSyncStatus('error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'A network error occurred during resend.', 'error');
                        updateSyncStatus('error');
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