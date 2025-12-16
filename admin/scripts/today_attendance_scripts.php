<script>
// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
let attendanceTable;

/**
 * Updates the Topbar Status (Text + Dot Color)
 * @param {string} state - 'loading', 'success', or 'error'
 */
function updateSyncStatus(state) {
    const $dot = $('.live-dot');
    const $text = $('#last-updated-time');
    const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    $dot.removeClass('text-success text-warning text-danger');

    if (state === 'loading') {
        $text.text('Syncing...');
        $dot.addClass('text-warning'); // Yellow
    } 
    else if (state === 'success') {
        $text.text(`Synced: ${time}`);
        $dot.addClass('text-success'); // Green
    } 
    else {
        $text.text(`Failed: ${time}`);
        $dot.addClass('text-danger');  // Red
    }
}

// 1.2 MASTER REFRESHER HOOK
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    loadAttendanceData(isManual);
};

// ==============================================================================
// 2. MAIN DATA FETCHER
// ==============================================================================
function loadAttendanceData(isManual = false) {
    // 1. Visual Feedback (Only if manual)
    if(isManual) {
        $('#refreshIcon').addClass('fa-spin'); 
    }
    
    // 2. Always set status to loading (Yellow Dot)
    updateSyncStatus('loading');

    $.ajax({
        url: 'api/get_today_attendance.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            
            // A. Update Stats Cards
            if (response.stats) {
                $('#val-present').text(response.stats.present);
                $('#val-total').text(response.stats.total_employees);
                $('#val-absent').text(response.stats.absent);
                $('#val-late').text(response.stats.late);
                $('#val-ontime').text(response.stats.ontime);
            }

            // B. Update Table Data (Client-Side Redraw)
            if (attendanceTable) {
                attendanceTable.clear(); 
                if (response.logs && response.logs.length > 0) {
                    attendanceTable.rows.add(response.logs); 
                }
                attendanceTable.draw(false); 
            }

            updateSyncStatus('success');
        },
        error: function(err) {
            console.error("Error fetching attendance:", err);
            updateSyncStatus('error');
        },
        complete: function() {
            // Stop spinner after delay if manual
            if(isManual) setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    // 3.1 DATATABLE INITIALIZATION
    attendanceTable = $('#todayTable').DataTable({
        // General Configuration
        data: [], // Start empty, filled by AJAX
        order: [[ 1, "desc" ]], 
        pageLength: 25,
        dom: 'rtip', 
        language: { "emptyTable": "No attendance records found for today." },
        responsive: true,

        // Column Definitions
        columns: [
            // Col 1: Employee Details
            { 
                data: null,
                className: 'align-middle',
                render: function (data, type, row) {
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
            // Col 4: Status Badges
            { 
                data: 'status_raw',
                className: 'text-center align-middle',
                render: function(data, type, row) {
                    let badges = '';
                    let status = data.toLowerCase();

                    if(status.includes('ontime')) badges += '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill me-1">Ontime</span>';
                    if(status.includes('late')) badges += '<span class="badge bg-soft-warning text-warning border border-warning px-2 rounded-pill me-1">Late</span>';
                    if(status.includes('undertime')) badges += '<span class="badge bg-soft-info text-info border border-info px-2 rounded-pill me-1">Undertime</span>';
                    if(status.includes('overtime')) badges += '<span class="badge bg-soft-primary text-primary border border-primary px-2 rounded-pill me-1">Overtime</span>';
                    if(status.includes('forgot')) badges += '<span class="badge bg-soft-danger text-danger border border-danger px-2 rounded-pill me-1">Forgot Time Out</span>';
                    
                    if(row.is_active) {
                        badges += '<span class="badge bg-soft-secondary text-secondary border px-2 rounded-pill"><i class="fa-solid fa-spinner fa-spin me-1"></i>Active</span>';
                    }
                    return badges;
                }
            },
            // Col 5: Hours
            { 
                data: 'hours', 
                className: 'fw-bold text-end text-gray-700 align-middle' 
            }
        ]
    });

    // 3.2 Custom Search Hook
    $('#customSearch').on('keyup', function() { 
        attendanceTable.search(this.value).draw(); 
    });

    // 3.3 Initial Load
    loadAttendanceData(true);

    // 3.4 Manual Refresh Button Listener
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        loadAttendanceData(true);
    });
});
</script>