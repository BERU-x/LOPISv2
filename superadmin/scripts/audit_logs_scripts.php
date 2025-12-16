<script>
// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var logsTable;

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
    if (logsTable) {
        // If Manual Click -> Spin Icon & Show 'Syncing...'
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // Reload DataTable (false = keep paging)
        logsTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. LOG ACTIONS
// ==============================================================================

// 2.1 View Details
function viewLog(id) {
    // Show loading state briefly or just fetch
    $.post('api/audit_logs_action.php', { action: 'get_details', id: id }, function(res) {
        if(res.status === 'success') {
            let d = res.data;
            $('#view_action').text(d.action);
            $('#view_agent').text(d.user_agent);
            $('#view_details').text(d.details || 'No additional details provided.');
            $('#logModal').modal('show');
        }
    }, 'json');
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
            
            $.post('api/audit_logs_action.php', { action: 'clear_logs' }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Cleared!', res.message, 'success');
                    window.refreshPageContent(true); // Trigger Manual Refresh Style
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
        ajax: { url: 'api/audit_logs_action.php', type: 'POST', data: { action: 'fetch' } },
        order: [[0, 'desc']], // Newest first
        pageLength: 25,
        dom: 'rtip',
        // AUTOMATIC UI UPDATES ON DRAW
        drawCallback: function(settings) {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },
        columns: [
            { 
                data: 'created_at', 
                className: 'text-nowrap align-middle',
                render: function(data) {
                    return new Date(data).toLocaleString();
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
                    let color = 'secondary';
                    if(data.includes('LOGIN')) color = 'success';
                    if(data.includes('DELETE')) color = 'danger';
                    if(data.includes('UPDATE')) color = 'warning';
                    if(data.includes('CREATE') || data.includes('ADD')) color = 'primary';
                    return `<span class="badge bg-soft-${color} text-${color}">${data}</span>`;
                }
            },
            { data: 'ip_address', className: 'text-monospace small align-middle' },
            {
                data: 'id', orderable: false, className: 'text-center align-middle',
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-secondary" onclick="viewLog(${data})"><i class="fas fa-eye"></i></button>`;
                }
            }
        ]
    });

    // 3.2 DETECT LOADING STATE
    $('#logsTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // 3.3 Manual Refresh Button Listener
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});
</script>