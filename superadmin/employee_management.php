<?php
// superadmin/employee_management.php
$page_title = 'Employee Accounts - LOPISv2';
$current_page = 'employee_management';

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Employee Account Management</h1>
        <button class="btn btn-teal shadow-sm" onclick="openAddModal()">
            <i class="fa-solid fa-plus fa-sm text-white-50 me-2"></i>Add New Account
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-label">
                <i class="fas fa-users-cog me-2"></i>List of Employee Accounts
            </h6>
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
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="addEmployeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addEmployeeForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-gray-800">
                        <i class="fas fa-user-plus me-2 text-gray-400"></i>Add New Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Select Employee</label>
                        <select class="form-select" id="add_employee_id" name="employee_id" required>
                            <option value="">Loading available employees...</option>
                        </select>
                        <small class="text-muted">Only shows employees without an account.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-gray-500"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="add_email" name="email" required placeholder="employee@lendell.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-gray-500"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="add_password" name="password" value="losi123">
                        </div>
                        <small class="text-xs text-info mt-1 d-block">
                            <i class="fas fa-info-circle me-1"></i> Default: <strong>losi123</strong>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Initial Status</label>
                        <select class="form-select" id="add_status" name="status">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm px-4">
                        <i class="fas fa-save me-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editEmployeeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editEmployeeForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-gray-800">
                        <i class="fas fa-user-edit me-2 text-gray-400"></i>Edit Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Employee ID</label>
                        <input type="text" class="form-control bg-light" id="edit_employee_id" name="employee_id" readonly>
                        <small class="text-muted">Cannot be changed once created.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-gray-500"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Reset Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-gray-500"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="Leave blank to keep current">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-gray-700">Account Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white btn-sm shadow-sm px-4">
                        <i class="fas fa-check-circle me-1"></i> Update Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/superadmin_employee_management.js"></script>