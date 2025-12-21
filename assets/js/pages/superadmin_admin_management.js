// assets/js/pages/superadmin_admin_management.js

// ==============================================================================
// 1. GLOBAL STATE
// ==============================================================================
var adminTable;

// Note: updateSyncStatus() is inherited globally from footer.php

// 1.1 MASTER REFRESHER HOOK
// Called automatically by footer.php every 15s or on manual click
window.refreshPageContent = function(isManual = false) {
    if (adminTable) {
        // Just reload the table.
        // The 'processing' event below will handle the "Syncing..." text.
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
        $('#editAdminForm')[0].reset(); // Reset the edit form

        Swal.fire({
            title: 'Loading...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            // ⭐ FIX: Use Global API_ROOT
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
                    $('#editAdminModal').modal('show'); // Show the edit modal
                } else {
                    Swal.fire('Error', res.message || 'Could not fetch details.', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Swal.fire('Error', 'Server request failed: ' + textStatus + ' - ' + errorThrown, 'error');
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR);
            }
        });
    } else {
        // --- ADD NEW MODE ---
        $('#addAdminForm')[0].reset(); // Reset the add form

        // **NEW: Set the default password**
        $('#add_password').val('losi@123'); // Set the default password here

        // Load available employees for new admin creation
        Swal.fire({
            title: 'Loading...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            // ⭐ FIX: Use Global API_ROOT
            url: API_ROOT + '/superadmin/admin_management_action.php',
            type: 'POST',
            data: { action: 'get_available_employees' },
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success' && res.employees) {
                    const employees = res.employees;
                    const employeeIdField = $('#add_employee_id'); // Target the add modal's select

                    employeeIdField.empty(); // Clear previous options

                    if (employees.length > 0) {
                        // Populate the select dropdown
                        employees.forEach(employee => {
                            employeeIdField.append($('<option>', {
                                value: employee.employee_id,
                                text: employee.employee_id + ' - ' + employee.name
                            }));
                        });
                    } else {
                        employeeIdField.append($('<option>', {
                            value: '',
                            text: 'No available employees'
                        }));
                    }

                    $('#addAdminModal').modal('show'); // Show the add modal
                } else {
                    Swal.fire('Error', res.message || 'Could not fetch available employees.', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Swal.fire('Error', 'Server request failed: ' + textStatus + ' - ' + errorThrown, 'error');
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR);
            }
        });
    }
}

// 2.2 Delete Admin
function deleteAdmin(id) {
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
                // ⭐ FIX: Use Global API_ROOT
                url: API_ROOT + '/superadmin/admin_management_action.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        // Reload table to show changes
                        adminTable.ajax.reload(null, false);
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
    adminTable = $('#adminTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        dom: 'rtip',
        ajax: {
            // ⭐ FIX: Use Global API_ROOT
            url: API_ROOT + "/superadmin/admin_management_action.php",
            type: "POST",
            data: { action: 'fetch' }
        },
        // ⭐ HOOK: Update Sync Status when table finishes drawing
        drawCallback: function(settings) {
            if (typeof updateSyncStatus === "function") updateSyncStatus('success');
        },
        columns: [{
                data: 'employee_id',
                className: "align-middle fw-bold",
                render: function(data) {
                    return '<span class="badge bg-soft-teal text-primary border border-teal px-2">' + data + '</span>';
                }
            },
            {
                data: 'email',
                className: "align-middle"
            },
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
                    return new Date(data).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
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
                        <button class="btn btn-sm btn-outline-secondary shadow-sm" onclick="deleteAdmin(${data})" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        language: {
            emptyTable: "No administrators found."
        }
    });

    // 3.2 DETECT LOADING STATE
    // This connects the DataTable loading event to our Global Sync Status text
    $('#adminTable').on('processing.dt', function(e, settings, processing) {
        if (processing) {
            if (typeof updateSyncStatus === "function") updateSyncStatus('loading');
        }
    });

    // 3.3 Handle Form Submission
    // $('#adminForm').on('submit', function(e) {  // REMOVE THIS
    //     e.preventDefault();
    //     const action = $('#admin_id').val() ? 'update' : 'create';
    //     const formData = $(this).serialize() + '&action=' + action;

    //     Swal.fire({
    //         title: 'Saving...',
    //         allowOutsideClick: false,
    //         didOpen: () => {
    //             Swal.showLoading();
    //         }
    //     });

    //     $.ajax({
    //         // ⭐ FIX: Use Global API_ROOT
    //         url: API_ROOT + '/superadmin/admin_management_action.php',
    //         type: 'POST',
    //         data: formData,
    //         dataType: 'json',
    //         success: function(res) {
    //             Swal.close();
    //             if (res.status === 'success') {
    //                 $('#adminModal').modal('hide');
    //                 Swal.fire({
    //                     icon: 'success',
    //                     title: 'Success',
    //                     text: res.message,
    //                     timer: 1500,
    //                     showConfirmButton: false
    //                 });
    //             // Reload table
    //             adminTable.ajax.reload(null, false);
    //         } else {
    //             Swal.fire('Error', res.message, 'error');
    //         }
    //     },
    //     error: function() {
    //         Swal.fire('Error', 'Server connection failed.', 'error');
    //     }
    // });

    // 3.4 Handle Add Admin Form Submission
    $('#addAdminForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=create'; // Action is always 'create'

        Swal.fire({
            title: 'Saving...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: API_ROOT + '/superadmin/admin_management_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success') {
                    $('#addAdminModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    adminTable.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
             error: function(jqXHR, textStatus, errorThrown) {
                Swal.fire('Error', 'Server request failed: ' + textStatus + ' - ' + errorThrown, 'error');
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR);
            }
        });
    });

    // 3.5 Handle Edit Admin Form Submission
    $('#editAdminForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=update'; // Action is always 'update'

        Swal.fire({
            title: 'Saving...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: API_ROOT + '/superadmin/admin_management_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success') {
                    $('#editAdminModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    adminTable.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
             error: function(jqXHR, textStatus, errorThrown) {
                Swal.fire('Error', 'Server request failed: ' + textStatus + ' - ' + errorThrown, 'error');
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR);
            }
        });
    });
});