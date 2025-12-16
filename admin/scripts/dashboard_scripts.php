<script>
// ==============================================================================
// 1. GLOBAL STATE VARIABLES
// ==============================================================================
let spinnerStartTime = 0; 
let payrollChart = null; 
let deptChart = null;    

// ==============================================================================
// 2. HELPER FUNCTIONS (Sync, Time, & UI)
// ==============================================================================

/**
 * Updates the Topbar Status (Text + Dot Color)
 * @param {string} state - 'loading', 'success', or 'error'
 */
function updateSyncStatus(state) {
    const $dot = $('.live-dot');
    const $text = $('#last-updated-time');
    const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    $dot.removeClass('text-success text-warning text-danger');

    if (state === 'loading') {
        $text.text('Syncing...');
        $dot.addClass('text-warning'); // Yellow
    } 
    else if (state === 'success') {
        $text.text(`Synced: ${time}`);
        $dot.addClass('text-success'); // Green
    } 
    else {
        $text.text(`Failed: ${time}`);
        $dot.addClass('text-danger');  // Red
    }
}

// 2.2 Sets the Greeting
function setWelcomeMessage() {
    const now = new Date();
    const hrs = now.getHours();
    let greet = (hrs < 12) ? "Good Morning! ☀️" : ((hrs >= 12 && hrs <= 17) ? "Good Afternoon! 🌤️" : "Good Evening! 🌙");
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    
    if($('#status-message').length) {
        $('#status-message').html(`${greet} &nbsp;|&nbsp; Today is ${now.toLocaleDateString('en-US', options)}`);
    }
}

// ==============================================================================
// 3. RENDER FUNCTIONS
// ==============================================================================

function updateMetrics(metrics) {
    $('#val-active-employees').text(Number(metrics.active_employees).toLocaleString());
    $('#val-new-hires').text(Number(metrics.new_hires_month).toLocaleString());
    
    // Pending CA Logic
    $('#val-pending-ca').text(Number(metrics.pending_ca_count).toLocaleString());
    if(metrics.pending_ca_count > 0) {
        $('#status-pending-ca').html(`<a href="cashadv_approval.php" class="text-decoration-none text-muted">View Details &rarr;</a>`);
    } else {
        $('#status-pending-ca').html(`<span class="text-muted fw-bold">All Cleared</span>`);
    }

    $('#val-pending-leaves').text(Number(metrics.pending_leave_count).toLocaleString());
    
    // Attendance
    $('#val-attendance-today').text(metrics.attendance_today);
    $('#val-attendance-total').text(metrics.active_employees);
}

function renderPayrollChart(history) {
    const ctx = document.getElementById("payrollHistoryChart").getContext('2d');
    if(payrollChart) payrollChart.destroy();

    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); 
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0.0)'); 

    payrollChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: history.labels,
            datasets: [{
                label: "Total Payout",
                data: history.data, 
                backgroundColor: gradient,
                borderColor: "#0CC0DF",
                pointBackgroundColor: "#ffffff",
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
                y: { 
                    grid: { borderDash: [2, 4], color: "#e3e6f0" }, 
                    ticks: { callback: function(value) { return '₱' + value.toLocaleString(); } }
                }
            }
        }
    });
}

function renderDeptChart(dataObj) {
    const ctx = document.getElementById("deptDistributionChart");
    if(deptChart) deptChart.destroy();

    deptChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(dataObj),
            datasets: [{
                data: Object.values(dataObj),
                backgroundColor: ['#0CC0DF', '#4e73df', '#6b36ccff', '#f6c23e', '#e74a3b', '#1cc88a', '#858796'], 
                borderWidth: 5,
                hoverBorderColor: "#ffffff"
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '75%', 
            plugins: { legend: { display: false } }
        },
    });
}

function renderLeavesList(leaves) {
    const container = $('#list-upcoming-leaves');
    container.empty();

    if (leaves.length === 0) {
        container.html(`
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-circle-check fa-2x mb-3"></i> <p class="mb-0">No approved leaves scheduled soon.</p>
            </div>
        `);
        return;
    }

    let html = '<ul class="list-group list-group-flush">';
    leaves.forEach(leave => {
        const start = new Date(leave.start_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
        const end = new Date(leave.end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
        const dateDisplay = (start === end) ? start : `${start} - ${end}`;

        html += `
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <div>
                    <h6 class="mb-0 text-gray-800">${leave.firstname} ${leave.lastname}</h6>
                    <small class="text-muted">${leave.leave_type}</small>
                </div>
                <span class="badge bg-soft-teal text-danger p-2">${dateDisplay}</span>
            </li>
        `;
    });
    html += '</ul>';
    container.html(html);
}

function renderHolidaysList(holidays) {
    const container = $('#list-upcoming-holidays');
    container.empty();

    if (holidays.length === 0) {
        container.html(`
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-calendar-days fa-2x mb-3"></i> <p class="mb-0">No upcoming holidays configured.</p>
            </div>
        `);
        return;
    }

    let html = '<ul class="list-group list-group-flush">';
    holidays.forEach(holiday => {
        const dateDisplay = new Date(holiday.holiday_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
        
        html += `
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <div>
                    <h6 class="mb-0 text-gray-800">${holiday.holiday_name}</h6>
                    <small class="text-muted">${holiday.holiday_type}</small>
                </div>
                <span class="badge bg-soft-teal text-primary p-2">${dateDisplay}</span>
            </li>
        `;
    });
    html += '</ul>';
    container.html(html);
}

// ==============================================================================
// 4. MAIN DATA FETCHER (Smart)
// ==============================================================================
// isManual = true (Show Spinner) | isManual = false (Silent Update)
function loadDashboardData(isManual = false) {
    
    // 1. Visual Feedback
    if(isManual) {
        $('#refreshIcon').addClass('fa-spin'); 
    }
    
    // 2. Always set dot to yellow
    updateSyncStatus('loading');

    $.ajax({
        url: 'api/get_dashboard_data.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.metrics) {
                updateMetrics(response.metrics);
                renderPayrollChart(response.payroll_history);
                renderDeptChart(response.dept_data);
                renderLeavesList(response.upcoming_leaves);
                renderHolidaysList(response.upcoming_holidays);
            }
            updateSyncStatus('success');
        },
        error: function(err) {
            console.error("Dashboard Sync Error", err);
            updateSyncStatus('error');
        },
        complete: function() {
            if(isManual) setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        }
    });
}

// ==============================================================================
// 5. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    setWelcomeMessage();

    // 1. Initial Load (Visual)
    loadDashboardData(true);

    // 2. Global Refresher Hook (Silent Mode capable)
    window.refreshPageContent = function(isManual) {
        loadDashboardData(isManual);
    };

    // 3. Manual Button Click
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault(); 
        loadDashboardData(true);
    });
});
</script>