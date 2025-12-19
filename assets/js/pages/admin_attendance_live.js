/**
 * Attendance Live Controller
 * Handles real-time attendance logs and dynamic status reporting.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
let attendanceTable;

/**
 * Updates the Topbar Status (Text + Dot Color)
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

// 1.2 MASTER REFRESHER HOOK
window.refreshPageContent = function(isManual = false) {
    loadAttendanceData(isManual);
};

// ==============================================================================
// 2. MAIN DATA FETCHER
// ==============================================================================
function loadAttendanceData(isManual = false) {
    if(isManual) $('#refreshIcon').addClass('fa-spin'); 
    updateSyncStatus('loading');

    $.ajax({
        // ⭐ UPDATED API PATH
        url: '../api/admin/attendance_live.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            
            // A. Update Stats Cards (Present, Late, Absent, etc.)
            if (response.stats) {
                $('#val-present').text(response.stats.present);
                $('#val-total').text(response.stats.total_employees);
                $('#val-absent').text(response.stats.absent);
                $('#val-late').text(response.stats.late);
                $('#val-ontime').text(response.stats.ontime);
            }

            // B. Update Table Data
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
            console.error("Attendance Sync Failed:", err);
            updateSyncStatus('error');
        },
        complete: function() {
            if(isManual) setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 600);
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    // 3.1 DATATABLE INITIALIZATION
    attendanceTable = $('#todayTable').DataTable({
        data: [], 
        order: [[ 1, "desc" ]], // Order by Time In
        pageLength: 25,
        dom: 'rtip', 
        responsive: true,
        columns: [
            // Col 1: Profile & Name
            { 
                data: null,
                className: 'align-middle',
                render: function (data, type, row) {
                    let photoPath = row.photo && row.photo !== 'default.png' 
                        ? `../assets/images/users/${row.photo}` 
                        : '../assets/images/users/default.png';
                    
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photoPath}" class="rounded-circle me-3 border shadow-sm" 
                                style="width: 42px; height: 42px; object-fit: cover;" 
                                onerror="this.src='../assets/images/users/default.png'">
                            <div>
                                <div class="fw-bold text-dark mb-0">${row.fullname}</div>
                                <div class="small text-muted font-monospace">${row.emp_code} • ${row.department}</div>
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
                        <div class="fw-bold text-primary">${row.time_in}</div>
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
                        return `<span class="badge bg-soft-secondary text-secondary font-monospace fw-normal">In Progress</span>`;
                    }
                    return `
                        <div class="fw-bold text-dark">${row.time_out}</div>
                        <div class="small text-muted">${row.date_out || '--'}</div>
                    `;
                }
            },
            // Col 4: Status Badges
            { 
                data: 'status',
                className: 'text-center align-middle',
                render: function(data, type, row) {
                    let status = (data || '').toLowerCase();
                    let html = '';

                    // Ontime/Late logic
                    if(status.includes('ontime')) 
                        html += '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill me-1">Ontime</span>';
                    else if(status.includes('late')) 
                        html += '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill me-1">Late</span>';

                    // Additional context
                    if(status.includes('overtime')) 
                        html += '<span class="badge bg-soft-primary text-primary border border-primary px-3 rounded-pill">OT</span>';
                    
                    if(row.is_active) {
                        html += '<span class="ms-1 text-success small"><i class="fa-solid fa-circle-play fa-fade"></i></span>';
                    }

                    return html || '<span class="text-muted small">--</span>';
                }
            },
            // Col 5: Duration
            { 
                data: 'hours', 
                className: 'fw-bold text-end text-dark align-middle font-monospace' 
            }
        ],
        language: { "emptyTable": "No employees have clocked in yet today." }
    });

    // 3.2 Custom Search Hook
    $('#customSearch').on('keyup', function() { 
        attendanceTable.search(this.value).draw(); 
    });

    // 3.3 Set Interval for Auto-Refresh (Every 60 Seconds)
    setInterval(function() {
        loadAttendanceData(false); // Silent refresh
    }, 60000);

    // 3.4 Initial Load
    loadAttendanceData(true);

    // 3.5 Manual Refresh Button
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        loadAttendanceData(true);
    });
});