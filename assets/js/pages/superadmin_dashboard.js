/**
 * Super Admin Dashboard Controller
 * Handles charts, metrics, and real-time syncing via the Global AppUtility.
 */

// ==============================================================================
// 1. STATE & VARIABLES
// ==============================================================================
let growthChart = null; 
let roleChart = null;     

// ==============================================================================
// 2. UI HELPER FUNCTIONS
// ==============================================================================
function setWelcomeMessage() {
    const hrs = new Date().getHours();
    const greet = (hrs < 12) ? "Good Morning! ☀️" : ((hrs < 18) ? "Good Afternoon! 🌤️" : "Good Evening! 🌙");
    const date = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    if($('#status-message').length) {
        $('#status-message').html(`${greet} &nbsp;|&nbsp; Today is ${date}`);
    }
}

// ==============================================================================
// 3. RENDER FUNCTIONS (Charts & Lists)
// ==============================================================================
function renderCharts(d) {
    // A. Growth History Chart (Line)
    const ctxGrowth = document.getElementById("growthHistoryChart");
    if (ctxGrowth) {
        if (growthChart) growthChart.destroy();
        const ctx = ctxGrowth.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 400);
        grad.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); 
        grad.addColorStop(1, 'rgba(255, 255, 255, 0.0)');
        
        growthChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: d.chart_growth_labels,
                datasets: [{ 
                    label: "New Users", 
                    data: d.chart_growth_data, 
                    backgroundColor: grad, 
                    borderColor: "#0CC0DF", 
                    fill: true, 
                    tension: 0.4 
                }]
            },
            options: { 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } }, 
                scales: { x: { grid: { display: false } } } 
            }
        });
    }

    // B. Role Distribution Chart (Doughnut)
    const ctxRole = document.getElementById("roleDistributionChart");
    if (ctxRole) {
        if (roleChart) roleChart.destroy();
        roleChart = new Chart(ctxRole.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ["Admins", "Employees", "Super Admins"],
                datasets: [{ 
                    data: d.chart_role_data, 
                    backgroundColor: ['#4e73df', '#1cc88a', '#e74a3b'], 
                    borderWidth: 5 
                }]
            },
            options: { 
                maintainAspectRatio: false, 
                cutout: '75%', 
                plugins: { legend: { display: false } } 
            }
        });
    }
}

function renderLists(d) {
     // A. Summary Cards
     $('#val-total-admins').text(d.total_admins);
     $('#val-total-employees').text(d.total_employees);
     $('#val-pending-users').text(d.pending_users);
     $('#val-active-today').text(d.active_today);

     // B. Recent Registrations
     const userHtml = d.recent_users.map(u => {
        let badgeClass = 'bg-secondary'; let badgeText = 'User';
        if(u.usertype == 1) { badgeClass = 'bg-primary'; badgeText = 'Admin'; }
        else if(u.usertype == 2) { badgeClass = 'bg-success'; badgeText = 'Emp'; }
        else if(u.usertype == 0) { badgeClass = 'bg-danger'; badgeText = 'Super'; }
        
        const date = new Date(u.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric'});
        
        return `<div class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                    <div>
                        <div class="fw-bold text-dark">${u.email}</div>
                        <div class="small text-muted">${date}</div>
                    </div>
                    <div><span class="badge ${badgeClass}">${badgeText}</span></div>
                </div>`;
    }).join('') || '<div class="p-4 text-center text-muted">No recent registrations.</div>';
    $('#list-recent-users').html(userHtml);

    // C. Recent Audit Logs
    const logHtml = d.recent_logs.map(l => {
        let icon = 'fa-info-circle text-secondary';
        if (l.action.includes('LOGIN')) icon = 'fa-key text-success';
        if (l.action.includes('DELETE')) icon = 'fa-trash text-danger';
        
        const time = new Date(l.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        
        return `<div class="list-group-item d-flex align-items-center px-4 py-3 border-bottom">
                    <div class="me-3"><i class="fas ${icon}"></i></div>
                    <div class="w-100">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold text-dark text-xs">${l.action}</span>
                            <span class="text-xs text-muted">${time}</span>
                        </div>
                        <div class="text-xs text-muted text-truncate" style="max-width: 250px;">
                            ${l.user} - ${l.details}
                        </div>
                    </div>
                </div>`;
    }).join('') || '<div class="p-4 text-center text-muted">No recent activity.</div>';
    $('#list-recent-logs').html(logHtml);
}

// ==============================================================================
// 4. MAIN DATA FETCHER (Integrated with AppUtility)
// ==============================================================================
function loadDashboardData(isManual = false) {
    
    // 1. Trigger Visuals via Global Utility
    if (window.AppUtility) {
        window.AppUtility.updateSyncStatus('loading');
    }

    $.ajax({
        // Uses global API_ROOT defined in footer.php
        url: API_ROOT + '/superadmin/dashboard_stats.php', 
        type: 'POST',
        data: { action: 'fetch_metrics' },
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                renderLists(res.data);
                renderCharts(res.data);
                
                // Success State
                if (window.AppUtility) window.AppUtility.updateSyncStatus('success');

            } else {
                console.error("Dashboard API Error:", res.message);
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        error: function(xhr, status, error) {
            console.error("Dashboard Sync Failed:", error);
            if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
        }
    });
}

// ==============================================================================
// 5. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    setWelcomeMessage();
    
    // 1. Initial Load
    loadDashboardData(false);

    // 2. Register Global Refresher Hook
    // The footer will call this automatically every 15 seconds or on manual click
    window.refreshPageContent = function(isManual) {
        loadDashboardData(isManual);
    };
});