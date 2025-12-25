/**
 * Admin Dashboard Controller
 * Handles metrics, Chart.js rendering, and real-time synchronization.
 * Integrated with Global AppUtility for Topbar syncing.
 */

// ==============================================================================
// 1. GLOBAL STATE VARIABLES
// ==============================================================================
let payrollChart = null; 
let deptChart = null;    

// ==============================================================================
// 2. UI HELPERS (Greetings)
// ==============================================================================

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
// 3. RENDER FUNCTIONS (Charts & Metrics)
// ==============================================================================

function updateMetrics(metrics) {
    $('#val-active-employees').text(Number(metrics.active_employees).toLocaleString());
    $('#val-new-hires').text(Number(metrics.new_hires_month).toLocaleString());
    $('#val-pending-ca').text(Number(metrics.pending_ca_count).toLocaleString());
    $('#val-pending-leaves').text(Number(metrics.pending_leave_count).toLocaleString());
    
    // Attendance Progress
    $('#val-attendance-today').text(metrics.attendance_today);
    $('#val-attendance-total').text(metrics.active_employees);
}

function renderPayrollChart(history) {
    const canvas = document.getElementById("payrollHistoryChart");
    if(!canvas) return;
    const ctx = canvas.getContext('2d');
    if(payrollChart) payrollChart.destroy();

    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(12, 192, 223, 0.2)'); 
    gradient.addColorStop(1, 'rgba(12, 192, 223, 0)'); 

    payrollChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: history.labels,
            datasets: [{
                label: "Total Payout",
                data: history.data, 
                backgroundColor: gradient,
                borderColor: "#0CC0DF",
                pointRadius: 3,
                pointBackgroundColor: "#0CC0DF",
                pointBorderColor: "#0CC0DF",
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { 
                    ticks: { callback: function(value) { return '₱' + value.toLocaleString(); } },
                    grid: { color: "rgba(234, 236, 244, 1)", drawBorder: false }
                },
                x: { grid: { display: false, drawBorder: false } }
            }
        }
    });
}

function renderDeptChart(dataObj) {
    const canvas = document.getElementById("deptDistributionChart");
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (deptChart) deptChart.destroy();

    const labels = Object.keys(dataObj);
    const counts = Object.values(dataObj);
    const total = counts.reduce((a, b) => a + b, 0);
    const colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];

    // 1. Render the Chart (No Labels/Legend)
    deptChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors,
                hoverBorderColor: "rgba(234, 236, 244, 1)",
                borderWidth: 2,
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '80%', // Thinner doughnut for a more modern look
            plugins: {
                legend: { display: false }, // ⭐ REMOVED LABELS
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: function(item) {
                            return ` ${item.label}: ${item.raw} users`;
                        }
                    }
                }
            },
        },
    });

    // 2. Inject Minimalist Summary List
    let summaryHtml = '<div class="row no-gutters">';
    labels.slice(0, 3).forEach((label, index) => { // Show top 3 departments
        const percent = Math.round((counts[index] / total) * 100);
        summaryHtml += `
            <div class="col-4 text-center">
                <div class="small text-gray-500 text-truncate px-1">${label}</div>
                <div class="font-weight-bold text-dark">
                    <i class="fas fa-circle me-1" style="color: ${colors[index]}; font-size: 8px;"></i>${percent}%
                </div>
            </div>`;
    });
    summaryHtml += '</div>';
    $('#dept-summary-list').html(summaryHtml);
}

function renderLeavesList(leaves) {
    const container = $('#list-upcoming-leaves');
    if (!leaves || leaves.length === 0) {
        container.html('<div class="text-center py-4 text-muted small">No approved leaves scheduled.</div>');
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    leaves.forEach(item => {
        // Fallback logic to prevent "undefined"
        const displayName = item.fullname ? item.fullname : `Employee #${item.employee_id}`;
        const profileImg = item.photo ? `../assets/images/profiles/${item.photo}` : `../assets/images/users/default.png`;
        
        html += `
            <div class="list-group-item px-0 py-3 bg-transparent border-bottom">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <img src="${profileImg}" class="rounded-circle shadow-sm" width="35" height="35" onerror="this.src='../assets/images/users/default.png'">
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-sm font-weight-bold text-dark">${displayName}</h6>
                        <small class="text-muted">${item.leave_type || 'General Leave'}</small>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-soft-teal px-2 py-1 mb-1" style="font-size: 0.7rem;">
                            <i class="far fa-calendar-alt me-1"></i> ${new Date(item.start_date).toLocaleDateString('en-US', {month:'short', day:'numeric'})}
                        </div>
                        <div class="text-xs text-muted">${item.duration_days || 1} day(s)</div>
                    </div>
                </div>
            </div>`;
    });
    html += '</div>';
    container.html(html);
}

function renderHolidaysList(holidays) {
    const container = $('#list-upcoming-holidays');
    if (!holidays || holidays.length === 0) {
        container.html('<div class="text-center py-4 text-muted small">No company holidays found.</div>');
        return;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0); // Normalize time to midnight for accurate day counting

    let html = '<div class="list-group list-group-flush">';
    holidays.forEach(h => {
        const hDate = new Date(h.holiday_date);
        hDate.setHours(0, 0, 0, 0);

        // Calculate Days Left
        const diffTime = hDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        // Determine Badge Style
        let daysLeftHtml = '';
        if (diffDays === 0) {
            daysLeftHtml = '<span class="badge bg-danger pulse-red animate__animated animate__pulse animate__infinite">TODAY</span>';
        } else if (diffDays === 1) {
            daysLeftHtml = '<span class="badge bg-warning text-dark">TOMORROW</span>';
        } else {
            daysLeftHtml = `<span class="text-muted small fw-bold">${diffDays} days left</span>`;
        }

        const isRegular = h.holiday_type.toLowerCase().includes('regular');
        
        html += `
            <div class="list-group-item px-0 py-3 bg-transparent border-bottom">
                <div class="d-flex align-items-center">
                    <div class="calendar-icon-mini me-3 text-center">
                        <div class="cal-month text-uppercase bg-danger text-white">${hDate.toLocaleDateString('en-US', {month:'short'})}</div>
                        <div class="cal-day bg-light text-dark font-weight-bold border border-top-0">${hDate.getDate()}</div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-sm font-weight-bold text-dark">${h.holiday_name}</h6>
                        <small class="${isRegular ? 'text-teal' : 'text-warning'} text-xs font-weight-bold">
                            <i class="fas fa-tag me-1"></i> ${h.holiday_type}
                        </small>
                    </div>
                    <div class="text-end">
                        ${daysLeftHtml}
                        <small class="text-muted d-block text-xs mt-1">${hDate.toLocaleDateString('en-US', {weekday:'long'})}</small>
                    </div>
                </div>
            </div>`;
    });
    html += '</div>';
    container.html(html);
}

// ==============================================================================
// 4. DATA FETCHER
// ==============================================================================

function loadDashboardData(isManual = false) {
    // Notify Global AppUtility of Loading state
    if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

    $.ajax({
        url: API_ROOT + '/admin/dashboard_data.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateMetrics(response.metrics);
                renderPayrollChart(response.payroll_history);
                renderDeptChart(response.dept_data);
                
                if (typeof renderLeavesList === "function") renderLeavesList(response.upcoming_leaves);
                if (typeof renderHolidaysList === "function") renderHolidaysList(response.upcoming_holidays);
                
                // Notify Global AppUtility of Success
                if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            } else {
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        error: function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
        }
    });
}

// ==============================================================================
// 5. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    setWelcomeMessage();
    loadDashboardData(false); // Initial load is quiet

    // Global hook for Master Refresher (footer.php)
    window.refreshPageContent = function(isManual) {
        loadDashboardData(isManual);
    };

    // Manual Refresh Button (Legacy support for local btn-refresh)
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault(); 
        loadDashboardData(true);
    });
});