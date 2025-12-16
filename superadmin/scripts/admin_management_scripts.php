<script>
// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var adminTable;

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

// 1.3 MASTER REFRESHER HOOK
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    if (adminTable) {
        // If Manual Click -> Spin Icon & Show 'Syncing...'
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // Reload DataTable (false = keep paging)
        adminTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL & CRUD LOGIC
// ==============================================================================

// 2.1 Open Modal (Add or Edit)
function openModal(id = null) {
    $('#adminForm')[0].reset();
    $('#admin_id').val('');
    $('#modalTitle').text(id ? 'Edit Admin' : 'Add New Admin');
    
    // Password Hint Logic
    if(id) {
        $('#password_hint').removeClass('d-none');
        $('#password').removeAttr('required').attr('placeholder', 'Leave blank to keep current');
    } else {
        $('#password_hint').addClass('d-none');
        $('#password').attr('required', 'required').attr('placeholder', 'Default: admin123');
    }

    if (id) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        
        $.ajax({
            url: 'api/admin_action.php',
            type: 'POST',
            data: { action: 'get_details', id: id },
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success' && res.details) {
                    const d = res.details;
                    $('#admin_id').val(d.id);
                    $('#employee_id').val(d.employee_id);
                    $('#email').val(d.email);
                    $('#status').val(d.status);
                    $('#adminModal').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'Could not fetch details.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    } else {
        $('#adminModal').modal('show');
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
                url: 'api/admin_action.php',
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
    adminTable = $('#adminTable').DataTable({ 
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: "api/admin_action.php",
            type: "POST",
            data: { action: 'fetch' }
        },
        // THIS HANDLES THE UI UPDATES AUTOMATICALLY
        drawCallback: function(settings) {
            updateSyncStatus('success');
            // Stop spinner after slight delay for smoothness
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
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
                        <button class="btn btn-sm btn-outline-secondary shadow-sm" onclick="deleteAdmin(${data})" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        language: { emptyTable: "No administrators found." }
    });

    // 3.2 DETECT LOADING STATE (For visual 'Syncing...' feedback)
    $('#adminTable').on('processing.dt', function (e, settings, processing) {
        if (processing) {
            // Only force 'loading' state if we aren't already manually spinning
            // (This handles paging/sorting clicks)
            if(!$('#refreshIcon').hasClass('fa-spin')) {
                updateSyncStatus('loading');
            }
        }
    });

    // 3.3 Handle Form Submission
    $('#adminForm').on('submit', function(e) {
        e.preventDefault();
        const action = $('#admin_id').val() ? 'update' : 'create';
        const formData = $(this).serialize() + '&action=' + action; 

        Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: 'api/admin_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success') {
                    $('#adminModal').modal('hide');
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
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});
</script>