<script>
    // --- Global Chart Instances ---
    let leavePieChart = null;
    let attendanceLineChart = null;

    // --- Spinner Timer Variable ---
    let spinnerStartTime = 0; 

    $(document).ready(function(){

        // ============================================================
        //  1. HELPER: VISUAL SYNC FEEDBACK (Topbar)
        // ============================================================
        function stopSpinnerSafely() {
            const minDisplayTime = 1000; 
            const timeElapsed = new Date().getTime() - spinnerStartTime;

            const updateTime = () => {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                $('#last-updated-time').text(timeString);
                $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-gray-400');
            };

            if (timeElapsed < minDisplayTime) {
                setTimeout(updateTime, minDisplayTime - timeElapsed);
            } else {
                updateTime();
            }
        }

        // ============================================================
        //  2. MAIN DATA LOADER
        // ============================================================
        function loadDashboardData() {
            spinnerStartTime = new Date().getTime(); 
            
            $('#refresh-spinner').removeClass('text-gray-400').addClass('fa-spin text-teal');
            $('#last-updated-time').text('Syncing...');

            $.ajax({
                url: 'api/get_employee_dashboard_data.php', 
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        updateDashboardUI(response.data);
                    }
                    stopSpinnerSafely();
                },
                error: function(err) { 
                    console.error("Dashboard sync error", err); 
                    $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-danger');
                    $('#last-updated-time').text('Error');
                }
            });
        }

        // ============================================================
        //  3. UI UPDATERS
        // ============================================================
        function updateDashboardUI(data) {
            // Text Cards
            $('#card-clock-in').text(data.attendance.time_in);
            
            const statusElem = $('#card-clock-status');
            statusElem.text(data.attendance.status_label);
            statusElem.removeClass('text-teal text-danger text-secondary text-primary');
            statusElem.addClass('text-' + (data.attendance.status_color === 'success' ? 'teal' : data.attendance.status_color));

            $('#card-ot-count').text(data.overtime.pending_count);
            $('#card-ot-hours').text(data.overtime.pending_hours);

            $('#card-loan-balance').text('₱' + Number(data.loans.balance).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#card-loan-status').text(data.loans.label);

            $('#card-leave-count').text(data.leave_stats.pending_count);
            $('#card-leave-days').text(data.leave_stats.pending_days);

            // Lists & Charts
            renderHolidaysList(data.upcoming_holidays);
            renderLeaveChart(data.leave_balances);
            renderAttendanceChart(data.weekly_hours); 
        }

        function renderHolidaysList(holidays) {
            const container = $('#holidays-list-container');
            container.empty();
            if (holidays.length === 0) {
                container.html(`<p class="text-center p-4 text-muted small">No upcoming holidays found.</p>`);
                return;
            }
            let html = '';
            
            // Get today's date (reset time)
            const today = new Date();
            today.setHours(0,0,0,0);

            holidays.forEach(holiday => {
                const holDate = new Date(holiday.holiday_date);
                holDate.setHours(0,0,0,0);
                
                const diffTime = holDate - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                let badgeText = '';
                let badgeClass = 'bg-soft-teal text-teal';

                if (diffDays === 0) {
                    badgeText = 'Today';
                    badgeClass = 'bg-soft-teal text-teal font-weight-bold';
                } else if (diffDays === 1) {
                    badgeText = 'Tomorrow';
                    badgeClass = 'bg-soft-teal text-teal';
                } else {
                    badgeText = `in ${diffDays} days`;
                }

                const day = holDate.toLocaleDateString('en-US', {day: 'numeric'});
                const month = holDate.toLocaleDateString('en-US', {month: 'short'});

                html += `
                    <div class="list-group-item d-flex align-items-center justify-content-between px-3 py-3">
                        <div class="d-flex align-items-center">
                            <div class="${badgeClass} rounded px-3 py-2 text-center me-3" style="min-width: 60px;">
                                <div class="font-weight-bold h5 mb-0">${day}</div>
                                <div class="small text-uppercase" style="font-size: 0.65rem;">${month}</div>
                            </div>
                            <div>
                                <h6 class="mb-0 text-gray-800 font-weight-bold">${holiday.holiday_name}</h6>
                                <span class="badge bg-light text-muted border">${badgeText}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.html(html);
        }

        // ============================================================
        //  4. CHART RENDERING (FIXED FOR CHART.JS V3/V4)
        // ============================================================
        function renderLeaveChart(balances) {
            const ctx = document.getElementById("modernLeaveChart");
            if (!ctx) return;

            // Prepare Data: Total Remaining vs Total Used
            const dataValues = [balances.total_remaining, balances.total_used];

            if (leavePieChart) {
                leavePieChart.data.datasets[0].data = dataValues;
                leavePieChart.update();
                return;
            }

            leavePieChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ["Available", "Used"], // Changed labels
                    datasets: [{
                        data: dataValues,
                        // Teal for Available, Light Gray for Used
                        backgroundColor: ['#0CC0DF', '#e3e6f0'], 
                        hoverBackgroundColor: ['#17a2b8', '#d1d3e2'],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyColor: "#858796",
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            padding: 15,
                            displayColors: true, // Show color box in tooltip
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    let value = context.parsed;
                                    // Calculate percentage for tooltip
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? Math.round((value / total) * 100) + '%' : '0%';
                                    return label + value + " days (" + percentage + ")";
                                }
                            }
                        }
                    },
                    cutout: '75%', 
                },
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

            const safeData = weeklyData || [0, 0, 0, 0, 0, 0, 0];

            attendanceLineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
                    datasets: [{
                        label: "Hours Worked",
                        tension: 0.3, 
                        backgroundColor: "rgba(32, 201, 151, 0.05)",
                        borderColor: "rgba(12, 192, 223, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(12, 192, 223, 1)",
                        pointBorderColor: "rgba(12, 192, 223, 1)",
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: "rgba(12, 192, 223, 1)",
                        pointHoverBorderColor: "rgba(12, 192, 223, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        data: safeData, 
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                    scales: {
                        // FIX: Updated to V3/V4 Syntax (Objects instead of Arrays)
                        x: { 
                            grid: { display: false, drawBorder: false }, 
                            ticks: { maxTicksLimit: 7 } 
                        },
                        y: { 
                            ticks: { 
                                maxTicksLimit: 5, 
                                padding: 10, 
                                callback: function(value) { return value + 'h'; } 
                            }, 
                            grid: { 
                                color: "rgb(234, 236, 244)", 
                                drawBorder: false, 
                                borderDash: [2] 
                            } 
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyColor: "#858796",
                            titleColor: '#6e707e',
                            titleFont: { size: 14 },
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            padding: 15,
                            displayColors: false,
                            intersect: false,
                            mode: 'index',
                            caretPadding: 10,
                        }
                    }
                }
            });
        }
        
        // ============================================================
        //  5. INITIALIZATION & FOOTER LINK
        // ============================================================
        
        loadDashboardData();
        window.refreshPageContent = loadDashboardData;

        // Welcome Msg
        function setWelcomeMessage() {
            const now = new Date();
            const hrs = now.getHours();
            let greet = (hrs < 12) ? "Good Morning! ☀️" : ((hrs >= 12 && hrs <= 17) ? "Good Afternoon! 🌤️" : "Good Evening! 🌙");
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            $('#status-message').html(`${greet} &nbsp;|&nbsp; Today is ${now.toLocaleDateString('en-US', options)}`);
        }
        setWelcomeMessage();
    }); 
</script>