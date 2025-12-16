<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var holidayTable;
let currentHolidayId = null;

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
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

// 1.2 MASTER REFRESHER TRIGGER
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    if (holidayTable) {
        // 1. Visual Feedback for Manual Actions
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // 2. Reload DataTable (false = keep paging)
        holidayTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. BUSINESS LOGIC (Modal, Multiplier, Delete)
// ==============================================================================

// 2.1 Map Philippine Holiday Types to standard payroll multipliers 
function getMultiplier(type) {
    switch (type) {
        case 'Regular': return 2.00;
        case 'Special Non-Working': return 1.30;
        case 'Special Working': return 1.00;
        case 'National Local': return 1.00;
        default: return 1.00;
    }
}

// 2.2 Function triggered by the Holiday Type dropdown change
function updateMultiplier() {
    const selectedType = $('#holiday_type').val();
    const multiplier = getMultiplier(selectedType);
    $('#payroll_multiplier').val(multiplier.toFixed(2));
}

// 2.3 Function to reset and open the Add/Edit Modal
function openModal(id = null) {
    currentHolidayId = id;
    $('#holidayForm')[0].reset();
    $('#holiday_id').val('');
    $('#modalTitle').text(id ? 'Edit Holiday' : 'Add New Holiday');
    
    // Hide the delete button by default, show only if editing
    if ($('#deleteHolidayBtn').length) {
        $('#deleteHolidayBtn').toggle(!!id);
    }

    if (id) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        $.ajax({
            url: 'api/holiday_action.php?action=get_details',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                Swal.close();
                
                if (data.status === 'success' && data.details) {
                    const details = data.details;
                    
                    $('#holiday_id').val(details.id);
                    $('#holiday_date').val(details.holiday_date);
                    $('#holiday_name').val(details.holiday_name);
                    $('#holiday_type').val(details.holiday_type);
                    
                    $('#payroll_multiplier').val(parseFloat(details.payroll_multiplier || 1.00).toFixed(2));
                    $('#holidayModal').modal('show');
                } else {
                    Swal.fire('Error', data.message || 'Could not fetch holiday details.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    } else {
        // Reset to default Regular Holiday multiplier on Add
        updateMultiplier(); 
        $('#holidayModal').modal('show');
    }
}

// 2.4 Function to delete a holiday
function deleteHoliday(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            $.ajax({
                url: 'api/holiday_action.php?action=delete',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        window.refreshPageContent(true); // Visual Refresh
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server Error. Delete failed.', 'error');
                }
            });
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    // 3.1 Attach global functions to window scope
    window.updateMultiplier = updateMultiplier;
    window.openModal = openModal;
    window.deleteHoliday = deleteHoliday;
    
    // Bind multiplier update logic to the dropdown change event
    $('#holiday_type').on('change', updateMultiplier);
    
    // Bind delete button inside modal to the current ID
    $('#deleteHolidayBtn').on('click', function() {
        if(currentHolidayId) {
            $('#holidayModal').modal('hide'); 
            deleteHoliday(currentHolidayId);
        }
    });

    // 3.2 INITIALIZE DATATABLE
    holidayTable = $('#holidayTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip',
        ajax: {
            url: "api/holiday_action.php?action=fetch",
            type: "GET",
        },

        // DRAW CALLBACK: Standardized UI updates
        drawCallback: function(settings) {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },

        columns: [
            // Col 0: Date
            { 
                data: 'holiday_date',
                className: 'text-nowrap fw-bold align-middle',
                render: function(data) {
                    return new Date(data).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }
            },
            // Col 1: Name
            { data: 'holiday_name', className: 'align-middle' },
            // Col 2: Type
            { data: 'holiday_type', className: 'align-middle' },
            // Col 3: Action
            { 
                data: 'id',
                orderable: false,
                className: 'text-center text-nowrap align-middle',
                width: '100px', 
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="openModal(${data})">
                            <i class="fa-solid fa-eye me-1"></i> Details
                        </button>`;
                }
            }
        ],
        language: { "emptyTable": "No holidays configured." }
    });

    // 3.3 DETECT LOADING STATE
    $('#holidayTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });
    
    // 3.4 FORM SUBMISSION (Create/Update)
    $('#holidayForm').on('submit', function(e) {
        e.preventDefault();
        
        const action = $('#holiday_id').val() ? 'update' : 'create';
        Swal.fire({ title: 'Saving Data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        $.ajax({
            url: 'api/holiday_action.php?action=' + action,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success') {
                    $('#holidayModal').modal('hide');
                    Swal.fire('Success', res.message, 'success');
                    window.refreshPageContent(true); // Visual Refresh
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            }
        });
    });
    
    // 3.5 Manual Refresh Listener
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});
</script>