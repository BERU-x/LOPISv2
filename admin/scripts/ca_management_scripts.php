<script>
var caTable;
var currentCAId;

$(document).ready(function() {
    
    // 1. INITIALIZE DATATABLE
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
        columns: [
            // Col 0: Employee
            { 
                data: 'employee_id', // Using ID as key, renderer uses full data
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
            { data: 'date_requested', className: 'text-center' },
            // Col 2: Amount
            { 
                data: 'amount',
                className: 'text-center fw-bold text-gray-800',
                render: function(data) { return '₱' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2}); }
            },
            // Col 3: Status
            { 
                data: 'status', 
                className: 'text-center',
                render: function(data) {
                    let cls = 'secondary';
                    if (data === 'Paid') return '<span class="badge bg-secondary">Paid</span>';
                    if (data === 'Deducted') return '<span class="badge bg-success">Approved</span>';
                    if (data === 'Pending') return '<span class="badge bg-warning text-dark">Pending</span>';
                    if (data === 'Cancelled') return '<span class="badge bg-danger">Rejected</span>';
                    return `<span class="badge bg-secondary">${data}</span>`;
                }
            },
            // Col 4: Actions (View Button)
            { 
                data: 'id', 
                orderable: false,
                className: 'text-center',
                render: function(data) {
                    return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="viewCA(${data})"><i class="fas fa-eye me-1"></i> Details </button>`;
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
    $('#applyFilterBtn').click(function() { caTable.ajax.reload(); });
    $('#clearFilterBtn').click(function() {
        $('#filter_start_date, #filter_end_date, #customSearch').val(''); 
        caTable.search('').ajax.reload();
    });

});

// --- VIEW DETAILS LOGIC ---
function viewCA(id) {
    currentCAId = id;
    
    // Reset Modal UI
    $('#modal_approved_input').val('').removeClass('d-none');
    $('#modal_approved_display').text('').addClass('d-none');
    $('#modal_actions').removeClass('d-none');

    $.ajax({
        url: 'api/cash_advance_action.php?action=get_details',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
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

            // Conditional Logic
            if(data.status === 'Pending') {
                $('#modal_approved_input').val(data.amount); // Default to requested amount
                $('#modal_actions').show();
            } else {
                // Read-only
                $('#modal_approved_input').addClass('d-none');
                $('#modal_approved_display').text('₱ ' + parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2})).removeClass('d-none');
                $('#modal_actions').hide();
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
        text: type === 'approve' ? `Approved Amount: ₱${amount}` : "This will mark the request as Cancelled.",
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
                        caTable.ajax.reload(null, false);
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
</script>