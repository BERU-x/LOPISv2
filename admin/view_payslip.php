<?php
// view_payslip.php

// --- 1. CONFIGURATION & INCLUDES ---
$page_title = 'View Payslip';
$current_page = 'payroll'; 

require 'template/header.php'; 
require 'template/sidebar.php'; 
require 'template/topbar.php';

// --- 2. GET DATA ---
$payroll_id = $_GET['id'] ?? null;

if (!is_numeric($payroll_id) || (int)$payroll_id <= 0) {
    echo "<div class='container-fluid'><div class='alert alert-danger'>Invalid Payroll ID.</div></div>";
    require 'template/footer.php';
    exit;
}
$payroll_id = (int)$payroll_id;

try {
    // A. Fetch Header Record
    $sql = "SELECT p.*, 
            e.employee_id as emp_code, e.firstname, e.lastname, e.middlename, e.department, e.position, e.suffix,
            c.daily_rate 
            FROM tbl_payroll p
            JOIN tbl_employees e ON p.employee_id = e.employee_id
            LEFT JOIN tbl_compensation c ON p.employee_id = c.employee_id
            WHERE p.id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $payroll_id]);
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payslip) {
        echo "<div class='container-fluid'><div class='alert alert-warning'>Record not found.</div></div>";
        require 'template/footer.php';
        exit;
    }

    // B. Fetch Line Items (Earnings & Deductions)
    $stmt_items = $pdo->prepare("SELECT * FROM tbl_payroll_items WHERE payroll_id = :pid ORDER BY id ASC");
    $stmt_items->execute([':pid' => $payroll_id]);
    $all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $earnings = [];
    $deductions = [];
    foreach ($all_items as $item) {
        if ($item['item_type'] == 'earning') $earnings[] = $item;
        elseif ($item['item_type'] == 'deduction') $deductions[] = $item;
    }

    // --- NEW: C. Fetch Leave Usage (For this Cut-Off) ---
    // We check tbl_leave for approved leaves (status=1) within the pay period
    $sql_leave = "SELECT leave_type, SUM(days_count) as total_days 
                  FROM tbl_leave 
                  WHERE employee_id = :eid 
                  AND status = 1 
                  AND start_date BETWEEN :start AND :end
                  GROUP BY leave_type";
    $stmt_leave = $pdo->prepare($sql_leave);
    $stmt_leave->execute([
        ':eid'   => $payslip['employee_id'],
        ':start' => $payslip['cut_off_start'],
        ':end'   => $payslip['cut_off_end']
    ]);
    $leaves_taken = $stmt_leave->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW: D. Fetch Financial/Loan Config (Reference) ---
    $sql_finance = "SELECT * FROM tbl_employee_financials WHERE employee_id = :eid LIMIT 1";
    $stmt_finance = $pdo->prepare($sql_finance);
    $stmt_finance->execute([':eid' => $payslip['employee_id']]);
    $financials = $stmt_finance->fetch(PDO::FETCH_ASSOC);

    // Format Name
    $full_name = strtoupper($payslip['lastname'] . ', ' . $payslip['firstname'] . ' ' . ($payslip['suffix'] ?? ''));
    if (!empty($payslip['middlename'])) {
        $full_name .= ' ' . substr($payslip['middlename'], 0, 1) . '.';
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<style>
    .payslip-paper {
        background: #fff;
        max-width: 850px;
        margin: 0 auto;
        padding: 40px;
        border: 1px solid #e3e6f0;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        position: relative;
    }
    input.editable-input {
        border: 1px dashed #d1d3e2;
        background-color: #f8f9fc;
        color: #4e73df;
        font-weight: 700;
        text-align: right;
        padding: 2px 5px;
        width: 120px;
        transition: all 0.2s;
    }
    input.editable-input:focus {
        border: 1px solid #17a2b8;
        background-color: #fff;
        outline: none;
    }
    @media print {
        @page { size: portrait; margin: 10mm; }
        body * { visibility: hidden; }
        #payslip-container, #payslip-container * { visibility: visible; }
        #payslip-container {
            position: absolute; left: 0; top: 0; width: 100%;
            margin: 0; padding: 0; border: none; box-shadow: none;
        }
        .no-print { display: none !important; }
        input.editable-input { border: none; background: transparent; padding: 0; color: #000; text-align: right; }
    }
</style>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Payslip Details</h1>
            <a href="payroll.php" class="text-xs text-teal fw-bold text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Payroll List</a>
        </div>
        
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success shadow-sm fw-bold" id="save-adjustment-btn">
                <i class="fas fa-save me-2"></i>Save Adjustments
            </button>
            <button class="btn btn-teal shadow-sm fw-bold" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
        </div>
    </div>

    <div id="payslip-container" class="payslip-paper rounded">
        <form id="payslip-form">
            <input type="hidden" name="payroll_id" value="<?php echo $payroll_id; ?>">

            <div class="row border-bottom border-2 border-teal pb-3 mb-4 align-items-end">
                <div class="col-8">
                    <h4 class="text-teal fw-bolder mb-0 text-uppercase">Payslip</h4>
                    <div class="small text-muted font-weight-bold">Ref: <?php echo htmlspecialchars($payslip['ref_no']); ?></div>
                </div>
                <div class="col-4 text-end">
                    <?php
                    $status = $payslip['status'];
                    $badgeClass = $status == 1 ? 'bg-success' : ($status == 2 ? 'bg-secondary' : 'bg-warning text-dark');
                    $statusText = $status == 1 ? 'PAID' : ($status == 2 ? 'CANCELLED' : 'PENDING');
                    ?>
                    <span class="badge <?php echo $badgeClass; ?> px-3 py-2 shadow-sm"><?php echo $statusText; ?></span>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-7">
                    <h5 class="fw-bold text-gray-800 mb-0"><?php echo htmlspecialchars($full_name); ?></h5>
                    <div class="text-gray-600 small"><?php echo htmlspecialchars($payslip['position']); ?></div>
                    <div class="text-gray-600 small"><?php echo htmlspecialchars($payslip['department']); ?></div>
                </div>
                <div class="col-md-5 text-end small">
                    <div class="row">
                        <div class="col-6 text-gray-600 fw-bold">Employee ID:</div>
                        <div class="col-6 text-dark fw-bold"><?php echo htmlspecialchars($payslip['emp_code']); ?></div>
                    </div>
                    <div class="row mt-1">
                        <div class="col-6 text-gray-600 fw-bold">Pay Period:</div>
                        <div class="col-6 text-dark">
                            <?php echo date('M d', strtotime($payslip['cut_off_start'])) . ' - ' . date('M d, Y', strtotime($payslip['cut_off_end'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-6 border-end">
                    <h6 class="text-success fw-bold text-uppercase border-bottom pb-2 mb-3">Earnings</h6>
                    <table class="table table-borderless table-sm small align-middle">
                        <tr>
                            <td class="text-gray-600 fst-italic">Daily Rate (Ref)</td>
                            <td class="text-end text-muted">₱ <?php echo number_format($payslip['daily_rate'], 2); ?></td>
                        </tr>
                        
                        <?php foreach($earnings as $earn): ?>
                        <tr>
                            <td class="text-gray-800 fw-bold"><?php echo htmlspecialchars($earn['item_name']); ?></td>
                            <td class="text-end">
                                <input type="number" step="0.01" 
                                       name="items[<?php echo $earn['id']; ?>]" 
                                       class="form-control form-control-sm editable-input earning-field" 
                                       value="<?php echo $earn['amount']; ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <div class="d-flex justify-content-between align-items-center bg-gray-100 p-2 rounded mt-4">
                        <span class="fw-bold text-gray-800">GROSS PAY</span>
                        <span class="fw-bold text-dark fs-6" id="disp_gross">
                            ₱ <?php echo number_format($payslip['gross_pay'], 2); ?>
                        </span>
                    </div>
                </div>

                <div class="col-6 ps-4">
                    <h6 class="text-danger fw-bold text-uppercase border-bottom pb-2 mb-3">Deductions</h6>
                    <table class="table table-borderless table-sm small align-middle">
                        
                        <?php if(count($deductions) > 0): ?>
                            <?php foreach($deductions as $deduct): ?>
                            <tr>
                                <td class="text-gray-600"><?php echo htmlspecialchars($deduct['item_name']); ?></td>
                                <td class="text-end">
                                    <input type="number" step="0.01" 
                                           name="items[<?php echo $deduct['id']; ?>]" 
                                           class="form-control form-control-sm editable-input deduction-field" 
                                           value="<?php echo $deduct['amount']; ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-muted text-center fst-italic py-4">No deductions recorded.</td></tr>
                        <?php endif; ?>
                    </table>

                    <div class="d-flex justify-content-between align-items-center bg-gray-100 p-2 rounded mt-4">
                        <span class="fw-bold text-gray-800">TOTAL DEDUCTIONS</span>
                        <span class="fw-bold text-danger fs-6" id="disp_deductions">
                            (₱ <?php echo number_format($payslip['total_deductions'], 2); ?>)
                        </span>
                    </div>
                </div>

                <div class="row mt-4 pt-3 border-top border-light">
                
                <div class="col-6">
                    <h6 class="text-gray-600 fw-bold text-uppercase text-xs mb-2">Leaves Taken (This Period)</h6>
                    <table class="table table-bordered table-sm small align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Type</th>
                                <th class="text-center">Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($leaves_taken) > 0): ?>
                                <?php foreach($leaves_taken as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                    <td class="text-center fw-bold"><?php echo $leave['total_days']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center text-muted fst-italic">No leaves taken.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="col-6">
                    <h6 class="text-gray-600 fw-bold text-uppercase text-xs mb-2">Active Loan Configs (Monthly)</h6>
                    <table class="table table-bordered table-sm small align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Loan Type</th>
                                <th class="text-end">Amortization</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $has_loans = false;
                            $loan_fields = [
                                'sss_loan' => 'SSS Loan', 
                                'pagibig_loan' => 'Pag-IBIG Loan', 
                                'company_loan' => 'Company Loan', 
                                'cash_advance' => 'Cash Advance'
                            ];
                            
                            if($financials):
                                foreach($loan_fields as $db_col => $label):
                                    $amount = floatval($financials[$db_col] ?? 0);
                                    if($amount > 0): 
                                        $has_loans = true;
                            ?>
                                <tr>
                                    <td><?php echo $label; ?></td>
                                    <td class="text-end text-muted">₱ <?php echo number_format($amount, 2); ?></td>
                                </tr>
                            <?php 
                                    endif;
                                endforeach;
                            endif;

                            if(!$has_loans): 
                            ?>
                                <tr><td colspan="2" class="text-center text-muted fst-italic">No active loans.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="row mt-4 pt-3 border-top border-2 border-teal"></div>
            </div>

            <div class="row mt-5">
                <div class="col-12 text-center">
                    <div class="d-inline-block px-5 py-3 border border-2 border-teal rounded bg-light">
                        <div class="text-xs fw-bold text-teal text-uppercase mb-1">Total Net Pay</div>
                        <h2 class="mb-0 fw-bolder text-gray-900" id="disp_net">
                            ₱ <?php echo number_format($payslip['net_pay'], 2); ?>
                        </h2>
                    </div>
                </div>
            </div>
            
            <div class="mt-5 text-center text-xs text-gray-500">
                <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
            </div>
        </form>
    </div>
    
    <div class="mb-5"></div>
</div>

<?php require 'template/footer.php'; ?>

<script>
$(document).ready(function() {

    // --- 1. Real-time Calculation ---
    function formatMoney(amount) {
        return '₱ ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function calculate() {
        let gross = 0;
        let total_deductions = 0;

        // Sum Earnings
        $('.earning-field').each(function() {
            gross += parseFloat($(this).val()) || 0;
        });

        // Sum Deductions
        $('.deduction-field').each(function() {
            total_deductions += parseFloat($(this).val()) || 0;
        });

        let net = gross - total_deductions;

        // Update DOM
        $('#disp_gross').text(formatMoney(gross));
        $('#disp_deductions').text('(₱ ' + total_deductions.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + ')');
        $('#disp_net').text(formatMoney(net));
    }

    // Trigger on any input change
    $('#payslip-form').on('input', 'input.editable-input', calculate);

    // --- 2. Save Adjustment (AJAX) ---
    $('#save-adjustment-btn').click(function() {
        let btn = $(this);
        let originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

        // Note: You will need a new "functions/update_payroll_items.php" to handle this
        // because we are now sending an array of item IDs, not just columns.
        $.ajax({
            url: 'functions/update_payroll_items.php', 
            type: 'POST',
            data: $('#payslip-form').serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    Swal.fire({
                        icon: 'success', title: 'Updated!',
                        text: res.message, timer: 1500, showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to save changes.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

});
</script>