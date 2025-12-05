<?php
// dashboard.php
$page_title = 'LOPISv2 - Admin Dashboard';
$current_page = 'dashboard';
require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="welcome-banner shadow mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 font-weight-bold mb-1">Dashboard Overview</h1>
                <p class="mb-0 opacity-75">Welcome back! Here's what's happening today.</p>
            </div>
            <button onclick="loadDashboardData()" class="btn btn-light text-teal font-weight-bold shadow-sm border-0">
                <i class="fas fa-sync-alt me-2"></i> Refresh Data
            </button>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 border-left-teal shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fas fa-users"></i></div>
                        <div>
                            <div class="text-label">Active Employees</div>
                            <div class="text-value h4 font-weight-bold mb-0" id="val-active-employees">
                                <i class="fas fa-spinner fa-spin text-muted text-xs"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <span class="text-teal font-weight-bold">
                            <i class="fas fa-plus"></i> <span id="val-new-hires">0</span>
                        </span> new this month
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fas fa-hand-holding-usd"></i></div>
                        <div>
                            <div class="text-label">Pending Cash Adv</div>
                            <div class="text-value h4 font-weight-bold mb-0" id="val-pending-ca">
                                <i class="fas fa-spinner fa-spin text-muted text-xs"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs" id="status-pending-ca">
                        </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fas fa-calendar-times"></i></div>
                        <div>
                            <div class="text-label">Pending Leaves</div>
                            <div class="text-value h4 font-weight-bold mb-0" id="val-pending-leaves">
                                <i class="fas fa-spinner fa-spin text-muted text-xs"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <a href="leave_management.php" class="text-decoration-none text-muted">View details &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-info me-3"><i class="fas fa-tasks"></i></div>
                        <div>
                            <div class="text-label">Today Attendance</div>
                            <div class="text-value h4 font-weight-bold mb-0">
                                <span id="val-attendance-today"><i class="fas fa-spinner fa-spin text-muted text-xs"></i></span>
                                <span class="text-muted" style="font-size: 0.6em;">
                                    / <span id="val-attendance-total">0</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <a href="today_attendance.php" class="text-decoration-none text-muted">View details &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card h-100 shadow">
                <div class="card-header bg-transparent border-0">
                    <h6 class="m-0 font-weight-bold text-label">Payroll History (Last 6 Months)</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="payrollHistoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card h-100 shadow">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h6 class="m-0 font-weight-bold text-label">Employees by Dept</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 250px;">
                        <canvas id="deptDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small text-muted">Hover over sections to see counts</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card h-100 shadow">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-label">Upcoming Approved Leaves</h6>
                    <a href="leave_management.php" class="text-decoration-none text-sm text-muted">See All &rarr;</a>
                </div>
                <div class="card-body pt-0" id="list-upcoming-leaves">
                    <div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card h-100 shadow">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-label">Upcoming Company Holidays</h6>
                    <a href="holidays.php" class="text-decoration-none text-sm text-muted">Manage &rarr;</a>
                </div>
                <div class="card-body pt-0" id="list-upcoming-holidays">
                     <div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/dashboard_scripts.php'; ?>