/**
 * Employee Management Controller
 * Updated: Global API_ROOT, Mutex Locking, and AppUtility Sync.
 */

var employeesTable; 
window.isProcessing = false; // ⭐ The "Lock"
window.currentPhotoUrl = null; 

// ==============================================================================
// 1. MASTER REFRESHER TRIGGER
// ==============================================================================
window.refreshPageContent = function(isManual = false) {
    if (window.isProcessing) return;

    if (employeesTable && $.fn.DataTable.isDataTable('#employeesTable')) {
        window.isProcessing = true; 
        
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');
        if (isManual) $('#refreshIcon').addClass('fa-spin');

        employeesTable.ajax.reload(function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
            window.isProcessing = false; // 🔓 Release lock
        }, false);
    }
};

/**
 * 1.1 HELPER: Dropify Re-initialization
 */
function resetDropify(targetId, defaultFile = null) {
    const $dropify = $(`#${targetId}`);
    if ($dropify.data('dropify')) {
        $dropify.dropify('destroy'); 
    }
    
    $dropify.removeAttr('data-default-file'); 
    if (defaultFile) {
        $dropify.attr('data-default-file', defaultFile);
    }
    
    const $wrapper = $dropify.closest('.dropify-wrapper').parent();
    if ($wrapper.length) {
        $wrapper.html($dropify.prop('outerHTML')); 
        const newId = targetId + '_temp_' + Date.now();
        $(`#${targetId}`).attr('id', newId).removeClass('dropify-touched'); 
        
        const $newDropify = $(`#${newId}`);
        $newDropify.attr('name', 'photo'); 
        if (defaultFile) $newDropify.attr('data-default-file', defaultFile);
        
        $newDropify.dropify();
        $newDropify.attr('id', targetId);
    } else {
        $dropify.dropify();
    }
}

// ==============================================================================
// 2. DATA FETCHING (Edit Modal)
// ==============================================================================

window.editEmployee = function(id) {
    Swal.fire({ title: 'Loading Profile...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    window.currentPhotoUrl = null; 

    $.ajax({
        // ⭐ UPDATED TO USE GLOBAL API_ROOT
        url: API_ROOT + '/admin/employee_action.php?action=get_details',
        type: 'POST',
        data: { employee_id: id },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if(response.status === 'success' && response.data) {
                const data = response.data;
                const form = $('#editEmployeeForm');
                
                // Populate Identity & Info
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

                // Populate Compensation
                form.find('#edit_daily_rate').val(data.daily_rate || 0);
                form.find('#edit_monthly_rate').val(data.monthly_rate || 0);
                form.find('#edit_food_allowance').val(data.food_allowance || 0);
                form.find('#edit_transpo_allowance').val(data.transpo_allowance || 0);
                
                // Financials
                form.find('#edit_bank_name').val(data.bank_name);
                form.find('#edit_account_number').val(data.account_number);

                // Photo Handling
                window.currentPhotoUrl = data.photo ? `${WEB_ROOT}/assets/images/users/${data.photo}` : null;
                
                $('#editModal').modal('show'); 
            } else {
                Swal.fire('Error', response.message || 'Fetch failed.', 'error');
            }
        },
        error: function() { Swal.fire('Error', 'Server connection lost.', 'error'); }
    });
};

// ==============================================================================
// 3. INITIALIZATION & EVENTS
// ==============================================================================

$(document).ready(function() {

    // 3.1 Initialize DataTable
    window.isProcessing = true; 

    employeesTable = $('#employeesTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            // ⭐ UPDATED TO USE GLOBAL API_ROOT
            url: API_ROOT + "/admin/employee_action.php?action=fetch", 
            type: "GET"
        },
        drawCallback: function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
            window.isProcessing = false; 
        },
        columns: [
            { data: 'employee_id', className: 'fw-bold text-dark align-middle' },
            { 
                data: 'lastname', 
                className: 'align-middle',
                render: function(data, type, row) {
                    const fullName = `${row.firstname} ${row.lastname}`;
                    const photo = row.photo ? `${WEB_ROOT}/assets/images/users/${row.photo}` : `${WEB_ROOT}/assets/images/users/default.png`;
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.src='${WEB_ROOT}/assets/images/users/default.png'">
                            <div>
                                <div class="fw-bold text-dark mb-0">${fullName}</div>
                                <div class="small text-muted">${row.position}</div>
                            </div>
                        </div>`;
                }
            },
            { 
                data: 'employment_status', 
                className: 'text-center align-middle',
                render: function(data) {
                    const statusMap = {0:'Probationary', 1:'Regular', 2:'Part-time', 3:'Contractual', 4:'OJT', 5:'Resigned', 6:'Terminated'};
                    const clsMap = {0:'warning', 1:'success', 2:'info', 3:'primary', 4:'info', 5:'danger', 6:'danger'};
                    const name = statusMap[data] || 'Unknown';
                    const cls = clsMap[data] || 'secondary';
                    return `<span class="badge bg-soft-${cls} text-${cls} border border-${cls} px-2 rounded-pill">${name}</span>`;
                }
            },
            { 
                data: 'daily_rate', 
                className: 'text-end fw-bold align-middle',
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            {
                data: 'employee_id',
                orderable: false,
                className: 'text-center align-middle',
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-teal fw-bold" onclick="editEmployee('${data}')"><i class="fa-solid fa-eye me-1"></i> Details</button>`;
                }
            }
        ],
        order: [[ 1, "asc" ]]
    });

    // 3.2 Form Submissions (Create/Update)
    $('.employee-form').on('submit', function(e) {
        e.preventDefault();
        const action = $(this).attr('id') === 'addEmployeeForm' ? 'create' : 'update';
        const formData = new FormData(this);
        
        Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            // ⭐ UPDATED TO USE GLOBAL API_ROOT
            url: API_ROOT + `/admin/employee_action.php?action=${action}`,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('.modal').modal('hide');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() { Swal.fire('Error', 'Server error during submission.', 'error'); }
        });
    });

    // 3.3 Modal UI Handling
    $('#editModal').on('shown.bs.modal', function() {
        resetDropify('photo_edit', window.currentPhotoUrl);
    });

    $('#addModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        resetDropify('photo');
    });

    // 3.4 Search & Refresh
    $('#customSearch').on('keyup', function() { employeesTable.search(this.value).draw(); });
    
    $('#btn-refresh').on('click', function(e) { 
        e.preventDefault(); 
        window.refreshPageContent(true); 
    });
});