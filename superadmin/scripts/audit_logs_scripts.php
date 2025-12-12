<script>
var logsTable;
let spinnerStartTime = 0;

function updateLastSyncTime() {
    $('#last-updated-time').text(new Date().toLocaleTimeString());
}
function stopSpinnerSafely() {
    setTimeout(() => { $('#refresh-spinner').removeClass('fa-spin text-teal'); updateLastSyncTime(); }, 500);
}
window.refreshPageContent = function() {
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    logsTable.ajax.reload(null, false);
};

// VIEW DETAILS
function viewLog(id) {
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

// CLEAR LOGS
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
            $.post('api/audit_logs_action.php', { action: 'clear_logs' }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Cleared!', res.message, 'success');
                    window.refreshPageContent();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}

$(document).ready(function() {
    
    // DATATABLE
    logsTable = $('#logsTable').DataTable({
        serverSide: true, // IMPORTANT: Load data from server
        processing: true,
        ajax: { url: 'api/audit_logs_action.php', type: 'POST', data: { action: 'fetch' } },
        order: [[0, 'desc']], // Newest first
        pageLength: 25,
        dom: 'rtip',
        drawCallback: function() { stopSpinnerSafely(); },
        columns: [
            { 
                data: 'created_at', 
                className: 'text-nowrap',
                render: function(data) {
                    return new Date(data).toLocaleString();
                }
            },
            { 
                data: 'username',
                className: 'fw-bold',
                render: function(data, type, row) {
                    return data ? data : '<span class="text-muted fst-italic">System / Guest</span>';
                }
            },
            { 
                data: 'usertype',
                render: function(data) {
                    if(data == 0) return '<span class="badge bg-danger">Super Admin</span>';
                    if(data == 1) return '<span class="badge bg-primary">Admin</span>';
                    if(data == 2) return '<span class="badge bg-info">Employee</span>';
                    return '<span class="badge bg-secondary">Unknown</span>';
                }
            },
            { 
                data: 'action',
                render: function(data) {
                    let color = 'secondary';
                    if(data.includes('LOGIN')) color = 'success';
                    if(data.includes('DELETE')) color = 'danger';
                    if(data.includes('UPDATE')) color = 'warning text-dark';
                    if(data.includes('CREATE') || data.includes('ADD')) color = 'primary';
                    return `<span class="badge bg-soft-${color} text-${color}">${data}</span>`;
                }
            },
            { data: 'ip_address', className: 'text-monospace small' },
            {
                data: 'id', orderable: false, className: 'text-center',
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-secondary" onclick="viewLog(${data})"><i class="fas fa-eye"></i></button>`;
                }
            }
        ]
    });

    updateLastSyncTime();
});
</script>