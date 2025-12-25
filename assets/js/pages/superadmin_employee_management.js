/**
 * Employee Account Management Controller
 * Handles Add/Edit Modals, DataTables, and Global Syncing.
 */

var employeeTable;

// ==============================================================================
// 1. MASTER REFRESHER HOOK
// ==============================================================================
window.refreshPageContent = function(isManual = false) {
    if (employeeTable) {
        if(isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        employeeTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. ADD MODAL LOGIC
// ==============================================================================
function openAddModal() {
    $('#addEmployeeForm')[0].reset();
    $('#add_password').val('losi123'); // Reset to default

    // Load available employees for the dropdown
    // This prevents creating duplicate accounts for the same person
    const $select = $('#add_employee_id');
    $select.html('<option value="">Loading...</option>');

    $.ajax({
        url: API_ROOT + '/superadmin/employee_account_action.php',
        type: 'POST',
        data: { action: 'get_available_employees' },
        dataType: 'json',
        success: function(res) {
            $select.empty();
            
            if (res.status === 'success' && res.employees.length > 0) {
                // Populate dropdown
                res.employees.forEach(emp => {
                    $select.append(`<option value="${emp.employee_id}">${emp.employee_id} - ${emp.name}</option>`);
                });
            } else {
                $select.append('<option value="">No employees available (All have accounts)</option>');
            }
            
            $('#addEmployeeModal').modal('show');
        },
        error: function() {
            $select.html('<option value="">Error loading list</option>');
            Swal.fire('Error', 'Could not fetch employee list.', 'error');
        }
    });
}

// Handle Add Form Submission
$('#addEmployeeForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize() + '&action=create';

    Swal.fire({ title: 'Creating Account...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.ajax({
        url: API_ROOT + '/superadmin/employee_account_action.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(res) {
            Swal.close();
            if (res.status === 'success') {
                $('#addEmployeeModal').modal('hide');
                Swal.fire({ 
                    icon: 'success', title: 'Success', 
                    text: res.message, timer: 1500, showConfirmButton: false 
                });
                if(window.AppUtility) window.AppUtility.updateSyncStatus('success');
                employeeTable.ajax.reload(null, false);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        error: function(xhr) {
            Swal.close();
            console.error(xhr.responseText);
            Swal.fire('Error', 'Server request failed.', 'error');
        }
    });
});

// ==============================================================================
// 3. EDIT MODAL LOGIC
// ==============================================================================
function openEditModal(id) {
    Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.ajax({
        url: API_ROOT + '/superadmin/employee_account_action.php',
        type: 'POST',
        data: { action: 'get_details', id: id },
        dataType: 'json',
        success: function(res) {
            Swal.close();
            if (res.status === 'success') {
                const d = res.details;
                
                // Populate Edit Modal Fields
                $('#edit_id').val(d.id);
                $('#edit_employee_id').val(d.employee_id); // Read-only field
                $('#edit_email').val(d.email);
                $('#edit_status').val(d.status);
                $('#edit_password').val(''); // Always clear password on edit open
                
                $('#editEmployeeModal').modal('show');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Failed to fetch account details.', 'error');
        }
    });
}

// Handle Edit Form Submission
$('#editEmployeeForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize() + '&action=update';

    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.ajax({
        url: API_ROOT + '/superadmin/employee_account_action.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(res) {
            Swal.close();
            if (res.status === 'success') {
                $('#editEmployeeModal').modal('hide');
                Swal.fire({ 
                    icon: 'success', title: 'Updated', 
                    text: res.message, timer: 1500, showConfirmButton: false 
                });
                if(window.AppUtility) window.AppUtility.updateSyncStatus('success');
                employeeTable.ajax.reload(null, false);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        error: function(xhr) {
            Swal.close();
            console.error(xhr.responseText);
            Swal.fire('Error', 'Server request failed.', 'error');
        }
    });
});

// ==============================================================================
// 4. DELETE LOGIC
// ==============================================================================
function deleteAccount(id) {
    Swal.fire({
        title: 'Delete Account?',
        text: "This removes login access. The HR record remains.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            
            if(window.AppUtility) window.AppUtility.updateSyncStatus('loading');

            $.ajax({
                url: API_ROOT + '/superadmin/employee_account_action.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        employeeTable.ajax.reload(null, false);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server connection failed.', 'error');
                    if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
                }
            });
        }
    });
}

// ==============================================================================
// 5. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    // Initialize DataTable
    employeeTable = $('#employeeTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        ajax: {
            url: API_ROOT + "/superadmin/employee_account_action.php", 
            type: "POST",
            data: { action: 'fetch' },
            error: function (xhr, error, code) {
                if (error !== 'abort') {
                    console.error("DataTables Error:", error);
                    if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
                }
            }
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
                        <button class="btn btn-sm btn-outline-secondary shadow-sm me-1" onclick="openEditModal(${data})" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary shadow-sm" onclick="deleteAccount(${data})" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        language: { 
            emptyTable: "<div class='py-4 text-center text-muted'>No employee accounts found.</div>",
            processing: "<div class='spinner-border text-primary spinner-border-sm'></div> Loading..."
        }
    });

    // Detect Loading State for Global Sync
    $('#employeeTable').on('processing.dt', function (e, settings, processing) {
        if (window.AppUtility) {
            if (processing) window.AppUtility.updateSyncStatus('loading');
        }
    });

    // Detect Success State
    $('#employeeTable').on('draw.dt', function() {
        if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
    });
});