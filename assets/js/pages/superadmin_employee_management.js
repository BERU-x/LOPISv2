// assets/js/pages/employee_management.js

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var employeeTable;

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
    if (employeeTable) {
        // If Manual Click -> Spin Icon & Show 'Syncing...'
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // Reload DataTable (false = keep paging)
        employeeTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL & CRUD LOGIC
// ==============================================================================

// 2.1 Open Modal (Add or Edit)
function openModal(id = null) {
    $('#employeeForm')[0].reset();
    $('#user_id').val('');
    $('#modalTitle').text(id ? 'Edit Account' : 'Add New Account');
    
    // Password Hint & ID Readonly Logic
    if(id) {
        $('#password_hint').removeClass('d-none');
        $('#password').removeAttr('required').attr('placeholder', 'Leave blank to keep current');
        $('#employee_id').attr('readonly', true);
    } else {
        $('#password_hint').addClass('d-none');
        $('#password').attr('required', 'required').attr('placeholder', 'Default: Employee@123');
        $('#employee_id').attr('readonly', false);
    }

    if (id) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        $.ajax({
            // ⭐ UPDATED PATH
            url: '../api/superadmin/employee_account_action.php',
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
                // ⭐ UPDATED PATH
                url: '../api/superadmin/employee_account_action.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        window.refreshPageContent(true); // Trigger Manual Refresh Style
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
            // ⭐ UPDATED PATH
            url: "../api/superadmin/employee_account_action.php", 
            type: "POST",
            data: { action: 'fetch' } 
        },
        // AUTOMATIC UI UPDATES ON DRAW
        drawCallback: function(settings) {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },
        columns: [
            { 
                data: 'employee_id', 
                className: "align-middle fw-bold",
                render: function(data) { 
                    return '<span class="badge bg-soft-info text-info border border-info px-2">' + data + '</span>'; 
                } 
            },
            { data: 'email', className: "align-middle" },
            { 
                data: 'status', 
                className: "text-center align-middle",
                render: function(data) {
                    if(data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fa-solid fa-check me-1"></i> Active</span>';
                    return '<span class="badge bg-soft-secondary text-secondary border border-secondary px-3 shadow-sm rounded-pill"><i class="fa-solid fa-ban me-1"></i> Inactive</span>';
                }
            },
            { 
                data: 'created_at', 
                className: "align-middle text-muted small",
                render: function(data) {
                    if(!data) return '';
                    return new Date(data).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                }
            },
            { 
                data: 'id', 
                orderable: false, 
                className: "text-center align-middle text-nowrap",
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-outline-secondary shadow-sm me-1" onclick="openModal(${data})" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary shadow-sm" onclick="deleteAccount(${data})" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        language: { emptyTable: "No employee accounts found." }
    });

    // 3.2 DETECT LOADING STATE
    $('#employeeTable').on('processing.dt', function (e, settings, processing) {
        if (processing) {
            if(!$('#refreshIcon').hasClass('fa-spin')) {
                updateSyncStatus('loading');
            }
        }
    });

    // 3.3 Handle Form Submission
    $('#employeeForm').on('submit', function(e) {
        e.preventDefault();
        const action = $('#user_id').val() ? 'update' : 'create';
        const formData = $(this).serialize() + '&action=' + action;

        Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            // ⭐ UPDATED PATH
            url: '../api/superadmin/employee_account_action.php',
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
                    window.refreshPageContent(true); // Manual refresh style on success
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            }
        });
    });

    // 3.4 Manual Refresh Button Listener
    $('#refreshIcon').closest('a, div').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});