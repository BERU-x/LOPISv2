<script>
let attendanceTable;
let spinnerStartTime = 0; // Global variable to track when the spin started

// ⭐ KEEP THIS FUNCTION EXACTLY AS IS
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    // Your minDisplayTime is 1000ms (1 second)
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    // Function to handle the final time update
    const updateTime = () => {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            second: '2-digit'
        });
        $('#last-updated-time').text(timeString);
    };

    // Remove the spin class and update the time
    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateTime();
    };

    // Check if enough time has passed
    if (timeElapsed < minDisplayTime) {
        // Wait the remainder before stopping
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        // Stop immediately
        finalizeStop();
    }
}

$(document).ready(function() {
    // 1. Initialize DataTable (Client-Side Mode)
    attendanceTable = $('#todayTable').DataTable({
        // ... (DataTable configuration is correct) ...
        "order": [[ 1, "desc" ]], 
        "pageLength": 25,
        "dom": 'rtip', 
        "language": { "emptyTable": "No attendance records found for today." },
        "columns": [
            { 
                data: null,
                render: function (data, type, row) {
                    return `
                        <div class="d-flex align-items-center">
                            <img src="../assets/images/${row.photo}" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark">${row.fullname}</div>
                                <div class="small text-muted">${row.department} • ${row.emp_code}</div>
                            </div>
                        </div>`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    return `
                        <div class="fw-bold text-dark">${row.time_in}</div>
                        <div class="small text-muted">${row.date_in}</div>
                    `;
                }
            },
            { 
                data: null,
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
            { 
                data: 'status_raw',
                className: 'text-center',
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
                        badges += '<span class="badge bg-soft-secondary text-secondary border px-2 rounded-pill"><i class="fas fa-spinner fa-spin me-1"></i>Active</span>';
                    }

                    return badges;
                }
            },
            { 
                data: 'hours', 
                className: 'fw-bold text-end text-gray-700' 
            }
        ]
    });

    // 2. Custom Search Hook
    $('#customSearch').on('keyup', function() { 
        attendanceTable.search(this.value).draw(); 
    });

    // 3. Initial Load
    reloadAttendanceData();
    
    // 4. CONNECT TO MASTER REFRESHER
    window.refreshPageContent = reloadAttendanceData;
});

// ⭐ CORRECTED FUNCTION: Uses stopSpinnerSafely for cleanup
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
            
            // B. Update Stats Cards
            $('#val-present').text(response.stats.present);
            $('#val-total').text(response.stats.total_employees);
            $('#val-absent').text(response.stats.absent);
            $('#val-late').text(response.stats.late);
            $('#val-ontime').text(response.stats.ontime);

            // C. Update Table Data
            attendanceTable.clear(); 
            if (response.logs.length > 0) {
                attendanceTable.rows.add(response.logs); 
            }
            attendanceTable.draw(false); 

            // D. CRITICAL: Hand off cleanup to the safe timer function
            stopSpinnerSafely(); 
        },
        error: function(err) {
            console.error("Error fetching attendance:", err);
            
            // On Error, stop immediate (no need to wait 1s if it failed)
            const icon = $('#refresh-spinner');
            icon.removeClass('fa-spin text-teal');
            
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit'});
            $('#last-updated-time').text(`Error @ ${timeString}`);
        }
    });
}
</script>