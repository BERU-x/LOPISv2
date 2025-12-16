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
// CRITICAL: Variable to hold the photo URL fetched by AJAX
window.currentPhotoUrl = null; 

var employmentStatuses = {
    <?php foreach ($employment_statuses as $id => $name) { echo "$id: '$name',"; } ?>
};

// 1.0 HELPER: Dynamically finds the project base URL
function getProjectBaseUrl() {
    const pathArray = window.location.pathname.split('/');
    const projectFolder = pathArray[1]; 
    return window.location.origin + '/' + projectFolder + '/';
}

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
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

// 1.2 MASTER REFRESHER TRIGGER
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    if (employeesTable) {
        // 1. Visual Feedback for Manual Actions
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // 2. Reload DataTable (false = keep paging)
        employeesTable.ajax.reload(null, false);
    }
};

// 1.3 HELPER: Function to safely reset and re-initialize Dropify (PRESERVED LOGIC)
function resetDropify(targetId, defaultFile = null) {
    const $dropify = $(`#${targetId}`);
    
    // Destroy instance
    if ($dropify.data('dropify')) {
        $dropify.dropify('destroy'); 
    }
    
    // Clear old data
    $dropify.removeAttr('data-default-file'); 
    
    // Set new default
    if (defaultFile) {
        $dropify.attr('data-default-file', defaultFile);
    }
    
    // Brute force DOM recreation to fix Dropify glitches
    const $wrapper = $dropify.closest('.dropify-wrapper').parent();
    if ($wrapper.length) {
        $wrapper.html($dropify.prop('outerHTML')); 
        $dropify.remove(); 
        
        const newId = targetId + '_temp_' + Date.now();
        $(`#${targetId}`).attr('id', newId).removeClass('dropify-touched'); 
        
        const $newDropify = $(`#${newId}`);
        $newDropify.attr('name', 'photo'); 
        
        if (defaultFile) {
            $newDropify.attr('data-default-file', defaultFile);
        }
        
        $newDropify.dropify();
        $newDropify.attr('id', targetId);
    } else {
        $dropify.dropify();
    }
}

// 1.4 HELPER: Resets the primary Add form
function resetForm() {
    $('#addEmployeeForm')[0].reset();
    resetDropify('photo');
}

// 2. WINDOW.EDITEMPLOYEE FUNCTION (Data Fetch for Edit Modal)
window.editEmployee = function(id) {
    Swal.fire({ title: 'Fetching Data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    
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

                // --- Photo Logic ---
                if (data.photo) {
                    const baseUrl = getProjectBaseUrl();
                    window.currentPhotoUrl = `${baseUrl}assets/images/${data.photo}`;
                } else {
                    window.currentPhotoUrl = null;
                }
                
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

    // --- INITIALIZE DROPIFY ---
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
        // DRAW CALLBACK: Standardized UI updates
        drawCallback: function(settings) {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },
        columns: [
            // Col 0: ID
            { data: 'employee_id', className: 'fw-bold text-gray-700 align-middle' },
            
            // Col 1: Name & Position
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
            
            // Col 2: Status
            { 
                data: 'employment_status', 
                className: 'text-center align-middle',
                render: function(data) {
                    var statusName = employmentStatuses[data] || 'Unknown';
                    var cls = (data == 1) ? 'success' : (data == 5 || data == 6) ? 'danger' : 'warning';
                    return `<span class="badge bg-soft-${cls} text-${cls} border border-${cls} px-2 rounded-pill">${statusName}</span>`;
                }
            },
            
            // Col 3: Daily Rate
            { 
                data: 'daily_rate', 
                className: 'text-end fw-bold align-middle',
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },

            // Col 4: Actions
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

    // --- 3. DETECT LOADING STATE ---
    $('#employeesTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // --- 4. Handle Form Submission: CREATE ---
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
                    window.refreshPageContent(true); // Visual Refresh
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    });

    // --- 5. Handle Form Submission: UPDATE ---
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
                    window.refreshPageContent(true); // Visual Refresh
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    });
    
    // --- 6. CRITICAL FIX: Initialize Dropify ONLY when the modal is fully visible ---
    $('#editModal').on('shown.bs.modal', function() {
        if (window.currentPhotoUrl) {
            resetDropify('photo_edit', window.currentPhotoUrl);
        } else {
            resetDropify('photo_edit');
        }
    });

    // --- 7. Modal Hide Listeners ---
    $('#addModal').on('hidden.bs.modal', function() {
        resetForm();
    });

    $('#editModal').on('hidden.bs.modal', function() {
        $('#editEmployeeForm')[0].reset();
        resetDropify('photo_edit'); 
        window.currentPhotoUrl = null;
    });

    // --- 8. Manual Refresh Listener ---
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});
</script>