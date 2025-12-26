/**
 * Attendance History Controller
 * Standardized with Mutex Locking and AppUtility Sync Patterns.
 * Handles Server-Side Processing (SSP) for historical logs with range filtering.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var attendanceTable;
window.isProcessing = false; // ⭐ The "Lock"

/**
 * 1.1 MASTER REFRESHER TRIGGER (Standardized with Mutex)
 */
window.refreshPageContent = function(isManual = false) {
    // 1. Check Mutex Lock
    if (window.isProcessing) return;

    if (attendanceTable && $.fn.DataTable.isDataTable('#attendanceTable')) {
        window.isProcessing = true;
        
        // 2. Update UI State
        if (window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        } else {
            $('#refreshIcon').addClass('fa-spin');
        }

        // 3. Reload DataTable without resetting pagination
        attendanceTable.ajax.reload(function(json) {
            // Unlock and Success status are handled in drawCallback
        }, false);
    }
};

// ==============================================================================
// 2. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    if ($('#attendanceTable').length) {
        
        // 2.1 Mutex Initial State
        window.isProcessing = true;

        // 2.2 Initialize DataTable (SSP Mode)
        attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            pageLength: 25,
            dom: 'rtip', 
            ajax: {
                url: API_ROOT + "/admin/attendance_ssp.php", 
                type: "GET",
                data: function (d) {
                    // Inject custom filters into the SSP request
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                },
                error: function() {
                    if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
                    window.isProcessing = false;
                    $('#refreshIcon').removeClass('fa-spin');
                }
            },
            
            // ⭐ DRAW CALLBACK (Mutex Unlock & Standardized Sync Status)
            drawCallback: function(settings) {
                if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
                
                // UI Cleanup
                setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
                
                // 🔓 Release the Lock
                window.isProcessing = false; 
            },

            // Column Mapping (Matches your tbl_attendance schema)
            columns: [
                { data: 'employee_name', className: 'align-middle' },
                { data: 'date', className: "text-nowrap align-middle" },
                { data: 'time_in', className: "align-middle" },
                { data: 'time_out', className: "align-middle" },
                { data: 'status', className: "text-center align-middle" }, 
                { data: 'num_hr', className: "text-center align-middle font-monospace" },
                { data: 'overtime_hr', className: "text-center align-middle font-monospace" }
            ],
            
            order: [[1, 'desc']], // Default: Newest dates first (a.date)
            
            language: {
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"></div>',
                emptyTable: "No attendance records found.",
                zeroRecords: "No matching records found."
            }
        });

        // 2.3 Custom Search Binding (Respects Mutex)
        $('#customSearch').on('keyup', function() {
            if (!window.isProcessing) {
                attendanceTable.search(this.value).draw();
            }
        });

        // 2.4 Range Filter Logic (Uses standard refresher)
        $('#applyFilterBtn').on('click', function() {
            window.refreshPageContent(true); 
        });
        
        $('#clearFilterBtn').on('click', function() {
            $('#filter_start_date, #filter_end_date, #customSearch').val('');
            if (!window.isProcessing) {
                attendanceTable.search('').draw();
                window.refreshPageContent(true); 
            }
        });

        // 2.5 Topbar Refresh Button binding
        $('#btn-refresh').on('click', function(e) {
            e.preventDefault();
            window.refreshPageContent(true);
        });
    }
});