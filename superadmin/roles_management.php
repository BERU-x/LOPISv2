<?php
// superadmin/roles_management.php
$page_title = 'Role & Permissions - LOPISv2';
$current_page = 'roles_management';

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Role & Permission Management</h1>
        <span class="badge bg-secondary">
            <i class="fas fa-info-circle me-1"></i> Changes save automatically
        </span>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-label">System Access Matrix</h6>
        </div>
        <div class="card-body">
            
            <form id="permissionForm">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center text-uppercase small fw-bold">
                            <tr>
                                <th class="text-start" width="50%">Feature / Capability</th>
                                <th width="25%">
                                    <span class="badge bg-secondary fs-6">Admin</span>
                                </th>
                                <th width="25%">
                                    <span class="badge bg-secondary fs-6">Employee</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="matrix-body">
                            <tr><td colspan="3" class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x text-gray-300"></i></td></tr>
                        </tbody>
                    </table>
                </div>
            </form>

        </div>
    </div>
</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/superadmin_roles_management.js"></script>