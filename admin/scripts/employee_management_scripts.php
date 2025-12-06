<?php
// scripts/employee_management_scripts.php
if (!isset($employment_statuses)) {
    $employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];
}
?>

<script>
var employeesTable;

// Convert PHP array to JavaScript object for status rendering
var employmentStatuses = {
    <?php foreach ($employment_statuses as $id => $name) { echo "$id: '$name',"; } ?>
};

// Function to safely reset and re-initialize Dropify
function resetDropify(targetId, defaultFile = null) {
    const $dropify = $(`#${targetId}`);
    
    $dropify.dropify('destroy'); 
    $dropify.removeAttr('data-default-file'); // Clear previous default file path
    
    if (defaultFile) {
        $dropify.attr('data-default-file', defaultFile);
    }
    
    // Re-initialize Dropify
    $dropify.dropify(); 
}

// Global function to call for fetching details and opening the Edit Modal
window.editEmployee = function(id) {
    
    Swal.fire({
        title: 'Loading Profile',
        text: `Fetching details for employee ID: ${id}...`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    $.ajax({
        url: 'api/employee_action.php?action=get_details',
        type: 'POST',
        data: { employee_id: id },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if(response.status === 'success' && response.data) {
                const data = response.data;
                const form = $('#editEmployeeForm');
                
                // --- CRITICAL: Set Hidden ID and Read-only ID ---
                $('#edit_employee_id_hidden').val(data.employee_id);
                $('#edit_employee_id_display').val(data.employee_id);
                
                // --- Populate Fields (Personal Info) ---
                form.find('#edit_firstname').val(data.firstname);
                form.find('#edit_middlename').val(data.middlename);
                form.find('#edit_lastname').val(data.lastname);
                form.find('#edit_suffix').val(data.suffix);
                form.find('#edit_birthdate').val(data.birthdate);
                form.find('#edit_gender').val(data.gender);
                form.find('#edit_contact_info').val(data.contact_info);
                form.find('#edit_address').val(data.address);
                
                // --- Populate Fields (Employment Info) ---
                form.find('#edit_position').val(data.position);
                form.find('#edit_department').val(data.department);
                form.find('#edit_employment_status').val(data.employment_status);
                
                // --- Dropify Update ---
                const photoUrl = data.photo ? '../assets/images/' + data.photo : null;
                resetDropify('photo_edit', photoUrl); 

                $('#editModal').modal('show');

            } else {
                Swal.fire('Error', response.message || 'Failed to fetch employee details.', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Server request failed while fetching details.', 'error');
        }
    });
};


$(document).ready(function() {

    // --- INITIALIZE DROPIFY ONCE (Standard Init) ---
    if(typeof $('.dropify').dropify === 'function') {
        $('.dropify').dropify();
    }
    
    // --- 1. Initialize DataTable (Server-Side) ---
    employeesTable = $('#employeesTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: "api/employee_action.php?action=fetch", 
            type: "GET"
        },
        columns: [
             // Col 0: ID
            { data: 'employee_id', className: 'fw-bold text-gray-700' },
            // Col 1: Name & Position
            { 
                data: 'lastname', 
                render: function(data, type, row) {
                    var fullName = row.firstname + ' ' + row.lastname;
                    var photo = row.photo ? '../assets/images/' + row.photo : '../assets/images/default.png';
                    
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark">${fullName}</div>
                                <div class="small text-muted">${row.position}</div>
                            </div>
                        </div>
                    `;
                }
            },
            // Col 2: Status
            { 
                data: 'employment_status', 
                className: 'text-center',
                render: function(data) {
                    var statusName = employmentStatuses[data] || 'Unknown';
                    var cls = (data == 1) ? 'success' : (data == 5 || data == 6) ? 'danger' : 'warning';
                    return `<span class="badge bg-soft-${cls} text-${cls} border border-${cls} px-2 rounded-pill">${statusName}</span>`;
                }
            },
            // Col 3: Actions
            {
                data: 'employee_id',
                orderable: false,
                className: 'text-center',
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="editEmployee('${data}')">
                            <i class="fas fa-eye me-1"></i> Details 
                        </button>
                    `;
                }
            }
        ],
        order: [[ 3, "asc" ]], 
    });

    // --- 2. Link Custom Search ---
    $('#customSearch').on('keyup', function() {
        employeesTable.search(this.value).draw();
    });

    // --- 3. Handle Form Submission: CREATE (Add Modal) ---
    $('#addEmployeeForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);

        Swal.fire({
            title: 'Saving Employee...',
            text: 'Processing data and handling photo upload...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: `api/employee_action.php?action=create`, // Dedicated CREATE action
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false, 
            contentType: false, 
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#addModal').modal('hide');
                    employeesTable.ajax.reload(null, false); 
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    });

    // --- 4. Handle Form Submission: UPDATE (Edit Modal) ---
    $('#editEmployeeForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        
        Swal.fire({
            title: 'Updating Profile...',
            text: 'Processing changes and updating records...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: `api/employee_action.php?action=update`, // Dedicated UPDATE action
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false, 
            contentType: false, 
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#editModal').modal('hide');
                    employeesTable.ajax.reload(null, false); 
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    });

    // --- 5. Modal Hide Listeners (Resets forms after closing) ---

    // A. Add Modal Hide Listener
    $('#addModal').on('hidden.bs.modal', function() {
        $('#addEmployeeForm')[0].reset();
        resetDropify('photo'); // Reset Dropify on the ADD modal
    });

    // B. Edit Modal Hide Listener
    $('#editModal').on('hidden.bs.modal', function() {
        $('#editEmployeeForm')[0].reset();
        resetDropify('photo_edit'); // Reset Dropify on the EDIT modal
    });
});
</script>