<?php
// dashboard.php
$page_title = 'Employee Dashboard';
$current_page = 'dashboard';

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="welcome-banner shadow mb-4">
    <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
        <div>
            <h1 class="h3 font-weight-bold mb-1" id="welcome-text">
                Welcome back, <?php echo $_SESSION['firstname'] ?? 'Employee'; ?>!
            </h1>
            <p class="text-white mb-0 opacity-75" id="status-message"></p> 
        </div>
    </div>
</div>

<div id="employee-dashboard-container">

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fas fa-clock"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">Last Clock-In</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800" id="card-clock-in">--:--</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <span class="font-weight-bold" id="card-clock-status">--</span> status
                        <a href="attendance.php" class="text-decoration-none text-muted ms-2 float-end">Details &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fas fa-business-time"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">OT Requests</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800">
                                <span id="card-ot-count">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Total: <span class="text-teal font-weight-bold"><span id="card-ot-hours">0.0</span> hrs</span> pending
                        <a href="file_overtime.php" class="text-decoration-none text-muted ms-2 float-end">View &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fas fa-wallet"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">Total Balance</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800" id="card-loan-balance">₱0.00</div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        Status: <span class="text-teal font-weight-bold" id="card-loan-status">Active</span>
                        <a href="balances.php" class="text-decoration-none text-muted ms-2 float-end">View &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-teal me-3"><i class="fas fa-calendar-minus"></i></div>
                        <div>
                            <div class="text-label text-uppercase text-xs font-weight-bold text-gray-600">Pending Leave</div>
                            <div class="text-value h5 mb-0 font-weight-bold text-gray-800">
                                <span id="card-leave-count">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 mb-0 text-muted text-xs">
                        <span class="text-teal font-weight-bold"><span id="card-leave-days">0.0</span> days</span> pending
                        <a href="request_leave.php" class="text-decoration-none text-muted ms-2 float-end">View &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between">
                    <h6 class="m-0 font-weight-bold text-gray-700">Attendance Overview</h6>
                    <small class="text-muted">Current Week</small>
                </div>
                <div class="card-body pt-0">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="modernAttendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-transparent border-0 pb-0"><h6 class="m-0 font-weight-bold text-gray-700">Leave Credits</h6></div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 200px; position: relative;">
                        <canvas id="modernLeaveChart"></canvas>
                    </div>
                    <div class="mt-3 text-center small text-muted">
                        <span class="me-3"><i class="fas fa-circle text-teal"></i> Available</span>
                        <span class="me-3"><i class="fas fa-circle text-gray-300"></i> Used</span>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0 pb-0"><h6 class="m-0 font-weight-bold text-gray-700">Quick Actions</h6></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="payslips.php" class="btn btn-outline-light w-100 p-3 border-0 bg-soft-teal text-teal" style="border-radius: 1rem;">
                                <i class="fas fa-file-invoice-dollar fa-lg mb-1"></i><br><span class="small font-weight-bold">Payslip</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="attendance.php" class="btn btn-outline-light w-100 p-3 border-0 bg-soft-teal text-teal" style="border-radius: 1rem;">
                                <i class="fas fa-clock fa-lg mb-1"></i><br><span class="small font-weight-bold">DTR</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0"><h6 class="m-0 font-weight-bold text-gray-700">Upcoming Holidays</h6></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush rounded-bottom" id="holidays-list-container">
                        <p class="text-center p-4 text-muted small">Loading holidays...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/dashboard_scripts.php'; ?>