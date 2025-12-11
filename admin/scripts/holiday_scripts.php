<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var holidayTable;
let spinnerStartTime = 0; 
let currentHolidayId = null;

// 1.1 Helper function: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.2 Helper function: Stops the spinner only after the minimum time has passed (Standardized to 1000ms)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; // Standardized to 1 second
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    if (timeElapsed < minDisplayTime) {
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        finalizeStop();
    }
}

// 1.3 Map Philippine Holiday Types to standard payroll multipliers 
function getMultiplier(type) {
    switch (type) {
        case 'Regular':
            return 2.00;
        case 'Special Non-Working':
            return 1.30;
        case 'Special Working':
            return 1.00;
        case 'National Local':
            return 1.00;
        default:
            return 1.00;
    }
}

// 1.4 Function triggered by the Holiday Type dropdown change
function updateMultiplier() {
    const selectedType = $('#holiday_type').val();
    const multiplier = getMultiplier(selectedType);
    $('#payroll_multiplier').val(multiplier.toFixed(2));
}

// 1.5 Function to reset and open the Add/Edit Modal (Used for both Edit and View)
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
        // Fetch existing data for editing
        $.ajax({
            url: 'api/holiday_action.php?action=get_details',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                Swal.close();
                
                // *** CRITICAL FIX: Check for data.status === 'success' ***
                if (data.status === 'success' && data.details) {
                    const details = data.details; // Use a clean variable name
                    
                    $('#holiday_id').val(details.id);
                    $('#holiday_date').val(details.holiday_date);
                    $('#holiday_name').val(details.holiday_name);
                    $('#holiday_type').val(details.holiday_type);
                    
                    // Ensure multiplier is parsed and formatted correctly before display
                    $('#payroll_multiplier').val(parseFloat(details.payroll_multiplier || 1.00).toFixed(2));
                    $('#holidayModal').modal('show');
                } else {
                    // Use data.message if available, otherwise generic error
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

// 1.6 Function to delete a holiday
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
            $.ajax({
                url: 'api/holiday_action.php?action=delete',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        window.refreshPageContent(); // Refresh table using hook
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

// 1.7 MASTER REFRESHER HOOK
window.refreshPageContent = function() {
    // Start animation timer and text
    spinnerStartTime = new Date().getTime(); 
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    // Reload table 
    if (holidayTable) {
        holidayTable.ajax.reload(null, false);
    }
};


$(document).ready(function() {

    // Attach global functions to window scope for HTML onclick/form events
    window.updateMultiplier = updateMultiplier;
    window.openModal = openModal;
    window.deleteHoliday = deleteHoliday;
    
    // Bind multiplier update logic to the dropdown change event
    $('#holiday_type').on('change', updateMultiplier);
    
    // Bind delete button inside modal to the current ID
    $('#deleteHolidayBtn').on('click', function() {
        if(currentHolidayId) {
            $('#holidayModal').modal('hide'); // Close modal before SweetAlert
            deleteHoliday(currentHolidayId);
        }
    });


    // 2. INITIALIZE DATATABLE
    holidayTable = $('#holidayTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip',
        ajax: {
            url: "api/holiday_action.php?action=fetch",
            type: "GET",
        },

        // CRITICAL: Triggers the safe stop function after data is received and drawn
        drawCallback: function(settings) {
            const icon = $('#refresh-spinner');
            if (icon.hasClass('fa-spin')) {
                stopSpinnerSafely();
            } else {
                updateLastSyncTime(); 
            }
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
            // Col 3: Action (Updated to FA6 icon)
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
    
    // 3. FORM SUBMISSION (Create/Update)
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
                    window.refreshPageContent(); // Refresh table using hook
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            }
        });
    });
    
    // Ensure initial load updates the time
    updateLastSyncTime();
});
</script>