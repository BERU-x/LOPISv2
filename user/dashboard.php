<?php
// employee_dashboard.php or dashboard.php

$page_title = 'Employee Dashboard';
$current_page = 'dashboard';

// Fetch ONLY the card data here. Chart data is handled via AJAX.
require 'fetch/dashboard_data.php';
require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
// --- Initialize variables with safe defaults (MUST use ?? to preserve fetched values) ---
$fullname = $fullname ?? 'Employee';

// Corrected Leave/OT initialization (preserves fetched value if it exists)
$pending_leave_count = $pending_leave_count ?? 0;
$pending_leave_days = $pending_leave_days ?? 0.0;
$last_leave_submission = $last_leave_submission ?? 'N/A';

// Corrected Punctuality/OT initialization
$last_time_in = $last_time_in ?? 'N/A';
$status_label = $status_label ?? 'No Data';
$status_color = $status_color ?? 'secondary';
$pending_ot_count = $pending_ot_count ?? 0;
$pending_ot_hours = $pending_ot_hours ?? 0.0;

$loan_balance = $loan_balance ?? 0.0;
$loan_type = $loan_type ?? 'N/A';
$loan_color = 'primary'; // Use primary/blue for financial focus
// --- Note: Removed Chart variables: $js_chart_labels, $js_chart_data, $MAX_LEAVE, etc. ---
// Define the base text and dynamic elements based on status
$welcome_text = "Welcome back, <b>" . htmlspecialchars($fullname) . "</b>!";
$secondary_message = '';

// --- Logic to define the personalized message ---
switch ($status_label) {
    case 'On Time':
    case 'Present':
        $secondary_message = "Great start! You're clocked in <b>On Time</b> today.";
        break;

    case 'Late':
        $secondary_message = "Your check-in was recorded as <b>Late</b>. Remember to strive for punctuality.";
        break;

    default:
        // Use a generic message for all other statuses (e.g., Logged, Early Out, Undertime, etc.)
        $secondary_message = "Please <b>Clock In</b> to start your workday.";
        break;
}

$status_message_html = "<p class='text-white mb-0 opacity-75'>{$secondary_message}</p>";
?>

<div class="welcome-banner shadow">
    <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
        <div>
            <h1 class="h3 font-weight-bold mb-1"><?php echo $welcome_text; ?></h1>
            <?php echo $status_message_html; ?>
        </div>
    </div>
</div>

<div class="row mb-4">

    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-teal me-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="text-label">Last Clock-In</div>
                        <div class="text-value"><?php echo htmlspecialchars($last_time_in); ?></div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    <span class="text-teal font-weight-bold">
                        <?php echo htmlspecialchars($status_label); ?>
                    </span> status
                    <a href="attendance.php" class="text-decoration-none text-muted ms-2">
                        View Details &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-teal me-3">
                        <i class="fas fa-hourglass-start"></i>
                    </div>
                    <div>
                        <div class="text-label">OT Requests</div>
                        <div class="text-value">
                            <?php
                            if (is_numeric($pending_ot_count)) {
                                echo htmlspecialchars($pending_ot_count);
                            } else {
                                echo htmlspecialchars($pending_ot_count);
                            }
                            ?>
                            <span class="text-xs text-muted">pending</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    Total: <span class="text-teal font-weight-bold">
                        <?php
                        echo number_format($pending_ot_hours ?? 0.0, 1);
                        ?> hours
                    </span> waiting approval
                    <a href="file_overtime.php" class="text-decoration-none text-muted ms-2">
                        View Details &rarr;
                    </a>
                </div>

            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-teal me-3">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div>
                        <div class="text-label">Total Loan Balances</div>
                        <div class="text-value">
                            <?php
                            // Display the calculated total balance
                            if (is_numeric($loan_balance)) {
                                echo '₱' . number_format($loan_balance, 2);
                            } else {
                                echo htmlspecialchars($loan_balance); // Displays 'Error' or 'N/A'
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    Status: <span class="text-teal font-weight-bold">
                        <?php echo htmlspecialchars($loan_type); ?>
                    </span>
                    <a href="balances.php" class="text-decoration-none text-muted ms-2">
                        View Details &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4 mb-xl-0">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-soft-teal me-3">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <div class="text-label">Pending Leave</div>
                        <div class="text-value">
                            <?php echo htmlspecialchars($pending_leave_count); ?>
                            <span class="text-xs text-muted">requests</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 mb-0 text-muted text-xs">
                    <span class="text-teal font-weight-bold">
                        <?php echo number_format($pending_leave_days ?? 0.0, 1); ?> days
                    </span> pending approval
                    <a href="request_leave.php" class="text-decoration-none text-muted ms-2">
                        View Details &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header bg-transparent border-0 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-label">Weekly Attendance</h6>
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
                <h6 class="m-0 font-weight-bold text-label">Leave Balance</h6>
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
                <h6 class="m-0 font-weight-bold text-label">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="payslips.php" class="btn btn-outline-light w-100 p-3 border-0 bg-soft-teal"
                            style="border-radius: 1rem;">
                            <i class="fas fa-file-invoice-dollar fa-lg mb-1"></i><br>
                            <span class="small font-weight-bold">Payslip</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="attendance.php" class="btn btn-outline-light w-100 p-3 border-0 bg-soft-teal"
                            style="border-radius: 1rem;">
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
                <h6 class="m-0 font-weight-bold text-label">Upcoming Holidays</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush rounded-bottom" id="holidays-list-container">
                    <p class="text-center p-3 text-muted">Loading holidays...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require 'template/footer.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    let myAttendanceChart; // Global declaration for potential updates
    let myLeaveChart;

    // Helper function to calculate days until a holiday
    function getDaysUntil(dateString) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const holidayDate = new Date(dateString);

        const diffTime = holidayDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Tomorrow';
        if (diffDays > 0) return `${diffDays} days away`;
        return 'Passed';
    }

    // Function to render the holiday list dynamically
    function renderHolidays(holidays) {
        const container = $('#holidays-list-container');
        container.empty(); // Clear loading message

        if (holidays.length === 0) {
            container.append('<p class="text-center p-3 text-muted">No upcoming holidays scheduled.</p>');
            return;
        }

        holidays.forEach(holiday => {
            // Get color/icon based on holiday type
            let iconClass = 'fas fa-calendar-alt';

            if (holiday.holiday_type.includes('Regular')) {
                iconClass = 'fas fa-star';
            } else if (holiday.holiday_type.includes('Special')) {
                iconClass = 'fas fa-sun';
            }

            // Format date
            const formattedDate = new Date(holiday.holiday_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const daysUntil = getDaysUntil(holiday.holiday_date);

            const html = `
                <a href="#" class="list-group-item list-group-item-action p-3 border-0 border-bottom">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="icon-box text-label me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                <i class="${iconClass}"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 font-weight-bold text-dark">${holiday.holiday_name}</h6>
                                <small class="text-muted">${formattedDate} (${holiday.holiday_type})</small>
                            </div>
                        </div>
                        <small class="text-teal font-weight-bold">${daysUntil}</small>
                    </div>
                </a>
            `;
            container.append(html);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {

        // Function to initialize and update charts
        function initializeCharts(data) {

            // --- 1. TEAL Area Chart (Attendance) ---
            var ctxAttn = document.getElementById("modernAttendanceChart").getContext('2d');
            var gradient = ctxAttn.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(12, 192, 223, 0.5)');
            gradient.addColorStop(1, 'rgba(255, 255, 255, 0.0)');

            if (myAttendanceChart) myAttendanceChart.destroy(); // Destroy existing chart if updating

            // Define the 8-hour target line data
            const TARGET_HOURS = 8;
            const TARGET_LINE_DATA = data.attendance_labels.map(label => TARGET_HOURS);

            myAttendanceChart = new Chart(ctxAttn, {
                type: 'line',
                data: {
                    labels: data.attendance_labels,
                    datasets: [
                        {
                            label: "Total Hours Worked", /* Primary Data */
                            data: data.attendance_data,
                            backgroundColor: gradient,
                            borderColor: "#0CC0DF",
                            pointBackgroundColor: "#ffffff",
                            pointBorderColor: "#0CC0DF",
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: "Standard 8 Hrs", /* Secondary Benchmark Line */
                            data: TARGET_LINE_DATA,
                            type: 'line',
                            fill: false,
                            pointRadius: 0,
                            borderWidth: 2,
                            borderColor: 'rgba(255, 99, 132, 0.7)',
                            borderDash: [6, 6],
                            tension: 0
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Hours: ' + context.parsed.y.toFixed(2);
                                    }
                                    return null;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            grid: { borderDash: [2, 4], color: "#e3e6f0" },
                            beginAtZero: true,
                            suggestedMax: 10, /* Max 10 hours for better scaling */
                            suggestedMin: 0,
                            ticks: {
                                callback: function (value) {
                                    if (value === 8) return '8 hrs';
                                    return value + ' hrs';
                                }
                            }
                        }
                    }
                }
            });

            // --- 2. TEAL Doughnut Chart (Leave) ---
            var ctxPie = document.getElementById("modernLeaveChart").getContext('2d');

            if (myLeaveChart) myLeaveChart.destroy(); // Destroy existing chart if updating

            myLeaveChart = new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: ["Available", "Used"],
                    datasets: [{
                        data: [data.leave_available, data.leave_used],
                        backgroundColor: ['#0CC0DF', '#eaecf4'],
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
        }

        // ----------------------------------------------------------------------------------
        // --- AJAX SECTION ---
        // ----------------------------------------------------------------------------------

        // --- 1. AJAX FETCH: Chart Data ---
        $.ajax({
            url: 'fetch/chart_data.php', // Existing Chart Data endpoint
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.attendance_data) {
                    initializeCharts(response);
                } else {
                    console.error("Failed to load chart data structure.");
                    // Fallback to default chart data on error
                    initializeCharts({
                        attendance_labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                        attendance_data: [0, 0, 0, 0, 0, 0, 0],
                        leave_available: 0,
                        leave_used: 100
                    });
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error fetching chart data:", textStatus, errorThrown);
                // Initialize charts with default (0) data on error
                initializeCharts({
                    attendance_labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                    attendance_data: [0, 0, 0, 0, 0, 0, 0],
                    leave_available: 0,
                    leave_used: 100
                });
            }
        });

        // --- 2. AJAX FETCH: Upcoming Holidays ---
        $.ajax({
            url: 'fetch/holidays_data.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success' && Array.isArray(response.data)) {
                    renderHolidays(response.data);
                } else {
                    $('#holidays-list-container').html('<p class="text-center p-3 text-muted">Error fetching holidays: ' + response.message + '</p>');
                }
            },
            error: function () {
                $('#holidays-list-container').html('<p class="text-center p-3 text-muted">Failed to connect to holiday data source.</p>');
            }
        });
    });
</script>