<script>
var payrollTable;

$(document).ready(function() {
    
    // 1. Load Stats
    loadStats();

    // 2. Initialize DataTables
    payrollTable = $('#payrollTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: "api/payroll_action.php?action=fetch", 
            type: "GET",
            data: function(d) {
                d.filter_start_date = $('#filter_start_date').val();
                d.filter_end_date = $('#filter_end_date').val();
            }
        },
        columns: [
            // Checkbox
            {
                data: 'id',
                className: 'text-center',
                orderable: false,
                render: function(data) {
                    return '<input type="checkbox" class="payroll-checkbox form-check-input" value="' + data + '">';
                }
            },
            // Employee
            { 
                data: 'employee_name',
                render: function(data, type, row) {
                    var ref = row.ref_no ? row.ref_no : '';
                    var photo = row.picture || 'default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="../assets/images/${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.src='../assets/images/default.png'">
                            <div>
                                <div class="fw-bold text-dark">${data}</div>
                                <div class="small text-muted">Ref: <span class="text-gray-600 fw-bold">${ref}</span></div>
                            </div>
                        </div>
                    `;
                }
            },
            // Cut-Off
            { 
                data: 'cut_off_start',
                render: function(data, type, row) {
                    return `<span class="fw-bold text-gray-700 small">${row.cut_off_start}</span> <i class="fas fa-arrow-right mx-1 text-xs text-muted"></i> <span class="fw-bold text-gray-700 small">${row.cut_off_end}</span>`;
                }
            },
            // Net Pay
            { 
                data: 'net_pay', 
                className: 'text-end fw-bolder text-gray-800', 
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            // Status
            { 
                data: 'status',
                className: 'text-center',
                render: function(data) {
                    if(data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill">Paid</span>';
                    return '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill">Pending</span>';
                }
            },
            // Action
            {
                data: 'id',
                orderable: false, 
                className: 'text-center',
                render: function(data) {
                    return `<a href="view_payslip.php?id=${data}" class="btn btn-sm btn-outline-teal shadow-sm fw-bold"><i class="fas fa-eye me-1"></i> Details </a>`;
                }
            }
        ]
    });

    // --- Search & Filter ---
    $('#customSearch').on('keyup', function() { payrollTable.search(this.value).draw(); });
    $('#applyFilterBtn').on('click', function() { payrollTable.ajax.reload(); loadStats(); }); // Reload stats on filter too
    $('#clearFilterBtn').on('click', function() {
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        payrollTable.search('').draw();
        payrollTable.ajax.reload();
        loadStats();
    });

    // --- Select All ---
    $('#selectAll').on('click', function(){
        var rows = payrollTable.rows({ 'search': 'applied' }).nodes();
        $('input[type="checkbox"]', rows).prop('checked', this.checked);
    });

    // --- GENERATE PAYROLL SUBMIT ---
    $('#generatePayrollForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Generating Payroll...',
            text: 'Calculating attendance, overtime, and deductions...',
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'api/create_payroll.php', // <--- Point to the new dedicated file
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#generatePayrollModal').modal('hide');
                    Swal.fire('Success', res.message, 'success');
                    payrollTable.ajax.reload();
                    loadStats();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) { 
                console.log(xhr.responseText); // Helpful for debugging calculation errors
                Swal.fire('Error', 'Server calculation error.', 'error'); 
            }
        });
    });

    // --- BATCH ACTIONS ---
    $('#btnBatchApprove').on('click', function() { performBatchAction('approve'); });
    $('#btnBatchEmail').on('click', function() { performBatchAction('send_email'); });
});

// Helper: Load Top Stats
function loadStats() {
    $.ajax({
        url: 'api/payroll_action.php?action=stats',
        dataType: 'json',
        success: function(data) {
            if(data.status === 'success') {
                $('#stat-payout').text('₱ ' + data.total_payout);
                $('#stat-pending').text(data.pending_count);
            }
        }
    });
}

// Helper: Batch Logic
function performBatchAction(subAction) {
    var selectedIds = [];
    $('.payroll-checkbox:checked').each(function() { selectedIds.push($(this).val()); });

    if (selectedIds.length === 0) {
        Swal.fire('No Selection', 'Please select records first.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Are you sure?',
        text: `Applying action to ${selectedIds.length} records.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Proceed'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/payroll_action.php?action=batch_action',
                type: 'POST',
                data: { ids: selectedIds, sub_action: subAction },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        payrollTable.ajax.reload();
                        loadStats();
                        $('#selectAll').prop('checked', false);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }
            });
        }
    });
}

function printBatchPayslips() {
    var start = $('#filter_start_date').val();
    var end = $('#filter_end_date').val();
    if(start === '' || end === '') {
        Swal.fire('Missing Date Range', 'Please select dates in the filter first.', 'warning');
        return;
    }
    window.open(`functions/print_batch_payslips.php?start=${start}&end=${end}`, '_blank');
}
</script>