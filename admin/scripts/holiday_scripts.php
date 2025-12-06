<script>
// --- GLOBAL STATE VARIABLES AND HELPERS ---
var holidayTable;
let spinnerStartTime = 0; 
let currentHolidayId = null;

// Helper function: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// Helper function: Stops the spinner only after the minimum time has passed (500ms)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 500; 
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
// ---------------------------------------------------

// Map Philippine Holiday Types to standard payroll multipliers (KEEPING FUNCTION for modal use)
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

// Function triggered by the Holiday Type dropdown change
function updateMultiplier() {
    const selectedType = $('#holiday_type').val();
    const multiplier = getMultiplier(selectedType);
    $('#payroll_multiplier').val(multiplier.toFixed(2));
}

// Function to reset and open the Add/Edit Modal (Used for both Edit and View)
function openModal(id = null) {
    currentHolidayId = id;
    $('#holidayForm')[0].reset();
    $('#holiday_id').val('');
    $('#modalTitle').text(id ? 'Edit Holiday' : 'Add New Holiday');

    if (id) {
        // Fetch existing data for editing
        $.ajax({
            url: 'api/holiday_action.php?action=get_details',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#holiday_id').val(data.details.id);
                    $('#holiday_date').val(data.details.holiday_date);
                    $('#holiday_name').val(data.details.holiday_name);
                    $('#holiday_type').val(data.details.holiday_type);
                    $('#payroll_multiplier').val(parseFloat(data.details.payroll_multiplier).toFixed(2));
                    $('#holidayModal').modal('show');
                } else {
                    Swal.fire('Error', 'Could not fetch holiday details.', 'error');
                }
            }
        });
    } else {
        // Reset to default Regular Holiday multiplier on Add
        updateMultiplier(); 
        $('#holidayModal').modal('show');
    }
}

// Function to delete a holiday
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
                }
            });
        }
    });
}


$(document).ready(function() {

    // Attach the global multiplier update function
    window.updateMultiplier = updateMultiplier;
    window.openModal = openModal;
    window.deleteHoliday = deleteHoliday;


    // 1. INITIALIZE DATATABLE
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
                className: 'text-nowrap fw-bold',
                render: function(data) {
                    return new Date(data).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }
            },
            // Col 1: Name
            { data: 'holiday_name' },
            // Col 2: Type
            { data: 'holiday_type' },
            // Col 3: Multiplier (REMOVED) - Keep structure for indexing stability if needed, but remove from list

            // Col 3 (Previous Col 4): Action (MODIFIED TO VIEW ONLY)
            { 
                data: 'id',
                orderable: false,
                className: 'text-center text-nowrap',
                width: '100px', // Set fixed width for single button
                render: function(data) {
                    // Only view button remaining, reusing openModal to display details
                    return `
                        <button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="openModal(${data})">
                            <i class="fas fa-eye me-1"></i> Details
                        </button>`;
                }
            }
        ],
        // IMPORTANT: We skip the multiplier column (index 3) in the PHP header, 
        // so we only list 4 columns here (Date, Name, Type, Action).
        // The server response must still return the 'payroll_multiplier' data field 
        // for the existing modal fetch logic to work.
        columnDefs: [
            // Hide the Multiplier column visually if the server cannot be changed immediately
            // But since the PHP table header was removed, we just remove the column definition.
            // If you need the multiplier data in the future without showing it, you can add 
            // { data: 'payroll_multiplier', visible: false }, here.
        ],
        language: { "emptyTable": "No holidays configured." }
    });
    
    // 2. FORM SUBMISSION (Create/Update)
    $('#holidayForm').on('submit', function(e) {
        e.preventDefault();
        const action = $('#holiday_id').val() ? 'update' : 'create';
        
        $.ajax({
            url: 'api/holiday_action.php?action=' + action,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#holidayModal').modal('hide');
                    Swal.fire('Success', res.message, 'success');
                    window.refreshPageContent(); // Refresh table using hook
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // 3. MASTER REFRESHER HOOK
    window.refreshPageContent = function() {
        // Start animation timer and text
        spinnerStartTime = new Date().getTime(); 
        $('#refresh-spinner').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');
        
        // Reload table 
        holidayTable.ajax.reload(null, false);
    };
    
    // Ensure initial load updates the time
    updateLastSyncTime();
});
</script>