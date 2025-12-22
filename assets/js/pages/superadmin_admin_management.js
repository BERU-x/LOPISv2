/**
 * Super Admin - Admin Management Controller
 * Handles DataTables, Modal CRUD operations, and Real-time Syncing.
 */

// ==============================================================================
// 1. GLOBAL STATE
// ==============================================================================
var adminTable;

// 1.1 MASTER REFRESHER HOOK
window.refreshPageContent = function(isManual = false) {
    if (adminTable) {
        if (isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        adminTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL & CRUD LOGIC
// ==============================================================================

// 2.1 Open Modal (Add or Edit)
function openModal(id = null) {
    if (id) {
        // --- EDIT MODE ---
        $('#editAdminForm')[0].reset(); 

        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: API_ROOT + '/superadmin/admin_management_action.php',
            type: 'POST',
            data: { action: 'get_details', id: id },
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success' && res.details) {
                    const d = res.details;
                    $('#edit_admin_id').val(d.id);
                    $('#edit_employee_id').val(d.employee_id);
                    $('#edit_email').val(d.email);
                    $('#edit_status').val(d.status);
                    $('#editAdminModal').modal('show'); 
                } else {
                    Swal.fire('Error', res.message || 'Could not fetch details.', 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                handleAjaxError(xhr, status, error);
            }
        });
    } else {
        // --- ADD NEW MODE ---
        $('#addAdminForm')[0].reset();
        $('#add_password').val('losi@123'); // Default password

        Swal.fire({ title: 'Loading Employees...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: API_ROOT + '/superadmin/admin_management_action.php',
            type: 'POST',
            data: { action: 'get_available_employees' },
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success' && res.employees) {
                    const employees = res.employees;
                    const employeeIdField = $('#add_employee_id'); 
                    employeeIdField.empty(); 

                    if (employees.length > 0) {
                        employees.forEach(emp => {
                            employeeIdField.append($('<option>', {
                                value: emp.employee_id,
                                text: emp.employee_id + ' - ' + emp.name
                            }));
                        });
                    } else {
                        employeeIdField.append($('<option>', { value: '', text: 'No available employees found' }));
                    }
                    $('#addAdminModal').modal('show'); 
                } else {
                    Swal.fire('Error', res.message || 'Could not fetch available employees.', 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                handleAjaxError(xhr, status, error);
            }
        });
    }
}

// 2.2 Delete Admin
function deleteAdmin(id) {
    Swal.fire({
        title: 'Revoke Admin Access?',
        text: "This user will be downgraded to a standard employee.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Yes, Revoke Access'
    }).then((result) => {
        if (result.isConfirmed) {
            
            if(window.AppUtility) window.AppUtility.updateSyncStatus('loading');

            $.ajax({
                url: API_ROOT + '/superadmin/admin_management_action.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Revoked!', res.message, 'success');
                        adminTable.ajax.reload(null, false);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error);
                    if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
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
    adminTable = $('#adminTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        ajax: {
            url: API_ROOT + "/superadmin/admin_management_action.php",
            type: "POST",
            data: { action: 'fetch' },
            error: function (xhr, error, code) {
                // Ignore user-cancelled requests
                if (error === 'abort') return;

                // ⭐ DEBUGGING: Log the crash report to console
                console.error("DataTable Fatal Error:", xhr.responseText);
                
                // Show user friendly error, but with a hint to check console
                if(xhr.status === 200 && xhr.responseText.startsWith('<')) {
                     // This means PHP outputted HTML (Fatal Error) instead of JSON
                     if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
                     return; // DataTables will show its own error alert usually, or we can suppress it
                }
                
                if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        columns: [
            {
                data: 'employee_id',
                className: "align-middle fw-bold",
                render: function(data) {
                    return '<span class="badge bg-soft-teal text-primary border border-teal px-2">' + data + '</span>';
                }
            },
            { data: 'email', className: "align-middle" },
            {
                data: 'status',
                className: "text-center align-middle",
                render: function(data) {
                    if (data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fa-solid fa-check me-1"></i> Active</span>';
                    return '<span class="badge bg-soft-secondary text-secondary border border-secondary px-3 shadow-sm rounded-pill"><i class="fa-solid fa-ban me-1"></i> Inactive</span>';
                }
            },
            {
                data: 'created_at',
                className: "align-middle text-muted small",
                render: function(data) {
                    if (!data) return '';
                    return new Date(data).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                }
            },
            {
                data: 'id',
                orderable: false,
                className: "text-center align-middle text-nowrap",
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-outline-primary shadow-sm me-1" onclick="openModal(${data})" title="Edit Admin">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger shadow-sm" onclick="deleteAdmin(${data})" title="Revoke Admin">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        language: {
            emptyTable: "<div class='py-4 text-center text-muted'>No administrators found.</div>",
            processing: "<div class='spinner-border text-primary spinner-border-sm'></div> Loading..."
        }
    });

    // 3.2 Loading States
    $('#adminTable').on('processing.dt', function(e, settings, processing) {
        if (window.AppUtility && processing) window.AppUtility.updateSyncStatus('loading');
    });
    $('#adminTable').on('draw.dt', function() {
        if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
    });

    // 3.3 Add Admin Form
    $('#addAdminForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=create'; 

        Swal.fire({ title: 'Creating...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: API_ROOT + '/superadmin/admin_management_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#addAdminModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Success', text: res.message, timer: 1500, showConfirmButton: false });
                    adminTable.ajax.reload(null, false);
                } else {
                    Swal.close();
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                handleAjaxError(xhr, status, error);
            }
        });
    });

    // 3.4 Edit Admin Form
    $('#editAdminForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=update'; 

        Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: API_ROOT + '/superadmin/admin_management_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#editAdminModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Updated', text: res.message, timer: 1500, showConfirmButton: false });
                    adminTable.ajax.reload(null, false);
                } else {
                    Swal.close();
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                handleAjaxError(xhr, status, error);
            }
        });
    });
});

// ==============================================================================
// 4. HELPER: GLOBAL AJAX ERROR HANDLER
// ==============================================================================
function handleAjaxError(xhr, status, error) {
    if (status === 'parsererror') {
        console.error("PHP PARSE ERROR:", xhr.responseText);
        Swal.fire({
            icon: 'error',
            title: 'Server Error (PHP)',
            html: `The server returned a crash report instead of JSON.<br>
                   <div class="text-start mt-2 p-2 bg-light border text-danger small font-monospace" style="max-height: 150px; overflow-y:auto;">
                     ${xhr.responseText.substring(0, 300)}...
                   </div>
                   <br>Check the browser console (F12) for the full report.`
        });
    } else {
        Swal.fire('Network Error', 'The request failed. Please check your connection.', 'error');
    }
}