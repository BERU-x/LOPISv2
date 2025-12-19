<?php
// superadmin/employee_management.php
// --- 1. PAGE CONFIGURATION ---
$page_title = 'Employee Accounts - LOPISv2';
$current_page = 'employee_management';

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Employee Account Management</h1>
        <button class="btn btn-teal shadow-sm" onclick="openModal()">
            <i class="fa-solid fa-plus fa-sm text-white-50 me-2"></i>Add New Account
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-label">List of Employee Accounts</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="employeeTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee ID</th>
                            <th class="border-0">Email Address</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0">Created At</th>
                            <th class="border-0 text-center" width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="employeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="employeeForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">Add New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="user_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Employee ID</label>
                        <input type="text" class="form-control" id="employee_id" name="employee_id" required placeholder="e.g. 101" maxlength="3">
                        <small class="text-muted">Must match an existing profile in HR.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="employee@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
                        <small class="text-muted d-none" id="password_hint">Only fill this if you want to change the password.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/superadmin_employee_management.js"></script>