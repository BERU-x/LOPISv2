<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var caTable;
var currentCAId;

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
    if (caTable) {
        caTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. MODAL LOGIC FUNCTIONS (Global for onclick binding)
// ==============================================================================

// --- VIEW DETAILS LOGIC ---
function viewCA(id) {
    currentCAId = id;
    const modalActions = $('#modal-actions');
    
    // Reset Modal UI and show loader in main modal body
    $('#viewCAModal .modal-body').html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-muted">Loading details...</p></div>');
    modalActions.empty(); // Clear old buttons
    
    $.ajax({
        url: 'api/cash_advance_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            
            // Re-render detailed content (Replace the loader)
            $('#viewCAModal .modal-body').html(renderCAModalBody(data)); 
            
            // Populate Fields
            var photo = (data.photo) ? '../assets/images/'+data.photo : '../assets/images/default.png';
            $('#modal_emp_photo').attr('src', photo);
            $('#modal_emp_name').text(data.firstname + ' ' + data.lastname);
            $('#modal_emp_dept').text(data.department || 'Employee');
            
            $('#modal_date_req').text(data.date_requested);
            $('#modal_remarks').text(data.remarks || 'No remarks provided.');
            $('#modal_req_amount').text('₱ ' + parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2}));

            // Status Badge
            var statusHtml = '<span class="badge bg-warning text-dark">Pending</span>';
            if(data.status === 'Paid') statusHtml = '<span class="badge bg-secondary">Paid</span>';
            if(data.status === 'Deducted') statusHtml = '<span class="badge bg-success">Approved</span>';
            if(data.status === 'Cancelled') statusHtml = '<span class="badge bg-danger">Rejected</span>';
            $('#modal_status').html(statusHtml);

            // Conditional Logic & Action Buttons
            if(data.status === 'Pending') {
                $('#modal_approved_input').val(data.amount).removeClass('d-none'); // Default to requested amount
                $('#modal_approved_display').addClass('d-none');
                
                // Add Approve/Reject Buttons to the footer
                modalActions.append(`
                    <button type="button" class="btn btn-danger fw-bold shadow-sm" onclick="processCA('reject')">Reject</button>
                    <button type="button" class="btn btn-success fw-bold shadow-sm" onclick="processCA('approve')">Approve</button>
                `);
            } else {
                // Read-only mode
                $('#modal_approved_input').addClass('d-none');
                $('#modal_approved_display').text('₱ ' + parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2})).removeClass('d-none');
                // modalActions is already empty from the initial reset
            }

            $('#viewCAModal').modal('show');
        }
    });
}

// --- APPROVE/REJECT LOGIC ---
function processCA(type) {
    var amount = $('#modal_approved_input').val();

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
                    if(res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        $('#viewCAModal').modal('hide');
                        window.refreshPageContent();
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

// Function to render the static HTML structure of the modal body (required for viewCA function)
function renderCAModalBody(data) {
    // This function assumes your main modal structure (viewCAModal) has target IDs like 
    // #modal_emp_photo, #modal_emp_name, etc. in its static HTML.
    // We return the content that replaces the initial loader.
    
    // Note: You must ensure this HTML structure matches your modal template.
    return `
        <div class="row">
            <div class="col-md-5 text-center border-end">
                <img id="modal_emp_photo" src="" class="rounded-circle border shadow-sm mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                <h5 class="fw-bold" id="modal_emp_name"></h5>
                <p class="text-muted small" id="modal_emp_dept"></p>
            </div>
            <div class="col-md-7">
                <h6 class="fw-bold text-gray-600 mb-3">Request Details</h6>
                <table class="table table-sm small">
                    <tr><td class="fw-bold">Status:</td><td id="modal_status"></td></tr>
                    <tr><td class="fw-bold">Date Requested:</td><td id="modal_date_req"></td></tr>
                    <tr><td class="fw-bold">Requested Amount:</td><td id="modal_req_amount" class="fw-bold text-teal"></td></tr>
                    
                    <tr class="align-items-center">
                        <td class="fw-bold">Approved Amount:</td>
                        <td>
                            <input type="number" step="0.01" id="modal_approved_input" class="form-control form-control-sm w-75 d-inline" />
                            <span id="modal_approved_display" class="fw-bold text-success d-none"></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <hr>
        <h6 class="fw-bold text-gray-600 mb-3">Remarks</h6>
        <div class="alert alert-light border small" id="modal_remarks"></div>
    `;
}

// ==============================================================================
// 3. DOCUMENT READY (Initialization)
// ==============================================================================
$(document).ready(function() {
    
    // 2. INITIALIZE DATATABLE
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

        drawCallback: function(settings) {
            const icon = $('#refresh-spinner');
            if (icon.hasClass('fa-spin')) {
                stopSpinnerSafely();
            } else {
                updateLastSyncTime(); 
            }
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
                            <img src="${imgPath}" class="rounded-circle me-3 border shadow-sm" style="width: 35px; height: 35px; object-fit: cover;">
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
                    if (data === 'Paid') return '<span class="badge bg-secondary">Paid</span>';
                    if (data === 'Deducted') return '<span class="badge bg-success">Approved</span>';
                    if (data === 'Pending') return '<span class="badge bg-warning text-dark">Pending</span>';
                    if (data === 'Cancelled') return '<span class="badge bg-danger">Rejected</span>';
                    return `<span class="badge bg-secondary">${data}</span>`;
                }
            },
            // Col 4: Actions (View Button - FA6 Update)
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

    // --- Search & Filters ---
    var searchTimeout;
    $('#customSearch').on('keyup', function() {
        clearTimeout(searchTimeout);
        var val = this.value;
        searchTimeout = setTimeout(function() { caTable.search(val).draw(); }, 400); 
    });
    
    $('#applyFilterBtn').click(function() { 
        window.refreshPageContent(); 
    });
    
    $('#clearFilterBtn').click(function() {
        $('#filter_start_date, #filter_end_date, #customSearch').val(''); 
        caTable.search('').draw(); 
        window.refreshPageContent();
    });
});
</script>