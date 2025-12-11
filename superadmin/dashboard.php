<?php
// --- 1. SET PAGE CONFIGURATIONS ---
$page_title = 'Super Admin Dashboard - LOPISv2';
$current_page = 'dashboard'; 

// --- 2. HARDCODED DASHBOARD DATA (Simulated Database Returns) ---

// Card 1: User Management (Focus on Admins managing the system)
$total_admins_count = 42; 
$admin_growth = 5; // % growth

// Card 2: Company Settings (Focus on Registered Companies)
$total_companies_count = 12;
$company_growth = 12; // % growth

// Card 3: Payroll Configuration (Focus on Active Schedules/Load)
$active_pay_schedules = 28; // Total schedules running across all companies
$schedule_completion = 85; // % of schedules processed this month

// Card 4: System Settings (Focus on Security/Health)
$security_alerts_count = 3; // Critical alerts needing attention

// Activity Data Arrays
$recent_companies = [
    ['name' => 'Innovatech Solutions', 'time' => '15m ago', 'status' => 'New Registration', 'icon' => 'fa-building', 'color' => 'bg-soft-primary'],
    ['name' => 'Quantum Builders', 'time' => '2h ago', 'status' => 'Payroll Processed', 'icon' => 'fa-file-invoice-dollar', 'color' => 'bg-soft-success'],
    ['name' => 'Brightstar Logistics', 'time' => '1d ago', 'status' => 'Admin Added', 'icon' => 'fa-user-shield', 'color' => 'bg-soft-info']
];

$audit_logs = [
    ['type' => 'Failed Login', 'user' => 'admin@innovatech', 'desc' => 'Failed 3 attempts', 'time' => '5m ago', 'color' => 'text-danger', 'icon' => 'fa-triangle-exclamation'],
    ['type' => 'Config Change', 'user' => 'Superadmin', 'desc' => 'Updated Tax Settings', 'time' => '1h ago', 'color' => 'text-info', 'icon' => 'fa-gear'],
    ['type' => 'Payroll Run', 'user' => 'admin@quantum', 'desc' => 'Processed 150 employees', 'time' => '2h ago', 'color' => 'text-success', 'icon' => 'fa-money-bill-transfer']
];

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Super Admin Dashboard</h1>
        <a href="reports.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fa-solid fa-download fa-sm text-white-50 me-2"></i>Generate System Report
        </a>
    </div>

    <div class="row">

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                System Admins</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_admins_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fa-solid fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2 mb-0 text-xs">
                        <span class="text-success mr-2"><i class="fa-solid fa-arrow-up"></i> <?php echo $admin_growth; ?>%</span>
                        <span class="text-muted">New admins this month</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Client Companies</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_companies_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fa-solid fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2 mb-0 text-xs">
                        <span class="text-success mr-2"><i class="fa-solid fa-arrow-up"></i> <?php echo $company_growth; ?>%</span>
                        <span class="text-muted">Since last quarter</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Pay Schedules
                            </div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $active_pay_schedules; ?></div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: <?php echo $schedule_completion; ?>%" aria-valuenow="<?php echo $schedule_completion; ?>" aria-valuemin="0"
                                            aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fa-solid fa-calendar-days fa-2x text-gray-300"></i>
                        </div>
                    </div>
                     <div class="mt-2 mb-0 text-xs">
                        <span class="text-muted"><?php echo $schedule_completion; ?>% completion rate</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Security Alerts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $security_alerts_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fa-solid fa-shield-halved fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    <div class="mt-2 mb-0 text-xs">
                        <span class="text-danger font-weight-bold">Immediate Action</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">System Growth Overview</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                            aria-labelledby="dropdownMenuLink">
                            <div class="dropdown-header">Dropdown Header:</div>
                            <a class="dropdown-item" href="#">Action</a>
                            <a class="dropdown-item" href="#">Another action</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">Something else here</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">User Roles Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="userRolesChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> Super Admin
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> Admin
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> Employee
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Company Activity</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach($recent_companies as $company): ?>
                        <div class="list-group-item p-3 border-0 border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="icon-box <?php echo $company['color']; ?> rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px; height:40px;">
                                    <i class="fa-solid <?php echo $company['icon']; ?> text-white"></i>
                                </div>
                                <div class="flex-grow-1 ml-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 font-weight-bold text-dark"><?php echo $company['name']; ?></h6>
                                        <small class="text-muted"><?php echo $company['time']; ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo $company['status']; ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="company_details.php" class="btn btn-sm btn-light text-primary font-weight-bold">View All Companies</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Audit Logs</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach($audit_logs as $log): ?>
                        <li class="list-group-item p-3 border-0 border-bottom">
                            <div class="d-flex w-100 justify-content-between mb-1">
                                <strong class="<?php echo $log['color']; ?>">
                                    <i class="fa-solid <?php echo $log['icon']; ?> me-1"></i> <?php echo $log['type']; ?>
                                </strong>
                                <small class="text-muted"><?php echo $log['time']; ?></small>
                            </div>
                            <p class="mb-0 small text-gray-600">
                                <strong><?php echo $log['user']; ?></strong> <?php echo $log['desc']; ?>
                            </p>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="audit_logs.php" class="btn btn-sm btn-light text-primary font-weight-bold">View All Logs</a>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
require 'template/footer.php';
?>