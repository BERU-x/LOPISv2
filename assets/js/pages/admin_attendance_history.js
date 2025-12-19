/**
 * Attendance History Controller
 * Handles Server-Side Processing (SSP) for historical logs with range filtering.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var attendanceTable; 

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
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

// 1.2 MASTER REFRESHER TRIGGER
window.refreshPageContent = function(isManual = false) {
    if (attendanceTable) {
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        // Reload DataTable without resetting pagination
        attendanceTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    if ($('#attendanceTable').length) {
        
        // 2.1 Initialize DataTable (SSP Mode)
        attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            pageLength: 25,
            dom: 'rtip', 
            ajax: {
                url: "../api/admin/attendance_ssp.php", 
                type: "GET",
                data: function (d) {
                    // Inject custom filters into the SSP request
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                },
                error: function() {
                    updateSyncStatus('error');
                }
            },
            
            // Standardized UI updates after data fetch
            drawCallback: function() {
                updateSyncStatus('success');
                setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
            },

            // Column Mapping (Matches updated SSP API)
            columns: [
                { data: 'employee_name', className: 'align-middle' },
                { data: 'date', className: "text-nowrap align-middle" },
                { data: 'time_in', className: "align-middle" },
                { data: 'status', className: "text-center align-middle" }, 
                { data: 'time_out', className: "align-middle" },
                { data: 'num_hr', className: "text-center align-middle" },
                { data: 'overtime_hr', className: "text-center align-middle" }
            ],
            
            order: [[1, 'desc']], // Default: Newest dates first
            
            language: {
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"></div> Loading logs...',
                emptyTable: "No attendance records found.",
                zeroRecords: "No matching records found."
            }
        });

        // 2.2 DETECT LOADING STATE (Silent sync vs Manual refresh)
        $('#attendanceTable').on('processing.dt', function (e, settings, processing) {
            if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
                updateSyncStatus('loading');
            }
        });

        // 2.3 Custom Search Binding
        $('#customSearch').on('keyup', function() {
            attendanceTable.search(this.value).draw();
        });

        // 2.4 Range Filter Buttons
        $('#applyFilterBtn').on('click', function() {
            window.refreshPageContent(true); 
        });
        
        $('#clearFilterBtn').on('click', function() {
            $('#filter_start_date, #filter_end_date, #customSearch').val('');
            attendanceTable.search('').draw();
            window.refreshPageContent(true); 
        });

        // 2.5 Topbar Refresh Button
        $('#btn-refresh').on('click', function(e) {
            e.preventDefault();
            window.refreshPageContent(true);
        });
    }
});