<?php
// superadmin/pending_emails.php
$page_title = 'Pending Emails - LOPISv2';
$current_page = 'pending_emails'; 

// Include your template files (adjust paths as necessary)
require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Pending Password Reset Emails</h1>
    </div>
    
    <div class="alert alert-info shadow-sm small">
        <i class="fas fa-exclamation-circle me-2"></i>
        This list contains password reset emails that failed to send due to SMTP errors or because the system's email notifications were disabled at the time of the request. The token itself may still be valid if not expired.
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 border-left-warning">
            <h6 class="m-0 font-weight-bold text-label">
                <i class="fas fa-paper-plane me-2"></i>Emails Requiring Manual Intervention
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="pendingEmailTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>User (Email/ID)</th>
                            <th>Reason</th>
                            <th>Attempted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>

<script src="../assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/vendor/datatables/dataTables.bootstrap4.min.js"></script>
<link href="../assets/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet"> 
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

<script>
$(document).ready(function() {
    
    // --- 1. INITIALIZE DATA TABLES ---
    var pendingEmailTable = $('#pendingEmailTable').DataTable({
        "processing": true,
        "serverSide": false, // Client-side for simplicity, fetching all pending records
        "ajax": {
            "url": "api/pending_emails_action.php?action=fetch_pending",
            "type": "GET", 
            "dataSrc": "data", 
            "error": function (xhr, error, code) {
                console.error("DataTables Error: ", error);
                // Optional: Show error
            }
        },
        "columns": [
            { 
                "data": null,
                "title": "User (Email/ID)",
                "render": function(data, type, row) {
                    return row.email + ' (ID: ' + row.employee_id + ')';
                }
            },
            { 
                "data": "reason", 
                "title": "Reason",
                "render": function(data) {
                    let text = data === 'DISABLED' ? 'System Disabled' : 'SMTP Failure';
                    let cls = data === 'DISABLED' ? 'bg-warning' : 'bg-danger';
                    return `<span class="badge ${cls} text-white">${text}</span>`;
                }
            },
            { "data": "attempted_at", "title": "Attempted At" },
            { 
                "data": "id",
                "title": "Action",
                "orderable": false,
                "render": function(data, type, row) {
                    return `<button class="btn btn-sm btn-primary btn-resend" data-id="${data}" data-email="${row.email}"><i class="fas fa-paper-plane"></i> Re-send</button>`;
                }
            }
        ],
        "order": [[2, "asc"]], // Oldest first
        "pageLength": 10
    });
    
    // --- 2. RESEND BUTTON HANDLER ---
    $('#pendingEmailTable tbody').on('click', '.btn-resend', function () {
        var button = $(this);
        var pendingId = button.data('id');
        var userEmail = button.data('email');

        Swal.fire({
            title: 'Confirm Re-send?',
            text: "Are you sure you want to resend the reset link to " + userEmail + "?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Re-send',
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/pending_emails_action.php',
                    type: 'POST',
                    data: { action: 'resend_email', id: pendingId },
                    dataType: 'json',
                    beforeSend: function() {
                        button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending...');
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Sent!', response.message, 'success');
                            pendingEmailTable.ajax.reload(); // Refresh table
                        } else if (response.status === 'info') {
                            Swal.fire('Check Settings', response.message, 'info');
                            pendingEmailTable.ajax.reload(); // Refresh table
                        } else {
                            Swal.fire('Failed', response.message, 'error');
                            pendingEmailTable.ajax.reload(); // Refresh table (in case timestamp updated)
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'A network error occurred during resend.', 'error');
                    },
                    complete: function() {
                        button.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Re-send');
                    }
                });
            }
        });
    });
});
</script>