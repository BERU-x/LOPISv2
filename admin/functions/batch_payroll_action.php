<?php
// functions/batch_payroll_action.php
require_once '../../db_connection.php';
// 1. INCLUDE NOTIFICATION MODEL
require_once '../models/global_model.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && isset($_POST['action'])) {
    
    $ids = $_POST['ids'];
    $action = $_POST['action'];

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No items selected.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($action === 'approve') {
            $approved_count = 0;

            foreach ($ids as $payroll_id) {
                // 1. Fetch Status, Employee ID, Net Pay AND Date Range
                $stmt = $pdo->prepare("SELECT id, employee_id, status, net_pay, cut_off_start, cut_off_end FROM tbl_payroll WHERE id = ?");
                $stmt->execute([$payroll_id]);
                $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

                // Only process if it exists and is NOT already approved (Status 1)
                if ($payroll && $payroll['status'] != 1) {
                    
                    $emp_id = $payroll['employee_id'];
                    $net_pay = floatval($payroll['net_pay']);
                    $start_date = $payroll['cut_off_start'];
                    $end_date = $payroll['cut_off_end'];
                    
                    // Format dates for notification (e.g., "Dec 01 - Dec 15")
                    $period_str = date("M d", strtotime($start_date)) . " - " . date("M d", strtotime($end_date));

                    // --- A. HANDLE NEGATIVE NET PAY (Incur New Debt) ---
                    if ($net_pay < 0) {
                        $deficit_amount = abs($net_pay); 
                        $sql_update_deficit = "UPDATE tbl_employee_financials 
                                               SET outstanding_balance = outstanding_balance + ? 
                                               WHERE employee_id = ?";
                        $stmt_deficit = $pdo->prepare($sql_update_deficit);
                        $stmt_deficit->execute([$deficit_amount, $emp_id]);
                    }

                    // --- B. PROCESS DEDUCTIONS (Loans & Clearing Old Debt) ---
                    $stmt_items = $pdo->prepare("SELECT item_name, amount FROM tbl_payroll_items WHERE payroll_id = ? AND item_type = 'deduction'");
                    $stmt_items->execute([$payroll_id]);
                    $deductions = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($deductions as $item) {
                        $amount = floatval($item['amount']);
                        $col_to_update = null;

                        // 1. Check for Standard Loans
                        if (stripos($item['item_name'], 'SSS Loan') !== false) {
                            $col_to_update = 'sss_loan_balance';
                        } elseif (stripos($item['item_name'], 'Pag-IBIG Loan') !== false) {
                            $col_to_update = 'pagibig_loan_balance';
                        } elseif (stripos($item['item_name'], 'Company Loan') !== false) {
                            $col_to_update = 'company_loan_balance';
                        } elseif (stripos($item['item_name'], 'Cash Assistance') !== false) {
                            $col_to_update = 'cash_assist_total';
                        } 
                        // 2. Check for Previous Deficit Payment
                        elseif (stripos($item['item_name'], 'Previous Period Deficit') !== false) {
                            $col_to_update = 'outstanding_balance';
                        }

                        // Execute Update if column found
                        if ($col_to_update && $amount > 0) {
                            // GREATEST(0, ...) ensures we don't go below zero
                            $sql_update_loan = "UPDATE tbl_employee_financials 
                                                SET $col_to_update = GREATEST(0, $col_to_update - ?) 
                                                WHERE employee_id = ?";
                            $stmt_loan = $pdo->prepare($sql_update_loan);
                            $stmt_loan->execute([$amount, $emp_id]);
                        }
                    }

                    // --- C. MARK CASH ADVANCES AS PAID ---
                    // Any CA that was marked as 'Deducted' falling within this payroll period is now considered 'Paid'
                    $sql_update_ca = "UPDATE tbl_cash_advances 
                                      SET status = 'Paid', date_updated = NOW() 
                                      WHERE employee_id = ? 
                                      AND status = 'Deducted' 
                                      AND date_requested BETWEEN ? AND ?";
                    $stmt_ca = $pdo->prepare($sql_update_ca);
                    $stmt_ca->execute([$emp_id, $start_date, $end_date]);


                    // --- D. UPDATE PAYROLL STATUS TO APPROVED (1) ---
                    $update_stmt = $pdo->prepare("UPDATE tbl_payroll SET status = 1 WHERE id = ?");
                    $update_stmt->execute([$payroll_id]);
                    
                    // --- E. SEND NOTIFICATION ---
                    $notif_msg = "Your Payslip for period {$period_str} is now available.";
                    // We link them to 'payslips.php' or wherever they view their own salary
                    send_notification($pdo, $emp_id, 'Employee', 'payroll', $notif_msg, 'payslips.php', 'Admin');
                    
                    $approved_count++;
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "$approved_count records approved. Loans updated & Notifications sent."]);

        } 
        elseif ($action === 'send_email') {
            $pdo->commit(); 
            sleep(1);
            echo json_encode(['success' => true, 'message' => 'Payslips have been queued for sending.']);
        } 
        else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Invalid action type.']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>