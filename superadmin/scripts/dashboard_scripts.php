<script>
// ==============================================================================
// 1. GLOBAL STATE VARIABLES
// ==============================================================================
let spinnerStartTime = 0; // Tracks when the spin started to prevent flickering
let growthChart = null;   // Chart instance for User Growth
let roleChart = null;     // Chart instance for Role Distribution

// ==============================================================================
// 2. HELPER FUNCTIONS (Sync, Time, & UI)
// ==============================================================================

// 2.1 Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 2.2 Stops the spinner safely (runs for at least 500ms to avoid UI flickering)
function stopSpinnerSafely() {
    const icon = $('#refreshIcon');
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

// 2.3 Sets the Greeting and Current Date in the banner
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

function updateMetrics(d) {
    // Animate numbers (simple text replacement for now)
    $('#val-total-admins').text(d.total_admins);
    $('#val-total-employees').text(d.total_employees);
    $('#val-pending-users').text(d.pending_users);
    $('#val-active-today').text(d.active_today);
}

function renderGrowthChart(labels, data) {
    const ctx = document.getElementById("growthHistoryChart").getContext('2d');
    if(growthChart) growthChart.destroy();

    // Create Gradient (Matches the reference style)
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(12, 192, 223, 0.5)'); 
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0.0)'); 

    growthChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: "New Users",
                data: data || [0, 0, 0, 0, 0, 0],
                backgroundColor: gradient,
                borderColor: "#0CC0DF",
                pointBackgroundColor: "#ffffff",
                pointBorderColor: "#0CC0DF",
                pointHoverBackgroundColor: "#0CC0DF",
                pointHoverBorderColor: "#0CC0DF",
                fill: true,
                tension: 0.4
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
            scales: {
                x: { grid: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 7 } },
                y: { ticks: { maxTicksLimit: 5, padding: 10 }, grid: { color: "rgb(234, 236, 244)", zeroLineColor: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2], zeroLineBorderDash: [2] } },
            },
            plugins: { legend: { display: false } }
        }
    });
}

function renderRoleChart(data) {
    const ctx = document.getElementById("roleDistributionChart");
    if(roleChart) roleChart.destroy();

    // 1. Define the legend callback function outside the chart options, 
    // but structure it to receive the chart instance.
    const customLegendCallback = (chartInstance) => {
        const chartData = chartInstance.data;
        
        // CRITICAL SAFETY CHECK: Ensure datasets array exists
        if (!chartData || !Array.isArray(chartData.datasets) || chartData.datasets.length === 0) {
            console.warn("Chart data not ready when legendCallback ran.");
            return '<div>Chart data initializing...</div>'; 
        }

        const dataset = chartData.datasets[0];
        if (!Array.isArray(dataset.data) || dataset.data.length === 0) {
             return '<div>Data array empty...</div>';
        }
        
        const colors = dataset.backgroundColor;
        let html = '<ul class="role-chart-legend-list">';

        chartData.labels.forEach((label, index) => {
            if (index < dataset.data.length && dataset.data[index] != null) {
                // Attach the index for interactivity
                html += `
                    <li data-index="${index}">
                        <span style="background-color:${colors[index]}"></span>
                        ${label}
                    </li>
                `;
            }
        });
        html += '</ul>';
        return html;
    };
    
    // 2. Function to inject the legend HTML
    const injectLegend = (chartInstance) => {
        const legendContainer = $('#role-chart-legend');
        
        // **FIX: Call the externally defined custom function directly.**
        const legendHtml = customLegendCallback(chartInstance); 
        
        legendContainer.html(legendHtml);
        
        // Re-attach click listeners (Optional: for toggling visibility)
        legendContainer.find('li').on('click', function() {
            const index = $(this).data('index');
            
            // Toggle visibility using the most compatible method
            const meta = chartInstance.getDatasetMeta(0);
            if (meta && meta.data[index]) {
                meta.data[index].hidden = !meta.data[index].hidden;
            } else if (chartInstance.toggleDataVisibility) {
                 chartInstance.toggleDataVisibility(index);
            }
            
            chartInstance.update();
            $(this).toggleClass('strikethrough-legend');
        });
    };


    roleChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ["Admins", "Employees", "Super Admins"],
            datasets: [{
                data: data || [0, 0, 0],
                backgroundColor: ['#4e73df', '#1cc88a', '#e74a3b'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#be2617'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
                borderWidth: 5,
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: { 
                backgroundColor: "rgb(255,255,255)", 
                bodyFontColor: "#858796", 
                borderColor: '#dddfeb', 
                borderWidth: 1, 
                xPadding: 15, 
                yPadding: 15, 
                displayColors: false, 
                caretPadding: 10 
            },
            plugins: { 
                legend: { 
                    display: false // Hide Chart.js native legend
                } 
            }, 
            cutout: '75%',
            
            // 3. Keep the custom callback definition, but it's only used internally by the chart now.
            legendCallback: customLegendCallback, 
            
            // 4. Attach the injection function to the chart's draw/animation complete hook
            animation: {
                onComplete: function(animation) {
                    // Call the separate injection function defined above
                    injectLegend(animation.chart);
                }
            },
        },
    });
}

function renderRecentUsers(users) {
    const container = $('#list-recent-users');
    let html = '';

    if(users && users.length > 0) {
        users.forEach(user => {
            let roleBadge = user.usertype == 1 ? '<span class="badge bg-primary">Admin</span>' : 
                           (user.usertype == 2 ? '<span class="badge bg-success">Emp</span>' : '<span class="badge bg-danger">Super</span>');
            
            let dateStr = new Date(user.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric'});

            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                    <div>
                        <div class="fw-bold text-dark">${user.email}</div>
                        <div class="small text-muted">${dateStr}</div>
                    </div>
                    <div>${roleBadge}</div>
                </div>
            `;
        });
    } else {
        html = '<div class="p-4 text-center text-muted">No recent registrations.</div>';
    }
    container.html(html);
}

function renderRecentLogs(logs) {
    const container = $('#list-recent-logs');
    let html = '';

    if(logs && logs.length > 0) {
        logs.forEach(log => {
            let iconClass = 'fa-info-circle text-secondary';
            if(log.action.includes('LOGIN')) iconClass = 'fa-key text-success';
            if(log.action.includes('DELETE')) iconClass = 'fa-trash text-danger';
            if(log.action.includes('UPDATE')) iconClass = 'fa-pen text-warning';

            html += `
                <div class="list-group-item d-flex align-items-center px-4 py-3 border-bottom">
                    <div class="me-3"><i class="fas ${iconClass}"></i></div>
                    <div class="w-100">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold text-dark text-xs text-uppercase">${log.action}</span>
                            <span class="text-xs text-muted">${new Date(log.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span>
                        </div>
                        <div class="text-xs text-muted text-truncate" style="max-width: 250px;">
                            ${log.user} - ${log.details}
                        </div>
                    </div>
                </div>
            `;
        });
    } else {
        html = '<div class="p-4 text-center text-muted">No recent activity.</div>';
    }
    container.html(html);
}


// ==============================================================================
// 4. MAIN DATA FETCHER
// ==============================================================================
function loadDashboardData() {
    // 1. Start Timer & Visual Feedback
    spinnerStartTime = new Date().getTime(); 
    const icon = $('#refresh-spinner');
    icon.addClass('fa-spin text-teal'); 
    $('#last-updated-time').text('Syncing...'); 
    
    // NOTE: We don't overwrite the welcome message here, 
    // we let setWelcomeMessage() handle the initial text.

    $.ajax({
        url: 'api/dashboard_action.php',
        type: 'POST',
        data: { action: 'fetch_metrics' },
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                const d = res.data;

                // Call Render Functions
                updateMetrics(d);
                renderRecentUsers(d.recent_users);
                renderRecentLogs(d.recent_logs);
                renderGrowthChart(d.chart_growth_labels, d.chart_growth_data);
                renderRoleChart(d.chart_role_data);
            }
                // Stop Spinner Safely
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

// ==============================================================================
// 5. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    // Set greeting immediately
    setWelcomeMessage();
    
    // Load Data
    loadDashboardData();

    // Attach to global scope if needed for other buttons
    window.refreshPageContent = loadDashboardData;
});
</script>