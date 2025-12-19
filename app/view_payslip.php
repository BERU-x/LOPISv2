<?php
// view_payslip.php

// --- 1. CONFIGURATION & INCLUDES ---
$page_title = 'View Payslip';
$current_page = 'payroll'; 

require '../template/header.php'; 
require '../template/sidebar.php'; 
require '../template/topbar.php';

// --- SESSION CHECK (Ensure variables exist) ---
// Adjust 'user_type' and 'user_id' to match your actual $_SESSION key names
$user_type = $_SESSION['usertype'] ?? 99;
$my_id = $_SESSION['employee_id'] ?? 0;

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

    // --- SECURITY CHECK: RESTRICT EMPLOYEE ACCESS ---
    // If user is Employee (2), they can ONLY view their own payslip
    if ($user_type == 2 && $payslip['employee_id'] != $my_id) {
        echo "<div class='container-fluid'><div class='alert alert-danger fw-bold text-center mt-5'><i class='fas fa-lock me-2'></i>Unauthorized Access: You cannot view this payslip.</div></div>";
        require 'template/footer.php';
        exit;
    }

    // B. Fetch Line Items (Earnings & Deductions)
    $stmt_items = $pdo->prepare("SELECT * FROM tbl_payroll_items WHERE payroll_id = :pid ORDER BY id ASC");
    $stmt_items->execute([':pid' => $payroll_id]);
    $all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $earnings = [];
    $deductions = [];
    $deduction_lookup = []; 

    foreach ($all_items as $item) {
        if ($item['item_type'] == 'earning') {
            $earnings[] = $item;
        } elseif ($item['item_type'] == 'deduction') {
            $deductions[] = $item;
            $deduction_lookup[$item['item_name']] = floatval($item['amount']);
        }
    }

    // C. Fetch Leave Usage
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
    $leaves_taken_lookup = array_column($leaves_taken, 'total_days', 'leave_type');


    // D. Fetch Leave Balances
    $remaining_balances = [];
    if (file_exists('models/leave_model.php')) {
        require_once 'models/leave_model.php';
        if (function_exists('get_leave_balance')) {
             $all_balances = get_leave_balance($pdo, $payslip['employee_id']);
             foreach($all_balances as $type => $data) {
                 if (isset($data['remaining'])) {
                     $remaining_balances[$type] = $data['remaining'];
                 }
             }
        }
    }

    // E. FETCH LEDGER BALANCES
    $sql_loan_balances = "
        SELECT category, running_balance 
        FROM tbl_employee_ledger 
        WHERE employee_id = :eid 
        AND (category, id) IN (
            SELECT category, MAX(id) 
            FROM tbl_employee_ledger 
            WHERE employee_id = :eid
            GROUP BY category
        )
    ";
    $stmt_loan_balances = $pdo->prepare($sql_loan_balances);
    $stmt_loan_balances->execute([':eid' => $payslip['employee_id']]);
    $ledger_balances = $stmt_loan_balances->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // F. Fetch Days Present
    $sql_days = "SELECT COUNT(DISTINCT date) as days_present 
                 FROM tbl_attendance 
                 WHERE employee_id = :eid 
                 AND date BETWEEN :start AND :end 
                 AND num_hr > 0";
    $stmt_days = $pdo->prepare($sql_days);
    $stmt_days->execute([
        ':eid'   => $payslip['employee_id'],
        ':start' => $payslip['cut_off_start'],
        ':end'   => $payslip['cut_off_end']
    ]);
    $days_present = intval($stmt_days->fetchColumn() ?: 0);

    // Format Name
    $full_name = strtoupper($payslip['lastname'] . ', ' . $payslip['firstname'] . ' ' . ($payslip['suffix'] ?? ''));
    if (!empty($payslip['middlename'])) {
        $full_name .= ' ' . substr($payslip['middlename'], 0, 1) . '.';
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage()); 
}

// --- DEFINE READ-ONLY LOGIC ---
// If Status is PAID (1) OR User is Employee (2), inputs are readonly
$is_editable = ($payslip['status'] != 1 && $user_type != 2);
$readonly_attr = $is_editable ? '' : 'readonly';
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
        border-radius: 4px;
    }
    input.editable-input:focus {
        border: 1px solid #4e73df;
        background-color: #fff;
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }
    /* Style specifically for Readonly inputs (Employees/Paid status) */
    input.editable-input[readonly] {
        border: none;
        background: transparent;
        color: #5a5c69;
        cursor: default;
        box-shadow: none;
    }
    .table-sm td, .table-sm th { padding: 0.3rem; }

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
        <div class="no-print">
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Payslip Details</h1>
            
            <?php if($user_type != 2): // Admin Back Link ?>
                <a href="../admin/payroll.php" class="text-decoration-none text-gray-600 small">
                    <i class="fas fa-arrow-left me-1"></i> Back to Payroll Management
                </a>
            <?php else: // Employee Back Link ?>
                <a href="../user/payslips.php" class="text-decoration-none text-gray-600 small">
                    <i class="fas fa-arrow-left me-1"></i> Back to My Payslips
                </a>
            <?php endif; ?>
        </div>
        
        <div class="d-flex gap-2">
            <?php if($is_editable): ?>
            <button type="button" id="btnSavePayslip" class="btn btn-primary shadow-sm fw-bold me-2">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
            <?php endif; ?>

            <a href="../functions/print_payslip.php?id=<?php echo $payroll_id; ?>" 
                target="_blank" 
                class="btn btn-teal shadow-sm fw-bold">
                <i class="fas fa-print me-2"></i>Print Payslip
            </a>
        </div>
    </div>

    <div id="payslip-container" class="payslip-paper rounded">
        <form id="payslip-form">
            <input type="hidden" name="payroll_id" value="<?php echo $payroll_id; ?>">

            <div class="row border-bottom border-2 border-teal pb-3 mb-4 align-items-end">
                <div class="col-8">
                    <h4 class="text-black fw-bolder mb-0 text-uppercase">Payslip</h4>
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
                    <div class="row mt-1">
                        <div class="col-6 text-gray-600 fw-bold">Days Worked:</div>
                        <div class="col-6 text-dark fw-bold"><?php echo $days_present; ?></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-6 border-end">
                    <h6 class="text-secondary fw-bold text-uppercase border-bottom pb-2 mb-3">Earnings</h6>
                    <table class="table table-borderless table-sm small align-middle">
                        <tr>
                            <td class="text-gray-600 fst-italic">Daily Rate</td>
                            <td class="text-end text-muted">₱ <?php echo number_format($payslip['daily_rate'], 2); ?></td>
                        </tr>
                        
                        <?php foreach($earnings as $earn): ?>
                        <tr>
                            <td class="text-gray-800 fw-bold"><?php echo htmlspecialchars($earn['item_name']); ?></td>
                            <td class="text-end">
                                <input type="number" step="0.01" 
                                       name="items[<?php echo $earn['id']; ?>]" 
                                       class="editable-input earning-field" 
                                       value="<?php echo $earn['amount']; ?>"
                                       <?php echo $readonly_attr; ?>>
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
                    <h6 class="text-secondary fw-bold text-uppercase border-bottom pb-2 mb-3">Deductions</h6>
                    <table class="table table-borderless table-sm small align-middle">
                        <?php if(count($deductions) > 0): ?>
                            <?php foreach($deductions as $deduct): ?>
                            <tr>
                                <td class="text-gray-600"><?php echo htmlspecialchars($deduct['item_name']); ?></td>
                                <td class="text-end">
                                    <input type="number" step="0.01" 
                                           name="items[<?php echo $deduct['id']; ?>]" 
                                           class="editable-input deduction-field" 
                                           value="<?php echo $deduct['amount']; ?>"
                                           <?php echo $readonly_attr; ?>>
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
            </div>

            <div class="row mt-4 pt-3 border-top border-light">
                
                <div class="col-6">
                    <h6 class="text-gray-600 fw-bold text-uppercase text-xs mb-2">Leave Summary (Total)</h6>
                    <table class="table table-bordered table-sm small align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Type</th>
                                <th class="text-center">Used (Current)</th>
                                <th class="text-center">Remaining (Year)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $leave_types = ['Vacation Leave', 'Sick Leave', 'Emergency Leave', 'Maternity/Paternity', 'Unpaid Leave'];
                            $has_leave_data = false;
                            
                            foreach($leave_types as $type):
                                $used = $leaves_taken_lookup[$type] ?? 0;
                                $remaining = $remaining_balances[$type] ?? 'N/A';
                                
                                if ($used > 0 || $remaining !== 'N/A'): 
                                    $has_leave_data = true;
                                    $rem_class = ($remaining !== 'N/A' && $remaining <= 0) ? 'text-danger' : 'text-success';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type); ?></td>
                                    <td class="text-center"><?php echo $used; ?></td>
                                    <td class="text-center fw-bold <?php echo $rem_class; ?>"><?php echo $remaining; ?></td>
                                </tr>
                            <?php 
                                    endif;
                                endforeach;
                                if(!$has_leave_data):
                            ?>
                                <tr><td colspan="3" class="text-center text-muted fst-italic">No relevant leave data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="col-6">
                    <h6 class="text-gray-600 fw-bold text-uppercase text-xs mb-2">Financial Accounts Summary</h6>
                    <table class="table table-bordered table-sm small align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Projected Balance</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $financial_categories = [
                                'SSS_Loan' => 'SSS Loan', 
                                'Pagibig_Loan' => 'Pag-IBIG Loan', 
                                'Company_Loan' => 'Company Loan', 
                                'Cash_Assist' => 'Cash Assistance',
                                'Savings' => 'Company Savings'
                            ];
                            $has_accounts = false;
                            
                            foreach($financial_categories as $category => $label):
                                $db_balance = floatval($ledger_balances[$category] ?? 0);
                                $current_deduction = 0;
                                foreach($deductions as $d_item) {
                                    if (strpos($d_item['item_name'], $label) !== false) {
                                        $current_deduction = floatval($d_item['amount']);
                                        break;
                                    }
                                }
                                
                                if ($category === 'Savings') {
                                    $calculated_remaining = $db_balance + $current_deduction;
                                    $math_operator = '+';
                                } else {
                                    $calculated_remaining = $db_balance - $current_deduction;
                                    $math_operator = '-';
                                }
                                
                                if ($db_balance > 0 || $current_deduction > 0):
                                    $has_accounts = true;
                                    $rem_class = 'text-dark';
                                    if ($category !== 'Savings' && $calculated_remaining <= 0) $rem_class = 'text-success';
                            ?>
                                <tr>
                                    <td><?php echo $label; ?></td>
                                    <td class="text-end fw-bold <?php echo $rem_class; ?>">
                                        <span>₱ <?php echo number_format($calculated_remaining, 2); ?></span>
                                        <div class="text-gray-400 text-xs font-weight-normal">
                                            (₱ <?php echo number_format($db_balance, 2); ?> <?php echo $math_operator; ?> ₱ <?php echo number_format($current_deduction, 2); ?>)
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endforeach;
                            
                            if(!$has_accounts): 
                            ?>
                                <tr><td colspan="2" class="text-center text-muted fst-italic">No active financial accounts.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="row mt-4 pt-3 border-top border-2 border-teal"></div>

            <div class="row mt-5">
                <div class="col-12 text-center">
                    <div class="d-inline-block px-5 py-3 border border-2 border-teal rounded bg-light">
                        <div class="text-xs fw-bold text-gray-800 text-uppercase mb-1">Total Net Pay</div>
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

<?php require '../template/footer.php'; ?>

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

    // Trigger on input change (Only works if field is editable)
    $('#payslip-form').on('input', 'input.editable-input', calculate);
    calculate(); // Initial check

    // --- 2. Save Changes (AJAX) ---
    // Only bind if button exists (Admins only)
    <?php if($is_editable): ?>
    $('#btnSavePayslip').on('click', function() {
        Swal.fire({
            title: 'Save Changes?',
            text: "This will update the payroll amounts.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, save it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show Loading
                Swal.fire({
                    title: 'Saving...',
                    text: 'Please wait.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: 'api/update_payslip.php',
                    type: 'POST',
                    data: $('#payslip-form').serialize(),
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('Saved!', res.message, 'success')
                                .then(() => { location.reload(); });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                        Swal.fire('Error', 'Server error occurred.', 'error');
                    }
                });
            }
        });
    });
    <?php endif; ?>

});
</script>