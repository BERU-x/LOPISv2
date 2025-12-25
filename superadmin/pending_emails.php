<?php
// superadmin/pending_emails.php
// PAGE: Global Email Queue Monitor
// DESCRIPTION: Displays list of emails waiting to be sent or failed.
//              Table data is loaded via AJAX by assets/js/pages/superadmin_email_queue.js

$page_title = 'Global Email Queue - LOPISv2';
$current_page = 'pending_emails'; 

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Global Email Queue</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 border-left-warning d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-label">
                <i class="fas fa-envelope-open-text me-2"></i>Pending & Failed Communications
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="pendingEmailTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Recipient Info</th>
                            <th class="border-0">Subject & Type</th>
                            <th class="border-0">Status / Error</th>
                            <th class="border-0">Queued At</th>
                            <th class="border-0 text-center" style="width: 120px;">Action</th>
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

<script src="../assets/js/pages/superadmin_email_queue.js"></script>