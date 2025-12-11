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
    
    // 3. Reload Table - The robust check
    if (otTable) {
        otTable.ajax.reload(null, false);
    } else if ($('#overtimeTable').length && $.fn.DataTable.isDataTable('#overtimeTable')) {
        // Fallback check
        $('#overtimeTable').DataTable().ajax.reload(null, false);
    } else {
        stopSpinnerSafely();
    }
};

// ==============================================================================
// 2. MODAL LOGIC FUNCTIONS (Must be global for onclick binding)
// ==============================================================================

// --- VIEW DETAILS LOGIC ---
function viewOT(id) {
    currentOTId = id;
    
    // Show Loader
    const modalContent = $('#viewOTModal .modal-content');
    modalContent.html(`
        <div class="text-center py-5">
            <div class="spinner-border text-teal" role="status"></div>
            <p class="mt-2 text-muted">Loading...</p>
        </div>
    `);

    $.ajax({
        url: 'api/overtime_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(res) {
            
            // *** CRITICAL FIX: Ensure 'data' variable holds the 'details' object ***
            let data = res.details; 
            
            if (!data) {
                modalContent.html(`
                    <div class="modal-header border-bottom-0">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center py-5">
                        <p class="text-danger">Error: Could not find 'details' in server response.</p>
                        <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button>
                    </div>
                `);
                $('#viewOTModal').modal('show');
                return;
            }
            // ***************************************************************

            const requestedHours = parseFloat(data.hours_requested || 0);
            const rawOtHours = parseFloat(data.raw_biometric_ot || 0);
            const approvedHours = parseFloat(data.hours_approved || 0);
            
            var statusHtml = '';
            if(data.status === 'Approved') statusHtml = '<span class="badge bg-success">Approved</span>';
            else if(data.status === 'Rejected') statusHtml = '<span class="badge bg-danger">Rejected</span>';
            else statusHtml = '<span class="badge bg-warning">Pending</span>';


            let approvedHtmlBlock = '';
            let actionButtonsHtml = '';

            // Handle Logic based on status
            if(data.status === 'Pending') {
                // Determine Initial Approved Value (Cap at requested or raw OT, whichever is lower)
                let defaultApproved = requestedHours;
                if (defaultApproved > rawOtHours) {
                    defaultApproved = rawOtHours;
                }
                
                approvedHtmlBlock = `
                    <div id="approved_input_group">
                        <input type="number" step="0.01" id="modal_approved_input" class="form-control form-control-sm text-center fw-bold" 
                               value="${defaultApproved.toFixed(2)}" min="0" max="${rawOtHours.toFixed(2)}"
                               title="Max allowed is ${rawOtHours.toFixed(2)} (Raw Biometric OT)">
                    </div>
                `;
                
                // *** BUTTONS RENDERED FOR PENDING STATE ***
                actionButtonsHtml = `
                    <button type="button" class="btn btn-danger btn-action fw-bold shadow-sm" onclick="processOT('reject')">
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                    <button type="button" class="btn btn-teal btn-action fw-bold shadow-sm ms-2" onclick="processOT('approve')">
                        <i class="fas fa-check me-1"></i> Approve
                    </button>
                `;
                
            } else {
                // Read-only mode
                approvedHtmlBlock = `
                    <span id="modal_approved_display" class="fw-bold text-lg">
                        ${approvedHours.toFixed(2)} hrs
                    </span>
                `;
            }

            // --- Full Modal Body HTML ---
            const fullModalHtml = `
                <div class="modal-header border-bottom-0 p-4"> 
                    <h5 class="modal-title fw-bold text-label" id="viewOTModalLabel">
                        <i class="fas fa-clock me-2"></i> Overtime Request Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <div class="row g-4">
                        <div class="col-md-5 text-center border-end">
                            <img src="${data.photo ? '../assets/images/'+data.photo : '../assets/images/default.png'}" 
                                 id="modal_emp_photo" class="rounded-circle border shadow-sm mb-3" 
                                 style="width: 80px; height: 80px; object-fit: cover;" onerror="this.src='../assets/images/default.png'">
                            <h5 class="fw-bold" id="modal_emp_name">${data.firstname} ${data.lastname}</h5>
                            <p class="text-muted small mb-0" id="modal_emp_dept">${data.department}</p>
                            <p class="text-muted small">Employee ID: ${data.employee_id}</p>
                            
                            <hr class="my-3">
                            <p class="small text-start">
                                <strong>Request Date:</strong> <span class="float-end" id="modal_ot_date">${data.ot_date}</span><br>
                                <strong>Date Filed:</strong> <span class="float-end">${data.created_at}</span>
                            </p>
                        </div>

                        <div class="col-md-7">
                            <h6 class="fw-bold text-gray-600 mb-3">Approval Overview</h6>
                            <table class="table table-sm small">
                                <tr>
                                    <td class="fw-bold">Status:</td>
                                    <td class="text-end">${statusHtml}</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Requested Hours:</td>
                                    <td class="text-end fw-bold text-teal">${requestedHours.toFixed(2)} hrs</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Raw Biometric OT:</td>
                                    <td class="text-end fw-bold text-danger">${rawOtHours.toFixed(2)} hrs</td>
                                </tr>
                            </table>

                            <h6 class="fw-bold text-gray-600 mt-4 mb-2">Approved Hours</h6>
                            ${approvedHtmlBlock}
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="fw-bold text-gray-600 mb-2">Reason for Overtime</h6>
                    <div class="alert alert-light border small" id="modal_reason">${data.reason || 'No reason provided.'}</div>
                </div>

                <div class="modal-footer border-top-0 p-4 justify-content-center" id="modal_actions_footer">
                    ${actionButtonsHtml}
                    <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Close</button>
                </div>
            `;
            
            modalContent.html(fullModalHtml);
            
            // Re-attach real-time input listener (if in Pending mode)
            if (data.status === 'Pending') {
                 // Raw OT max limit
                 const maxOt = parseFloat(data.raw_biometric_ot || 0);

                $('#modal_approved_input').off('input.ot_cap').on('input.ot_cap', function() {
                    let enteredValue = parseFloat($(this).val());
                    if (isNaN(enteredValue) || enteredValue < 0) {
                         $(this).val(0.00); // Set to zero if NaN or negative
                         enteredValue = 0;
                    } 
                    if (enteredValue > maxOt) {
                        $(this).val(maxOt.toFixed(2));
                    }
                });
            }

            $('#viewOTModal').modal('show');
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
            modalContent.html(`
                <div class="modal-header border-bottom-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <p class="text-danger">Failed to load details. Server status: ${textStatus}.</p>
                    <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button>
                </div>
            `);
            $('#viewOTModal').modal('show');
        }
    });
}

// --- APPROVE/REJECT LOGIC ---
function processOT(action) {
    // Note: Since the input field is dynamically rebuilt, scope the lookup to the modal
    var approvedHrs = $('#viewOTModal').find('#modal_approved_input').val();

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
                    if(data == 'Approved') return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fa-solid fa-check me-1"></i> Approved</span>';
                    if(data == 'Rejected') return '<span class="badge bg-soft-danger text-danger border border-danger px-3 shadow-sm rounded-pill"><i class="fa-solid fa-times me-1"></i> Rejected</span>';
                    return '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill"><i class="fa-solid fa-clock me-1"></i> Pending</span>';
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