<script>
// --- GLOBAL STATE VARIABLES ---
let adminTable; 
let spinnerStartTime = 0; // Tracks when the spin started (for synchronization visuals)

// 1. HELPER: Updates the final timestamp text (e.g., "10:30:05 AM")
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    // This assumes an element with ID 'last-updated-time' exists in your topbar.
    $('#last-updated-time').text(timeString);
}

// 2. HELPER: Stops the spinner safely (ensures it runs for at least 500ms)
function stopSpinnerSafely() {
    // This assumes an element with ID 'refresh-spinner' exists in your topbar.
    const icon = $('#refresh-spinner');
    const minDisplayTime = 500; // Minimum spin time in ms
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    if (timeElapsed < minDisplayTime) {
        // Wait the remaining time
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        // Stop immediately
        finalizeStop();
    }
}

// 3. HELPER: Resets the Add/Edit form
function resetForm() {
    $('#adminForm')[0].reset();
    $('#modalTitle').text('Add New Admin');
    $('#formAction').val('add');
    $('#userId').val('');
    $('#employeeId').prop('readonly', false);
    $('#passwordLabel').text('Password');
    $('#password').attr('placeholder', 'Default: admin123');
    $('#passwordHelp').hide();
}

// --- MASTER REFRESHER TRIGGER ---
// Allows Topbar buttons and internal actions to trigger a full, visually-synced table reload.
window.refreshPageContent = function() {
    // 1. Record Start Time
    spinnerStartTime = new Date().getTime(); 
    
    // 2. Start Visual feedback
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    // 3. Reload table (Draw callback will handle stopping the spinner)
    adminTable.ajax.reload(null, false);
};

// Internal function used after CRUD operations
function reloadTable() {
    window.refreshPageContent();
}

$(document).ready(function() {
    
    // --- INITIALIZE DATATABLE ---
    adminTable = $('#dataTable').DataTable({
        // Add classes for visual consistency
        "responsive": true, 
        "serverSide": true,
        
        "ajax": {
            "url": "api/admin_action.php", 
            "type": "POST",
            "data": { action: 'fetch' }
        },
        
        // ⭐ CRITICAL: Triggers the safe stop function after data is received and drawn
        "drawCallback": function(settings) {
            const icon = $('#refresh-spinner');
            
            if (icon.hasClass('fa-spin')) { 
                stopSpinnerSafely();
            } else {
                updateLastSyncTime(); 
            }
        },

        // COLUMN DEFINITIONS (Matches the HTML <thead>)
        "columns": [
            // Col 1: Emp ID
            { 
                "data": "employee_id", 
                "className": "align-middle", // Added for consistency
                "render": function(data) { 
                    return '<span class="badge bg-secondary">' + data + '</span>'; 
                } 
            },
            // Col 2: Email Address
            { "data": "email", "className": "align-middle" },
            // Col 3: Status
            { 
                "data": "status",
                "className": "align-middle",
                "render": function(data) {
                    return (data == 1) 
                        ? '<span class="badge bg-success">Active</span>' 
                        : '<span class="badge bg-danger">Inactive</span>';
                }
            },
            // Col 4: Created At
            { 
                "data": "created_at",
                "className": "align-middle",
                "render": function(data) {
                    if(!data) return '';
                    let date = new Date(data);
                    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                }
            },
            // Col 5: Actions
            { 
                "data": null, 
                "orderable": false, // Actions column should not be sortable
                "className": "align-middle text-center text-nowrap", // Center align actions
                "render": function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-info text-white me-1 edit-btn" 
                            data-id="${row.id}"
                            data-empid="${row.employee_id}"
                            data-email="${row.email}"
                            data-status="${row.status}">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-btn" data-id="${row.id}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                }
            }
        ]
    });

    // --- 6. HANDLE FORM SUBMISSION (ADD/EDIT) ---
    $('#adminForm').on('submit', function(e) {
        e.preventDefault();
        let btn = $('#saveBtn');
        let originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        let formData = new FormData(this);

        $.ajax({
            url: 'api/admin_action.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' || (res.message && res.message.includes('success'))) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        showConfirmButton: false,
                        timer: 1500
                    });
                    $('#adminModal').modal('hide');
                    reloadTable(); 
                } else {
                    Swal.fire('Error', res.message || 'Unknown error', 'error');
                }
            },
            error: function() {
                Swal.fire('Server Error', 'Failed to process request.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // --- 7. HANDLE DELETE ---
    $(document).on('click', '.delete-btn', function() {
        let id = $(this).data('id');
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
                    url: 'api/admin_action.php',
                    type: 'POST',
                    data: { action: 'delete', user_id: id },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('Deleted!', res.message, 'success');
                            reloadTable(); 
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // --- 8. EDIT BUTTON LOGIC ---
    $(document).on('click', '.edit-btn', function() {
        let id = $(this).data('id');
        let empid = $(this).data('empid');
        let email = $(this).data('email');
        let status = $(this).data('status');

        resetForm();
        $('#modalTitle').text('Edit Admin');
        $('#formAction').val('edit');
        $('#userId').val(id);
        $('#employeeId').val(empid).prop('readonly', true);
        $('#email').val(email);
        $('#status').val(status);
        $('#passwordLabel').text('Change Password');
        $('#password').attr('placeholder', 'Leave blank to keep current');
        $('#passwordHelp').show();
        $('#adminModal').modal('show');
    });
});
</script>