<?php
// --- 1. SET PAGE CONFIGURATIONS ---
$page_title = 'Super Admin Dashboard - LOPISv2';
$current_page = 'dashboard'; 

require 'template/header.php';

// --- ADDED: REQUIRE MODEL & FETCH DYNAMIC DATA ---
// Assume $pdo is available after header.php
require 'models/superadmin_model.php'; 

$metrics = get_dashboard_metrics($pdo);
$role_counts = get_user_role_counts($pdo);

// Prepare the PHP array for Chart.js
$role_counts_json = json_encode($role_counts); 

// Map fetched metrics to variables
$total_companies_count = $metrics['total_companies'] ?? 0;
$active_users_count = $metrics['active_users'] ?? 0;
$monthly_payrolls_count = $metrics['monthly_payrolls'] ?? 0;
$audit_alerts_count = $metrics['audit_alerts'] ?? 0;

require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="welcome-banner shadow">
        <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
            <div>
                <h1 class="h3 font-weight-bold mb-1">Overview</h1>
                <p class="mb-0 opacity-75">System-wide performance and activity monitoring.</p>
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
                        <div class="icon-box bg-soft-teal me-3">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <div class="text-label">Total Companies</div>
                            <div class="text-value"><?php echo number_format($total_companies_count); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <span class="text-teal font-weight-bold"><i class="fas fa-arrow-up"></i> 12%</span> growth
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div class="text-label">Active Users</div>
                            <div class="text-value"><?php echo number_format($active_users_count); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Across all organizations
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
                            <div class="text-label">Payrolls (Mo)</div>
                            <div class="text-value"><?php echo number_format($monthly_payrolls_count); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Processed this month
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-warning me-3">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <div class="text-label">Audit Alerts</div>
                            <div class="text-value"><?php echo number_format($audit_alerts_count); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Requires attention
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-800">Platform Growth</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h6 class="m-0 font-weight-bold text-gray-800">User Roles</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 250px;">
                        <canvas id="userRolesChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="me-2">
                            <i class="fas fa-circle text-teal"></i> Superadmin
                        </span>
                        <span class="me-2">
                            <i class="fas fa-circle text-primary"></i> Admin
                        </span>
                        <span class="me-2">
                            <i class="fas fa-circle text-secondary"></i> User
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0">
                    <h6 class="m-0 font-weight-bold text-gray-800">Recent Company Activity</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        
                        <div class="list-group-item p-3 border-0 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-teal me-3" style="width:40px; height:40px; font-size:1rem;">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 font-weight-bold text-dark">Innovatech Solutions</h6>
                                        <small class="text-muted">15m ago</small>
                                    </div>
                                    <small class="text-muted">New company registered</small>
                                </div>
                            </div>
                        </div>

                        <div class="list-group-item p-3 border-0 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-success me-3" style="width:40px; height:40px; font-size:1rem;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 font-weight-bold text-dark">Quantum Builders</h6>
                                        <small class="text-muted">2h ago</small>
                                    </div>
                                    <small class="text-muted">First payroll processed successfully</small>
                                </div>
                            </div>
                        </div>

                        <div class="list-group-item p-3 border-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-primary me-3" style="width:40px; height:40px; font-size:1rem;">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 font-weight-bold text-dark">Brightstar Logistics</h6>
                                        <small class="text-muted">1d ago</small>
                                    </div>
                                    <small class="text-muted">New Admin account created</small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="company_management.php" class="btn btn-sm btn-light text-teal font-weight-bold">View All Companies</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-transparent border-0">
                    <h6 class="m-0 font-weight-bold text-gray-800">Recent Audit Logs</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        
                        <li class="list-group-item p-3 border-0 border-bottom">
                            <div class="d-flex w-100 justify-content-between mb-1">
                                <strong class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Failed Login</strong>
                                <small class="text-muted">5m ago</small>
                            </div>
                            <p class="mb-0 small text-gray-600"><strong>Admin@innovatech</strong> failed 3 attempts.</p>
                        </li>

                        <li class="list-group-item p-3 border-0 border-bottom">
                            <div class="d-flex w-100 justify-content-between mb-1">
                                <strong class="text-info"><i class="fas fa-cog me-1"></i> Config Change</strong>
                                <small class="text-muted">1h ago</small>
                            </div>
                            <p class="mb-0 small text-gray-600"><strong>Superadmin</strong> updated Tax Settings.</p>
                        </li>

                        <li class="list-group-item p-3 border-0">
                            <div class="d-flex w-100 justify-content-between mb-1">
                                <strong class="text-success"><i class="fas fa-money-bill-wave me-1"></i> Payroll Run</strong>
                                <small class="text-muted">2h ago</small>
                            </div>
                            <p class="mb-0 small text-gray-600"><strong>Admin@quantum</strong> processed 150 employees.</p>
                        </li>

                    </ul>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="audit_logs.php" class="btn btn-sm btn-light text-teal font-weight-bold">View All Logs</a>
                </div>
            </div>
        </div>
    </div>


</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- 1. SUPERADMIN GROWTH CHART (Teal Area) ---
    var ctxGrowth = document.getElementById("growthChart").getContext('2d');
    var gradientGrowth = ctxGrowth.createLinearGradient(0, 0, 0, 400);
    gradientGrowth.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); // Teal
    gradientGrowth.addColorStop(1, 'rgba(255, 255, 255, 0.0)');

    new Chart(ctxGrowth, {
        type: 'line',
        data: {
            labels: ["Jun", "Jul", "Aug", "Sep", "Oct", "Nov"],
            datasets: [{
                label: "New Companies",
                data: [10, 15, 25, 30, 35, 42],
                backgroundColor: gradientGrowth,
                borderColor: "#0CC0DF",
                pointBackgroundColor: "#fff",
                pointBorderColor: "#0CC0DF",
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { borderDash: [2, 4] }, beginAtZero: true }
            }
        }
    });

    // --- 2. USER ROLES CHART (Doughnut) ---
    var ctxRoles = document.getElementById("userRolesChart");
    // Ensure the data is loaded before initializing the chart
    const roleData = <?php echo $role_counts_json; ?>; 
    
    new Chart(ctxRoles, {
        type: 'doughnut',
        data: {
            labels: ["Superadmin", "Admin", "User"],
            datasets: [{
                data: roleData, // DYNAMIC INJECTION
                backgroundColor: ['#0CC0DF', '#4e73df', '#eaecf4'], // Teal, Blue, Gray
                hoverBackgroundColor: ['#0abad8', '#2e59d9', '#e3e6f0'],
                borderWidth: 5,
                hoverBorderColor: "#fff"
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: { legend: { display: false } }
        },
    });
});
</script>

<?php
require 'template/footer.php';
?>