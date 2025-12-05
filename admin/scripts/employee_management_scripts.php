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

$(document).ready(function() {

    // --- INITIALIZE DROPIRY ONCE (Standard Init) ---
    if(typeof $('.dropify').dropify === 'function') {
        $('.dropify').dropify();
    }
    
    // --- 1. Initialize DataTable (Server-Side) ---
    employeesTable = $('#employeesTable').DataTable({
        // ... (DataTable settings remain the same) ...
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
            
            // Col 2: Daily Rate
            { 
                data: 'daily_rate', 
                className: 'text-end fw-bold',
                render: function(data) {
                    return '₱' + parseFloat(data || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
                }
            },
            
            // Col 3: Status
            { 
                data: 'employment_status', 
                className: 'text-center',
                render: function(data) {
                    var statusName = employmentStatuses[data] || 'Unknown';
                    var cls = (data == 1) ? 'success' : (data == 5 || data == 6) ? 'danger' : 'warning';
                    return `<span class="badge bg-soft-${cls} text-${cls} border border-${cls} px-2 rounded-pill">${statusName}</span>`;
                }
            },
            
            // Col 4: Actions (View/Edit)
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

    // --- 3. Handle Form Submission (Unified CREATE/UPDATE Logic) ---
    $('#addEmployeeForm').on('submit', function(e) {
        e.preventDefault();
        
        const actionType = $('#form_action_type').val();
        const apiAction = actionType === 'update' ? 'update' : 'create';

        var formData = new FormData(this);

        Swal.fire({
            title: actionType === 'update' ? 'Updating Profile...' : 'Saving Employee...',
            text: 'Processing data and handling photo upload...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: `api/employee_action.php?action=${apiAction}`,
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false, 
            contentType: false, 
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#addEmployeeModal').modal('hide');
                    employeesTable.ajax.reload(null, false); 
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Server request failed. Check console for details.', 'error');
                console.error("AJAX Error:", xhr.responseText);
            }
        });
    });

    // --- 4. Function to Fetch Details and Open Modal for Editing (Fixed Dropify Logic) ---
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
                    const form = $('#addEmployeeForm');
                    
                    // A. Set form mode to UPDATE
                    $('#form_action_type').val('update');
                    
                    // B. Populate Fields
                    form.find('#employee_id').val(data.employee_id).prop('readonly', true);
                    form.find('#firstname').val(data.firstname);
                    form.find('#middlename').val(data.middlename);
                    form.find('#lastname').val(data.lastname);
                    form.find('#suffix').val(data.suffix);
                    form.find('#birthdate').val(data.birthdate);
                    form.find('#gender').val(data.gender);
                    form.find('#contact_info').val(data.contact_info);
                    form.find('#address').val(data.address);
                    
                    form.find('#position').val(data.position);
                    form.find('#department').val(data.department);
                    form.find('#employment_status').val(data.employment_status);
                    
                    form.find('#daily_rate').val(data.daily_rate);
                    form.find('#monthly_rate').val(data.monthly_rate);
                    form.find('#food_allowance').val(data.food_allowance);
                    form.find('#transpo_allowance').val(data.transpo_allowance);
                    
                    form.find('input[name="bank_name"]').val(data.bank_name);
                    form.find('input[name="account_number"]').val(data.account_number);

                    // C. Stable Dropify Update Logic (Using jQuery wrapper)
                    const photoUrl = data.photo ? '../assets/images/' + data.photo : null;
                    
                    // 1. Clear current preview safely
                    $('#photo').dropify('clear'); 

                    if (photoUrl) {
                         // 2. Set the default file attribute value
                        $('#photo').attr('data-default-file', photoUrl); 
                        // 3. Force Dropify to load the new file from the attribute
                        $('#photo').dropify('destroy').dropify(); 
                    } else {
                        // Ensure the attribute is clean if no photo exists
                        $('#photo').removeAttr('data-default-file');
                        $('#photo').dropify('destroy').dropify(); 
                    }

                    // D. Update Modal UI
                    $('#addEmployeeModalLabel').html('<i class="fas fa-user-edit me-3"></i> Edit Employee Profile: ' + data.employee_id);
                    $('button[name="add_employee"]').html('<i class="fas fa-save me-2"></i> Update Employee');

                    $('#addEmployeeModal').modal('show');

                } else {
                    Swal.fire('Error', response.message || 'Failed to fetch employee details.', 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Server request failed while fetching details.', 'error');
            }
        });
    };
    
    // --- 5. Modal Hide Listener (Stable Dropify Reset) ---
    $('#addEmployeeModal').on('hidden.bs.modal', function() {
        const form = $('#addEmployeeForm');
        
        // Reset form fields
        form[0].reset();
        
        // Stable Dropify Reset: Clear preview and reset to initial state
        // Use destroy/re-init pattern here as well for consistency, but focused on reset
        $('#photo').dropify('clear'); // Safely clear preview
        $('#photo').removeAttr('data-default-file'); // Clear file path attribute
        $('#photo').dropify('destroy').dropify(); // Reset to clean state

        // Reset Form Mode and UI for CREATE
        form.find('#employee_id').prop('readonly', false);
        $('#form_action_type').val('create');
        $('#addEmployeeModalLabel').html('<i class="fas fa-user-plus me-3"></i> Add New Employee Profile');
        $('button[name="add_employee"]').html('<i class="fas fa-save me-2"></i> Save New Employee');
    });
});
</script>