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

    // C. Fetch Leave Usage (For this Cut-Off)
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
    
    // Convert array for easier lookup
    $leaves_taken_lookup = array_column($leaves_taken, 'total_days', 'leave_type');


    // 🛑 D. Fetch Financial/Loan Config (Reference)
    $sql_finance = "SELECT * FROM tbl_employee_financials WHERE employee_id = :eid LIMIT 1";
    $stmt_finance = $pdo->prepare($sql_finance);
    $stmt_finance->execute([':eid' => $payslip['employee_id']]);
    $financials = $stmt_finance->fetch(PDO::FETCH_ASSOC);

    // 🛑 E. Fetch Employee's Remaining Leave Balances
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

    // 🛑 F. Fetch Days Present
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
?>

<style>
    /* ... existing CSS remains unchanged ... */
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
        color: black;
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
        </div>
        
        <div class="d-flex gap-2">
            <a href="functions/print_payslip.php?id=<?php echo $payroll_id; ?>" 
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
                                            class="form-control form-control-sm editable-input earning-field" 
                                            value="<?php echo $earn['amount']; ?>" readonly>
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
                                            class="form-control form-control-sm editable-input deduction-field" 
                                            value="<?php echo $deduct['amount']; ?>" readonly>
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
                        <h6 class="text-gray-600 fw-bold text-uppercase text-xs mb-2">Leave Summary (Total)</h6>
                        <table class="table table-bordered table-sm small align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-center">Used (Current Period)</th>
                                    <th class="text-center">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $leave_types = ['Vacation Leave', 'Sick Leave', 'Emergency Leave', 'Maternity/Paternity', 'Unpaid Leave'];
                                $has_leave_data = false;
                                foreach($leave_types as $type):
                                    $used = $leaves_taken_lookup[$type] ?? 0;
                                    $remaining = $remaining_balances[$type] ?? 'N/A';
                                    
                                    // Check if there is any relevant data to display
                                    if ($used > 0 || $remaining !== 'N/A'): 
                                        $has_leave_data = true;
                                        $remaining_class = ($remaining !== 'N/A' && $remaining <= 0) ? 'text-black' : 'text-black';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type); ?></td>
                                    <td class="text-center"><?php echo $used; ?></td>
                                    <td class="text-center fw-bold <?php echo $remaining_class; ?>"><?php echo $remaining; ?></td>
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
                            <h6 class="text-gray-600 fw-bold text-uppercase text-xs mb-2">Active Loan Configs</h6>
                            <table class="table table-bordered table-sm small align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Loan Type</th>
                                        <th class="text-end">Current Balance</th> 
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $has_loans = false;
                                    
                                    // Map the AMORTIZATION columns/keys to Labels
                                    $loan_fields = [
                                        'sss_loan'              => 'SSS Loan', 
                                        'pagibig_loan'          => 'Pag-IBIG Loan', 
                                        'company_loan'          => 'Company Loan', 
                                        'cash_assist_deduction' => 'Cash Assistance'
                                    ];
                                    
                                    if($financials):
                                        foreach($loan_fields as $amort_col => $label):
                                            // 1. Check if the loan is active (has an amortization setting > 0)
                                            $amortization = floatval($financials[$amort_col] ?? 0);
                                            
                                            if($amortization > 0): 
                                                $has_loans = true;

                                                // 2. Get the DATABASE Balance (Total before this payslip, usually)
                                                $db_balance = 0;
                                                if ($amort_col === 'cash_assist_deduction') {
                                                    $db_balance = floatval($financials['cash_assist_total'] ?? 0);
                                                } elseif ($amort_col === 'company_loan') {
                                                    $db_balance = floatval($financials['company_loan_balance'] ?? 0);
                                                } elseif ($amort_col === 'sss_loan') {
                                                    $db_balance = floatval($financials['sss_loan_balance'] ?? 0);
                                                } elseif ($amort_col === 'pagibig_loan') {
                                                    $db_balance = floatval($financials['pagibig_loan_balance'] ?? 0);
                                                }

                                                // 3. Find the CURRENT DEDUCTION amount in this payslip
                                                $current_deduction = 0;
                                                foreach($deductions as $d_item) {
                                                    // We check if the item name contains the Label (e.g., "SSS Loan")
                                                    if (strpos($d_item['item_name'], $label) !== false) {
                                                        $current_deduction = floatval($d_item['amount']);
                                                        break; // Stop once found
                                                    }
                                                }

                                                // 4. Calculate ACTUAL REMAINING (DB Balance - Current Deduction)
                                                $calculated_remaining = $db_balance - $current_deduction;

                                                // 5. Determine styling
                                                $bal_class = ($calculated_remaining > 0) ? 'text-black' : 'text-black';
                                                
                                                // Optional: visual breakdown tooltip or logic
                                                // $display_text = number_format($db_balance, 2) . ' - ' . number_format($current_deduction, 2) . ' = ' . number_format($calculated_remaining, 2);
                                    ?>
                                                <tr>
                                                    <td><?php echo $label; ?></td>
                                                    <td class="text-end fw-bold text-dark small">
                                                        <span class="<?php echo $bal_class; ?>">
                                                            ₱ <?php echo number_format($calculated_remaining, 2); ?>
                                                        </span>
                                                        <div class="text-gray-400 text-xs font-weight-normal">
                                                            (<?php echo number_format($db_balance, 2); ?> - <?php echo number_format($current_deduction, 2); ?>)
                                                        </div>
                                                    </td>
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

    // Trigger on any input change and initial calculation
    $('#payslip-form').on('input', 'input.editable-input', calculate);
    calculate(); // Initial calculation

});
</script>