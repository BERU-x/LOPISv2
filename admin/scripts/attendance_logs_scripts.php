<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var attendanceTable; 

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
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

// 1.2 MASTER REFRESHER TRIGGER
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    if (attendanceTable) {
        // 1. Visual Feedback for Manual Actions
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // 2. Reload DataTable (false = keep paging)
        attendanceTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    if ($('#attendanceTable').length) {
        
        // 2.1 Initialize DataTable
        attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: false, 
            dom: 'rtip', 

            ajax: {
                url: "api/attendance_data.php", 
                type: "GET",
                data: function (d) {
                    // Pass filter values to the API
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },
            
            // DRAW CALLBACK: Standardized UI updates
            drawCallback: function(settings) {
                updateSyncStatus('success');
                setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
            },

            columns: [
                // Col 1: Employee
                { 
                    data: 'employee_name',
                    className: 'align-middle', 
                    render: function(data, type, row) {
                        var photo = row.photo ? '../assets/images/' + row.photo : '../assets/images/default.png';
                        var id = row.employee_id ? row.employee_id : '';

                        return `
                            <div class="d-flex align-items-center">
                                <img src="${photo}" class="rounded-circle me-3 border shadow-sm" 
                                    style="width: 40px; height: 40px; object-fit: cover;" 
                                    onerror="this.src='../assets/images/default.png'">
                                <div>
                                    <div class="fw-bold text-dark">${data}</div>
                                    <div class="small text-muted">${id}</div>
                                </div>
                            </div>
                        `;
                    }
                },
                // Col 2: Date
                { data: 'date', className: "text-nowrap align-middle" },
                // Col 3: Time In
                { data: 'time_in', className: "fw-bold text-dark align-middle" },
                // Col 4: Status (Badges)
                { data: 'status', className: "text-center align-middle", render: function (data) { return data; } }, 
                // Col 5: Time Out
                { data: 'time_out', className: "fw-bold text-dark align-middle" },
                // Col 6: Hours
                { 
                    data: 'num_hr',
                    className: "text-center fw-bold text-gray-700 align-middle",
                    render: function (data) {
                        return data > 0 ? parseFloat(data).toFixed(2) : '—';
                    }
                },
                // Col 7: Overtime
                { 
                    data: 'overtime_hr',
                    className: "text-center align-middle",
                    render: function (data) {
                        return (data > 0) ? '+' + parseFloat(data).toFixed(2) : '—';
                    }
                }
            ],
            
            language: {
                processing: "Loading...",
                emptyTable: "No attendance records found.",
                zeroRecords: "No matching records found."
            }
        });

        // 2.2 DETECT LOADING STATE
        $('#attendanceTable').on('processing.dt', function (e, settings, processing) {
            if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
                updateSyncStatus('loading');
            }
        });

        // 2.3 Custom Search Binding
        $('#customSearch').on('keyup', function() {
            attendanceTable.search(this.value).draw();
        });

        // 2.4 Apply Filter Button
        $('#applyFilterBtn').off('click').on('click', function() {
            window.refreshPageContent(true); // Manual Refresh
        });
        
        // 2.5 Clear Filter Button
        $('#clearFilterBtn').off('click').on('click', function() {
            // Reset filters
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            $('#customSearch').val(''); 
            
            // Clear Search & Refresh
            attendanceTable.search(''); 
            window.refreshPageContent(true); 
        });

        // 2.6 Topbar Refresh Button
        $('#btn-refresh').on('click', function(e) {
            e.preventDefault();
            window.refreshPageContent(true);
        });
    }
});
</script>