<script>
// ==============================================================================
// 1. GLOBAL STATE VARIABLES
// ==============================================================================
let spinnerStartTime = 0; // Global variable to track when the spin started
let payrollChart = null;  // Chart.js instance for Payroll
let deptChart = null;     // Chart.js instance for Department Distribution

// ==============================================================================
// 2. HELPER FUNCTIONS (Sync, Time, & UI)
// ==============================================================================

// 2.1 Updates the final timestamp text (e.g., "10:30:05 AM")
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 2.2 Stops the spinner safely (runs for at least 1000ms)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime();
    };

    if (timeElapsed < minDisplayTime) {
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        finalizeStop();
    }
}

// 2.3 Sets the Greeting and Current Date in the header
function setWelcomeMessage() {
    const now = new Date();
    const hrs = now.getHours();
    let greet = (hrs < 12) ? "Good Morning! ☀️" : ((hrs >= 12 && hrs <= 17) ? "Good Afternoon! 🌤️" : "Good Evening! 🌙");
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    $('#status-message').html(`${greet} &nbsp;|&nbsp; Today is ${now.toLocaleDateString('en-US', options)}`);
}

// ==============================================================================
// 3. RENDER FUNCTIONS (Updating specific UI blocks)
// ==============================================================================

function updateMetrics(metrics) {
    $('#val-active-employees').text(Number(metrics.active_employees).toLocaleString());
    $('#val-new-hires').text(Number(metrics.new_hires_month).toLocaleString());
    
    // Pending CA Logic
    $('#val-pending-ca').text(Number(metrics.pending_ca_count).toLocaleString());
    if(metrics.pending_ca_count > 0) {
        $('#status-pending-ca').html(`<a href="cashadv_approval.php" class="text-decoration-none text-muted">View Details &rarr;</a>`);
    } else {
        $('#status-pending-ca').html(`<span class="text-muted font-weight-bold">All Cleared</span>`);
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

    const labels = Object.keys(dataObj);
    const values = Object.values(dataObj);

    deptChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                // Using consistent, hardcoded colors for stability
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
// 4. MAIN DATA FETCHER (Triggered on load and refresh)
// ==============================================================================
function loadDashboardData() {
    // 1. Start Timer & Visual Feedback
    spinnerStartTime = new Date().getTime(); 
    const icon = $('#refresh-spinner');
    icon.addClass('fa-spin text-teal'); 
    $('#last-updated-time').text('Syncing...'); 

    $.ajax({
        url: 'api/get_dashboard_data.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            // Check for structure, though typically guaranteed by API
            if (response.metrics) {
                updateMetrics(response.metrics);
                renderPayrollChart(response.payroll_history);
                renderDeptChart(response.dept_data);
                renderLeavesList(response.upcoming_leaves);
                renderHolidaysList(response.upcoming_holidays);
            }
            
            // 2. Success: Stop spin & Update Time using the safe function
            stopSpinnerSafely();
            
            
        },
        error: function(err) {
            console.error("Error loading dashboard data", err);
            // On error, stop spin immediately and show error time
            $('#refresh-spinner').removeClass('fa-spin text-teal');
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit'});
            $('#last-updated-time').text(`Error @ ${timeString}`);
        }
    });
}


$(document).ready(function() {
    
    // Set the welcome message immediately
    setWelcomeMessage();

    // 1. Load data immediately when page opens
    loadDashboardData();

    // 2. CONNECT THE MASTER REFRESHER
    // This hook is used by the Topbar buttons to reload this specific page content.
    window.refreshPageContent = loadDashboardData;
});
</script>