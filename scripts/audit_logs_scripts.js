// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var logsTable;

// --- GLOBAL CONFIGURATION ---
// Define the global API path for portability and maintenance
const AUDIT_LOGS_API_URL = '../api/audit_logs_global_api.php'; 

/**
 * Updates the Topbar Status (Text + Dot Color)
 * @param {string} state - 'loading', 'success', 'error', or 'initial'
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
    else if (state === 'error') {
        $text.text(`Failed: ${time}`);
        $dot.addClass('text-danger');  // Red
    }
}

// 1.2 MASTER REFRESHER HOOK
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    if (logsTable) {
        // If Manual Click -> Spin Icon & Show 'Syncing...'
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // Reload DataTable (false = keep paging)
        logsTable.ajax.reload(function() {
             // Optional: Force stop spin if success callback above doesn't trigger fast enough
        }, false); 
    }
};

// ==============================================================================
// 2. LOG ACTIONS
// ==============================================================================

// 2.1 View Details
function viewLog(id) {
    $.post(AUDIT_LOGS_API_URL, { action: 'get_details', id: id }, function(res) {
        if(res.status === 'success') {
            let d = res.data;
            
            // Check if the modal elements exist before setting them
            $('#view_action').text(d.action || 'N/A');
            $('#view_agent').text(d.user_agent || 'N/A');
            // Use .html() if you expect line breaks or specific formatting within details
            $('#view_details').text(d.details || 'No additional details provided.');
            
            // Assuming your modal has the ID 'logModal'
            $('#logModal').modal('show');
        } else {
             Swal.fire('Error', res.message || 'Could not fetch log details.', 'error');
        }
    }, 'json').fail(function() {
        Swal.fire('Error', 'Network error during details fetch.', 'error');
    });
}

// 2.2 Clear Logs
function confirmClearLogs() {
    Swal.fire({
        title: 'Clear Audit Logs?',
        text: "This will permanently delete all logs older than 30 days. This cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, clear them!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Clearing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            $.post(AUDIT_LOGS_API_URL, { action: 'clear_logs' }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Cleared!', res.message, 'success');
                    window.refreshPageContent(true); // Trigger Manual Refresh Style
                } else {
                    // This handles status: 'error' messages from the API (e.g., Permission denied)
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(function() {
                 Swal.fire('Error', 'Network error during log cleanup.', 'error');
            });
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    // 3.1 DATATABLE CONFIG
    logsTable = $('#logsTable').DataTable({
        serverSide: true, 
        processing: true,
        ajax: { 
            // Use the centralized API URL defined above
            url: AUDIT_LOGS_API_URL, 
            type: 'POST', 
            data: { action: 'fetch' },
            error: function (xhr, error, thrown) {
                // Handle API error in DataTables load
                updateSyncStatus('error');
                setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
                console.error("DataTables Server Error:", xhr.responseText);
            }
        },
        order: [[0, 'desc']], // Newest first
        pageLength: 25,
        dom: 'rtip', // Only show Table, Info, and Pagination
        
        // AUTOMATIC UI UPDATES ON SUCCESSFUL DRAW
        drawCallback: function(settings) {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },
        
        // --- COLUMN DEFINITIONS ---
        columns: [
            { 
                data: 'created_at', 
                className: 'text-nowrap align-middle',
                render: function(data) {
                    // Ensures clean time rendering
                    return new Date(data).toLocaleString('en-US'); 
                }
            },
            { 
                data: 'username',
                className: 'fw-bold align-middle',
                render: function(data, type, row) {
                    return data ? data : '<span class="text-muted fst-italic">System / Guest</span>';
                }
            },
            { 
                data: 'usertype',
                className: 'align-middle',
                render: function(data) {
                    // Use soft colors for better visibility and context
                    if(data == 0) return '<span class="badge bg-soft-danger text-danger">Super Admin</span>';
                    if(data == 1) return '<span class="badge bg-soft-primary text-primary">Admin</span>';
                    if(data == 2) return '<span class="badge bg-soft-info text-info">Employee</span>';
                    return '<span class="badge bg-soft-secondary text-secondary">Unknown</span>';
                }
            },
            { 
                data: 'action',
                className: 'align-middle',
                render: function(data) {
                    // Dynamic badge coloring based on action type
                    let color = 'secondary';
                    if(data.includes('LOGIN_SUCCESS')) color = 'success';
                    else if(data.includes('DELETE')) color = 'danger';
                    else if(data.includes('UPDATE')) color = 'warning';
                    else if(data.includes('CREATE') || data.includes('ADD')) color = 'primary';
                    else if(data.includes('FAIL')) color = 'danger';
                    else if(data.includes('PASSWORD_RESET')) color = 'info';
                    
                    return `<span class="badge bg-soft-${color} text-${color}">${data}</span>`;
                }
            },
            { data: 'ip_address', className: 'text-monospace small align-middle' },
            {
                data: 'id', orderable: false, className: 'text-center align-middle',
                render: function(data) {
                    // Note: Ensure the confirmClearLogs button is only visible to Superadmin via PHP template logic
                    return `<button class="btn btn-sm btn-outline-secondary" onclick="viewLog(${data})"><i class="fas fa-eye"></i></button>`;
                }
            }
        ]
    });

    // 3.2 DETECT LOADING STATE
    // This correctly uses the internal DataTables processing flag
    $('#logsTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // 3.3 Manual Refresh Button Listener
    // Note: Assumes the button has the ID 'btn-refresh'
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
    
    // 3.4 Clear Logs Button Listener (Assumes a button with ID 'clearLogsBtn' calls this)
    $('#clearLogsBtn').on('click', function(e) {
        e.preventDefault();
        confirmClearLogs();
    });
});