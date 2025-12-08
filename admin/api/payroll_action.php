<?php
// api/payroll_action.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../db_connection.php';
require_once __DIR__ . '/../models/global_model.php'; // Required for notifications

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';

// =====================================================================
// 1. FETCH STATS (Top Cards)
// =====================================================================
if ($action === 'stats') {
    try {
        $sql_payout = "SELECT SUM(net_pay) FROM tbl_payroll WHERE status = 1 
                        AND MONTH(cut_off_end) = MONTH(CURRENT_DATE()) 
                        AND YEAR(cut_off_end) = YEAR(CURRENT_DATE())";
        $total = $pdo->query($sql_payout)->fetchColumn() ?: 0;

        $sql_pending = "SELECT COUNT(id) FROM tbl_payroll WHERE status = 0";
        $pending = $pdo->query($sql_pending)->fetchColumn() ?: 0;

        echo json_encode(['status' => 'success', 'total_payout' => number_format($total, 2), 'pending_count' => number_format($pending)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =====================================================================
// 2. FETCH DATATABLES (SSP)
// =====================================================================
if ($action === 'fetch') {
    $columns = [
        1 => 'employee_name', 
        2 => 'p.cut_off_end',
        3 => 'p.net_pay',
        4 => 'p.status'
    ];

    $draw   = $_GET['draw'] ?? 1;
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = $_GET['search']['value'] ?? '';
    
    $filter_start = $_GET['filter_start_date'] ?? null;
    $filter_end   = $_GET['filter_end_date'] ?? null;

    $sql_base = " FROM tbl_payroll p LEFT JOIN tbl_employees e ON p.employee_id = e.employee_id ";
    
    $where_conds = [];
    $params = [];

    if (!empty($search)) {
        $term = "%$search%";
        $where_conds[] = "(p.ref_no LIKE ? OR CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) LIKE ?)";
        $params[] = $term; $params[] = $term;
    }

    if (!empty($filter_start) && !empty($filter_end)) {
        $where_conds[] = "p.cut_off_end BETWEEN ? AND ?";
        $params[] = $filter_start; $params[] = $filter_end;
    }

    $where_sql = !empty($where_conds) ? " WHERE " . implode(" AND ", $where_conds) : "";

    $order_sql = " ORDER BY p.id DESC"; 
    if (isset($_GET['order'])) {
        $colIdx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        if (isset($columns[$colIdx])) {
            $colName = $columns[$colIdx] === 'employee_name' ? "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)" : $columns[$colIdx];
            $order_sql = " ORDER BY $colName $dir";
        }
    }

    $recordsTotal = $pdo->query("SELECT COUNT(*) $sql_base")->fetchColumn();
    
    $stmt_filtered = $pdo->prepare("SELECT COUNT(*) $sql_base $where_sql");
    $stmt_filtered->execute($params);
    $recordsFiltered = $stmt_filtered->fetchColumn();

    $sql_data = "SELECT p.id, p.employee_id, p.ref_no, 
                  CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) as employee_name, 
                  e.department, p.cut_off_start, p.cut_off_end, 
                  p.net_pay, p.status, e.photo as picture 
                  $sql_base $where_sql $order_sql LIMIT $start, $length";

    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["draw" => intval($draw), "recordsTotal" => intval($recordsTotal), "recordsFiltered" => intval($recordsFiltered), "data" => $data]);
    exit;
}

// =====================================================================
// 3. BATCH ACTIONS (Approve / Email) - UPDATED FOR LEDGER
// =====================================================================
if ($action === 'batch_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];
    $sub_action = $_POST['sub_action'] ?? '';

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No records selected.']);
        exit;
    }

    try {
        // --- PREPARE LEDGER STATEMENTS ---
        // Get the last known running balance for a category
        $stmt_get_last_bal = $pdo->prepare("
            SELECT running_balance 
            FROM tbl_employee_ledger 
            WHERE employee_id = ? AND category = ? 
            ORDER BY transaction_date DESC, created_at DESC 
            LIMIT 1
        ");
        
        // Insert a new transaction into the unified ledger
        // ⭐️ FIX: Added 'amortization' column and placeholder (?)
        $stmt_ledger_insert = $pdo->prepare("
            INSERT INTO tbl_employee_ledger 
                (employee_id, category, payroll_id, ref_no, transaction_type, amount, amortization, running_balance, remarks, transaction_date) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // -----------------------------------

        if ($sub_action === 'approve') {
            $pdo->beginTransaction();
            $approved_count = 0;

            foreach ($ids as $payroll_id) {
                // Fetch Details
                $stmt = $pdo->prepare("SELECT id, ref_no, employee_id, status, net_pay, cut_off_start, cut_off_end FROM tbl_payroll WHERE id = ?");
                $stmt->execute([$payroll_id]);
                $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

                // Only process if currently PENDING (Status 0)
                if ($payroll && $payroll['status'] == 0) {
                    $emp_id = $payroll['employee_id'];
                    $net_pay = floatval($payroll['net_pay']);
                    $start_date = $payroll['cut_off_start'];
                    $end_date = $payroll['cut_off_end'];
                    $ref_no = $payroll['ref_no'];

                    // A. Handle Deficit (Negative Net Pay) - NEW LEDGER ENTRY
                    if ($net_pay < 0) {
                        $deficit = abs($net_pay);
                        
                        // NOTE: Assuming 'Previous_Deficit' is an allowed category in tbl_employee_ledger
                        $stmt_get_last_bal->execute([$emp_id, 'Previous_Deficit']);
                        $last_deficit_bal = floatval($stmt_get_last_bal->fetchColumn() ?: 0);
                        
                        $new_deficit_bal = $last_deficit_bal + $deficit; // Deficits increase the balance (less net)
                        
                        $stmt_ledger_insert->execute([
                            $emp_id, 
                            'Previous_Deficit', 
                            $payroll_id, 
                            $ref_no, 
                            'Adjustment', 
                            $deficit, 
                            0.00, // Amortization is 0 for deficit carry over
                            $new_deficit_bal, 
                            'Payroll Deficit Carry Over', 
                            $end_date
                        ]);
                    }

                    // B. Process Items (Loans, Cash Assistance, Savings) - UNIFIED LEDGER INSERT
                    $stmt_items = $pdo->prepare("SELECT item_name, amount FROM tbl_payroll_items WHERE payroll_id = ? AND item_type = 'deduction'");
                    $stmt_items->execute([$payroll_id]);
                    $deductions = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($deductions as $item) {
                        $amount = floatval($item['amount']);
                        if ($amount <= 0) continue;

                        $category = null;
                        $trans_type = null;
                        $remarks = 'Payroll Deduction';
                        $is_savings = false;

                        // --- Map Item Name to Ledger Category ---
                        if (stripos($item['item_name'], 'SSS Loan') !== false) {
                            $category = 'SSS_Loan';
                            $trans_type = 'Loan_Payment';
                        } elseif (stripos($item['item_name'], 'Pag-IBIG Loan') !== false) {
                            $category = 'Pagibig_Loan';
                            $trans_type = 'Loan_Payment';
                        } elseif (stripos($item['item_name'], 'Company Loan') !== false) {
                            $category = 'Company_Loan';
                            $trans_type = 'Loan_Payment';
                        } elseif (stripos($item['item_name'], 'Cash Assistance') !== false) {
                            $category = 'Cash_Assist';
                            $trans_type = 'Loan_Payment';
                        } elseif (stripos($item['item_name'], 'Company Savings') !== false) {
                            $category = 'Savings';
                            $trans_type = 'Deposit'; // Savings is a deposit into the savings account
                            $is_savings = true;
                        } elseif (stripos($item['item_name'], 'Previous Period Deficit') !== false) {
                            // This deduction pays off the carried deficit
                            $category = 'Previous_Deficit'; 
                            $trans_type = 'Loan_Payment';
                        } 
                        // Note: Other deductions (Tax, PhilHealth, etc.) are NOT tracked in tbl_employee_ledger.

                        if ($category) {
                            // 1. Get the latest running balance for this category
                            $stmt_get_last_bal->execute([$emp_id, $category]);
                            $last_running_bal = floatval($stmt_get_last_bal->fetchColumn() ?: 0);

                            // 2. Calculate the new running balance and check for overpayment cap
                            if ($is_savings) {
                                $new_running_bal = $last_running_bal + $amount;
                            } else {
                                $new_running_bal = $last_running_bal - $amount;
                                
                                if ($new_running_bal < 0) {
                                    // Recalculate actual amount paid if capping is required
                                    $amount = $last_running_bal; 
                                    $new_running_bal = 0.00;
                                    $remarks = 'Loan Fully Paid (Cap Applied)';
                                }
                            }
                            
                            // 3. Insert the new transaction into tbl_employee_ledger
                            // ⭐️ FIX: Use the actual $end_date variable for the transaction date.
                            $stmt_ledger_insert->execute([
                                $emp_id, 
                                $category, 
                                $payroll_id, 
                                $ref_no, 
                                $trans_type, 
                                $amount, 
                                0.00, // Amortization 
                                $new_running_bal, 
                                $remarks, 
                                $end_date // Transaction Date 
                            ]);
                        }
                    }

                    // C. Mark Cash Advances as Paid
                    // We look for Pending advances within the date range
                    $pdo->prepare("UPDATE tbl_cash_advances SET status = 'Paid', date_updated = NOW() 
                                     WHERE employee_id = ? AND status = 'Pending' AND date_requested BETWEEN ? AND ?")
                        ->execute([$emp_id, $start_date, $end_date]);

                    // D. Update Payroll Status to PAID (1)
                    $pdo->prepare("UPDATE tbl_payroll SET status = 1 WHERE id = ?")->execute([$payroll_id]);

                    // E. Send Notification
                    $period_str = date("M d", strtotime($start_date)) . " - " . date("M d", strtotime($end_date));
                    send_notification($pdo, $emp_id, 'Employee', 'payroll', "Payslip for {$period_str} is available.", 'payslips.php', null);
                    
                    $approved_count++;
                }
            }
            $pdo->commit();
            $msg = "$approved_count records approved successfully.";
        } 
        elseif ($sub_action === 'send_email') {
            // Email logic here...
            $msg = "Payslips queued for sending.";
        } 
        else {
            throw new Exception("Invalid batch action.");
        }

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>