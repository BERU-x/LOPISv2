<?php
// superadmin/pending_emails.php
$page_title = 'Pending Emails - LOPISv2';
$current_page = 'pending_emails'; 

// Include your template files (adjust paths as necessary)
require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
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

<?php require '../template/footer.php'; ?>

<script src="../assets/js/pages/superadmin_pending_email.js"></script>