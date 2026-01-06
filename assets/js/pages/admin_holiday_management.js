/**
 * Holiday Management Controller
 * Handles Holiday CRUD, automated payroll multipliers, and real-time UI syncing.
 * Standardized with AppUtility for Sync Status.
 */

// ==============================================================================
// 1. GLOBAL STATE & MASTER REFRESHER
// ==============================================================================
var holidayTable;
window.currentHolidayId = null;

/**
 * MASTER REFRESHER TRIGGER
 * Integrates with AppUtility to show the Syncing/Synced state
 */
window.refreshPageContent = function(isManual = false) {
    if (holidayTable) {
        if (isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        holidayTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. BUSINESS LOGIC (Attached to window for global access)
// ==============================================================================

/**
 * 2.1 Map Philippine Holiday Types to standard payroll multipliers
 */
function getMultiplier(type) {
    const rates = {
        'Regular': 2.00,             // Double Pay
        'Special Non-Working': 1.30, // 30% Premium
        'Special Working': 1.00,     // Regular Pay
        'Default': 1.00
    };
    return rates[type] || rates['Default'];
}

/**
 * 2.2 Function triggered by the Holiday Type dropdown change
 */
window.updateMultiplier = function() {
    const selectedType = $('#holiday_type').val();
    const multiplier = getMultiplier(selectedType);
    $('#payroll_multiplier').val(multiplier.toFixed(2));
};

/**
 * 2.3 Function to reset and open the Add/Edit Modal
 */
window.openModal = function(id = null) {
    window.currentHolidayId = id;
    const $form = $('#holidayForm');
    const $modal = $('#holidayModal');
    
    $form[0].reset();
    $('#holiday_id').val('');
    $('#modalTitle').text(id ? 'Edit Holiday Details' : 'Register New Holiday');
    
    // Toggle visibility of Delete button (only for existing records)
    $('#deleteHolidayBtn').toggleClass('d-none', !id);

    if (id) {
        Swal.fire({ 
            title: 'Fetching details...', 
            allowOutsideClick: false, 
            didOpen: () => { Swal.showLoading(); } 
        });
        
        $.post('../api/admin/holiday_action.php?action=get_details', { id: id }, function(res) {
            Swal.close();
            if (res.status === 'success') {
                const d = res.details;
                $('#holiday_id').val(d.id);
                $('#holiday_date').val(d.holiday_date);
                $('#holiday_name').val(d.holiday_name);
                $('#holiday_type').val(d.holiday_type);
                $('#payroll_multiplier').val(parseFloat(d.payroll_multiplier).toFixed(2));
                $modal.modal('show');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json').fail(() => {
            Swal.close();
            Swal.fire('Error', 'Network error occurred.', 'error');
        });
    } else {
        window.updateMultiplier(); 
        $modal.modal('show');
    }
};

/**
 * 2.4 Function to delete a holiday
 */
window.deleteHoliday = function(id) {
    Swal.fire({
        title: 'Delete Holiday?',
        text: "This may affect payroll calculations for this date range.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74a3b',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            $.post('../api/admin/holiday_action.php?action=delete', { id: id }, function(res) {
                if (res.status === 'success') {
                    Swal.fire('Deleted', res.message, 'success');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
};

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    // 3.1 INITIALIZE DATATABLE
    holidayTable = $('#holidayTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        stateSave: true,
        dom: 'rtip',
        ajax: {
            url: "../api/admin/holiday_action.php?action=fetch",
            type: "GET",
            error: function() {
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        drawCallback: function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
        },
        columns: [
            { 
                data: 'holiday_date',
                className: 'fw-bold align-middle',
                render: function(data) {
                    return new Date(data).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }
            },
            { data: 'holiday_name', className: 'align-middle' },
            { 
                data: 'holiday_type', 
                className: 'align-middle',
                render: function(data) {
                    let badgeClass = data === 'Regular' ? 'bg-soft-primary text-primary' : 'bg-soft-info text-info';
                    return `<span class="badge ${badgeClass} border px-2 text-xs">${data}</span>`;
                }
            },
            { 
                data: 'id',
                orderable: false,
                className: 'text-center align-middle',
                render: d => `<button class="btn btn-sm btn-outline-teal fw-bold shadow-sm" onclick="openModal(${d})"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</button>`
            }
        ]
    });

    // 3.2 FORM SUBMISSION
    let isSubmitting = false;
    $('#holidayForm').on('submit', function(e) {
        e.preventDefault();
        if (isSubmitting) return;

        const action = $('#holiday_id').val() ? 'update' : 'create';
        isSubmitting = true;

        Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        $.ajax({
            url: '../api/admin/holiday_action.php?action=' + action,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                isSubmitting = false;
                if (res.status === 'success') {
                    $('#holidayModal').modal('hide');
                    Swal.fire('Success', res.message, 'success');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                isSubmitting = false;
                Swal.fire('Error', 'Server communication failure.', 'error');
            }
        });
    });

    // 3.3 Event Bindings
    $('#holiday_type').on('change', window.updateMultiplier);
    
    $('#deleteHolidayBtn').on('click', function() {
        if(window.currentHolidayId) {
            $('#holidayModal').modal('hide');
            window.deleteHoliday(window.currentHolidayId);
        }
    });

    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});