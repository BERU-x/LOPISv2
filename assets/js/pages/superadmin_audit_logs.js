/**
 * Audit Logs Controller
 * Handles the monitoring and purging of system activity logs.
 * Integrated with Global AppUtility for Topbar syncing.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var logsTable;

// 1.2 MASTER REFRESHER HOOK
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    if (logsTable) {
        if(isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        // Reload DataTable without resetting pagination
        logsTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. LOG ACTIONS
// ==============================================================================

// 2.1 View Details
function viewLog(id) {
    $.post(API_ROOT + '/superadmin/audit_logs_action.php', { action: 'get_details', id: id }, function(res) {
        if(res.status === 'success') {
            let d = res.data;
            $('#view_action').text(d.action);
            $('#view_user').text(res.email || 'System Account'); // From Join
            $('#view_agent').text(d.user_agent);
            $('#view_details').text(d.details || 'No additional details provided.');
            $('#logModal').modal('show');
        }
    }, 'json');
}

// 2.2 Clear Logs (Older than 30 days)
function confirmClearLogs() {
    Swal.fire({
        title: 'Purge Old Logs?',
        text: "This will permanently delete activity logs older than 30 days. This action is irreversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, Purge Now'
    }).then((result) => {
        if (result.isConfirmed) {
            if(window.AppUtility) window.AppUtility.updateSyncStatus('loading');
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            $.post(API_ROOT + '/superadmin/audit_logs_action.php', { action: 'clear_logs' }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success!', res.message, 'success');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                    if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
                }
            }, 'json');
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
            url: API_ROOT + '/superadmin/audit_logs_action.php', 
            type: 'POST', 
            data: { action: 'fetch' },
            error: function() {
                if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        order: [[0, 'desc']], // Most recent activity first
        pageLength: 25,
        dom: 'rtip',
        drawCallback: function() {
            // Sync with Topbar via Global Utility
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
        },
        columns: [
            { 
                data: 'created_at', 
                className: 'text-nowrap align-middle small',
                render: function(data) {
                    return new Date(data).toLocaleString('en-US', { 
                        year: 'numeric', month: 'short', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit', second: '2-digit' 
                    });
                }
            },
            { 
                data: 'full_name', 
                className: 'fw-bold align-middle',
                render: function(data, type, row) {
                    // If the user_id is 0 or full_name is null, it's a System process
                    if (!data || row.user_id == 0) {
                        return `<span class="text-primary">
                                    <i class="fas fa-robot me-1"></i> LOPIS System
                                </span>`;
                    }
                    return data;
                }
            },
            { 
                data: 'usertype',
                className: 'align-middle',
                render: function(data) {
                    let roles = {
                        0: '<span class="badge bg-soft-danger text-danger border border-danger">Super Admin</span>',
                        1: '<span class="badge bg-soft-primary text-primary border border-primary">Admin</span>',
                        2: '<span class="badge bg-soft-info text-info border border-info">Employee</span>'
                    };
                    return roles[data] || '<span class="badge bg-soft-secondary text-secondary">System</span>';
                }
            },
            { 
                data: 'action',
                className: 'align-middle',
                render: function(data) {
                    let color = 'secondary';
                    let upper = data.toUpperCase();
                    
                    if(upper.includes('LOGIN')) color = 'success';
                    else if(upper.includes('DELETE') || upper.includes('REVOKE')) color = 'danger';
                    else if(upper.includes('UPDATE') || upper.includes('EDIT')) color = 'warning';
                    else if(upper.includes('CREATE') || upper.includes('ADD') || upper.includes('GRANT')) color = 'primary';
                    
                    return `<span class="badge bg-soft-${color} text-${color} border border-${color}">${data}</span>`;
                }
            },
            { data: 'ip_address', className: 'text-monospace small align-middle font-monospace' },
            {
                data: 'id', 
                orderable: false, 
                className: 'text-center align-middle',
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-secondary shadow-sm" onclick="viewLog(${data})" title="View Full Details">
                                <i class="fas fa-eye"></i>
                            </button>`;
                }
            }
        ],
        language: {
            processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"></div> Loading logs...'
        }
    });

    // 3.2 DETECT LOADING STATE
    $('#logsTable').on('processing.dt', function (e, settings, processing) {
        if (processing && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
    });
});