/**
 * Overtime Management Controller
 * Handles Overtime processing with real-time biometric verification and capping.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var otTable;
var currentOTId;

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
 */
function updateSyncStatus(state) {
    const $dot = $('.live-dot');
    const $text = $('#last-updated-time');
    const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    $dot.removeClass('text-success text-warning text-danger');

    if (state === 'loading') {
        $text.text('Syncing...');
        $dot.addClass('text-warning'); 
    } 
    else if (state === 'success') {
        $text.text(`Synced: ${time}`);
        $dot.addClass('text-success'); 
    } 
    else {
        $text.text(`Failed: ${time}`);
        $dot.addClass('text-danger');  
    }
}

// 1.2 MASTER REFRESHER TRIGGER
window.refreshPageContent = function(isManual = false) {
    if (otTable) {
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        otTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    // 2.1 INITIALIZE DATATABLE (SSP Mode)
    otTable = $('#overtimeTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        dom: 'rtip',
        ajax: {
            url: "../api/admin/overtime_action.php?action=fetch",
            type: "GET",
            data: function(d) {
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
            }
        },
        drawCallback: function() {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },
        columns: [
            {
                data: 'employee_id',
                className: "align-middle",
                render: function(data, type, row) {
                    let photo = row.photo ? `../assets/images/users/${row.photo}` : '../assets/images/users/default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${photo}" class="rounded-circle me-3 border shadow-sm" 
                                style="width: 38px; height: 38px; object-fit: cover;"
                                onerror="this.src='../assets/images/users/default.png'">
                            <div>
                                <div class="fw-bold text-dark mb-0">${row.firstname} ${row.lastname}</div>
                                <div class="small text-muted font-monospace">${row.employee_id}</div>
                            </div>
                        </div>`;
                }
            },
            { data: 'ot_date', className: "text-center align-middle fw-bold" },
            { 
                data: 'hours_requested', 
                className: "text-center align-middle",
                render: d => `<span class="badge bg-light text-dark border px-2">${parseFloat(d).toFixed(2)} hrs</span>`
            },
            { 
                data: 'status', 
                className: "text-center align-middle",
                render: function(data) {
                    if(data == 'Approved') return '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Approved</span>';
                    if(data == 'Rejected') return '<span class="badge bg-soft-danger text-danger border border-danger px-3 rounded-pill">Rejected</span>';
                    return '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill">Pending</span>';
                }
            },
            {
                data: 'id',
                orderable: false,
                className: "text-center align-middle",
                render: d => `<button class="btn btn-sm btn-outline-teal fw-bold" onclick="viewOT(${d})"><i class="fa-solid fa-magnifying-glass me-1"></i> Review</button>`
            }
        ],
        order: [[ 1, "desc" ]] // Sort by date primarily
    });

    // 2.2 Event Listeners
    $('#customSearch').on('keyup', function() { otTable.search(this.value).draw(); });
    $('#applyFilterBtn').on('click', function() { window.refreshPageContent(true); });
    $('#clearFilterBtn').on('click', function() { 
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        otTable.search('').draw();
        window.refreshPageContent(true);
    });
    $('#btn-refresh').on('click', function(e) { e.preventDefault(); window.refreshPageContent(true); });
});

// ==============================================================================
// 3. MODAL & ACTION LOGIC
// ==============================================================================

function viewOT(id) {
    currentOTId = id;
    const modalContent = $('#viewOTModal .modal-content');
    modalContent.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div></div>');

    $.ajax({
        url: '../api/admin/overtime_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(res) {
            let data = res.details; 
            if (!data) return;

            const requestedHours = parseFloat(data.hours_requested || 0);
            const rawOtHours = parseFloat(data.raw_biometric_ot || 0);
            const approvedHours = parseFloat(data.hours_approved || 0);
            
            let statusBadge = data.status === 'Approved' ? 'success' : (data.status === 'Rejected' ? 'danger' : 'warning');
            
            let html = `
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-teal"></i>Review Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-5 text-center border-end">
                            <img src="../assets/images/users/${data.photo || 'default.png'}" class="rounded-circle border mb-3 shadow-sm" style="width: 90px; height: 90px; object-fit: cover;" onerror="this.src='../assets/images/users/default.png'">
                            <h6 class="fw-bold mb-0">${data.firstname} ${data.lastname}</h6>
                            <div class="badge bg-soft-${statusBadge} text-${statusBadge} mb-2">${data.status}</div>
                            <div class="small text-muted">${data.department}</div>
                        </div>
                        <div class="col-md-7 ps-4">
                            <h6 class="fw-bold text-gray-600 mb-3 small uppercase">OT Verification</h6>
                            <div class="p-3 bg-light rounded border mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Requested:</span>
                                    <span class="fw-bold text-teal">${requestedHours.toFixed(2)} hrs</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="small">Actual Biometric:</span>
                                    <span class="fw-bold text-danger">${rawOtHours.toFixed(2)} hrs</span>
                                </div>
                            </div>`;

            if(data.status === 'Pending') {
                let defaultApproved = Math.min(requestedHours, rawOtHours);
                html += `
                    <label class="form-label fw-bold small">Hours to Approve:</label>
                    <div class="input-group">
                        <input type="number" step="0.01" id="modal_approved_input" class="form-control fw-bold border-teal" 
                               value="${defaultApproved.toFixed(2)}" min="0" max="${rawOtHours.toFixed(2)}">
                        <span class="input-group-text bg-teal text-white">HRS</span>
                    </div>
                    <div class="form-text text-xs text-danger">⚠️ Capped at biometric actual (${rawOtHours.toFixed(2)} hrs).</div>`;
            } else {
                html += `
                    <div class="text-center p-2 border rounded bg-white">
                        <div class="small text-muted">Final Approved Hours</div>
                        <div class="h4 fw-bold mb-0 text-dark">${approvedHours.toFixed(2)} hrs</div>
                    </div>`;
            }

            html += `
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="fw-bold small text-muted">EMPLOYEE REASON</label>
                        <div class="alert alert-light border small py-2 mb-0">${data.reason || 'No reason provided.'}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 justify-content-center">`;

            if(data.status === 'Pending') {
                html += `
                    <button type="button" class="btn btn-light-danger fw-bold me-2 px-4" onclick="processOT('reject')">Reject</button>
                    <button type="button" class="btn btn-success fw-bold px-4 shadow-sm" onclick="processOT('approve')">Approve OT</button>`;
            } else {
                html += `<button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>`;
            }

            html += `</div>`;
            modalContent.html(html);

            // Re-attach capping logic for real-time validation
            if (data.status === 'Pending') {
                $('#modal_approved_input').on('input', function() {
                    let val = parseFloat($(this).val());
                    if (val > rawOtHours) $(this).val(rawOtHours.toFixed(2));
                    if (val < 0) $(this).val(0);
                });
            }
            $('#viewOTModal').modal('show');
        }
    });
}

// 3.2 APPROVE/REJECT LOGIC
function processOT(action) {
    const approvedHrs = $('#modal_approved_input').val();

    if(action === 'approve' && (approvedHrs === '' || parseFloat(approvedHrs) <= 0)) {
        Swal.fire('Invalid Input', 'Please enter valid hours to approve.', 'warning');
        return;
    }

    Swal.fire({
        title: action === 'approve' ? 'Approve Overtime?' : 'Reject Overtime?',
        text: action === 'approve' ? `You are approving ${approvedHrs} hours for payroll calculation.` : "This request will be marked as invalid.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'approve' ? '#1cc88a' : '#e74a3b',
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
}