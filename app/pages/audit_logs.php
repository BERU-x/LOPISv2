<?php
// C:\xampp\htdocs\LOPISv2\app\pages\audit_logs.php
$page_title = 'System Audit Logs - LOPISv2';
$current_page = 'audit_logs';

// Include the Global Model for authentication and helpers
require_once __DIR__ . '/../models/global_app_model.php'; 

// --- SECURITY CHECK: Only allow Super Admin (0) and Admin (1) to view logs ---
require_authorization([0, 1], '../index.php');

// --- CORRECTED TEMPLATE PATHS ---
require __DIR__ . '/../../template/header.php';
require __DIR__ . '/../../template/sidebar.php';
require __DIR__ . '/../../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">System Audit Trails</h1>
        <button class="btn btn-danger btn-sm shadow-sm" onclick="confirmClearLogs()">
            <i class="fas fa-trash-alt fa-sm text-white-50 me-2"></i>Clear Old Logs
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-label">Activity History</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle" id="logsTable" width="100%">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th style="min-width: 150px;">Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th class="text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody class="small"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-gray-800" id="modalTitle">Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="bg-light" width="30%">Action</th>
                            <td id="view_action" class="fw-bold"></td>
                        </tr>
                        <tr>
                            <th class="bg-light">User Agent</th>
                            <td id="view_agent" class="small text-muted text-break"></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Full Details</th>
                            <td class="bg-light text-dark p-3 rounded" style="font-family: monospace;">
                                <div id="view_details" class="text-break"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../template/footer.php'; // <-- CORRECTED PATH ?>

<script src="../scripts/login_scripts.js"></script>