/**
 * Attendance Live Controller
 * Features: Mutex Locking, Raw Data CSV Export, Stats Auto-Update, and Global Sync.
 */
let attendanceTable;
window.isProcessing = false; // ⭐ The "Lock"

// 1. MASTER REFRESHER HOOK
window.refreshPageContent = function(isManual = false) {
    if (window.isProcessing) return; 

    if (attendanceTable && $.fn.DataTable.isDataTable('#todayTable')) {
        window.isProcessing = true; 
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');
        
        attendanceTable.ajax.reload(function(json) {
            // Stats card update logic is handled by drawCallback below
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            window.isProcessing = false; 
        }, false);
    }
};

$(document).ready(function() {
    // 1. CLEANUP
    if ($.fn.DataTable.isDataTable('#todayTable')) {
        $('#todayTable').DataTable().destroy();
        $('#todayTable').empty();
        $('#todayTable').append('<thead class="bg-light text-gray-600 text-xs font-weight-bold text-uppercase"><tr><th>Employee Name</th><th>Clock In</th><th>Clock Out</th><th class="text-center">Log Status</th><th class="text-end">Duration</th></tr></thead><tbody></tbody>');
    }

    // 2. INITIALIZATION
    window.isProcessing = true; 

    attendanceTable = $('#todayTable').DataTable({
        destroy: true,
        ajax: {
            url: API_ROOT + '/admin/attendance_live.php',
            dataSrc: 'logs'
        },
        // 'B' initializes buttons, 'f' search, 'r' processing, 't' table, 'i' info, 'p' pagination
        dom: 'Bfrtip', 
        buttons: [{
            extend: 'csvHtml5',
            className: 'd-none', // Hidden, triggered by custom UI button
            title: 'Attendance_Report_' + new Date().toISOString().slice(0, 10),
            exportOptions: { 
                columns: [0, 1, 2, 3, 4],
                format: {
                    header: function (data) {
                        // Strips the HTML sorting spans from the header text
                        return data.replace(/<[^>]*>?/gm, '').trim();
                    },
                    body: function (data, rowIdx, columnIdx) {
                        const rowData = attendanceTable.row(rowIdx).data();
                        const raw = rowData.raw_data;
                        switch(columnIdx) {
                            case 0: return raw.name;
                            case 1: return raw.clock_in;
                            case 2: return raw.clock_out; // Now contains "No Time Out" if flagged
                            case 3: return raw.status;
                            case 4: return raw.duration;
                            default: return data;
                        }
                    }
                }
            },
            customize: function (csv) {
                return "LOPISv2 TODAY'S ATTENDANCE FEED\n" + 
                       "Report Date: " + new Date().toLocaleDateString() + "\n" + 
                       "----------------------------------\n" + csv;
            }
        }],
        // ⭐ AUTO-UPDATE STATS CARDS
        drawCallback: function(settings) {
            const json = settings.json;
            if (json && json.stats) {
                $('#val-present').text(json.stats.present);
                $('#val-total').text(json.stats.total_employees);
                $('#val-absent').text(json.stats.absent);
                $('#val-late').text(json.stats.late);
                $('#val-ontime').text(json.stats.ontime);
            }
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            window.isProcessing = false; 
        },
        order: [[1, "desc"]],
        pageLength: 25,
        columns: [
            { data: null, render: function (d, t, row) {
                let img = row.photo && row.photo !== 'default.png' ? `../assets/images/users/${row.photo}` : '../assets/images/users/default.png';
                return `<div class="d-flex align-items-center">
                            <img src="${img}" class="rounded-circle me-3 border shadow-sm" width="42" height="42" style="object-fit:cover" onerror="this.src='../assets/images/users/default.png'">
                            <div>
                                <div class="fw-bold text-dark">${row.fullname}</div>
                                <div class="small text-muted font-monospace">${row.emp_code} • ${row.department}</div>
                            </div>
                        </div>`;
            }},
            // Column 2: Time In (Time Only)
            { data: 'time_in', className: 'fw-bold align-middle' },
            
            // Column 3: Clock Out (Handles "No Time Out" Logic)
            { data: null, className: 'align-middle', render: d => {
                if (d.is_missing_out) {
                    // ⭐ FIXED SCHEDULE + PAST 6PM TRIGGER
                    return `<span class="badge bg-soft-danger text-danger border fw-bold animate__animated animate__flash animate__slow animate__infinite">No Time Out</span>`;
                } else if (d.is_active) {
                    return `<span class="badge bg-soft-teal text-teal border fw-normal">Working</span>`;
                } else {
                    return `<span class="fw-bold text-dark">${d.time_out}</span>`;
                }
            }},
            
            { data: 'status', className: 'text-center align-middle', render: function(d, t, row) {
                let s = (d || '').toLowerCase();
                let h = '';
                if(s.includes('ontime')) h += '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill me-1">Ontime</span>';
                if(s.includes('late')) h += '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill me-1">Late</span>';
                if(row.overtime > 0) h += `<span class="badge bg-soft-primary text-primary border border-primary px-3 rounded-pill">OT</span>`;
                if(row.is_active) h += '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill"><i class="fa-solid fa-circle-play fa-fade me-1"></i>Active</span>';
                return h || '<span class="text-muted small">--</span>';
            }},
            { data: 'hours', className: 'fw-bold text-end text-dark align-middle font-monospace' }
        ]
    });

    // 3. EVENT BINDINGS
    $('#btn-export-csv').on('click', function(e) {
        e.preventDefault();
        attendanceTable.button('.buttons-csv').trigger();
    });

    $('#customSearch').on('keyup', function() { 
        attendanceTable.search(this.value).draw(); 
    });

    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});