<?php
// payroll.php

// --- 1. PAGE CONFIGURATION ---
$page_title = 'Payroll Processing';
$current_page = 'payroll'; 

// --- 2. INCLUDES ---
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';

// --- FETCH STATS ---
$total_payout = 0;
$pending_count = 0;
try {
    $sql_payout = "SELECT SUM(net_pay) FROM tbl_payroll WHERE status = 1 
                   AND MONTH(cut_off_end) = MONTH(CURRENT_DATE()) AND YEAR(cut_off_end) = YEAR(CURRENT_DATE())";
    $stmt_payout = $pdo->query($sql_payout);
    $total_payout = $stmt_payout->fetchColumn() ?: 0;

    $sql_pending = "SELECT COUNT(id) FROM tbl_payroll WHERE status = 0";
    $stmt_pending = $pdo->query($sql_pending);
    $pending_count = $stmt_pending->fetchColumn() ?: 0;
} catch (PDOException $e) { error_log($e->getMessage()); }
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Payroll Management</h1>
            <p class="text-muted mb-0">Manage salaries, deductions, and generate payouts.</p>
        </div>
        
        <div class="d-flex gap-2">
            <button class="btn btn-white text-gray-800 shadow-sm fw-bold border" onclick="printBatchPayslips()">
                <i class="fas fa-print me-2"></i> Print Batch (Date Range)
            </button>
            
            <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
                <i class="fas fa-plus text-white me-2"></i> Run Payroll
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-teal shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-teal text-uppercase mb-1">Total Payout (This Month)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ₱ <?php echo number_format($total_payout, 2); ?>
                            </div> 
                        </div>
                        <div class="col-auto"><i class="fas fa-coins fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Approvals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_count); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-gray-800">
                <i class="fas fa-filter me-2 text-secondary"></i>Filter Records
            </h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Cut-Off Start</label>
                    <input type="date" id="filter_start_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Cut-Off End</label>
                    <input type="date" id="filter_end_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="button" id="applyFilterBtn" class="btn btn-teal w-100 fw-bold shadow-sm">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="button" id="clearFilterBtn" class="btn btn-secondary w-100 fw-bold shadow-sm">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-column flex-md-row align-items-center justify-content-between bg-white">
            
            <div class="d-flex align-items-center mb-2 mb-md-0">
                <h6 class="m-0 font-weight-bold text-gray-800 me-3"><i class="fas fa-list-alt me-2"></i>Payroll History</h6>
                
                <div class="dropdown no-arrow">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle fw-bold" type="button" id="batchDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-check-square me-1"></i> Batch Actions
                    </button>
                    <div class="dropdown-menu shadow animated--fade-in" aria-labelledby="batchDropdown">
                        <a class="dropdown-item" href="javascript:void(0);" id="btnBatchApprove">
                            <i class="fas fa-check text-success me-2"></i> Approve Selected
                        </a>
                        <a class="dropdown-item" href="javascript:void(0);" id="btnBatchEmail">
                            <i class="fas fa-envelope text-primary me-2"></i> Send Payslips
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search payroll..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="payrollTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="text-center" style="width: 20px;">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th class="border-0">Employee</th>
                            <th class="border-0">Cut-Off Period</th>
                            <th class="border-0 text-end">Net Pay</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-teal"><i class="fas fa-calculator me-2"></i>Run Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="functions/create_payroll.php" method="POST">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Target Employee</label>
                        <select name="employee_id" class="form-select">
                            <option value="all" selected>All Active Employees</option>
                            <option disabled>----------------</option>
                            <?php
                            // Fetch active employees for the dropdown
                            $stmt_dropdown = $pdo->query("SELECT employee_id, firstname, lastname FROM tbl_employees WHERE employment_status = 1 ORDER BY lastname ASC");
                            while($row = $stmt_dropdown->fetch()){
                                echo "<option value='{$row['employee_id']}'>{$row['lastname']}, {$row['firstname']} ({$row['employee_id']})</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Payroll Type</label>
                        <select name="payroll_type" class="form-select">
                            <option value="semi-monthly">Semi-Monthly (15th/30th)</option>
                            <option value="monthly">Monthly</option>
                            <option value="special">13th Month / Special</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Date Range</label>
                        <div class="input-group">
                            <input type="date" name="start_date" class="form-control" required>
                            <span class="input-group-text bg-light">to</span>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" name="generate_btn" class="btn btn-teal fw-bold shadow-sm">
                            <i class="fas fa-check-circle me-2"></i> Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>

<?php if(isset($_SESSION['status'])) { ?>
    <script>
        Swal.fire({
            toast: true, position: 'top-end', icon: "<?php echo $_SESSION['status_code']; ?>",
            title: "<?php echo $_SESSION['status_title']; ?>", text: "<?php echo $_SESSION['status']; ?>",
            showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
    </script>
<?php unset($_SESSION['status'], $_SESSION['status_title'], $_SESSION['status_code']); } ?>

<script>
$(document).ready(function() {

    // --- Initialize DataTable (Server-Side) ---
    var payrollTable = $('#payrollTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: false, 
        dom: 'rtip', 
        ajax: {
            url: "fetch/payroll_ssp.php", 
            type: "GET",
            data: function(d) {
                d.filter_start_date = $('#filter_start_date').val();
                d.filter_end_date = $('#filter_end_date').val();
            }
        },
        columns: [
            // Col 0: Checkbox
            {
                data: 'id',
                className: 'text-center',
                render: function(data) {
                    return '<input type="checkbox" class="payroll-checkbox form-check-input" value="' + data + '">';
                }
            },
            // Col 1: Employee (Ref #, Name, Avatar)
            { 
                data: 'employee_name',
                render: function(data, type, row) {
                    var ref = row.ref_no ? row.ref_no : '';
                    var photo = (row.picture && row.picture !== "") ? row.picture : (row.photo ? row.photo : 'default.png');
                    var dept = row.department ? row.department : '';

                    return `
                        <div class="d-flex align-items-center">
                            <img src="../assets/images/${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark">${data}</div>
                                <div class="small text-muted">Ref: <span class="text-gray-600 fw-bold">${ref}</span> • ${dept}</div>
                            </div>
                        </div>
                    `;
                }
            },
            // Col 2: Cut-Off
            { 
                data: 'cut_off_start',
                render: function(data, type, row) {
                    return `<span class="fw-bold text-gray-700 small">${row.cut_off_start}</span> <i class="fas fa-arrow-right mx-1 text-xs text-muted"></i> <span class="fw-bold text-gray-700 small">${row.cut_off_end}</span>`;
                }
            },
            // [REMOVED] Gross Pay Column
            // [REMOVED] Deductions Column
            
            // Col 3: Net Pay
            { data: 'net_pay', className: 'text-end fw-bolder text-gray-800', render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') },
            
            // Col 4: Status
            { 
                data: 'status',
                className: 'text-center',
                render: function(data) {
                    if(data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill">Paid</span>';
                    if(data == 2) return '<span class="badge bg-soft-secondary text-secondary border border-secondary px-3 shadow-sm rounded-pill">Cancelled</span>';
                    return '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill">Pending</span>';
                }
            },
            // Col 5: Action (View Payslip) - Index 5 in the new column count
            {
                data: 'id', // Use the actual record ID
                orderable: false, 
                className: 'text-center',
                render: function(data) {
                    // Removed target="_blank" to open the link in the current tab
                    return `<a href="view_payslip.php?id=${data}" class="btn btn-sm btn-light text-secondary border shadow-sm rounded-circle" title="View Payslip">
                                <i class="fas fa-eye"></i>
                            </a>`;
                }
            }
        ]
    });

    // --- Link Custom Search ---
    $('#customSearch').on('keyup', function() {
        payrollTable.search(this.value).draw();
    });

    // --- "Select All" Logic ---
    $('#selectAll').on('click', function(){
        var rows = payrollTable.rows({ 'search': 'applied' }).nodes();
        $('input[type="checkbox"]', rows).prop('checked', this.checked);
    });

    // --- Batch Action Function ---
    function performBatchAction(actionType) {
        var selectedIds = [];
        $('.payroll-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire('No Selection', 'Please select at least one record.', 'warning');
            return;
        }

        let actionText = actionType === 'approve' ? 'Approve Selected' : 'Send Emails to Selected';
        let confirmBtnText = actionType === 'approve' ? 'Yes, Approve' : 'Yes, Send';

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to ${actionText} (${selectedIds.length} items).`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1cc88a',
            cancelButtonColor: '#858796',
            confirmButtonText: confirmBtnText
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'functions/batch_payroll_action.php',
                    type: 'POST',
                    data: { ids: selectedIds, action: actionType },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success', response.message, 'success');
                            payrollTable.ajax.reload();
                            $('#selectAll').prop('checked', false);
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server request failed.', 'error');
                    }
                });
            }
        });
    }

    // --- Button Event Listeners ---
    $('#btnBatchApprove').on('click', function() { performBatchAction('approve'); });
    $('#btnBatchEmail').on('click', function() { performBatchAction('send_email'); });

    // --- Filter Logic ---
    $('#applyFilterBtn').on('click', function() { payrollTable.ajax.reload(); });
    $('#clearFilterBtn').on('click', function() {
        $('#filter_start_date').val('');
        $('#filter_end_date').val('');
        $('#customSearch').val('');
        payrollTable.search('').draw();
        payrollTable.ajax.reload();
    });
});

function printBatchPayslips() {
    // 1. Get values from the existing filter inputs
    var start_date = $('#filter_start_date').val();
    var end_date = $('#filter_end_date').val();

    // 2. Validate
    if(start_date === '' || end_date === '') {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Date Range',
            text: 'Please select a "Cut-Off Start" and "Cut-Off End" date in the filter section first.'
        });
        return;
    }

    // 3. Open the generator in a new tab
    var url = 'functions/print_batch_payslips.php?start=' + start_date + '&end=' + end_date;
    window.open(url, '_blank');
}
</script>