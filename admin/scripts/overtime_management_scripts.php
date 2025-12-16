<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var otTable;
var currentOTId;

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
    if (otTable) {
        // 1. Visual Feedback for Manual Actions
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // 2. Reload DataTable (false = keep paging)
        otTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    // 2.1 INITIALIZE DATATABLE
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

        // DRAW CALLBACK: Standardized UI updates
        drawCallback: function(settings) {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },

        columns: [
            // Col 0: Employee
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
            // Col 1: Date
            { data: 'ot_date', className: "text-center align-middle" },
            // Col 2: Req Hrs
            { data: 'hours_requested', className: "text-center fw-bold align-middle" },
            // Col 3: Status
            { 
                data: 'status', 
                className: "text-center align-middle",
                render: function(data) {
                    if(data == 'Approved') return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fa-solid fa-check me-1"></i> Approved</span>';
                    if(data == 'Rejected') return '<span class="badge bg-soft-danger text-danger border border-danger px-3 shadow-sm rounded-pill"><i class="fa-solid fa-times me-1"></i> Rejected</span>';
                    return '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill"><i class="fa-solid fa-clock me-1"></i> Pending</span>';
                }
            },
            // Col 4: Actions
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

    // 2.2 DETECT LOADING STATE
    $('#overtimeTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // 2.3 Filters & Search
    $('#customSearch').on('keyup', function() { otTable.search(this.value).draw(); });
    
    $('#applyFilterBtn').on('click', function() { 
        window.refreshPageContent(true); 
    });
    
    $('#clearFilterBtn').on('click', function() { 
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        otTable.search('').draw();
        window.refreshPageContent(true);
    });

    // 2.4 Manual Refresh Button
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});

// ==============================================================================
// 3. MODAL & ACTION LOGIC
// ==============================================================================

// 3.1 VIEW DETAILS LOGIC (With Biometric Cap)
function viewOT(id) {
    currentOTId = id;
    const modalContent = $('#viewOTModal .modal-content');
    
    // Show Loader
    modalContent.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>');

    $.ajax({
        url: 'api/overtime_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(res) {
            let data = res.details; 
            if (!data) {
                modalContent.html('<div class="modal-body text-center py-5"><p class="text-danger">Error: Could not find details.</p><button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button></div>');
                $('#viewOTModal').modal('show');
                return;
            }

            const requestedHours = parseFloat(data.hours_requested || 0);
            const rawOtHours = parseFloat(data.raw_biometric_ot || 0);
            const approvedHours = parseFloat(data.hours_approved || 0);
            
            let statusHtml = '';
            if(data.status === 'Approved') statusHtml = '<span class="badge bg-success">Approved</span>';
            else if(data.status === 'Rejected') statusHtml = '<span class="badge bg-danger">Rejected</span>';
            else statusHtml = '<span class="badge bg-warning">Pending</span>';

            let approvedHtmlBlock = '';
            let actionButtonsHtml = '';

            // --- Logic: Pending State vs Read Only ---
            if(data.status === 'Pending') {
                // Cap at requested or raw OT, whichever is lower
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
                
                actionButtonsHtml = `
                    <button type="button" class="btn btn-danger btn-action fw-bold shadow-sm" onclick="processOT('reject')">
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                    <button type="button" class="btn btn-teal btn-action fw-bold shadow-sm ms-2" onclick="processOT('approve')">
                        <i class="fas fa-check me-1"></i> Approve
                    </button>
                `;
            } else {
                approvedHtmlBlock = `<span id="modal_approved_display" class="fw-bold text-lg">${approvedHours.toFixed(2)} hrs</span>`;
            }

            // --- Render Full HTML ---
            const fullModalHtml = `
                <div class="modal-header border-bottom-0 p-4"> 
                    <h5 class="modal-title fw-bold text-label"><i class="fas fa-clock me-2"></i> Overtime Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <div class="row g-4">
                        <div class="col-md-5 text-center border-end">
                            <img src="${data.photo ? '../assets/images/'+data.photo : '../assets/images/default.png'}" 
                                 class="rounded-circle border shadow-sm mb-3" style="width: 80px; height: 80px; object-fit: cover;" onerror="this.src='../assets/images/default.png'">
                            <h5 class="fw-bold">${data.firstname} ${data.lastname}</h5>
                            <p class="text-muted small mb-0">${data.department}</p>
                            <p class="text-muted small">ID: ${data.employee_id}</p>
                            <hr class="my-3">
                            <p class="small text-start">
                                <strong>Request Date:</strong> <span class="float-end">${data.ot_date}</span><br>
                                <strong>Date Filed:</strong> <span class="float-end">${data.created_at}</span>
                            </p>
                        </div>
                        <div class="col-md-7">
                            <h6 class="fw-bold text-gray-600 mb-3">Approval Overview</h6>
                            <table class="table table-sm small">
                                <tr><td class="fw-bold">Status:</td><td class="text-end">${statusHtml}</td></tr>
                                <tr><td class="fw-bold">Requested Hours:</td><td class="text-end fw-bold text-teal">${requestedHours.toFixed(2)} hrs</td></tr>
                                <tr><td class="fw-bold">Raw Biometric OT:</td><td class="text-end fw-bold text-danger">${rawOtHours.toFixed(2)} hrs</td></tr>
                            </table>
                            <h6 class="fw-bold text-gray-600 mt-4 mb-2">Approved Hours</h6>
                            ${approvedHtmlBlock}
                        </div>
                    </div>
                    <hr class="my-4">
                    <h6 class="fw-bold text-gray-600 mb-2">Reason</h6>
                    <div class="alert alert-light border small">${data.reason || 'No reason provided.'}</div>
                </div>
                <div class="modal-footer border-top-0 p-4 justify-content-center">
                    ${actionButtonsHtml}
                    <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Close</button>
                </div>
            `;
            
            modalContent.html(fullModalHtml);
            
            // Re-attach Logic for Real-time Capping
            if (data.status === 'Pending') {
                 const maxOt = parseFloat(data.raw_biometric_ot || 0);
                $('#modal_approved_input').off('input.ot_cap').on('input.ot_cap', function() {
                    let enteredValue = parseFloat($(this).val());
                    if (isNaN(enteredValue) || enteredValue < 0) {
                         $(this).val(0.00); 
                    } 
                    if (enteredValue > maxOt) {
                        $(this).val(maxOt.toFixed(2));
                    }
                });
            }
            $('#viewOTModal').modal('show');
        },
        error: function() {
            modalContent.html('<div class="modal-body text-center py-5"><p class="text-danger">Failed to load details.</p><button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button></div>');
            $('#viewOTModal').modal('show');
        }
    });
}

// 3.2 APPROVE/REJECT LOGIC
function processOT(action) {
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
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            $.ajax({
                url: 'api/overtime_action.php?action=update_status',
                type: 'POST',
                data: { id: currentOTId, status_action: action, approved_hours: approvedHrs },
                dataType: 'json',
                success: function(res) {
                    Swal.close();
                    if(res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        $('#viewOTModal').modal('hide');
                        window.refreshPageContent(true); // Visual Refresh
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
</script>