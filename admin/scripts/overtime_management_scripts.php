<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var otTable;
var currentOTId;

// 1.1 Global variable to track when the spin started
let spinnerStartTime = 0; 

// 1.2 HELPER FUNCTION: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.3 HELPER FUNCTION: Stops the spinner safely (waits for minDisplayTime = 1000ms)
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

// 1.4 MASTER REFRESHER HOOK (Must be global)
window.refreshPageContent = function() {
    // 1. Record Start Time
    spinnerStartTime = new Date().getTime(); 
    
    // 2. Start Visual feedback & Text
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    // 3. Reload Table
    if (otTable) {
        otTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL LOGIC FUNCTIONS (Must be global for onclick binding)
// ==============================================================================

// --- VIEW DETAILS LOGIC ---
function viewOT(id) {
    currentOTId = id;
    
    // Reset Modal
    $('#modal_approved_input').val('').removeClass('d-none');
    $('#modal_approved_display').text('').addClass('d-none');
    $('#modal_actions').removeClass('d-none');

    // Show Loader
    const modalBody = $('#viewOTModal .modal-body');
    modalBody.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>');
    
    $.ajax({
        url: 'api/overtime_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            
            // Re-render the detailed body content after loading
            // Note: Assuming your HTML structure requires a detailed layout
            const requestedHours = parseFloat(data.hours_requested || 0);
            const rawOtHours = parseFloat(data.raw_biometric_ot || 0);
            
            modalBody.html(renderOTDetails(data)); // Assuming you have a separate render function in HTML template, or replace this with detailed rendering logic

            // Populate static details
            $('#modal_emp_photo').attr('src', data.photo ? '../assets/images/'+data.photo : '../assets/images/default.png');
            $('#modal_emp_name').text(data.firstname + ' ' + data.lastname);
            $('#modal_emp_dept').text(data.department);
            $('#modal_ot_date').text(data.ot_date);
            $('#modal_reason').text(data.reason || 'No reason provided.');
            $('#modal_raw_ot').text(rawOtHours.toFixed(2));
            $('#modal_req_hrs').text(requestedHours.toFixed(2));
            
            var statusHtml = '<span class="badge bg-warning text-dark">Pending</span>';
            if(data.status === 'Approved') statusHtml = '<span class="badge bg-success">Approved</span>';
            if(data.status === 'Rejected') statusHtml = '<span class="badge bg-danger">Rejected</span>';
            $('#modal_status').html(statusHtml);

            // Handle Logic based on status
            if(data.status === 'Pending') {
                
                // 1. Determine Initial Approved Value (Cap at requested or raw OT, whichever is lower)
                let defaultApproved = requestedHours;
                if (defaultApproved > rawOtHours) {
                    defaultApproved = rawOtHours;
                }
                
                $('#modal_approved_input').val(defaultApproved.toFixed(2));
                
                // 2. Attach Real-time Input Cap Listener (Ensures input doesn't exceed Raw OT)
                $('#modal_approved_input').off('input.ot_cap').on('input.ot_cap', function() {
                    let enteredValue = parseFloat($(this).val());
                    if (!isNaN(enteredValue) && enteredValue > rawOtHours) {
                        $(this).val(rawOtHours.toFixed(2));
                    }
                });

                $('#modal_actions').show(); 
                $('#modal_approved_input').removeClass('d-none');
                $('#modal_approved_display').addClass('d-none');
                
            } else {
                // Read-only mode
                $('#modal_approved_input').addClass('d-none');
                $('#modal_approved_display').text(data.hours_approved + ' hrs').removeClass('d-none');
                $('#modal_actions').hide(); 
                $('#modal_approved_input').off('input.ot_cap'); // Remove listener
            }

            $('#viewOTModal').modal('show');
        },
        error: function() {
             modalBody.html('<div class="text-center py-5"><p class="text-danger">Failed to load details. Try again.</p></div>');
        }
    });
}

// --- APPROVE/REJECT LOGIC ---
function processOT(action) {
    var approvedHrs = $('#modal_approved_input').val();

    if(action === 'approve' && (approvedHrs === '' || parseFloat(approvedHrs) <= 0)) {
        Swal.fire('Error', 'Please enter valid approved hours.', 'error');
        return;
    }

    Swal.fire({
        title: action === 'approve' ? 'Approve Overtime?' : 'Reject Overtime?',
        text: action === 'approve' ? `Approved Hours: ${approvedHrs}` : "This cannot be undone.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'approve' ? '#1cc88a' : '#e74a3b',
        confirmButtonText: 'Yes, Confirm'
    }).then((result) => {
        if(result.isConfirmed) {
            $.ajax({
                url: 'api/overtime_action.php?action=update_status',
                type: 'POST',
                data: { 
                    id: currentOTId, 
                    status_action: action,
                    approved_hours: approvedHrs
                },
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        $('#viewOTModal').modal('hide');
                        window.refreshPageContent(); // Use the hook for refresh
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server Error', 'error');
                }
            });
        }
    });
}


$(document).ready(function() {

    // 3. INITIALIZE DATATABLE
    otTable = $('#overtimeTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        dom: 'rtip',
        
        ajax: {
            url: "api/overtime_action.php?action=fetch",
            type: "GET",
            data: function(d) {
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
            }
        },

        drawCallback: function(settings) {
            const icon = $('#refresh-spinner');
            if (icon.hasClass('fa-spin')) {
                stopSpinnerSafely();
            } else {
                updateLastSyncTime(); 
            }
        },

        columns: [
            // Col 1: Employee
            {
                data: 'employee_id',
                className: "align-middle",
                render: function(data, type, row) {
                    var photo = row.photo ? '../assets/images/'+row.photo : '../assets/images/default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" 
                                style="width: 35px; height: 35px; object-fit: cover;"
                                onerror="this.src='../assets/images/default.png'">
                            <div>
                                <div class="fw-bold text-dark text-sm">${row.firstname} ${row.lastname}</div>
                                <div class="text-xs text-muted">${row.department || ''}</div>
                            </div>
                        </div>`;
                }
            },
            // Col 2: Date
            { data: 'ot_date', className: "text-center align-middle" },
            // Col 3: Req Hrs
            { data: 'hours_requested', className: "text-center fw-bold align-middle" },
            // Col 4: Status
            { 
                data: 'status', 
                className: "text-center align-middle",
                render: function(data) {
                    if(data == 'Approved') return '<span class="badge bg-success">Approved</span>';
                    if(data == 'Rejected') return '<span class="badge bg-danger">Rejected</span>';
                    return '<span class="badge bg-warning text-dark">Pending</span>';
                }
            },
            // Col 5: Actions (FA6 Update)
            {
                data: 'id',
                orderable: false,
                className: "text-center align-middle",
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="viewOT(${data})"><i class="fa-solid fa-eye me-1"></i> Details </button>`;
                }
            }
        ]
    });

    // Filters - Use the hook for reloads
    $('#customSearch').on('keyup', function() { otTable.search(this.value).draw(); });
    
    $('#applyFilterBtn').on('click', function() { 
        window.refreshPageContent(); 
    });
    
    $('#clearFilterBtn').on('click', function() { 
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        otTable.search('').draw();
        window.refreshPageContent();
    });
});
</script>