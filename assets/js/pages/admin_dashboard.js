/**
 * Admin Dashboard Controller
 * Handles metrics, Chart.js rendering, and real-time synchronization.
 */

// ==============================================================================
// 1. GLOBAL STATE VARIABLES
// ==============================================================================
let payrollChart = null; 
let deptChart = null;    

// ==============================================================================
// 2. UI HELPERS (Sync Status & Greetings)
// ==============================================================================

function updateSyncStatus(state) {
    const $dot = $('.live-dot');
    const $text = $('#last-updated-time');
    const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    $dot.removeClass('text-success text-warning text-danger');

    if (state === 'loading') {
        $text.text('Syncing...');
        $dot.addClass('text-warning'); 
    } 
    else if (state === 'success') {
        $text.text(`Synced: ${time}`);
        $dot.addClass('text-success'); 
    } 
    else {
        $text.text(`Failed: ${time}`);
        $dot.addClass('text-danger');  
    }
}

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
// 3. RENDER FUNCTIONS (Charts & Lists)
// ==============================================================================

function updateMetrics(metrics) {
    $('#val-active-employees').text(Number(metrics.active_employees).toLocaleString());
    $('#val-new-hires').text(Number(metrics.new_hires_month).toLocaleString());
    $('#val-pending-ca').text(Number(metrics.pending_ca_count).toLocaleString());
    $('#val-pending-leaves').text(Number(metrics.pending_leave_count).toLocaleString());
    
    // Attendance Progress Circle or Text
    $('#val-attendance-today').text(metrics.attendance_today);
    $('#val-attendance-total').text(metrics.active_employees);
}

function renderPayrollChart(history) {
    const ctx = document.getElementById("payrollHistoryChart").getContext('2d');
    if(payrollChart) payrollChart.destroy();

    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(78, 115, 223, 0.2)'); 
    gradient.addColorStop(1, 'rgba(78, 115, 223, 0)'); 

    payrollChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: history.labels,
            datasets: [{
                label: "Total Payout",
                data: history.data, 
                backgroundColor: gradient,
                borderColor: "#4e73df",
                pointRadius: 3,
                pointBackgroundColor: "#4e73df",
                pointBorderColor: "#4e73df",
                pointHoverRadius: 5,
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
    if(!canvas) return;
    const ctx = canvas.getContext('2d');
    if(deptChart) deptChart.destroy();

    deptChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(dataObj),
            datasets: [{
                data: Object.values(dataObj),
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'], 
                hoverOffset: 4
            }],
        },
        options: {
            maintainAspectRatio: false,
            cutout: '70%', 
            plugins: { legend: { display: true, position: 'bottom', labels: { usePointStyle: true, padding: 20 } } }
        },
    });
}

// ==============================================================================
// 4. DATA FETCHER
// ==============================================================================

function loadDashboardData(isManual = false) {
    if(isManual) $('#refreshIcon').addClass('fa-spin'); 
    updateSyncStatus('loading');

    $.ajax({
        // ⭐ UPDATED API PATH
        url: '../api/admin/dashboard_data.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateMetrics(response.metrics);
                renderPayrollChart(response.payroll_history);
                renderDeptChart(response.dept_data);
                
                // Helper functions for lists (Assuming defined or empty-checked)
                if (typeof renderLeavesList === "function") renderLeavesList(response.upcoming_leaves);
                if (typeof renderHolidaysList === "function") renderHolidaysList(response.upcoming_holidays);
                
                updateSyncStatus('success');
            } else {
                updateSyncStatus('error');
            }
        },
        error: function() {
            updateSyncStatus('error');
        },
        complete: function() {
            if(isManual) setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 600);
        }
    });
}

// ==============================================================================
// 5. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    setWelcomeMessage();
    loadDashboardData(true);

    // Global hook for Master Refresher (footer.php)
    window.refreshPageContent = function(isManual) {
        loadDashboardData(isManual);
    };

    // Manual Refresh Button
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault(); 
        loadDashboardData(true);
    });
});