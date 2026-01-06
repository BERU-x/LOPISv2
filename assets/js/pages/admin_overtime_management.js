/**
 * Overtime Management Controller
 * Handles Overtime processing with real-time biometric verification.
 * Standardized with AppUtility for Sync Status.
 */

// ==============================================================================
// 1. GLOBAL STATE & MASTER REFRESHER
// ==============================================================================
var otTable;
var currentOTId;

/**
 * MASTER REFRESHER TRIGGER
 * Integrates with AppUtility to show the Syncing/Synced state
 */
window.refreshPageContent = function(isManual = false) {
    if (otTable) {
        if (isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        otTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL & ACTION LOGIC (Global Access)
// ==============================================================================

/**
 * Opens the OT Review Modal and fetches Biometric data from attendance logs
 */
window.viewOT = function(id) {
    currentOTId = id;
    const modalContent = $('#viewOTModal .modal-content');
    
    modalContent.html(`
        <div class="modal-body text-center py-5">
            <div class="spinner-border text-teal mb-3" role="status"></div>
            <div class="text-muted small fw-bold">Verifying Biometric Logs...</div>
        </div>
    `);

    $('#viewOTModal').modal('show');

    $.ajax({
        url: '../api/admin/overtime_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(res) {
            if (res.status !== 'success') {
                modalContent.html(`<div class="modal-body text-danger">${res.message}</div>`);
                return;
            }

            const d = res.details; 
            const requestedHours = parseFloat(d.hours_requested || 0);
            const rawOtHours = parseFloat(d.raw_biometric_ot || 0);
            const approvedHours = parseFloat(d.hours_approved || 0);
            const statusBadge = d.status === 'Approved' ? 'success' : (d.status === 'Rejected' ? 'danger' : 'warning');
            
            // Logic: Default approval is the lesser of requested vs actual biometric
            let defaultApproved = Math.min(requestedHours, rawOtHours);

            let html = `
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-fingerprint me-2 text-teal"></i>Review OT Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-5 text-center border-end">
                            <img src="../assets/images/users/${d.photo || 'default.png'}" class="rounded-circle border mb-3 shadow-sm" style="width: 90px; height: 90px; object-fit: cover;" onerror="this.src='../assets/images/users/default.png'">
                            <h6 class="fw-bold mb-0">${d.firstname} ${d.lastname}</h6>
                            <div class="badge bg-soft-${statusBadge} text-${statusBadge} border border-${statusBadge} mt-2">${d.status}</div>
                            <div class="small text-muted mt-1">${d.department}</div>
                        </div>
                        <div class="col-md-7 ps-4">
                            <h6 class="fw-bold text-muted mb-3 small text-uppercase">Biometric Verification</h6>
                            <div class="p-3 bg-light rounded border mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Requested:</span>
                                    <span class="fw-bold text-dark">${requestedHours.toFixed(2)} hrs</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="small">System Actual:</span>
                                    <span class="fw-bold text-teal">${rawOtHours.toFixed(2)} hrs</span>
                                </div>
                            </div>`;

            if(d.status === 'Pending') {
                html += `
                    <label class="form-label fw-bold small">Final Hours to Approve:</label>
                    <div class="input-group">
                        <input type="number" step="0.01" id="modal_approved_input" class="form-control fw-bold border-teal" 
                               value="${defaultApproved.toFixed(2)}" min="0" max="${rawOtHours.toFixed(2)}">
                        <span class="input-group-text bg-teal text-white">HRS</span>
                    </div>
                    <div class="form-text text-xs text-danger mt-2">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Max limit: Biometric Actual (${rawOtHours.toFixed(2)} hrs).
                    </div>`;
            } else {
                html += `
                    <div class="text-center p-3 border rounded bg-white shadow-sm">
                        <div class="small text-muted text-uppercase fw-bold" style="font-size: 10px;">Payroll Approved Hours</div>
                        <div class="h3 fw-bold mb-0 text-teal">${approvedHours.toFixed(2)} <small style="font-size: 14px">hrs</small></div>
                    </div>`;
            }

            html += `
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="fw-bold small text-muted text-uppercase">Reason Provided</label>
                        <div class="alert alert-light border small py-2 mb-0">${d.reason || 'No justification provided.'}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 justify-content-center">`;

            if(d.status === 'Pending') {
                html += `
                    <button type="button" class="btn btn-outline-danger fw-bold me-2 px-4" onclick="processOT('reject')">Reject</button>
                    <button type="button" class="btn btn-teal fw-bold px-4 shadow-sm" onclick="processOT('approve')">Approve OT</button>`;
            } else {
                html += `<button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Close</button>`;
            }

            html += `</div>`;
            modalContent.html(html);

            // Real-time Capping Logic
            $('#modal_approved_input').on('input', function() {
                let val = parseFloat($(this).val());
                if (val > rawOtHours) $(this).val(rawOtHours.toFixed(2));
                if (val < 0) $(this).val(0);
            });
        }
    });
};

/**
 * Handles the actual API call for Approval or Rejection
 */
window.processOT = function(action) {
    const approvedHrs = $('#modal_approved_input').val();

    if(action === 'approve' && (approvedHrs === '' || parseFloat(approvedHrs) < 0)) {
        Swal.fire('Invalid Input', 'Please enter valid hours.', 'warning');
        return;
    }

    Swal.fire({
        title: action === 'approve' ? 'Approve Overtime?' : 'Reject Overtime?',
        text: action === 'approve' ? `You are approving ${approvedHrs} hours for payroll.` : "This will mark the request as invalid.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'approve' ? '#0cc0df' : '#e74a3b',
        confirmButtonText: 'Confirm'
    }).then((result) => {
        if(result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            $.post('../api/admin/overtime_action.php?action=update_status', { 
                id: currentOTId, 
                status_action: action, 
                approved_hours: approvedHrs 
            }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#viewOTModal').modal('hide');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
};

// ==============================================================================
// 3. INITIALIZATION & DATATABLES
// ==============================================================================
$(document).ready(function() {
    otTable = $('#overtimeTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "../api/admin/overtime_action.php?action=fetch",
            type: "GET",
            data: function(d) {
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
            },
            // Handle error state in AppUtility
            error: function() {
                if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        drawCallback: function() {
            // Signal success to AppUtility
            if(window.AppUtility) {
                window.AppUtility.updateSyncStatus('success');
            }
        },
        columns: [
            {
                data: 'employee_id',
                render: function(data, type, row) {
                    let photo = row.photo ? `../assets/images/users/${row.photo}` : '../assets/images/users/default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 38px; height: 38px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark mb-0">${row.firstname} ${row.lastname}</div>
                                <div class="small text-muted font-monospace">${row.employee_id}</div>
                            </div>
                        </div>`;
                }
            },
            { data: 'ot_date', className: "text-center fw-bold" },
            { 
                data: 'hours_requested', 
                className: "text-center",
                render: d => `<span class="badge bg-light text-dark border px-2">${parseFloat(d).toFixed(2)} hrs</span>`
            },
            { 
                data: 'status', 
                className: "text-center",
                render: function(data) {
                    if(data == 'Approved') return '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Approved</span>';
                    if(data == 'Rejected') return '<span class="badge bg-soft-danger text-danger border border-danger px-3 rounded-pill">Rejected</span>';
                    return '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill">Pending</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                className: "text-center",
                render: d => `<button class="btn btn-sm btn-outline-teal fw-bold" onclick="viewOT(${d})"><i class="fa-solid fa-magnifying-glass me-1"></i> Review</button>`
            }
        ],
        order: [[ 1, "desc" ]]
    });

    // Custom UI Logic
    $('#customSearch').on('keyup', function() { otTable.search(this.value).draw(); });
    $('#btn-refresh').on('click', function(e) { e.preventDefault(); window.refreshPageContent(true); });
});