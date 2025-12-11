<?php
// --- 1. PAGE CONFIGURATION ---
$page_title = 'Admin Management - LOPISv2';
$current_page = 'admin_management';

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';

// NOTE: No PHP Database Query here anymore!
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Admin Management</h1>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#adminModal"
            onclick="resetForm()">
            <i class="fa-solid fa-plus fa-sm text-white-50 me-2"></i>Add New Admin
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">List of System Administrators</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="dataTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Emp ID</th>
                            <th class="border-0">Email Address</th>
                            <th class="border-0">Status</th>
                            <th class="border-0">Created At</th>
                            <th class="border-0">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
require 'template/footer.php';
include 'scripts/admin_management_scripts.php';
?>