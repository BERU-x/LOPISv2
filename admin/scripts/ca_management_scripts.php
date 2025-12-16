<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var caTable;
var currentCAId;

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
    if (caTable) {
        // 1. Visual Feedback for Manual Actions
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // 2. Reload DataTable (false = keep paging)
        caTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL LOGIC FUNCTIONS
// ==============================================================================

// 2.1 Helper to render Modal Body HTML
function renderCAModalBody(data, statusHtml, amountFormatted) {
    const photo = (data.photo && data.photo.trim() !== '') ? '../assets/images/'+data.photo : '../assets/images/default.png';
    const requestedAmount = parseFloat(data.amount || 0);
    const isPending = data.status === 'Pending';
    
    let approvedInputHtml = '';
    
    if(isPending) {
        // Input field for pending status
        approvedInputHtml = `
            <input type="number" step="0.01" id="modal_approved_input" 
                class="form-control form-control-sm w-75 d-inline fw-bold text-center" 
                value="${requestedAmount.toFixed(2)}" />
        `;
    } else {
        // Display only for finalized status
        const approvedDisplay = data.status === 'Cancelled' ? 'N/A' : amountFormatted;
        
        // Hidden input to prevent JS errors if referenced, plus visible text
        approvedInputHtml = `
            <input type="hidden" id="modal_approved_input" value="${requestedAmount}" />
            <span id="modal_approved_display" class="fw-bold text-success">${approvedDisplay}</span>
        `;
    }

    return `
        <div class="row">
            <div class="col-md-5 text-center border-end">
                <img id="modal_emp_photo" src="${photo}" class="rounded-circle border shadow-sm mb-3" style="width: 100px; height: 100px; object-fit: cover;" onerror="this.src='../assets/images/default.png'">
                <h5 class="fw-bold" id="modal_emp_name">${data.firstname} ${data.lastname}</h5>
                <p class="text-muted small" id="modal_emp_dept">${data.department || 'Employee'}</p>
            </div>
            <div class="col-md-7">
                <h6 class="fw-bold text-gray-600 mb-3">Request Details</h6>
                <table class="table table-sm small">
                    <tr><td class="fw-bold">Status:</td><td>${statusHtml}</td></tr>
                    <tr><td class="fw-bold">Date Requested:</td><td>${data.date_requested}</td></tr>
                    <tr><td class="fw-bold">Requested Amount:</td><td class="fw-bold text-teal">${amountFormatted}</td></tr>
                    
                    <tr class="align-items-center">
                        <td class="fw-bold align-middle">Approved Amount:</td>
                        <td>${approvedInputHtml}</td>
                    </tr>
                </table>
            </div>
        </div>
        <hr>
        <h6 class="fw-bold text-gray-600 mb-3">Remarks</h6>
        <div class="alert alert-light border small" id="modal_remarks">${data.remarks || 'No remarks provided.'}</div>
    `;
}

// 2.2 VIEW DETAILS LOGIC
function viewCA(id) {
    currentCAId = id;
    const modalBody = $('#viewCAModal .modal-body');
    const modalActions = $('#modal-actions');
    
    // Show Loader
    modalBody.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-muted">Loading details...</p></div>');
    modalActions.empty(); 
    
    $.ajax({
        url: 'api/cash_advance_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(res) {
            
            if (res.status !== 'success' || !res.details) {
                modalBody.html('<div class="text-center py-5"><p class="text-danger">Failed to fetch CA details.</p></div>');
                $('#viewCAModal').modal('show');
                return;
            }
            
            const data = res.details;
            const amountFormatted = '₱ ' + parseFloat(data.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            
            // Status Badge
            var statusHtml = '<span class="badge bg-warning text-dark">Pending</span>';
            if(data.status === 'Paid') statusHtml = '<span class="badge bg-secondary">Paid</span>';
            if(data.status === 'Deducted') statusHtml = '<span class="badge bg-success">Approved</span>';
            if(data.status === 'Cancelled') statusHtml = '<span class="badge bg-danger">Rejected</span>';

            // Render Body
            modalBody.html(renderCAModalBody(data, statusHtml, amountFormatted)); 
            
            // Action Buttons (Only for Pending)
            if(data.status === 'Pending') {
                modalActions.append(`
                    <button type="button" class="btn btn-danger fw-bold shadow-sm" onclick="processCA('reject')">Reject</button>
                    <button type="button" class="btn btn-teal fw-bold shadow-sm ms-2" onclick="processCA('approve')">Approve</button>
                `);
            } 
            
            $('#viewCAModal').modal('show');
        },
        error: function() {
            modalBody.html('<div class="text-center py-5"><p class="text-danger">Server connection failed.</p></div>');
            $('#viewCAModal').modal('show');
        }
    });
}

// 2.3 APPROVE/REJECT LOGIC
function processCA(type) {
    var amount = $('#viewCAModal').find('#modal_approved_input').val();

    if(type === 'approve' && (amount === '' || parseFloat(amount) <= 0)) {
        Swal.fire('Error', 'Please enter a valid amount.', 'error');
        return;
    }

    Swal.fire({
        title: type === 'approve' ? 'Approve Request?' : 'Reject Request?',
        text: type === 'approve' ? `Approved Amount: ₱${parseFloat(amount).toFixed(2)}` : "This will mark the request as Cancelled.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: type === 'approve' ? '#1cc88a' : '#e74a3b',
        confirmButtonText: 'Yes, Confirm'
    }).then((result) => {
        if(result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            $.ajax({
                url: 'api/cash_advance_action.php?action=process',
                type: 'POST',
                data: { 
                    id: currentCAId, 
                    type: type, 
                    amount: amount 
                },
                dataType: 'json',
                success: function(res) {
                    Swal.close();
                    if(res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        $('#viewCAModal').modal('hide');
                        window.refreshPageContent(true); // Visual Refresh
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
    
    // 3.1 INITIALIZE DATATABLE
    caTable = $('#caTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip',
        ajax: {
            url: "api/cash_advance_action.php?action=fetch",
            type: "GET",
            data: function (d) {
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
                className: 'align-middle',
                render: function(data, type, row) {
                    var imgPath = (row.photo && row.photo.trim() !== '') ? '../assets/images/' + row.photo : '../assets/images/default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="${imgPath}" class="rounded-circle me-3 border shadow-sm" style="width: 35px; height: 35px; object-fit: cover;" onerror="this.src='../assets/images/default.png'">
                            <div>
                                <div class="fw-bold text-dark text-sm">${row.firstname} ${row.lastname}</div>
                                <div class="text-xs text-muted">${row.department || 'Employee'}</div>
                            </div>
                        </div>
                    `;
                }
            },
            // Col 1: Date
            { data: 'date_requested', className: 'text-center align-middle' },
            // Col 2: Amount
            { 
                data: 'amount',
                className: 'text-center fw-bold text-gray-800 align-middle',
                render: function(data) { return '₱' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2}); }
            },
            // Col 3: Status
            { 
                data: 'status', 
                className: 'text-center align-middle',
                render: function(data) {
                    if (data === 'Paid') return '<span class="badge bg-soft-secondary text-secondary border border-secondary px-3 shadow-sm rounded-pill"><i class="fa-solid fa-file-invoice me-1"></i> Paid</span>';
                    if (data === 'Deducted') return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fa-solid fa-check me-1"></i> Approved</span>';
                    if (data === 'Pending') return '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill"><i class="fa-solid fa-clock me-1"></i> Pending</span>';
                    if (data === 'Cancelled') return '<span class="badge bg-soft-danger text-danger border border-danger px-3 shadow-sm rounded-pill"><i class="fa-solid fa-times me-1"></i> Rejected</span>';
                    return `<span class="badge bg-secondary">${data}</span>`;
                }
            },
            // Col 4: Actions
            { 
                data: 'id', 
                orderable: false,
                className: 'text-center align-middle',
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="viewCA(${data})"><i class="fa-solid fa-eye me-1"></i> Details </button>`;
                }
            }
        ]
    });

    // 3.2 DETECT LOADING STATE
    $('#caTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // 3.3 Search & Filters
    var searchTimeout;
    $('#customSearch').on('keyup', function() {
        clearTimeout(searchTimeout);
        var val = this.value;
        searchTimeout = setTimeout(function() { caTable.search(val).draw(); }, 400); 
    });
    
    $('#applyFilterBtn').click(function() { 
        window.refreshPageContent(true); 
    });
    
    $('#clearFilterBtn').click(function() {
        $('#filter_start_date, #filter_end_date, #customSearch').val(''); 
        caTable.search('').draw(); 
        window.refreshPageContent(true);
    });

    // 3.4 Manual Refresh Button Listener
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});
</script>