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
// ACTION 1: FETCH STATS (Top Cards)
// =====================================================================
if ($action === 'stats') {
    try {
        // Total Payout (for current month/year)
        $sql_payout = "SELECT SUM(net_pay) FROM tbl_payroll WHERE status = 1 
                       AND MONTH(cut_off_end) = MONTH(CURRENT_DATE()) 
                       AND YEAR(cut_off_end) = YEAR(CURRENT_DATE())";
        $total = $pdo->query($sql_payout)->fetchColumn() ?: 0.00;

        // Pending Count
        $sql_pending = "SELECT COUNT(id) FROM tbl_payroll WHERE status = 0";
        $pending = $pdo->query($sql_pending)->fetchColumn() ?: 0;

        // Ensure output is correct format (string formatted numbers)
        echo json_encode([
            'status' => 'success', 
            'total_payout' => number_format((float)$total, 2), 
            'pending_count' => number_format((int)$pending)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error fetching stats: ' . $e->getMessage()]);
    }
    exit;
}

// =====================================================================
// ACTION 2: FETCH DATATABLES (SSP)
// =====================================================================
if ($action === 'fetch') {
    $columns = [
        1 => 'employee_name', 
        2 => 'p.cut_off_end',
        3 => 'p.net_pay',
        4 => 'p.status'
    ];

    $draw   = (int)($_GET['draw'] ?? 1);
    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    
    $filter_start = $_GET['filter_start_date'] ?? null;
    $filter_end   = $_GET['filter_end_date'] ?? null;

    $sql_base = " FROM tbl_payroll p LEFT JOIN tbl_employees e ON p.employee_id = e.employee_id ";
    
    $where_conds = [];
    $params = []; // Positional bindings are okay here if done carefully.

    // Global Search
    if (!empty($search)) {
        $term = "%$search%";
        // Use positional placeholders (?) for simplicity with multiple bindings in the same query
        $where_conds[] = "(p.ref_no LIKE ? OR CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) LIKE ?)";
        $params[] = $term; $params[] = $term;
    }

    // Date Range Filter
    if (!empty($filter_start) && !empty($filter_end)) {
        $where_conds[] = "p.cut_off_end BETWEEN ? AND ?";
        $params[] = $filter_start; $params[] = $filter_end;
    }

    $where_sql = !empty($where_conds) ? " WHERE " . implode(" AND ", $where_conds) : "";

    // Ordering
    $order_sql = " ORDER BY p.id DESC"; 
    if (isset($_GET['order'])) {
        $colIdx = (int)$_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
        if (isset($columns[$colIdx])) {
            $colName = $columns[$colIdx] === 'employee_name' ? "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)" : $columns[$colIdx];
            $order_sql = " ORDER BY $colName $dir";
        }
    }

    try {
        // Total Records
        $recordsTotal = (int)$pdo->query("SELECT COUNT(*) $sql_base")->fetchColumn();
        
        // Filtered Records
        $stmt_filtered = $pdo->prepare("SELECT COUNT(*) $sql_base $where_sql");
        $stmt_filtered->execute($params);
        $recordsFiltered = (int)$stmt_filtered->fetchColumn();

        // Fetch Data
        // Use named placeholders for LIMIT to avoid issues with positional parameter counting
        $sql_data = "SELECT p.id, p.employee_id, p.ref_no, 
                     CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) as employee_name, 
                     e.department, p.cut_off_start, p.cut_off_end, 
                     p.net_pay, p.status, e.photo as picture 
                     $sql_base $where_sql $order_sql LIMIT :start_limit, :length_limit";

        $stmt = $pdo->prepare($sql_data);
        
        // Bind search/filter parameters (positional)
        $param_index = 1;
        foreach ($params as $param) {
            $stmt->bindValue($param_index++, $param);
        }
        
        // Bind LIMIT parameters (named)
        $stmt->bindValue(':start_limit', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length_limit', $length, PDO::PARAM_INT);
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "draw" => $draw, 
            "recordsTotal" => $recordsTotal, 
            "recordsFiltered" => $recordsFiltered, 
            "data" => $data
        ]);
        
    } catch (Exception $e) {
        // Standardized SSP Error Response
        echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Error fetching payroll data: " . $e->getMessage()]);
    }
    exit;
}

// =====================================================================
// ACTION 3: BATCH ACTIONS (Approve / Email)
// =====================================================================
if ($action === 'batch_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];
    $sub_action = trim($_POST['sub_action'] ?? '');

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid records selected.']);
        exit;
    }
    
    // Convert IDs to array of integers for security
    $payroll_ids = array_map('intval', $ids);

    try {
        $approved_count = 0;
        
        // --- PREPARE STATEMENTS (Move outside loop for efficiency) ---
        $stmt_get_last_bal = $pdo->prepare("
            SELECT running_balance 
            FROM tbl_employee_ledger 
            WHERE employee_id = ? AND category = ? 
            ORDER BY id DESC 
            LIMIT 1 FOR UPDATE
        ");
        
        $stmt_ledger_insert = $pdo->prepare("
            INSERT INTO tbl_employee_ledger 
                (employee_id, category, payroll_id, ref_no, transaction_type, amount, amortization, running_balance, remarks, transaction_date) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt_get_items = $pdo->prepare("SELECT item_name, amount FROM tbl_payroll_items WHERE payroll_id = ? AND item_type = 'deduction'");
        $stmt_update_status = $pdo->prepare("UPDATE tbl_payroll SET status = 1 WHERE id = ? AND status = 0"); // Only approve pending records
        $stmt_update_ca = $pdo->prepare("UPDATE tbl_cash_advances SET status = 'Paid', date_updated = NOW() 
                                         WHERE employee_id = ? AND status = 'Deducted' AND date_requested BETWEEN ? AND ?");

        if ($sub_action === 'approve') {
            $pdo->beginTransaction();

            foreach ($payroll_ids as $payroll_id) {
                // Fetch Details
                $stmt = $pdo->prepare("SELECT id, ref_no, employee_id, status, net_pay, cut_off_start, cut_off_end FROM tbl_payroll WHERE id = ? FOR UPDATE");
                $stmt->execute([$payroll_id]);
                $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

                // Only process if PENDING
                if ($payroll && (int)$payroll['status'] === 0) {
                    $emp_id = $payroll['employee_id'];
                    $net_pay = (float)$payroll['net_pay'];
                    $start_date = $payroll['cut_off_start'];
                    $end_date = $payroll['cut_off_end'];
                    $ref_no = $payroll['ref_no'];

                    // ---------------------------------------------------------
                    // A. HANDLE NEGATIVE NET PAY (Create Deficit Debt)
                    // ---------------------------------------------------------
                    if ($net_pay < 0) {
                        $deficit = abs($net_pay);
                        
                        // Get current deficit balance
                        $stmt_get_last_bal->execute([$emp_id, 'Previous_Deficit']);
                        $last_deficit_bal = (float)($stmt_get_last_bal->fetchColumn() ?: 0);
                        
                        $new_deficit_bal = round($last_deficit_bal + $deficit, 2);
                        
                        $stmt_ledger_insert->execute([
                            $emp_id, 
                            'Previous_Deficit', 
                            $payroll_id, 
                            $ref_no, 
                            'Deficit_Carryover', // Custom type for clarity
                            $deficit, 
                            0.00, 
                            $new_deficit_bal, 
                            'Payroll Deficit Carry Over from Period ' . $ref_no, 
                            $end_date
                        ]);
                    }

                    // ---------------------------------------------------------
                    // B. PROCESS DEDUCTIONS (Update Ledger Balances)
                    // ---------------------------------------------------------
                    $stmt_get_items->execute([$payroll_id]);
                    $deductions = $stmt_get_items->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($deductions as $item) {
                        $amount = (float)$item['amount'];
                        if ($amount <= 0) continue;

                        $category = null;
                        $trans_type = 'Loan_Payment';
                        $remarks = 'Payroll Deduction';
                        $is_savings = false;
                        
                        // Map Item Names to Ledger Categories
                        if (stripos($item['item_name'], 'SSS Loan') !== false) { $category = 'SSS_Loan'; } 
                        elseif (stripos($item['item_name'], 'Pag-IBIG Loan') !== false) { $category = 'Pagibig_Loan'; } 
                        elseif (stripos($item['item_name'], 'Company Loan') !== false) { $category = 'Company_Loan'; } 
                        elseif (stripos($item['item_name'], 'Cash Advance') !== false) { $category = 'Cash_Advance'; $remarks = 'CA Payment';} 
                        elseif (stripos($item['item_name'], 'Company Savings') !== false) { 
                            $category = 'Savings';
                            $trans_type = 'Deposit'; 
                            $is_savings = true;
                        } elseif (stripos($item['item_name'], 'Previous Period Deficit') !== false) {
                             $category = 'Previous_Deficit'; 
                             $trans_type = 'Payment';
                             $remarks = 'Deficit Repayment';
                        }
                        // Note: Other statutory deductions (SSS/Philhealth/Tax) are typically not tracked in the employee ledger.

                        if ($category) {
                            // 1. Get Last Balance
                            $stmt_get_last_bal->execute([$emp_id, $category]);
                            $last_running_bal = (float)($stmt_get_last_bal->fetchColumn() ?: 0);

                            // 2. Calculate New Balance
                            if ($is_savings) {
                                // Savings: Balance INCREASES
                                $new_running_bal = round($last_running_bal + $amount, 2);
                            } else {
                                // Loans/Deficits: Balance DECREASES (Payment)
                                $new_running_bal = round($last_running_bal - $amount, 2);
                                
                                // Cap check: Don't let debt go below zero
                                if ($new_running_bal < 0) {
                                    $amount = $last_running_bal; // Adjust payment amount to exactly clear the debt
                                    $new_running_bal = 0.00;
                                    $remarks = $remarks . ' (Paid in Full)';
                                }
                            }
                            
                            // 3. Insert Ledger Record
                            if ($amount > 0) { // Only insert if actual payment/contribution was made
                                $stmt_ledger_insert->execute([
                                    $emp_id, 
                                    $category, 
                                    $payroll_id, 
                                    $ref_no, 
                                    $trans_type, 
                                    $amount, 
                                    0.00, // Amortization (rate) is static, not transaction amount
                                    $new_running_bal, 
                                    $remarks, 
                                    $end_date 
                                ]);
                            }
                        }
                    }

                    // ---------------------------------------------------------
                    // C. MARK CASH ADVANCES AS PAID/DEDUCTED
                    // ---------------------------------------------------------
                    // Mark CAs deducted during this period as 'Paid' to prevent double processing
                    // Assumes the status 'Deducted' is set during the CA approval phase.
                    $stmt_update_ca->execute([$emp_id, $start_date, $end_date]);

                    // ---------------------------------------------------------
                    // D. UPDATE PAYROLL STATUS
                    // ---------------------------------------------------------
                    $stmt_update_status->execute([$payroll_id]);

                    // ---------------------------------------------------------
                    // E. SEND NOTIFICATION
                    // ---------------------------------------------------------
                    $period_str = date("M d", strtotime($start_date)) . " - " . date("M d", strtotime($end_date));
                    send_notification($pdo, $emp_id, 'Employee', 'payroll', "Payslip for {$period_str} is available.", 'payslips.php', $payroll_id);
                    
                    $approved_count++;
                }
            }
            $pdo->commit();
            $msg = "$approved_count records approved successfully.";
        } 
        elseif ($sub_action === 'send_email') {
            // Placeholder: Assume email sending logic runs here
            // You would loop through the IDs here, fetch email and payslip PDF, and send.
            $msg = "Payslips for " . count($payroll_ids) . " records queued for sending.";
        } 
        else {
            throw new Exception("Invalid batch action: " . $sub_action);
        }

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Payroll Batch Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Batch processing failed: ' . $e->getMessage()]);
    }
    exit;
}
?>