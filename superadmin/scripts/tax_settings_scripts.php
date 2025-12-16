<script>
// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var taxTable;

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
    if (taxTable) {
        // If Manual Click -> Spin Icon & Show 'Syncing...'
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // Reload DataTable (false = keep paging)
        taxTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL & CRUD LOGIC
// ==============================================================================

// 2.1 Open Modal (Add or Edit)
function openModal(id = null) {
    $('#taxForm')[0].reset();
    $('#tax_id').val('');
    $('#modalTitle').text(id ? 'Edit Tax Slab' : 'Add Tax Slab');

    if(id) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        $.post('api/tax_settings_action.php', { action: 'get_details', id: id }, function(res) {
            Swal.close();
            if(res.status === 'success') {
                let d = res.details;
                $('#tax_id').val(d.id);
                $('#tier_name').val(d.tier_name);
                $('#min_income').val(d.min_income);
                $('#max_income').val(d.max_income);
                $('#base_tax').val(d.base_tax);
                $('#excess_rate').val(d.excess_rate);
                $('#taxModal').modal('show');
            } else {
                Swal.fire('Error', res.message || 'Fetch failed', 'error');
            }
        }, 'json');
    } else {
        $('#taxModal').modal('show');
    }
}

// 2.2 Delete Slab
function deleteSlab(id) {
    Swal.fire({
        title: 'Delete Tax Slab?', 
        text: "This might affect payroll calculations.",
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#d33', 
        confirmButtonText: 'Yes, delete it!'
    }).then((res) => {
        if(res.isConfirmed) {
            $.post('api/tax_settings_action.php', { action: 'delete', id: id }, function(data) {
                if(data.status === 'success') {
                    Swal.fire('Deleted', data.message, 'success');
                    window.refreshPageContent(true); // Trigger Manual Refresh Style
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            }, 'json');
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    // 3.1 INITIALIZE DATATABLE
    taxTable = $('#taxTable').DataTable({
        ajax: { url: 'api/tax_settings_action.php', type: 'POST', data: { action: 'fetch' } },
        dom: 'rtip',
        ordering: true,
        order: [[1, 'asc']], // Order by Min Income
        drawCallback: function() { 
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },
        columns: [
            { data: 'tier_name', className: 'align-middle fw-bold' },
            { 
                data: 'min_income', className: 'align-middle',
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            { 
                data: 'max_income', className: 'align-middle',
                render: function(data) {
                    return data ? '₱ ' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2}) : '<span class="badge bg-soft-secondary text-secondary">And Above</span>';
                }
            },
            { 
                data: 'base_tax', className: 'align-middle',
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            { 
                data: 'excess_rate', className: 'text-center align-middle fw-bold',
                render: function(data) { return data + '%'; }
            },
            {
                data: 'id', orderable: false, className: 'text-center align-middle',
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="openModal(${data})"><i class="fas fa-pen"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="deleteSlab(${data})"><i class="fas fa-trash"></i></button>
                    `;
                }
            }
        ]
    });

    // 3.2 DETECT LOADING STATE
    $('#taxTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // 3.3 FORM SUBMIT
    $('#taxForm').on('submit', function(e) {
        e.preventDefault();
        let action = $('#tax_id').val() ? 'update' : 'create';
        let formData = $(this).serialize() + '&action=' + action;

        Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        $.post('api/tax_settings_action.php', formData, function(res) {
            Swal.close();
            if(res.status === 'success') {
                $('#taxModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Saved', text: res.message, timer: 1500, showConfirmButton: false });
                window.refreshPageContent(true);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // 3.4 Manual Refresh Button Listener
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});
</script>