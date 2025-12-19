// assets/js/pages/superadmin_dashboard.js

// ==============================================================================
// 1. STATE & VARIABLES
// ==============================================================================
let growthChart = null;   
let roleChart = null;     

// ==============================================================================
// 2. UI HELPER FUNCTIONS
// ==============================================================================

/**
 * Updates the Topbar Status (Text + Dot Color) in the Topbar
 */
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
    const hrs = new Date().getHours();
    const greet = (hrs < 12) ? "Good Morning! ☀️" : ((hrs < 18) ? "Good Afternoon! 🌤️" : "Good Evening! 🌙");
    const date = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    if($('#status-message').length) {
        $('#status-message').html(`${greet} &nbsp;|&nbsp; Today is ${date}`);
    }
}

// ==============================================================================
// 3. RENDER FUNCTIONS
// ==============================================================================

function renderCharts(d) {
    // --- Growth Chart ---
    const ctxGrowth = document.getElementById("growthHistoryChart");
    if (ctxGrowth) {
        if (growthChart) growthChart.destroy();
        const ctx = ctxGrowth.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 400);
        grad.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); grad.addColorStop(1, 'rgba(255, 255, 255, 0.0)');
        
        growthChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: d.chart_growth_labels,
                datasets: [{ label: "New Users", data: d.chart_growth_data, backgroundColor: grad, borderColor: "#0CC0DF", fill: true, tension: 0.4 }]
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } } } }
        });
    }

    // --- Role Chart ---
    const ctxRole = document.getElementById("roleDistributionChart");
    if (ctxRole) {
        if (roleChart) roleChart.destroy();
        roleChart = new Chart(ctxRole.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ["Admins", "Employees", "Super Admins"],
                datasets: [{ data: d.chart_role_data, backgroundColor: ['#4e73df', '#1cc88a', '#e74a3b'], borderWidth: 5 }]
            },
            options: { maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false } } }
        });
    }
}

function renderLists(d) {
    // --- Metrics ---
    $('#val-total-admins').text(d.total_admins);
    $('#val-total-employees').text(d.total_employees);
    $('#val-pending-users').text(d.pending_users);
    $('#val-active-today').text(d.active_today);

    // --- Recent Users ---
    const userHtml = d.recent_users.map(u => {
        const badge = u.usertype == 1 ? '<span class="badge bg-primary">Admin</span>' : (u.usertype == 2 ? '<span class="badge bg-success">Emp</span>' : '<span class="badge bg-danger">Super</span>');
        const date = new Date(u.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric'});
        return `<div class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                    <div><div class="fw-bold text-dark">${u.email}</div><div class="small text-muted">${date}</div></div>
                    <div>${badge}</div>
                </div>`;
    }).join('') || '<div class="p-4 text-center text-muted">No recent registrations.</div>';
    $('#list-recent-users').html(userHtml);

    // --- Recent Logs ---
    const logHtml = d.recent_logs.map(l => {
        let icon = l.action.includes('LOGIN') ? 'fa-key text-success' : (l.action.includes('DELETE') ? 'fa-trash text-danger' : 'fa-info-circle text-secondary');
        return `<div class="list-group-item d-flex align-items-center px-4 py-3 border-bottom">
                    <div class="me-3"><i class="fas ${icon}"></i></div>
                    <div class="w-100">
                        <div class="d-flex justify-content-between"><span class="fw-bold text-dark text-xs">${l.action}</span><span class="text-xs text-muted">${new Date(l.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span></div>
                        <div class="text-xs text-muted text-truncate" style="max-width: 250px;">${l.user} - ${l.details}</div>
                    </div>
                </div>`;
    }).join('') || '<div class="p-4 text-center text-muted">No recent activity.</div>';
    $('#list-recent-logs').html(logHtml);
}

// ==============================================================================
// 4. MAIN DATA FETCHER
// ==============================================================================
function loadDashboardData(isManual = false) {
    
    // 1. Visuals: Only spin icon if clicked manually
    if (isManual) $('#refreshIcon').addClass('fa-spin');
    
    // 2. Set status to "Syncing..."
    updateSyncStatus('loading');

    $.ajax({
        // API Path relative to the PHP page executing this script
        url: '../api/superadmin/dashboard_stats.php',
        type: 'POST',
        data: { action: 'fetch_metrics' },
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                renderLists(res.data);
                renderCharts(res.data);
                updateSyncStatus('success');
            } else {
                updateSyncStatus('error');
            }
        },
        error: function(err) {
            console.error("Dashboard Sync Error:", err);
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
    
    // Initial Load (Visual)
    loadDashboardData(true);

    // Expose to Master Refresher (Global Footer)
    window.refreshPageContent = function(isManual) {
        loadDashboardData(isManual);
    };

    // Manual Click Listener
    // Note: We target the parent of #refreshIcon because usually the <a> or <div> is the clickable element
    $('#refreshIcon').closest('a, div').on('click', function(e) {
        e.preventDefault(); 
        loadDashboardData(true);
    });
});