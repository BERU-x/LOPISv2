<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
let attendanceTable;
let spinnerStartTime = 0; // Global variable to track when the spin started

// 1.1 HELPER: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.2 HELPER: Stops the spinner safely (waits for minDisplayTime = 1000ms)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    if (timeElapsed < minDisplayTime) {
        // Wait the remainder before stopping
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        // Stop immediately
        finalizeStop();
    }
}

// 1.3 MASTER FUNCTION: Fetches data and updates table/metrics
function reloadAttendanceData() {
    spinnerStartTime = new Date().getTime(); // 1. Record Start Time
    const icon = $('#refresh-spinner');
    icon.addClass('fa-spin text-teal'); 

    // 2. Start Syncing Text
    $('#last-updated-time').text('Syncing...'); 

    $.ajax({
        url: 'api/get_today_attendance.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            
            // A. Update Stats Cards
            $('#val-present').text(response.stats.present);
            $('#val-total').text(response.stats.total_employees);
            $('#val-absent').text(response.stats.absent);
            $('#val-late').text(response.stats.late);
            $('#val-ontime').text(response.stats.ontime);

            // B. Update Table Data
            attendanceTable.clear(); 
            if (response.logs && response.logs.length > 0) {
                attendanceTable.rows.add(response.logs); 
            }
            attendanceTable.draw(false); 

            // C. CRITICAL: Hand off cleanup to the safe timer function
            stopSpinnerSafely(); 
        },
        error: function(err) {
            console.error("Error fetching attendance:", err);
            
            // On Error, stop immediate (no waiting)
            const icon = $('#refresh-spinner');
            icon.removeClass('fa-spin text-teal');
            
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit'});
            $('#last-updated-time').text(`Error @ ${timeString}`);
        }
    });
}


$(document).ready(function() {
    
    // ==============================================================================
    // 2. DATATABLE INITIALIZATION (Client-Side Mode)
    // ==============================================================================
    attendanceTable = $('#todayTable').DataTable({
        // General Configuration
        "data": [], // Start empty, filled by AJAX
        "order": [[ 1, "desc" ]], 
        "pageLength": 25,
        "dom": 'rtip', 
        "language": { "emptyTable": "No attendance records found for today." },
        "responsive": true,

        // Column Definitions
        "columns": [
            // Col 1: Employee Details (Name, Photo, Dept, Code)
            { 
                data: null,
                className: 'align-middle',
                render: function (data, type, row) {
                    // Ensure image path is safe and handles missing photo
                    let photo = row.photo && row.photo !== 'default.png' ? `../assets/images/${row.photo}` : '../assets/images/default.png';
                    
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" 
                                style="width: 40px; height: 40px; object-fit: cover;" 
                                onerror="this.src='../assets/images/default.png'">
                            <div>
                                <div class="fw-bold text-dark">${row.fullname}</div>
                                <div class="small text-muted">${row.department} • ${row.emp_code}</div>
                            </div>
                        </div>`;
                }
            },
            // Col 2: Time In
            { 
                data: null,
                className: 'align-middle text-nowrap',
                render: function(data, type, row) {
                    return `
                        <div class="fw-bold text-dark">${row.time_in}</div>
                        <div class="small text-muted">${row.date_in}</div>
                    `;
                }
            },
            // Col 3: Time Out
            { 
                data: null,
                className: 'align-middle text-nowrap',
                render: function(data, type, row) {
                    if(row.is_active) {
                        return `<span class="text-muted small fst-italic">--:--</span>`;
                    }
                    return `
                        <div class="fw-bold text-dark">${row.time_out}</div>
                        <div class="small text-muted">${row.date_out}</div>
                    `;
                }
            },
            // Col 4: Status Badges (Updated to FA6)
            { 
                data: 'status_raw',
                className: 'text-center align-middle',
                render: function(data, type, row) {
                    let badges = '';
                    let status = data.toLowerCase();

                    if(status.includes('ontime')) 
                        badges += '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill me-1">Ontime</span>';
                    if(status.includes('late')) 
                        badges += '<span class="badge bg-soft-warning text-warning border border-warning px-2 rounded-pill me-1">Late</span>';
                    if(status.includes('undertime')) 
                        badges += '<span class="badge bg-soft-info text-info border border-info px-2 rounded-pill me-1">Undertime</span>';
                    if(status.includes('overtime')) 
                        badges += '<span class="badge bg-soft-primary text-primary border border-primary px-2 rounded-pill me-1">Overtime</span>';
                    if(status.includes('forgot')) 
                        badges += '<span class="badge bg-soft-danger text-danger border border-danger px-2 rounded-pill me-1">Forgot Time Out</span>';
                    
                    if(row.is_active) {
                        // Updated to FA6 fa-solid
                        badges += '<span class="badge bg-soft-secondary text-secondary border px-2 rounded-pill"><i class="fa-solid fa-spinner fa-spin me-1"></i>Active</span>';
                    }

                    return badges;
                }
            },
            // Col 5: Hours Worked
            { 
                data: 'hours', 
                className: 'fw-bold text-end text-gray-700 align-middle' 
            }
        ]
    });

    // 3. Custom Search Hook
    $('#customSearch').on('keyup', function() { 
        attendanceTable.search(this.value).draw(); 
    });

    // 4. Initial Load
    reloadAttendanceData();
    
    // 5. CONNECT TO MASTER REFRESHER (Hook for Topbar button)
    window.refreshPageContent = reloadAttendanceData;
});
</script>