/**
 * Cash Advance Management Controller
 * Handles Cash Advance approvals, rejections, and standardized UI syncing.
 */

// ==============================================================================
// 1. GLOBAL STATE & REFRESHER
// ==============================================================================
var caTable;
var currentCAId;

window.refreshPageContent = function(isManual = false) {
    if (caTable) {
        if(isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        caTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL LOGIC FUNCTIONS
// ==============================================================================

function renderCAModalBody(data, statusHtml, amountFormatted) {
    const photo = (data.photo && data.photo.trim() !== '') ? `../assets/images/users/${data.photo}` : `../assets/images/users/default.png`;
    const requestedAmount = parseFloat(data.amount || 0);
    const isPending = data.status === 'Pending';
    
    let approvedInputHtml = '';
    
    if(isPending) {
        approvedInputHtml = `
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light fw-bold">₱</span>
                <input type="number" step="0.01" id="modal_approved_input" 
                    class="form-control fw-bold text-center border-teal" 
                    value="${requestedAmount.toFixed(2)}" />
            </div>
        `;
    } else {
        const approvedDisplay = data.status === 'Cancelled' ? '<span class="text-danger">Rejected</span>' : `<span class="text-success">${amountFormatted}</span>`;
        approvedInputHtml = `<span class="fw-bold">${approvedDisplay}</span>`;
    }

    return `
        <div class="row align-items-center">
            <div class="col-md-5 text-center border-end">
                <img src="${photo}" class="rounded-circle border shadow-sm mb-3" style="width: 90px; height: 90px; object-fit: cover;" onerror="this.src='../assets/images/users/default.png'">
                <h6 class="fw-bold mb-1">${data.firstname} ${data.lastname}</h6>
                <p class="text-muted small mb-0">${data.department || 'Employee'}</p>
                <div class="mt-2">${statusHtml}</div>
            </div>
            <div class="col-md-7 ps-4">
                <h6 class="fw-bold text-muted mb-3 small text-uppercase">Transaction Details</h6>
                <table class="table table-sm table-borderless small">
                    <tr><td class="text-muted">Date Filed:</td><td class="fw-bold text-end">${data.date_requested}</td></tr>
                    <tr><td class="text-muted">Requested:</td><td class="fw-bold text-end text-teal">${amountFormatted}</td></tr>
                    <tr class="border-top"><td class="pt-2 text-dark fw-bold">Approval Amount:</td><td class="pt-2 text-end">${approvedInputHtml}</td></tr>
                </table>
            </div>
        </div>
        <hr class="my-3">
        <h6 class="fw-bold text-muted mb-2 small text-uppercase">Employee Justification</h6>
        <div class="bg-light p-3 rounded border small font-italic">${data.remarks || 'No justification provided.'}</div>
    `;
}

window.viewCA = function(id) {
    currentCAId = id;
    const modalBody = $('#viewCAModal .modal-body');
    const modalActions = $('#modal-actions');
    
    modalBody.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div></div>');
    modalActions.empty(); 
    
    $.post('../api/admin/cash_advance_action.php?action=get_details', { id: id }, function(res) {
        if (res.status === 'success' && res.details) {
            const data = res.details;
            const amountFormatted = '₱' + parseFloat(data.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            
            let statusHtml = '';
            if(data.status === 'Deducted') statusHtml = '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Approved</span>';
            else if(data.status === 'Cancelled') statusHtml = '<span class="badge bg-soft-danger text-danger border border-danger px-3 rounded-pill">Rejected</span>';
            else statusHtml = '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill">Pending</span>';

            modalBody.html(renderCAModalBody(data, statusHtml, amountFormatted)); 
            
            if(data.status === 'Pending') {
                modalActions.append(`
                    <button type="button" class="btn btn-light-danger fw-bold px-4 me-auto" onclick="processCA('reject')">Reject</button>
                    <button type="button" class="btn btn-success fw-bold px-4 shadow-sm" onclick="processCA('approve')">Approve Request</button>
                `);
            } 
            $('#viewCAModal').modal('show');
        }
    }, 'json');
}

window.processCA = function(type) {
    let amount = $('#modal_approved_input').val();

    if(type === 'approve' && (amount === '' || parseFloat(amount) <= 0)) {
        Swal.fire('Input Required', 'Please enter a valid amount to approve.', 'warning');
        return;
    }

    Swal.fire({
        title: type === 'approve' ? 'Approve Cash Advance?' : 'Reject this Request?',
        text: type === 'approve' ? `Confirming disbursement of ₱${parseFloat(amount).toLocaleString()}.` : "This will cancel the request and notify the employee.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: type === 'approve' ? '#1cc88a' : '#e74a3b',
        confirmButtonText: 'Confirm'
    }).then((result) => {
        if(result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            $.post('../api/admin/cash_advance_action.php?action=process', { id: currentCAId, type: type, amount: amount }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Updated', res.message, 'success');
                    $('#viewCAModal').modal('hide');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    caTable = $('#caTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "../api/admin/cash_advance_action.php?action=fetch",
            type: "GET",
            data: function (d) {
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
            },
            error: function() {
                if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        drawCallback: function() {
            if(window.AppUtility) window.AppUtility.updateSyncStatus('success');
        },
        columns: [
            { 
                data: 'employee_id', 
                render: function(data, type, row) {
                    let imgPath = row.photo ? `../assets/images/users/${row.photo}` : `../assets/images/users/default.png`;
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${imgPath}" class="rounded-circle me-3 border shadow-sm" style="width: 38px; height: 38px; object-fit: cover;" onerror="this.src='../assets/images/users/default.png'">
                            <div>
                                <div class="fw-bold text-dark mb-0">${row.firstname} ${row.lastname}</div>
                                <div class="small text-muted font-monospace">${row.employee_id}</div>
                            </div>
                        </div>`;
                }
            },
            { data: 'date_requested', className: 'text-center align-middle fw-bold' },
            { 
                data: 'amount',
                className: 'text-end align-middle fw-bold text-dark',
                render: d => '₱' + parseFloat(d).toLocaleString('en-US', {minimumFractionDigits: 2})
            },
            { 
                data: 'status', 
                className: 'text-center align-middle',
                render: function(data) {
                    let badge = {
                        'Pending': 'warning',
                        'Deducted': 'success',
                        'Cancelled': 'danger',
                        'Paid': 'secondary'
                    };
                    let label = data === 'Deducted' ? 'Approved' : (data === 'Cancelled' ? 'Rejected' : data);
                    return `<span class="badge bg-soft-${badge[data]} text-${badge[data]} border border-${badge[data]} px-3 rounded-pill">${label}</span>`;
                }
            },
            { 
                data: 'id', 
                orderable: false,
                className: 'text-center align-middle',
                render: d => `<button class="btn btn-sm btn-outline-teal fw-bold" onclick="viewCA(${d})"><i class="fa-solid fa-eye me-1"></i> Review</button>`
            }
        ]
    });

    // Custom Search with debounce
    $('#customSearch').on('keyup', function() { 
        clearTimeout(window.searchTimer);
        window.searchTimer = setTimeout(() => { caTable.search(this.value).draw(); }, 400); 
    });
    
    $('#applyFilterBtn').click(() => { window.refreshPageContent(true); });
    $('#clearFilterBtn').click(() => {
        $('#filter_start_date, #filter_end_date, #customSearch').val(''); 
        caTable.search('').draw(); 
        window.refreshPageContent(true);
    });
    $('#btn-refresh').on('click', (e) => { 
        e.preventDefault(); 
        window.refreshPageContent(true); 
    });
});