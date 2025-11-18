<?php
$page_title = 'Employee Dashboard';
$current_page = 'dashboard';
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php'; 
?>

<div class="welcome-banner shadow">
    <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
        <div>
            <h1 class="h3 font-weight-bold mb-1">Welcome back, <?php echo htmlspecialchars($fullname); ?>!</h1>
            <p class="mb-0 opacity-75">You have <span class="font-weight-bold text-white">2 pending tasks</span> to review today.</p>
        </div>
        <a href="request_leave.php" class="btn btn-light text-teal font-weight-bold shadow-sm border-0">
            <i class="fas fa-plus me-2"></i> Request Leave
        </a>
    </div>
</div>

<div class="row mb-4">
    
    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-primary me-3">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <div class="text-label">Next Payday</div>
                        <div class="text-value">Nov 30</div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    <span class="text-primary font-weight-bold">13 days</span> remaining
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-teal me-3">
                        <i class="fas fa-plane-departure"></i>
                    </div>
                    <div>
                        <div class="text-label">Vacation</div>
                        <div class="text-value">8 <span class="text-xs text-muted">days</span></div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    <span class="text-teal mr-2"><i class="fa fa-arrow-up"></i> +2 days</span> earned
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-warning me-3">
                        <i class="fas fa-first-aid"></i>
                    </div>
                    <div>
                        <div class="text-label">Sick Leave</div>
                        <div class="text-value">4 <span class="text-xs text-muted">days</span></div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    Valid until Dec 31
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-secondary me-3">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <div class="text-label">Pending</div>
                        <div class="text-value">1 <span class="text-xs text-muted">req</span></div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    Submitted 2 days ago
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-gray-800">Weekly Attendance</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="chart-area" style="height: 320px;">
                    <canvas id="modernAttendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-5 mb-4">
        
        <div class="card mb-4">
            <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="m-0 font-weight-bold text-gray-800">Leave Balance</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie" style="height: 200px;">
                    <canvas id="modernLeaveChart"></canvas>
                </div>
                <div class="mt-3 text-center small">
                    <span class="me-2">
                        <i class="fas fa-circle text-teal"></i> Available
                    </span>
                    <span class="me-2">
                        <i class="fas fa-circle text-secondary"></i> Used
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="m-0 font-weight-bold text-gray-800">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="payslips.php" class="btn btn-outline-light w-100 p-3 border-0 bg-soft-primary" style="border-radius: 1rem;">
                            <i class="fas fa-file-invoice-dollar fa-lg mb-1"></i><br>
                            <span class="small font-weight-bold">Payslip</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="attendance.php" class="btn btn-outline-light w-100 p-3 border-0 bg-soft-teal" style="border-radius: 1rem;">
                            <i class="fas fa-clock fa-lg mb-1"></i><br>
                            <span class="small font-weight-bold">DTR</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-transparent border-0">
                <h6 class="m-0 font-weight-bold text-gray-800">Company Bulletin</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush rounded-bottom">
                    <a href="#" class="list-group-item list-group-item-action p-3 border-0 border-bottom">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-warning me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <i class="fas fa-glass-cheers"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 font-weight-bold text-dark">Year-End Holiday Party</h6>
                                    <small class="text-muted">Dec 20th @ Main Hall</small>
                                </div>
                            </div>
                            <small class="text-muted">3d ago</small>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action p-3 border-0">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-soft-teal me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <i class="fas fa-file-medical"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 font-weight-bold text-dark">New HMO Policy</h6>
                                    <small class="text-muted">Review by Jan 1st</small>
                                </div>
                            </div>
                            <small class="text-muted">1w ago</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- 1. TEAL Area Chart ---
    var ctx = document.getElementById("modernAttendanceChart").getContext('2d');
    
    var gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); // Start Teal
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0.0)'); // End Transparent

    var myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Mon", "Tue", "Wed", "Thu", "Fri"],
            datasets: [{
                label: "Hours",
                data: [8, 8.5, 7.5, 9, 8], 
                backgroundColor: gradient,
                borderColor: "#0CC0DF", // Teal Border
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
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } }, 
                y: { grid: { borderDash: [2, 4], color: "#e3e6f0" }, beginAtZero: true }
            }
        }
    });

    // --- 2. TEAL Doughnut Chart ---
    var ctxPie = document.getElementById("modernLeaveChart");
    var myPieChart = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: ["Available", "Used"],
            datasets: [{
                data: [85, 15],
                backgroundColor: ['#0CC0DF', '#eaecf4'], // Teal & Light Gray
                hoverBackgroundColor: ['#0abad8', '#e3e6f0'],
                hoverBorderColor: "#ffffff",
                borderWidth: 5 
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