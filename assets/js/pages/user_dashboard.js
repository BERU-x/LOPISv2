/**
 * Employee Dashboard Controller
 * Handles real-time syncing of personal stats, productivity charts, and holiday lists.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
let leavePieChart = null;
let attendanceLineChart = null;
let spinnerStartTime = 0; 

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
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

/**
 * 1.2 MASTER REFRESHER HOOK
 */
window.refreshPageContent = function(isManual = false) {
    // Visual feedback for manual refresh clicks
    if (isManual) {
        $('#refreshIcon').addClass('fa-spin');
        updateSyncStatus('loading');
    }
    // Logic is handled by the main data loader
    loadDashboardData();
};

// ==============================================================================
// 2. DATA FETCHING & UI SYNC
// ==============================================================================

function loadDashboardData() {
    spinnerStartTime = new Date().getTime(); 
    
    // UI Feedback
    $('#refresh-spinner').removeClass('text-gray-400').addClass('fa-spin text-teal');
    updateSyncStatus('loading');

    $.ajax({
        url: '../api/employee/get_dashboard_data.php', 
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                updateDashboardUI(res.data);
                updateSyncStatus('success');
            } else {
                updateSyncStatus('error');
            }
            stopSpinnerSafely();
        },
        error: function(xhr, status, error) { 
            // 1. Check if the error is due to session expiry (401)
            if (xhr.status === 401) {
                alert("Your session has expired. Please login again.");
                window.location.href = '../index.php'; // Redirect to login
                return;
            }

            // 2. Standard error handling
            updateSyncStatus('error');
            $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-danger');
            stopSpinnerSafely();
        }
    });
}

function stopSpinnerSafely() {
    const minDisplayTime = 800; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeUI = () => {
        $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-gray-400');
        $('#refreshIcon').removeClass('fa-spin');
    };

    if (timeElapsed < minDisplayTime) {
        setTimeout(finalizeUI, minDisplayTime - timeElapsed);
    } else {
        finalizeUI();
    }
}

// ==============================================================================
// 3. UI DOM MANIPULATION
// ==============================================================================

function updateDashboardUI(data) {
    // Attendance & Clocking
    $('#card-clock-in').text(data.attendance.time_in);
    const statusElem = $('#card-clock-status');
    statusElem.text(data.attendance.status_label)
              .removeClass('text-success text-danger text-secondary text-primary')
              .addClass('text-' + data.attendance.status_color);

    // Overtime & Financials
    $('#card-ot-count').text(data.overtime.pending_count);
    $('#card-ot-hours').text(parseFloat(data.overtime.pending_hours).toFixed(1));
    
    // Loan Formatting (PHP Currency)
    const loanBalance = Number(data.loans.balance).toLocaleString('en-US', {
        style: 'currency',
        currency: 'PHP'
    });
    $('#card-loan-balance').text(loanBalance);
    $('#card-loan-status').text(data.loans.label);

    // Leaves Summary
    $('#card-leave-count').text(data.leave_stats.pending_count);
    $('#card-leave-days').text(data.leave_stats.pending_days);

    // Component Rendering
    renderHolidaysList(data.upcoming_holidays);
    renderLeaveChart(data.leave_balances);
    renderAttendanceChart(data.weekly_hours); 

}

function renderHolidaysList(holidays) {
    const container = $('#holidays-list-container');
    container.empty();
    if (!holidays || holidays.length === 0) {
        container.html(`<p class="text-center p-4 text-muted small">No upcoming holidays found.</p>`);
        return;
    }

    const today = new Date();
    today.setHours(0,0,0,0);

    let html = '';
    holidays.forEach(h => {
        const hDate = new Date(h.holiday_date);
        hDate.setHours(0,0,0,0);
        const diffDays = Math.ceil((hDate - today) / (1000 * 60 * 60 * 24));

        let badge = diffDays === 0 ? 'Today' : (diffDays === 1 ? 'Tomorrow' : `in ${diffDays} days`);
        let color = diffDays === 0 ? 'teal' : 'primary';

        html += `
            <div class="list-group-item d-flex align-items-center justify-content-between border-0 px-3 py-3 mb-2 bg-light rounded shadow-xs">
                <div class="d-flex align-items-center">
                    <div class="bg-white border rounded px-2 py-1 text-center me-3 shadow-sm" style="min-width: 55px;">
                        <div class="fw-bold h5 mb-0 text-dark">${hDate.getDate()}</div>
                        <div class="text-xs text-uppercase text-muted">${hDate.toLocaleDateString('en-US', {month: 'short'})}</div>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-gray-800">${h.holiday_name}</h6>
                        <span class="text-xs text-${color} fw-bold">${badge}</span>
                    </div>
                </div>
            </div>`;
    });
    container.html(html);
}

// ==============================================================================
// 4. CHART RENDERING (Chart.js v3/v4)
// ==============================================================================

function renderLeaveChart(balances) {
    const ctx = document.getElementById("modernLeaveChart");
    if (!ctx) return;

    const chartData = [balances.total_remaining, balances.total_used];

    if (leavePieChart) {
        leavePieChart.data.datasets[0].data = chartData;
        leavePieChart.update();
        return;
    }

    leavePieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ["Available", "Used"],
            datasets: [{
                data: chartData,
                backgroundColor: ['#0CC0DF', '#eaecf4'],
                hoverBackgroundColor: ['#0aa8c3', '#dddfeb'],
                borderWidth: 0
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '80%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: "#fff",
                    bodyColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: (ctx) => ` ${ctx.label}: ${ctx.raw} Days`
                    }
                }
            }
        }
    });
}

function renderAttendanceChart(weeklyData) {
    const ctx = document.getElementById("modernAttendanceChart");
    if (!ctx) return;
    
    if (attendanceLineChart) {
        attendanceLineChart.data.datasets[0].data = weeklyData;
        attendanceLineChart.update();
        return;
    }

    attendanceLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
            datasets: [{
                label: "Hours Worked",
                data: weeklyData,
                fill: true,
                tension: 0.4,
                borderColor: "#0CC0DF",
                backgroundColor: "rgba(12, 192, 223, 0.1)",
                pointBackgroundColor: "#0CC0DF",
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: { grid: { display: false, drawBorder: false } },
                y: { 
                    beginAtZero: true, 
                    max: 12,
                    ticks: { 
                        stepSize: 2, 
                        callback: (v) => v + 'h' 
                    },
                    grid: { color: "rgba(234, 236, 244, 0.5)", drawBorder: false }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: "#fff",
                    titleColor: "#6e707e",
                    bodyColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    padding: 12
                }
            }
        }
    });
}

// ==============================================================================
// 5. INITIALIZATION
// ==============================================================================

$(document).ready(function(){
    // Greeting Header
    const greet = () => {
        const h = new Date().getHours();
        let m = h < 12 ? "Good Morning ☀️" : (h < 18 ? "Good Afternoon 🌤️" : "Good Evening 🌙");
        const dateStr = new Date().toLocaleDateString('en-US', { 
            weekday: 'long', month: 'long', day: 'numeric' 
        });
        $('#status-message').html(`${m} &nbsp;|&nbsp; Today is ${dateStr}`);
    };
    
    greet();
    loadDashboardData();

    // Manual Refresh Event
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});