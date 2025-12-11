<?php
// scripts/employee_management_scripts.php
if (!isset($employment_statuses)) {
    $employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];
}
?>

<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var employeesTable; 
let spinnerStartTime = 0; 
// CRITICAL: Variable to hold the photo URL fetched by AJAX
window.currentPhotoUrl = null; 

var employmentStatuses = {
    <?php foreach ($employment_statuses as $id => $name) { echo "$id: '$name',"; } ?>
};

// 1.0 HELPER: Dynamically finds the project base URL (e.g., http://localhost/LOPISv2/)
function getProjectBaseUrl() {
    // Current URL is typically something like: http://localhost/LOPISv2/admin/employee_management.php
    const pathArray = window.location.pathname.split('/');
    
    // We assume the project folder is the second segment (index 1) after the domain root (index 0 is empty)
    const projectFolder = pathArray[1]; 
    
    // Return the absolute base URL including the project folder
    return window.location.origin + '/' + projectFolder + '/';
}

// 1.1 HELPER: Updates the final timestamp text (Must be globally accessible)
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.2 HELPER: Stops the spinner safely (Calls the global updateLastSyncTime)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
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

// 1.3 MASTER REFRESHER TRIGGER (Hook for Topbar/Buttons)
window.refreshPageContent = function() {
    // 1. Start Sync Visuals
    spinnerStartTime = new Date().getTime(); 
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    // 2. Reload the DataTable
    if (employeesTable) { // Safety check added
        employeesTable.ajax.reload(null, false);
    }
};

// 1.4 INTERNAL RELOAD (Used after CRUD operations)
function reloadEmployeeTable() {
    window.refreshPageContent();
}

// 1.5 HELPER: Function to safely reset and re-initialize Dropify (FINAL BRUTE FORCE ID CHANGE)
function resetDropify(targetId, defaultFile = null) {
    const $dropify = $(`#${targetId}`);
    
    // 1. If an instance exists, destroy it.
    if ($dropify.data('dropify')) {
        $dropify.dropify('destroy'); 
    }
    
    // 2. Clear old data attribute
    $dropify.removeAttr('data-default-file'); 
    
    // 3. Set the new default file URL using the HTML attribute
    if (defaultFile) {
        $dropify.attr('data-default-file', defaultFile);
    }
    
    // 4. CRITICAL: Change the ID and then immediately change it back (or give it a temp unique ID)
    // We will clear the element's inner HTML wrapper if it's there
    const $wrapper = $dropify.closest('.dropify-wrapper').parent();
    if ($wrapper.length) {
        $wrapper.html($dropify.prop('outerHTML')); // Recreate the input element cleanly
        $dropify.remove(); // Remove the old jquery reference
        
        // Re-establish the reference to the newly inserted element
        const newId = targetId + '_temp_' + Date.now();
        $(`#${targetId}`).attr('id', newId).removeClass('dropify-touched'); // Change ID and reset class
        
        const $newDropify = $(`#${newId}`);
        $newDropify.attr('name', 'photo'); // Ensure name is correct
        
        if (defaultFile) {
            $newDropify.attr('data-default-file', defaultFile);
        }
        
        // 5. Re-initialize Dropify on the new element.
        $newDropify.dropify();
        
        // Restore the original ID for form submission (optional, but safe)
        $newDropify.attr('id', targetId);
    } else {
        // If the wrapper structure is not found, just re-initialize the original element as a fallback
        $dropify.dropify();
    }
}

// 1.6 HELPER: Resets the primary Add form
function resetForm() {
    $('#addEmployeeForm')[0].reset();
    resetDropify('photo');
}

// 2. WINDOW.EDITEMPLOYEE FUNCTION (Data Fetch for Edit Modal)
window.editEmployee = function(id) {
    Swal.fire({ title: 'Fetching Employee Data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    
    // Reset temporary URL store before AJAX call
    window.currentPhotoUrl = null; 

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
                
                // --- Populate Fields ---
                $('#edit_employee_id_hidden').val(data.employee_id);
                $('#edit_employee_id_display').val(data.employee_id);
                
                form.find('#edit_firstname').val(data.firstname);
                form.find('#edit_middlename').val(data.middlename);
                form.find('#edit_lastname').val(data.lastname);
                form.find('#edit_suffix').val(data.suffix);
                form.find('#edit_birthdate').val(data.birthdate);
                form.find('#edit_gender').val(data.gender);
                form.find('#edit_contact_info').val(data.contact_info);
                form.find('#edit_address').val(data.address);
                
                form.find('#edit_position').val(data.position);
                form.find('#edit_department').val(data.department);
                form.find('#edit_employment_status').val(data.employment_status);

                form.find('#edit_daily_rate').val(data.daily_rate || 0.00);
                form.find('#edit_monthly_rate').val(data.monthly_rate || 0.00);
                form.find('#edit_food_allowance').val(data.food_allowance || 0.00);
                form.find('#edit_transpo_allowance').val(data.transpo_allowance || 0.00);
                
                form.find('#edit_bank_name').val(data.bank_name);
                form.find('#edit_account_number').val(data.account_number);

                // --- Photo FIX: CALCULATE URL AND STORE IT GLOBALLY ---
                if (data.photo) {
                    const baseUrl = getProjectBaseUrl();
                    window.currentPhotoUrl = `${baseUrl}assets/images/${data.photo}`;
                    console.log("Final Brute Force URL:", window.currentPhotoUrl); 
                } else {
                    window.currentPhotoUrl = null;
                }
                
                // CRITICAL: SHOW THE MODAL FIRST. Dropify re-initialization will happen
                // in the 'shown.bs.modal' event listener.
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

    // --- INITIALIZE DROPIFY for ADD modal (Once) ---
    if(typeof $('.dropify').dropify === 'function') {
        $('#photo').dropify(); 
        $('#photo_edit').dropify(); 
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
        
        // DRAW CALLBACK: Triggers the safe stop function after data is drawn
        drawCallback: function(settings) {
            const icon = $('#refresh-spinner');
            if (icon.hasClass('fa-spin')) { 
                stopSpinnerSafely();
            } else {
                updateLastSyncTime(); // Update time on initial load
            }
        },
        
        columns: [
            // Column 0: ID
            { data: 'employee_id', className: 'fw-bold text-gray-700 align-middle' },
            
            // Column 1: Name & Position
            { 
                data: 'lastname', 
                className: 'align-middle',
                orderable: true,
                render: function(data, type, row) {
                    var fullName = row.firstname + ' ' + row.lastname;
                    var photo = row.photo ? '../assets/images/' + row.photo : '../assets/images/default.png';
                    
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.src='../assets/images/default.png'">
                            <div>
                                <div class="fw-bold text-dark">${fullName}</div>
                                <div class="small text-muted">${row.position}</div>
                            </div>
                        </div>
                    `;
                }
            },
            
            // Column 2: Status
            { 
                data: 'employment_status', 
                className: 'text-center align-middle',
                render: function(data) {
                    var statusName = employmentStatuses[data] || 'Unknown';
                    var cls = (data == 1) ? 'success' : (data == 5 || data == 6) ? 'danger' : 'warning';
                    return `<span class="badge bg-soft-${cls} text-${cls} border border-${cls} px-2 rounded-pill">${statusName}</span>`;
                }
            },
            
            // Column 3: Daily Rate
            { 
                data: 'daily_rate', 
                className: 'text-end fw-bold align-middle',
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },

            // Column 4: Actions
            {
                data: 'employee_id',
                orderable: false,
                className: 'text-center align-middle',
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="editEmployee('${data}')"><i class="fa-solid fa-eye me-1"></i> Details</button>`;
                }
            }
        ],
        order: [[ 1, "asc" ]], 
    });

    // --- 2. Link Custom Search ---
    $('#customSearch').on('keyup', function() {
        employeesTable.search(this.value).draw();
    });

    // --- 3. Handle Form Submission: CREATE (Add Modal) ---
    $('#addEmployeeForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        Swal.fire({ title: 'Saving Employee...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: `api/employee_action.php?action=create`, type: 'POST', data: formData, dataType: 'json', processData: false, contentType: false, 
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#addModal').modal('hide');
                    reloadEmployeeTable();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Error', 'Server request failed. Check console for details.', 'error');
            }
        });
    });

    // --- 4. Handle Form Submission: UPDATE (Edit Modal) ---
    $('#editEmployeeForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        Swal.fire({ title: 'Updating Profile...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: `api/employee_action.php?action=update`, type: 'POST', data: formData, dataType: 'json', processData: false, contentType: false, 
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#editModal').modal('hide');
                    reloadEmployeeTable();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Error', 'Server request failed. Check console for details.', 'error');
            }
        });
    });
    
    // --- 6. CRITICAL FIX: Initialize Dropify ONLY when the modal is fully visible ---
    $('#editModal').on('shown.bs.modal', function() {
        // This runs after the modal has fully finished transitioning and is visible.
        
        // We ensure Dropify is destroyed/re-initialized now with the correct URL.
        if (window.currentPhotoUrl) {
            resetDropify('photo_edit', window.currentPhotoUrl);
        } else {
            // Re-initialize to ensure it displays the "Click or Drag" message cleanly
            resetDropify('photo_edit');
        }
    });

    // --- 5. Modal Hide Listeners (Resets forms after closing) ---

    $('#addModal').on('hidden.bs.modal', function() {
        resetForm();
    });

    $('#editModal').on('hidden.bs.modal', function() {
        $('#editEmployeeForm')[0].reset();
        // Reset the input field but also ensure the global URL is cleared
        resetDropify('photo_edit'); 
        window.currentPhotoUrl = null;
    });
});
</script>