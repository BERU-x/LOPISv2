// assets/js/pages/pay_components.js

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var earningsTable, deductionsTable;

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
    // 1. Visual Feedback for Manual Click
    if(isManual) {
        $('#refreshIcon').addClass('fa-spin');
        updateSyncStatus('loading');
    }

    // 2. Reload Tables (Silent)
    // We reload both. The 'drawCallback' in earningsTable will handle removing the spinner/success state.
    if (earningsTable) earningsTable.ajax.reload(null, false);
    if (deductionsTable) deductionsTable.ajax.reload(null, false);
};

// ==============================================================================
// 2. MODAL & CRUD LOGIC
// ==============================================================================

// 2.1 Open Modal (Add or Edit)
function openModal(type, id = null) {
    $('#componentForm')[0].reset();
    $('#comp_id').val('');
    $('#comp_type').val(type);
    
    // UI Updates
    let typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
    $('#modalTitle').text(id ? 'Edit ' + typeLabel : 'Add New ' + typeLabel);

    if(id) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        $.ajax({
            // ⭐ UPDATED PATH
            url: '../api/superadmin/pay_components_action.php',
            type: 'POST',
            data: { action: 'get_details', id: id },
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    let d = res.details;
                    $('#comp_id').val(d.id);
                    $('#name').val(d.name);
                    $('#is_taxable').val(d.is_taxable);
                    $('#is_recurring').val(d.is_recurring);
                    $('#componentModal').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'Fetch failed', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed', 'error');
            }
        });
    } else {
        $('#componentModal').modal('show');
    }
}

// 2.2 Delete Component
function deleteComponent(id) {
    Swal.fire({
        title: 'Delete Component?', 
        text: "This will remove it from future calculations.",
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#d33', 
        confirmButtonText: 'Yes, delete it!'
    }).then((res) => {
        if(res.isConfirmed) {
            // ⭐ UPDATED PATH
            $.post('../api/superadmin/pay_components_action.php', { action: 'delete', id: id }, function(data) {
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
    
    // 3.1 Shared Column Config
    const columnsConfig = [
        { data: 'name', className: 'align-middle fw-bold' },
        { 
            data: 'is_taxable', className: 'text-center align-middle',
            render: function(data) {
                return data == 1 
                    ? '<span class="badge bg-soft-primary text-primary">Taxable</span>' 
                    : '<span class="badge bg-soft-secondary text-secondary">Non-Taxable</span>';
            }
        },
        { 
            data: 'is_recurring', className: 'text-center align-middle',
            render: function(data) {
                return data == 1 
                    ? '<i class="fas fa-redo text-success" title="Recurring"></i>' 
                    : '<i class="fas fa-clock text-warning" title="One-Time"></i>';
            }
        },
        {
            data: 'id', orderable: false, className: 'text-center align-middle',
            render: function(data, type, row) {
                return `
                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="openModal('${row.type}', ${data})"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="deleteComponent(${data})"><i class="fas fa-trash"></i></button>
                `;
            }
        }
    ];

    // 3.2 Initialize Earnings Table
    earningsTable = $('#earningsTable').DataTable({
        // ⭐ UPDATED PATH
        ajax: { url: '../api/superadmin/pay_components_action.php', type: 'POST', data: { action: 'fetch', type: 'earning' } },
        columns: columnsConfig,
        dom: 'rtip',
        drawCallback: function() { 
            // Only update sync status on the main table to avoid double-firing
            updateSyncStatus('success'); 
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        }
    });

    // 3.3 Initialize Deductions Table
    deductionsTable = $('#deductionsTable').DataTable({
        // ⭐ UPDATED PATH
        ajax: { url: '../api/superadmin/pay_components_action.php', type: 'POST', data: { action: 'fetch', type: 'deduction' } },
        columns: columnsConfig,
        dom: 'rtip'
    });

    // 3.4 Detect Loading State (For visual 'Syncing...' feedback)
    $('#earningsTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // 3.5 Form Submit
    $('#componentForm').on('submit', function(e) {
        e.preventDefault();
        let action = $('#comp_id').val() ? 'update' : 'create';
        let formData = $(this).serialize() + '&action=' + action;

        Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        // ⭐ UPDATED PATH
        $.post('../api/superadmin/pay_components_action.php', formData, function(res) {
            Swal.close();
            if(res.status === 'success') {
                $('#componentModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Saved', text: res.message, timer: 1500, showConfirmButton: false });
                window.refreshPageContent(true);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
    
    // 3.6 Manual Refresh Button Listener
    $('#refreshIcon').closest('a, div').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});