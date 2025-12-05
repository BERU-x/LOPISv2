<script>
let attendanceTable;

$(document).ready(function() {
    // 1. Initialize DataTable (Empty)
    attendanceTable = $('#todayTable').DataTable({
        "order": [[ 1, "desc" ]], // Sort by Time In initially
        "pageLength": 25,
        "dom": 'rtip', 
        "language": { "emptyTable": "No attendance records found for today." },
        "columns": [
            // Column 0: Employee
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
            // Column 1: Time In
            { 
                data: null,
                render: function(data, type, row) {
                    return `
                        <div class="fw-bold text-dark">${row.time_in}</div>
                        <div class="small text-muted">${row.date_in}</div>
                    `;
                }
            },
            // Column 2: Time Out
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
            // Column 3: Status (Badge Generation)
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
            // Column 4: Hours
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

    // 3. Load Data immediately
    reloadAttendanceData();
    
    // Optional: Auto-refresh every 30 seconds
    // setInterval(reloadAttendanceData, 30000);
});

function reloadAttendanceData() {
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

            // B. Update Table
            attendanceTable.clear(); // Clear old rows
            if (response.logs.length > 0) {
                attendanceTable.rows.add(response.logs); // Add new rows
            }
            attendanceTable.draw(); // Redraw table
        },
        error: function(err) {
            console.error("Error fetching attendance:", err);
        }
    });
}
</script>