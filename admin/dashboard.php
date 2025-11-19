<?php
// Set the page title and include the header
$page_title = 'Admin Dashboard - LOPISv2';
$current_page = 'dashboard';

require 'template/header.php';

// --- ADDED: DATA FETCHING LOGIC ---
// ASSUMPTION: $pdo is available and $_SESSION['company_id'] is set.
$company_id = $_SESSION['company_id'] ?? 1; // Default to 1 if session not set (for testing)
require_once 'models/employee_model.php'; 
require_once 'models/admin_dashboard_model.php'; 

// Fetch all necessary data
$metrics = get_admin_dashboard_metrics($pdo, $company_id);
$dept_data = get_dept_distribution_data($pdo, $company_id);

// Prepare variables for HTML/JS injection
$active_employees_count = $metrics['active_employees'];
$new_hires_month_count = $metrics['new_hires_month'];
$pending_leave_count = $metrics['pending_leave_count'];

// Prepare chart data structure
$dept_labels = json_encode(array_keys($dept_data));
$dept_counts = json_encode(array_values($dept_data));

require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

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

    <div class="row mb-4">

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div class="text-label">Active Employees</div>
                            <div class="text-value"><?php echo number_format($active_employees_count); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <span class="text-success font-weight-bold">
                            <i class="fas fa-plus"></i> <?php echo number_format($new_hires_month_count); ?>
                        </span> new this month
                    </div>
                </div>
            </div>
        </div>

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

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-warning me-3">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <div class="text-label">Leave Requests</div>
                            <div class="text-value"><?php echo number_format($pending_leave_count); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Waiting for approval
                    </div>
                </div>
            </div>
        </div>

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
    </div> <div class="row mb-4">
        
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
                            <i class="fas fa-circle" style="color: #6b36ccff;"></i> I.T.
                        </span>
                        <span class="me-2">
                            <i class="fas fa-circle" style="color: #0CC0DF;"></i> Operations
                        </span>                      
                        <span class="me-2">
                            <i class="fas fa-circle" style="color: #4e73df;"></i> Field
                        </span>
                        <span class="me-2">
                            <i class="fas fa-circle" style="color: #f6c23e;"></i> Corporate
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php require 'functions/add_employee.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- 1. PAYROLL HISTORY CHART (Teal Gradient) ---
    // NOTE: This remains static as fetch logic for historical data was not provided
    var ctxPayroll = document.getElementById("payrollHistoryChart").getContext('2d');
    
    var gradientPayroll = ctxPayroll.createLinearGradient(0, 0, 0, 400);
    gradientPayroll.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); // Start Teal
    gradientPayroll.addColorStop(1, 'rgba(255, 255, 255, 0.0)'); // End Transparent

    new Chart(ctxPayroll, {
        type: 'line',
        data: {
            labels: ["Jun", "Jul", "Aug", "Sep", "Oct", "Nov"], // Dummy Months
            data: [450000, 460000, 455000, 480000, 495000, 510000], // Dummy Data
            datasets: [{
                label: "Total Payout",
                data: [450000, 460000, 455000, 480000, 495000, 510000], 
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

    // --- 2. DEPARTMENT DISTRIBUTION (Doughnut - DYNAMIC) ---
    var ctxDept = document.getElementById("deptDistributionChart");
    
    // Inject PHP data here
    const deptLabels = <?php echo $dept_labels; ?>;
    const deptCounts = <?php echo $dept_counts; ?>;
    
    new Chart(ctxDept, {
        type: 'doughnut',
        data: {
            labels: deptLabels,
            datasets: [{
                data: deptCounts, // Dynamic Data
                // Define colors for the chart elements based on the number of fetched departments
                backgroundColor: ['#0CC0DF', '#4e73df', '#6b36ccff', '#f6c23e', '#e74a3b', '#1cc88a', '#858796'], 
                hoverBackgroundColor: ['#0abad8', '#2e59d9', '#6b36ccff', '#dda20a', '#c73a2f', '#1cc88a', '#6d7083'],
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