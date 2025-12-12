<?php
// superadmin/roles_management.php
// --- PAGE CONFIGURATION ---
$page_title = 'Role & Permissions - LOPISv2';
$current_page = 'roles_management';

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';

// 1. Fetch Features
$stmt = $pdo->query("SELECT * FROM tbl_features ORDER BY category DESC, description ASC");
$features = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Active Permissions
$stmtPerm = $pdo->query("SELECT * FROM tbl_role_permissions");
$active_permissions = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);

function isPermitted($usertype, $feature_id, $permissions) {
    foreach ($permissions as $p) {
        if ($p['usertype'] == $usertype && $p['feature_id'] == $feature_id) {
            return 'checked';
        }
    }
    return '';
}
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
            <h6 class="m-0 font-weight-bold text-primary">System Access Matrix</h6>
        </div>
        <div class="card-body">
            
            <form id="permissionForm">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center text-uppercase small fw-bold">
                            <tr>
                                <th class="text-start" width="50%">Feature / Capability</th>
                                <th width="25%">
                                    <span class="badge bg-primary fs-6">Admin (Level 1)</span>
                                </th>
                                <th width="25%">
                                    <span class="badge bg-info fs-6">Employee (Level 2)</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_cat = '';
                            foreach ($features as $f): 
                                if ($f['category'] != $current_cat): 
                                    $current_cat = $f['category'];
                            ?>
                                <tr class="table-secondary">
                                    <td colspan="3" class="fw-bold text-gray-800 small text-uppercase px-3">
                                        <i class="fas fa-layer-group me-2"></i><?= $current_cat ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <tr>
                                <td class="px-3">
                                    <div class="fw-bold text-dark"><?= $f['description'] ?></div>
                                    <small class="text-muted font-monospace"><?= $f['feature_code'] ?></small>
                                </td>
                                
                                <td class="text-center bg-light">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input perm-toggle" type="checkbox" 
                                            data-usertype="1" 
                                            data-feature="<?= $f['id'] ?>"
                                            <?= isPermitted(1, $f['id'], $active_permissions) ?>>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input perm-toggle" type="checkbox" 
                                            data-usertype="2" 
                                            data-feature="<?= $f['id'] ?>"
                                            <?= isPermitted(2, $f['id'], $active_permissions) ?>>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </form>

        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/roles_management_scripts.php'; ?>