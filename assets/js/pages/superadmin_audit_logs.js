// assets/js/pages/audit_logs.js

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var logsTable;

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
    if (logsTable) {
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
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
    $.post('../api/superadmin/audit_logs_action.php', { action: 'get_details', id: id }, function(res) {
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
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            $.post('../api/superadmin/audit_logs_action.php', { action: 'clear_logs' }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success!', res.message, 'success');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
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
            url: '../api/superadmin/audit_logs_action.php', 
            type: 'POST', 
            data: { action: 'fetch' } 
        },
        order: [[0, 'desc']], // Most recent activity first
        pageLength: 25,
        dom: 'rtip',
        drawCallback: function() {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
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
                data: 'full_name', // Uses the COALESCE name from our new API
                className: 'fw-bold align-middle',
                render: function(data, type, row) {
                    return data ? data : '<span class="text-muted fst-italic">System Process</span>';
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
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // 3.3 Manual Refresh Icon Listener
    $('#refreshIcon').closest('a, div').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});