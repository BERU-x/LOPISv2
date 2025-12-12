<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var employeeTable;
let spinnerStartTime = 0; 
let currentUserId = null;

// 1.1 Helper: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' });
    $('#last-updated-time').text(timeString);
}

// 1.2 Helper: Stops the spinner safely
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

// 1.3 MASTER REFRESHER HOOK
window.refreshPageContent = function() {
    spinnerStartTime = new Date().getTime(); 
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    if (employeeTable) {
        employeeTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL & CRUD LOGIC
// ==============================================================================

// 2.1 Open Modal (Add or Edit)
function openModal(id = null) {
    currentUserId = id;
    
    // Reset Form & UI
    $('#employeeForm')[0].reset();
    $('#user_id').val('');
    $('#modalTitle').text(id ? 'Edit Account' : 'Add New Account');
    
    // Password Hint Logic
    if(id) {
        $('#password_hint').removeClass('d-none');
        $('#password').removeAttr('required').attr('placeholder', 'Leave blank to keep current');
        $('#employee_id').attr('readonly', true); // Prevent changing ID on edit to avoid mismatches
    } else {
        $('#password_hint').addClass('d-none');
        $('#password').attr('required', 'required').attr('placeholder', 'Default: employee123');
        $('#employee_id').attr('readonly', false);
    }

    if (id) {
        // EDIT MODE: Fetch details via AJAX
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        $.ajax({
            url: 'api/user_action.php', // Note path adjustment
            type: 'POST',
            data: { action: 'get_details', id: id },
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success' && res.details) {
                    const d = res.details;
                    $('#user_id').val(d.id);
                    $('#employee_id').val(d.employee_id);
                    $('#email').val(d.email);
                    $('#status').val(d.status);
                    $('#employeeModal').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'Could not fetch details.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    } else {
        // ADD MODE
        $('#employeeModal').modal('show');
    }
}

// 2.2 Delete Account
function deleteAccount(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This user will lose access to the system.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '../api/user_action.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        window.refreshPageContent();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server connection failed.', 'error');
                }
            });
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    // 3.1 Initialize DataTable
    employeeTable = $('#employeeTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: "api/user_action.php", // Points to the generic User API
            type: "POST",
            data: { action: 'fetch' } // The API defaults to usertype=2 if not specified, or we handle it there
        },
        drawCallback: function(settings) {
            const icon = $('#refresh-spinner');
            if (icon.hasClass('fa-spin')) {
                stopSpinnerSafely();
            } else {
                updateLastSyncTime(); 
            }
        },
        columns: [
            // Col 1: Emp ID
            { 
                data: 'employee_id', 
                className: "align-middle fw-bold",
                render: function(data) { 
                    return '<span class="badge bg-soft-info text-info border border-info px-2">' + data + '</span>'; 
                } 
            },
            // Col 2: Email
            { data: 'email', className: "align-middle" },
            // Col 3: Status
            { 
                data: 'status', 
                className: "text-center align-middle",
                render: function(data) {
                    if(data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fa-solid fa-check me-1"></i> Active</span>';
                    return '<span class="badge bg-soft-secondary text-secondary border border-secondary px-3 shadow-sm rounded-pill"><i class="fa-solid fa-ban me-1"></i> Inactive</span>';
                }
            },
            // Col 4: Created At
            { 
                data: 'created_at', 
                className: "align-middle text-muted small",
                render: function(data) {
                    if(!data) return '';
                    return new Date(data).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                }
            },
            // Col 5: Actions
            { 
                data: 'id', 
                orderable: false, 
                className: "text-center align-middle text-nowrap",
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-outline-primary shadow-sm me-1" onclick="openModal(${data})" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger shadow-sm" onclick="deleteAccount(${data})" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        language: { emptyTable: "No employee accounts found." }
    });

    // 3.2 Handle Form Submission
    $('#employeeForm').on('submit', function(e) {
        e.preventDefault();
        
        const action = $('#user_id').val() ? 'update' : 'create';
        const formData = $(this).serialize() + '&action=' + action;

        Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: 'api/user_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success') {
                    $('#employeeModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    window.refreshPageContent();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            }
        });
    });

    // Initial time update
    updateLastSyncTime();
});
</script>