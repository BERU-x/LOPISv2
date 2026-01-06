<?php
// view_payslip.php

// --- 1. CONFIGURATION & INCLUDES ---
$page_title = 'View Payslip';
$current_page = 'payroll'; 

require __DIR__ . '/../template/header.php'; 
require __DIR__ . '/../template/sidebar.php'; 
require __DIR__ . '/../template/topbar.php';

// --- SESSION CHECK ---
$user_type = $_SESSION['usertype'] ?? 99;
$my_id = $_SESSION['employee_id'] ?? 0;

// --- 2. GET DATA ---
$payroll_id = $_GET['id'] ?? null;

if (!is_numeric($payroll_id) || (int)$payroll_id <= 0) {
    echo "<div class='container-fluid py-5'><div class='alert alert-danger shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i>Invalid Payroll ID.</div></div>";
    require '../template/footer.php';
    exit;
}
$payroll_id = (int)$payroll_id;

try {
    // A. Fetch Header Record (Including Daily Rate for display)
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
        echo "<div class='container-fluid py-5'><div class='alert alert-warning shadow-sm'>Record not found. It may have been recalculated.</div></div>";
        require '../template/footer.php';
        exit;
    }

    // --- SECURITY CHECK ---
    if ($user_type == 2 && $payslip['employee_id'] != $my_id) {
        echo "<div class='container-fluid py-5'><div class='alert alert-danger fw-bold text-center'><i class='fas fa-lock me-2'></i>Unauthorized Access</div></div>";
        require '../template/footer.php';
        exit;
    }

    // B. Fetch Line Items
    $stmt_items = $pdo->prepare("SELECT * FROM tbl_payroll_items WHERE payroll_id = :pid ORDER BY item_type DESC, id ASC");
    $stmt_items->execute([':pid' => $payroll_id]);
    $all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $earnings = [];
    $deductions = [];

    foreach ($all_items as $item) {
        if ($item['item_type'] == 'earning') $earnings[] = $item;
        else $deductions[] = $item;
    }

    // C. Fetch Days Present
    $sql_days = "SELECT COUNT(DISTINCT date) as days_present 
                 FROM tbl_attendance 
                 WHERE employee_id = :eid AND date BETWEEN :start AND :end AND num_hr > 0";
    $stmt_days = $pdo->prepare($sql_days);
    $stmt_days->execute([
        ':eid'   => $payslip['employee_id'],
        ':start' => $payslip['cut_off_start'],
        ':end'   => $payslip['cut_off_end']
    ]);
    $days_present = intval($stmt_days->fetchColumn() ?: 0);

    // D. Fetch Loan Balances (For Footer Info)
    $sql_loan = "SELECT category, running_balance FROM tbl_employee_ledger 
                 WHERE employee_id = :eid AND (category, id) IN (SELECT category, MAX(id) FROM tbl_employee_ledger WHERE employee_id = :eid GROUP BY category)";
    $stmt_loan = $pdo->prepare($sql_loan);
    $stmt_loan->execute([':eid' => $payslip['employee_id']]);
    $ledger_balances = $stmt_loan->fetchAll(PDO::FETCH_KEY_PAIR);

    $full_name = strtoupper($payslip['lastname'] . ', ' . $payslip['firstname'] . ' ' . ($payslip['suffix'] ?? ''));

} catch (PDOException $e) {
    die("Error: " . $e->getMessage()); 
}

$is_editable = ($payslip['status'] == 0 && $user_type != 2);
$readonly_attr = $is_editable ? '' : 'readonly';
?>

<style>
    .payslip-paper { background: #fff; max-width: 850px; margin: 0 auto; padding: 40px; border: 1px solid #e3e6f0; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
    input.editable-input { border: 1px dashed #d1d3e2; background-color: #f8f9fc; color: #4e73df; font-weight: 700; text-align: right; padding: 2px 5px; width: 110px; border-radius: 4px; }
    input.editable-input[readonly] { border: none; background: transparent; color: #5a5c69; cursor: default; }
    .text-late { color: #e74a3b !important; font-weight: bold; }
    @media print { .no-print { display: none !important; } .payslip-paper { border: none; box-shadow: none; padding: 0; } }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Payslip View</h1>
            <a href="javascript:history.back()" class="text-decoration-none text-gray-600 small"><i class="fas fa-arrow-left me-1"></i> Return</a>
        </div>
        <div class="d-flex gap-2">
            <?php if($is_editable): ?>
            <button type="button" id="btnSyncPayroll" class="btn btn-outline-warning shadow-sm fw-bold"><i class="fas fa-sync-alt me-2"></i>Recalculate</button>
            <button type="button" id="btnSavePayslip" class="btn btn-primary shadow-sm fw-bold"><i class="fas fa-save me-2"></i>Save Changes</button>
            <?php endif; ?>
            <a href="../functions/print_payslip.php?id=<?php echo $payroll_id; ?>" target="_blank" class="btn btn-teal shadow-sm fw-bold"><i class="fas fa-print me-2"></i>Print</a>
        </div>
    </div>

    <div id="payslip-container" class="payslip-paper rounded mb-5">
        <form id="payslip-form">
            <input type="hidden" name="payroll_id" value="<?php echo $payroll_id; ?>">

            <div class="row border-bottom border-2 border-teal pb-3 mb-4">
                <div class="col-8">
                    <h4 class="text-black fw-bolder mb-0">EMPLOYEE PAYSLIP</h4>
                    <div class="small text-muted fw-bold">REF: <?php echo $payslip['ref_no']; ?></div>
                </div>
                <div class="col-4 text-end">
                    <?php 
                        $badge = ($payslip['status'] == 1) ? 'bg-success' : (($payslip['status'] == 2) ? 'bg-secondary' : 'bg-warning text-dark');
                        $txt = ($payslip['status'] == 1) ? 'FINALIZED' : (($payslip['status'] == 2) ? 'CANCELLED' : 'DRAFT');
                    ?>
                    <span class="badge <?php echo $badge; ?> px-3 py-2"><?php echo $txt; ?></span>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-7">
                    <h5 class="fw-bold text-gray-800 mb-0"><?php echo $full_name; ?></h5>
                    <div class="text-muted small"><?php echo $payslip['position']; ?> | <?php echo $payslip['department']; ?></div>
                </div>
                <div class="col-md-5 text-end small">
                    <p class="mb-0"><strong>Period:</strong> <?php echo date('M d', strtotime($payslip['cut_off_start'])) . ' - ' . date('M d, Y', strtotime($payslip['cut_off_end'])); ?></p>
                    <p class="mb-0 text-teal fw-bold"><strong>Daily Rate:</strong> ₱ <?php echo number_format($payslip['daily_rate'], 2); ?></p>
                    <p class="mb-0"><strong>Days Present:</strong> <?php echo $days_present; ?></p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-6 border-end">
                    <h6 class="text-teal fw-bold text-uppercase border-bottom pb-2 mb-3">Earnings</h6>
                    <table class="table table-borderless table-sm small align-middle">
                        <?php foreach($earnings as $earn): ?>
                        <tr>
                            <td>
                                <?php echo $earn['item_name']; ?>
                                <?php if($earn['item_name'] == 'Basic Pay'): ?>
                                    <span class="d-block text-xs text-muted fst-italic">(<?php echo $days_present; ?> Days × ₱<?php echo number_format($payslip['daily_rate'], 2); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <input type="number" step="0.01" name="items[<?php echo $earn['id']; ?>]" class="editable-input earning-field" value="<?php echo $earn['amount']; ?>" <?php echo $readonly_attr; ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="col-6 ps-4">
                    <h6 class="text-danger fw-bold text-uppercase border-bottom pb-2 mb-3">Deductions</h6>
                    <table class="table table-borderless table-sm small align-middle">
                        <?php foreach($deductions as $ded): 
                            $is_late = ($ded['item_name'] == 'Late/Undertime');
                        ?>
                        <tr class="<?php echo $is_late ? 'text-late' : ''; ?>">
                            <td><?php echo $ded['item_name']; ?></td>
                            <td class="text-end">
                                <input type="number" step="0.01" name="items[<?php echo $ded['id']; ?>]" class="editable-input deduction-field" value="<?php echo $ded['amount']; ?>" <?php echo $readonly_attr; ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="row mt-4 pt-3 border-top">
                <div class="col-6">
                    <div class="d-flex justify-content-between align-items-center bg-gray-100 p-2 rounded">
                        <span class="small fw-bold">GROSS PAY</span>
                        <span class="fw-bold" id="disp_gross">₱ <?php echo number_format($payslip['gross_pay'], 2); ?></span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex justify-content-between align-items-center bg-gray-100 p-2 rounded">
                        <span class="small fw-bold">DEDUCTIONS</span>
                        <span class="fw-bold text-danger" id="disp_deductions">₱ <?php echo number_format($payslip['total_deductions'], 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 text-center">
                    <div class="d-inline-block px-5 py-3 border border-2 border-teal rounded bg-soft-teal text-white shadow">
                        <div class="text-xs fw-bold text-uppercase mb-1">Net Take-Home Pay</div>
                        <h2 class="mb-0 fw-bolder" id="disp_net">₱ <?php echo number_format($payslip['net_pay'], 2); ?></h2>
                    </div>
                </div>
            </div>

            <?php if(!empty($ledger_balances)): ?>
            <div class="row mt-5 border-top pt-3 no-print">
                <div class="col-12">
                    <h6 class="text-xs fw-bold text-muted text-uppercase mb-2">Remaining Loan Balances</h6>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach($ledger_balances as $cat => $bal): if($bal <= 0) continue; ?>
                            <div class="bg-light px-2 py-1 rounded border small">
                                <span class="text-muted"><?php echo str_replace('_', ' ', $cat); ?>:</span> 
                                <span class="fw-bold text-dark">₱<?php echo number_format($bal, 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../template/footer.php'; ?>

<script>
$(document).ready(function() {
    function formatMoney(amount) { return '₱ ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function calculate() {
        let gross = 0; let ded = 0;
        $('.earning-field').each(function() { gross += parseFloat($(this).val()) || 0; });
        $('.deduction-field').each(function() { ded += parseFloat($(this).val()) || 0; });
        $('#disp_gross').text(formatMoney(gross));
        $('#disp_deductions').text(formatMoney(ded));
        $('#disp_net').text(formatMoney(gross - ded));
    }
    $('#payslip-form').on('input', 'input.editable-input', calculate);

    $('#btnSyncPayroll').on('click', function() {
        Swal.fire({
            title: 'Recalculate?',
            text: "Pulling fresh biometric logs. Days Worked: <?php echo $days_present; ?>. Edits will be lost.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Re-Sync'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Syncing...', didOpen: () => { Swal.showLoading(); } });
                $.post('../admin/functions/create_payroll.php', {
                    employee_id: '<?php echo $payslip['employee_id']; ?>',
                    payroll_type: 'semi-monthly',
                    start_date: '<?php echo $payslip['cut_off_start']; ?>',
                    end_date: '<?php echo $payslip['cut_off_end']; ?>'
                }, function(res) {
                    if (res.status === 'success') {
                        location.href = 'view_payslip.php?id=' + res.new_id;
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    $('#btnSavePayslip').on('click', function() {
        Swal.fire({ title: 'Save Changes?', icon: 'question', showCancelButton: true }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api/update_payslip.php', $('#payslip-form').serialize(), function(res) {
                    if (res.status === 'success') Swal.fire('Saved!', '', 'success').then(() => location.reload());
                    else Swal.fire('Error', res.message, 'error');
                }, 'json');
            }
        });
    });
});
</script>