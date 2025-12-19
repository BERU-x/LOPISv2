<?php
// superadmin/dashboard.php
session_start();

// --- SECURITY GATE: SUPER ADMIN ONLY (Role 0) ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['usertype'] != 0) {
    header("Location: ../index.php");
    exit;
}

$page_title = 'Super Admin Dashboard - LOPISv2';
$current_page = 'dashboard';

// --- INCLUDE GLOBAL TEMPLATES ---
require_once '../template/header.php';
require_once '../template/sidebar.php';
require_once '../template/topbar.php';
?>

<style>
/* Custom CSS for the dynamically generated Chart.js Legend */
#role-chart-legend {
    text-align: center; 
}
#role-chart-legend ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    justify-content: center;
    flex-wrap: wrap; 
}
#role-chart-legend li {
    cursor: default; 
    display: inline-flex; 
    align-items: center;
    margin: 0 10px;
    font-size: 0.8rem;
    color: #858796;
    margin-top: 5px; 
}
#role-chart-legend span {
    display: inline-block;
    width: 12px; 
    height: 12px;
    border-radius: 50%;
    margin-right: 6px;
}
</style>

<div class="container-fluid">
    
    <div class="welcome-banner shadow mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 font-weight-bold mb-1">Dashboard Overview</h1>
                <p class="text-white mb-0 opacity-75" id="status-message"></p>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fa-solid fa-user-shield"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">System Admins</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800" id="val-total-admins">...</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <a href="admin_management.php" class="text-decoration-none text-muted ms-2">See Details &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fa-solid fa-users"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">Total Employees</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800" id="val-total-employees">...</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <a href="employee_management.php" class="text-decoration-none text-muted ms-2">See Details &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fa-solid fa-user-clock"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">Pending Approvals</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800" id="val-pending-users">...</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <a href="#" class="text-decoration-none text-muted ms-2">See Details &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fa-solid fa-chart-line"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">Active Today</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800" id="val-active-today">...</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <a href="#" class="text-decoration-none text-muted ms-2">See Details &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-gray-800">User Growth History (6 Months)</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="growthHistoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h6 class="m-0 font-weight-bold text-gray-800">User Roles</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 250px;">
                        <canvas id="roleDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small text-muted" id="role-chart-legend">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-800">Newest Registrations</h6>
                    <a href="employee_management.php" class="text-decoration-none text-sm text-muted">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body pt-0 p-0">
                    <div class="list-group list-group-flush" id="list-recent-users">
                         <div class="text-center py-5 text-muted"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-800">Recent Audit Activity</h6>
                    <a href="audit_logs.php" class="text-decoration-none text-sm text-muted">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body pt-0 p-0">
                    <div class="list-group list-group-flush" id="list-recent-logs">
                          <div class="text-center py-5 text-muted"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// --- INCLUDE FOOTER ---
require_once '../template/footer.php'; 
?>

<script src="../assets/js/pages/superadmin_dashboard.js"></script>