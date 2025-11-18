<?php
// Set the page title and include the header
$page_title = 'Admin Dashboard - LOPISv2';
$current_page = 'dashboard';

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">

    <!-- 1. WELCOME BANNER -->
    <div class="welcome-banner shadow">
        <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
            <div>
                <h1 class="h3 font-weight-bold mb-1">Dashboard Overview</h1>
                <p class="mb-0 opacity-75">Manage your employees, payroll, and leave requests.</p>
            </div>
            <a href="reports.php" class="btn btn-light text-teal font-weight-bold shadow-sm border-0">
                <i class="fas fa-download me-2"></i> Generate Report
            </a>
        </div>
    </div>

    <!-- 2. KPI CARDS (Modern Style) -->
    <div class="row mb-4">

        <!-- Active Employees -->
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div class="text-label">Active Employees</div>
                            <div class="text-value">145</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <span class="text-success font-weight-bold"><i class="fas fa-plus"></i> 5</span> new this month
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Status -->
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-success me-3">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div>
                            <div class="text-label">Payroll Status</div>
                            <div class="h5 font-weight-bold mb-0 text-gray-800">Pending</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <span class="text-warning font-weight-bold">Action Required</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Leave -->
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-warning me-3">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <div class="text-label">Leave Requests</div>
                            <div class="text-value">8</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Waiting for approval
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-info me-3">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <div class="text-label">Upcoming Tasks</div>
                            <div class="text-value">3</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Due within 7 days
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- End KPI Row -->

    <!-- 3. CHARTS ROW -->
    <div class="row mb-4">
        
        <!-- Chart 1: Payroll History (Area Chart) -->
        <div class="col-xl-8 col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-800">Payroll History</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="payrollHistoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Department Distribution (Doughnut) -->
        <div class="col-xl-4 col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h6 class="m-0 font-weight-bold text-gray-800">Employees by Dept</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 250px;">
                        <canvas id="deptDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="me-2">
                            <i class="fas fa-circle text-teal"></i> IT
                        </span>
                        <span class="me-2">
                            <i class="fas fa-circle text-primary"></i> HR
                        </span>
                        <span class="me-2">
                            <i class="fas fa-circle text-info"></i> Ops
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- End Charts Row -->

    <!-- 4. QUICK ACTIONS & NOTIFICATIONS ROW -->
    <div class="row">

        <!-- Quick Actions Grid -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h6 class="m-0 font-weight-bold text-gray-800">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        
                        <!-- Add Employee -->
                        <div class="col-md-6">
                            <a href="#" class="card bg-soft-primary border-0 h-100 text-decoration-none transition-hover" 
                            data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                <div class="card-body d-flex align-items-center p-4">
                                    <div class="icon-box bg-white text-primary shadow-sm me-3">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold text-primary mb-1">Add New Employee</h6>
                                        <small class="text-muted">Create active profile</small>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Process Payroll -->
                        <div class="col-md-6">
                            <a href="payroll.php" class="card bg-soft-success border-0 h-100 text-decoration-none transition-hover">
                                <div class="card-body d-flex align-items-center p-4">
                                    <div class="icon-box bg-white text-success shadow-sm me-3">
                                        <i class="fas fa-calculator"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold text-success mb-1">Process Payroll</h6>
                                        <small class="text-muted">Run monthly payroll</small>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Approve Leave -->
                        <div class="col-md-6">
                            <a href="leave_management.php" class="card bg-soft-warning border-0 h-100 text-decoration-none transition-hover">
                                <div class="card-body d-flex align-items-center p-4">
                                    <div class="icon-box bg-white text-warning shadow-sm me-3">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold text-warning mb-1">Approve Leave</h6>
                                        <small class="text-muted">8 pending requests</small>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Generate Report -->
                        <div class="col-md-6">
                            <a href="reports.php" class="card bg-soft-info border-0 h-100 text-decoration-none transition-hover">
                                <div class="card-body d-flex align-items-center p-4">
                                    <div class="icon-box bg-white text-info shadow-sm me-3">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold text-info mb-1">Reports Center</h6>
                                        <small class="text-muted">View analytics</small>
                                    </div>
                                </div>
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications Panel -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0">
                    <h6 class="m-0 font-weight-bold text-gray-800">Notifications</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        
                        <!-- Notification 1 -->
                        <a href="#" class="list-group-item list-group-item-action p-3 border-0 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-primary me-3" style="width:40px; height:40px; font-size:1rem;">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <strong class="text-dark">Payroll run complete</strong>
                                    </div>
                                    <small class="text-muted">Nov 14, 10:30 AM</small>
                                </div>
                            </div>
                        </a>

                        <!-- Notification 2 -->
                        <a href="#" class="list-group-item list-group-item-action p-3 border-0 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-success me-3" style="width:40px; height:40px; font-size:1rem;">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <strong class="text-dark">New Employee Added</strong>
                                    </div>
                                    <small class="text-muted">Jane Doe added by HR</small>
                                </div>
                            </div>
                        </a>

                        <!-- Notification 3 -->
                        <a href="#" class="list-group-item list-group-item-action p-3 border-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-danger me-3" style="width:40px; height:40px; font-size:1rem;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <strong class="text-dark">Action Required</strong>
                                    </div>
                                    <small class="text-muted">Tax calculation error found</small>
                                </div>
                            </div>
                        </a>

                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="#" class="btn btn-sm btn-light text-teal font-weight-bold w-100">View All Alerts</a>
                </div>
            </div>
        </div>

    </div>

</div>
<!-- /.container-fluid -->
<?php require 'functions/add_employee.php'; ?>
<!-- JS for Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- 1. PAYROLL HISTORY CHART (Teal Gradient) ---
    var ctxPayroll = document.getElementById("payrollHistoryChart").getContext('2d');
    
    var gradientPayroll = ctxPayroll.createLinearGradient(0, 0, 0, 400);
    gradientPayroll.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); // Start Teal
    gradientPayroll.addColorStop(1, 'rgba(255, 255, 255, 0.0)'); // End Transparent

    new Chart(ctxPayroll, {
        type: 'line',
        data: {
            labels: ["Jun", "Jul", "Aug", "Sep", "Oct", "Nov"], // Dummy Months
            datasets: [{
                label: "Total Payout",
                data: [450000, 460000, 455000, 480000, 495000, 510000], // Dummy Data
                backgroundColor: gradientPayroll,
                borderColor: "#0CC0DF", // Teal Border
                pointBackgroundColor: "#ffffff",
                pointBorderColor: "#0CC0DF",
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4 // Curvy line
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += '₱' + context.parsed.y.toLocaleString();
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: { 
                    grid: { borderDash: [2, 4], color: "#e3e6f0" }, 
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) { return '₱' + value.toLocaleString(); } 
                    }
                }
            }
        }
    });

    // --- 2. DEPARTMENT DISTRIBUTION (Doughnut) ---
    var ctxDept = document.getElementById("deptDistributionChart");
    new Chart(ctxDept, {
        type: 'doughnut',
        data: {
            labels: ["IT Dept", "HR Dept", "Operations", "Finance"],
            datasets: [{
                data: [45, 15, 60, 25], // Dummy Data
                backgroundColor: ['#0CC0DF', '#4e73df', '#36b9cc', '#f6c23e'], // Teal, Blue, Cyan, Yellow
                hoverBackgroundColor: ['#0abad8', '#2e59d9', '#2c9faf', '#dda20a'],
                borderWidth: 5,
                hoverBorderColor: "#ffffff"
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '75%', // Thin modern ring
            plugins: { 
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed + ' employees';
                            }
                            return label;
                        }
                    }
                }
            }
        },
    });
});
</script>

<?php
require 'template/footer.php';
?>