<?php
// dashboard.php
$page_title = 'Admin Dashboard - LOPISv2';
$current_page = 'dashboard';

require 'template/header.php';
require_once 'models/admin_dashboard_model.php'; 

// --- FETCH REAL DATA ---
$metrics = get_admin_dashboard_metrics($pdo);
$dept_data = get_dept_distribution_data($pdo);
$payroll_history = get_payroll_history($pdo);
// ✅ NEW DATA FETCH
$upcoming_leaves = get_upcoming_leaves($pdo, 5);
$upcoming_holidays = get_upcoming_holidays($pdo, 5);


// Prepare Data for View
$active_employees_count = $metrics['active_employees'];
$new_hires_month_count  = $metrics['new_hires_month'];
$pending_leave_count    = $metrics['pending_leave_count'];
$attendance_today       = $metrics['attendance_today'];

// ✅ NEW: Cash Advance Data
$pending_ca_count       = $metrics['pending_ca_count'];

// ✅ NEW: Determine Cash Advance Card Color
// If there are pending requests, show Warning (Yellow), otherwise Success (Teal/Green)
$ca_text_color = ($pending_ca_count > 0) ? 'text-warning' : 'text-success';
$ca_icon_bg    = ($pending_ca_count > 0) ? 'bg-soft-warning' : 'bg-soft-success';
$ca_icon       = ($pending_ca_count > 0) ? 'fa-hand-holding-usd' : 'fa-check-circle';

// Prepare Chart JSON Data
$dept_labels = json_encode(array_keys($dept_data));
$dept_counts = json_encode(array_values($dept_data));

$payroll_labels = json_encode($payroll_history['labels']);
$payroll_values = json_encode($payroll_history['data']);

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
            <a href="reports.php" class="btn btn-light text-teal font-weight-bold shadow-sm border-0">
                <i class="fas fa-download me-2"></i> Generate Report
            </a>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 border-left-teal shadow-sm">
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
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box <?php echo $ca_icon_bg; ?> me-3">
                            <i class="fas <?php echo $ca_icon; ?>"></i>
                        </div>
                        <div>
                            <div class="text-label">Cash Advances</div>
                            <div class="h5 font-weight-bold mb-0 <?php echo $ca_text_color; ?>">
                                <?php echo number_format($pending_ca_count); ?> <small class="text-muted text-xs font-weight-normal">Pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <?php if($pending_ca_count > 0): ?>
                            <span class="text-warning font-weight-bold">Action Required</span>
                        <?php else: ?>
                            <span class="text-success font-weight-bold">All Cleared</span>
                        <?php endif; ?>
                        
                        <a href="cashadv_approval.php" class="text-decoration-none text-muted ms-2">
                            Review &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-danger me-3">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <div>
                            <div class="text-label">Pending Leaves</div>
                            <div class="text-value"><?php echo number_format($pending_leave_count); ?></div>
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
                        <div class="icon-box bg-soft-info me-3">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <div class="text-label">Today Attendance</div>
                            
                            <div class="text-value">
                                <?php echo $attendance_today; ?>
                                <span class="text-muted" style="font-size: 0.6em;">
                                    / <?php echo $active_employees_count; ?>
                                </span>
                            </div>
                            
                        </div>
                    </div>
                    
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <a href="today_attendance.php" class="text-decoration-none text-muted">
                            View details <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card h-100 shadow">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
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
                    <div class="mt-4 text-center small text-muted">
                        Hover over sections to see counts
                    </div>
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
                <div class="card-body pt-0">
                    <?php if (count($upcoming_leaves) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcoming_leaves as $leave): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <h6 class="mb-0 text-gray-800"><?php echo htmlspecialchars($leave['firstname'] . ' ' . $leave['lastname']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($leave['leave_type']); ?></small>
                                    </div>
                                    <span class="badge bg-soft-danger text-danger p-2">
                                        <?php echo date("M d", strtotime($leave['start_date'])); ?> 
                                        <?php echo ($leave['start_date'] != $leave['end_date']) ? ' - ' . date("M d", strtotime($leave['end_date'])) : ''; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-3"></i>
                            <p class="mb-0">No approved leaves scheduled soon.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card h-100 shadow">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-label">Upcoming Company Holidays</h6>
                    <a href="holidays.php" class="text-decoration-none text-sm text-muted">Manage &rarr;</a>
                </div>
                <div class="card-body pt-0">
                    <?php if (count($upcoming_holidays) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcoming_holidays as $holiday): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <h6 class="mb-0 text-gray-800"><?php echo htmlspecialchars($holiday['holiday_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($holiday['holiday_type']); ?></small>
                                    </div>
                                    <span class="badge bg-soft-primary text-primary p-2">
                                        <?php echo date("M d, Y", strtotime($holiday['holiday_date'])); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                            <p class="mb-0">No upcoming holidays configured.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- 1. PAYROLL HISTORY CHART (Dynamic) ---
    var ctxPayroll = document.getElementById("payrollHistoryChart").getContext('2d');
    
    // PHP Data Injection
    const payrollLabels = <?php echo $payroll_labels; ?>;
    const payrollData = <?php echo $payroll_values; ?>;

    var gradientPayroll = ctxPayroll.createLinearGradient(0, 0, 0, 400);
    gradientPayroll.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); 
    gradientPayroll.addColorStop(1, 'rgba(255, 255, 255, 0.0)'); 

    new Chart(ctxPayroll, {
        type: 'line',
        data: {
            labels: payrollLabels,
            datasets: [{
                label: "Total Payout",
                data: payrollData, 
                backgroundColor: gradientPayroll,
                borderColor: "#0CC0DF",
                pointBackgroundColor: "#ffffff",
                pointBorderColor: "#0CC0DF",
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
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
                            if (label) { label += ': '; }
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

    // --- 2. DEPARTMENT DISTRIBUTION (Dynamic) ---
    var ctxDept = document.getElementById("deptDistributionChart");
    
    const deptLabels = <?php echo $dept_labels; ?>;
    const deptCounts = <?php echo $dept_counts; ?>;
    
    new Chart(ctxDept, {
        type: 'doughnut',
        data: {
            labels: deptLabels,
            datasets: [{
                data: deptCounts,
                backgroundColor: ['#0CC0DF', '#4e73df', '#6b36ccff', '#f6c23e', '#e74a3b', '#1cc88a', '#858796'], 
                hoverBackgroundColor: ['#0abad8', '#2e59d9', '#6b36ccff', '#dda20a', '#c73a2f', '#1cc88a', '#6d7083'],
                borderWidth: 5,
                hoverBorderColor: "#ffffff"
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '75%', 
            plugins: { 
                legend: { display: false }, 
            }
        },
    });
});
</script>

<?php require 'template/footer.php'; ?>