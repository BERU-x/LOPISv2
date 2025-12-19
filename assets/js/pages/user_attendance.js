/**
 * Employee Attendance Controller
 * Handles personal attendance history, date filtering, and multi-status badges.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
let attendanceTable; 
let spinnerStartTime = 0; 

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

/**
 * 1.2 MASTER REFRESHER HOOK
 * Connects the global refresh button to this specific module.
 */
window.refreshPageContent = function(isManual = false) {
    if (isManual) {
        $('#refreshIcon').addClass('fa-spin');
        $('#refresh-spinner').addClass('fa-spin text-teal').removeClass('text-gray-400');
        updateSyncStatus('loading');
    }
    
    spinnerStartTime = new Date().getTime(); 
    
    if (attendanceTable) {
        attendanceTable.ajax.reload(null, false);
    }
};

/**
 * 1.3 HELPER: Stops the spinner safely (prevents flickering)
 */
function stopSpinnerSafely() {
    const minDisplayTime = 800; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeUI = () => {
        $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-gray-400');
        $('#refreshIcon').removeClass('fa-spin');
    };

    if (timeElapsed < minDisplayTime) {
        setTimeout(finalizeUI, minDisplayTime - timeElapsed);
    } else {
        finalizeUI();
    }
}

// ==============================================================================
// 2. INITIALIZATION
// ==============================================================================

$(document).ready(function() {
    
    if ($('#attendanceTable').length) {
        
        attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            order: [[0, 'desc']], 
            searching: false, 
            dom: 'rtip', 

            ajax: {
                url: "../api/employee/attendance_action.php?action=fetch", 
                type: "GET",
                data: function (d) {
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                },
                error: function() {
                    updateSyncStatus('error');
                    stopSpinnerSafely();
                }
            },

            drawCallback: function() {
                updateSyncStatus('success');
                stopSpinnerSafely();
            },
            
            columns: [
                { data: 'date', className: "align-middle" },
                { data: 'time_in', className: "fw-bold text-dark align-middle" },
                { 
                    data: 'status', 
                    className: "text-center align-middle text-wrap",
                    orderable: false,
                    render: data => data // Render pre-formatted HTML badges from API
                }, 
                { 
                    data: 'time_out', 
                    className: "fw-bold text-dark align-middle",
                    render: data => data // Handles cross-day date labels
                },
                { 
                    data: 'num_hr',
                    className: "text-center fw-bold text-gray-700 align-middle",
                    render: d => d > 0 ? parseFloat(d).toFixed(2) : '—'
                },
                { 
                    data: 'ot_hr',
                    className: "text-center align-middle", 
                    render: d => d > 0 ? `<span class="text-primary fw-bold">+${parseFloat(d).toFixed(2)}</span>` : '—'
                }
            ],
            
            language: {
                processing: "<div class='spinner-border text-teal spinner-border-sm' role='status'></div>",
                emptyTable: "No attendance logs found for this period."
            }
        });

        // ============================================================
        //  3. FILTER LOGIC
        // ============================================================
        
        $('#applyFilterBtn').on('click', function() {
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            attendanceTable.ajax.reload();
            setTimeout(() => { $(this).prop('disabled', false).html('<i class="fas fa-filter me-1"></i> Apply'); }, 1000);
        });
        
        $('#clearFilterBtn').on('click', function() {
            $('#filter_start_date, #filter_end_date').val('');
            attendanceTable.ajax.reload();
        });

        // Manual Refresh listener for the page-specific button
        $('#btn-refresh').on('click', function(e) {
            e.preventDefault();
            window.refreshPageContent(true);
        });
    }
});