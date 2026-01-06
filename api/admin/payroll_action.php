<?php
/**
 * api/admin/payroll_action.php
 * Handles Payroll Stats, SSP Fetching, and Batch Approval Logic.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/email_handler.php'; 
require_once __DIR__ . '/../../app/models/notification_model.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['usertype'], [0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';

// =====================================================================
// ACTION 1: FETCH STATS (Top Dashboard Cards)
// =====================================================================
if ($action === 'stats') {
    try {
        // Total Payout for current month
        $sql_payout = "SELECT SUM(net_pay) FROM tbl_payroll WHERE status = 1 
                       AND MONTH(cut_off_end) = MONTH(CURRENT_DATE()) 
                       AND YEAR(cut_off_end) = YEAR(CURRENT_DATE())";
        $total = $pdo->query($sql_payout)->fetchColumn() ?: 0.00;

        // Pending Count
        $sql_pending = "SELECT COUNT(id) FROM tbl_payroll WHERE status = 0";
        $pending = $pdo->query($sql_pending)->fetchColumn() ?: 0;

        echo json_encode([
            'status' => 'success', 
            'total_payout' => number_format((float)$total, 2), 
            'pending_count' => (int)$pending
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =====================================================================
// ACTION 2: FETCH DATATABLES (SSP Mode)
// =====================================================================
if ($action === 'fetch') {
    $columns = [1 => 'employee_name', 2 => 'p.cut_off_end', 3 => 'p.net_pay', 4 => 'p.status'];

    $draw   = (int)($_GET['draw'] ?? 1);
    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    
    $filter_start = $_GET['filter_start_date'] ?? null;
    $filter_end   = $_GET['filter_end_date'] ?? null;

    $sql_base = " FROM tbl_payroll p LEFT JOIN tbl_employees e ON p.employee_id = e.employee_id ";
    $where_conds = [];
    $bindings = [];

    if (!empty($search)) {
        $where_conds[] = "(p.ref_no LIKE :search OR e.firstname LIKE :search OR e.lastname LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    if ($filter_start && $filter_end) {
        $where_conds[] = "p.cut_off_end BETWEEN :fstart AND :fend";
        $bindings[':fstart'] = $filter_start;
        $bindings[':fend'] = $filter_end;
    }

    $where_sql = !empty($where_conds) ? " WHERE " . implode(" AND ", $where_conds) : "";
    $order_sql = " ORDER BY p.id DESC"; // Default

    try {
        $recordsTotal = (int)$pdo->query("SELECT COUNT(*) FROM tbl_payroll")->fetchColumn();
        
        $stmt_filtered = $pdo->prepare("SELECT COUNT(*) $sql_base $where_sql");
        $stmt_filtered->execute($bindings);
        $recordsFiltered = (int)$stmt_filtered->fetchColumn();

        $sql_data = "SELECT p.*, CONCAT_WS(' ', e.firstname, e.lastname) as employee_name, 
                     e.department, e.photo as picture 
                     $sql_base $where_sql $order_sql LIMIT :offset, :limit";

        $stmt = $pdo->prepare($sql_data);
        foreach ($bindings as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            "draw" => $draw, 
            "recordsTotal" => $recordsTotal, 
            "recordsFiltered" => $recordsFiltered, 
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    } catch (Exception $e) {
        echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    }
    exit;
}

// =====================================================================
// ACTION 3: BATCH APPROVAL (Payroll Finalization)
// =====================================================================
if ($action === 'batch_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];
    $sub_action = $_POST['sub_action'] ?? '';

    if (empty($ids) || $sub_action !== 'approve') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid batch request.']);
        exit;
    }

    // Auth Check variables for Audit
    $admin_emp_id = $_SESSION['employee_id']; // Alphanumeric ID
    $admin_type = $_SESSION['usertype'];

    $payroll_ids = array_map('intval', $ids);
    try {
        $pdo->beginTransaction();
        $approved_count = 0;

        // Prepared Statements for Financials and Status
        $stmt_upd_fin = $pdo->prepare("UPDATE tbl_financial_records SET sss_bal = sss_bal - :sss, pagibig_bal = pagibig_bal - :pi, company_bal = company_bal - :co, cash_bal = cash_bal - :ca, savings_bal = savings_bal + :sav WHERE employee_id = :eid");
        $stmt_upd_ca_table = $pdo->prepare("UPDATE tbl_cash_advances SET status = 'Paid', date_updated = NOW() WHERE employee_id = ? AND status = 'Deducted' AND date_requested <= ?");
        $stmt_ledger_ins = $pdo->prepare("INSERT INTO tbl_employee_ledger (employee_id, category, payroll_id, ref_no, transaction_type, amount, running_balance, remarks, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ledger_bal = $pdo->prepare("SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = ? AND category = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");

        foreach ($payroll_ids as $pid) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_payroll WHERE id = ? AND status = 0 FOR UPDATE");
            $stmt->execute([$pid]);
            $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payroll) {
                $eid = $payroll['employee_id']; // Target Alphanumeric ID
                $ref = $payroll['ref_no'];
                $end_date = $payroll['cut_off_end'];
                $net_pay = (float)$payroll['net_pay'];

                // 1. Handle Negative Salary (Deficit)
                if ($net_pay < 0) {
                    $deficit = abs($net_pay);
                    $stmt_ledger_bal->execute([$eid, 'Previous_Deficit']);
                    $new_def_bal = (float)($stmt_ledger_bal->fetchColumn() ?: 0) + $deficit;
                    $stmt_ledger_ins->execute([$eid, 'Previous_Deficit', $pid, $ref, 'Deficit', $deficit, $new_def_bal, "Payroll Deficit from $ref", $end_date]);
                }

                // 2. Process Deductions
                $stmt_items = $pdo->prepare("SELECT item_name, amount FROM tbl_payroll_items WHERE payroll_id = ? AND item_type = 'deduction'");
                $stmt_items->execute([$pid]);
                $deduct_map = ['sss'=>0, 'pi'=>0, 'co'=>0, 'ca'=>0, 'sav'=>0];

                foreach ($stmt_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                    $amt = (float)$item['amount'];
                    if ($amt <= 0) continue;
                    $cat = null; $type = 'Loan_Payment'; $remarks = "Payment via $ref";

                    if (stripos($item['item_name'], 'SSS Loan') !== false) { $cat = 'SSS_Loan'; $deduct_map['sss'] = $amt; }
                    elseif (stripos($item['item_name'], 'Pag-IBIG Loan') !== false) { $cat = 'Pagibig_Loan'; $deduct_map['pi'] = $amt; }
                    elseif (stripos($item['item_name'], 'Company Loan') !== false) { $cat = 'Company_Loan'; $deduct_map['co'] = $amt; }
                    elseif (stripos($item['item_name'], 'Cash Assistance') !== false) { $cat = 'Cash_Assist'; $deduct_map['ca'] = $amt; }
                    elseif (stripos($item['item_name'], 'Savings') !== false) { $cat = 'Savings'; $type = 'Deposit'; $deduct_map['sav'] = $amt; }
                    elseif (stripos($item['item_name'], 'Previous Payroll Deficit') !== false) { $cat = 'Previous_Deficit'; $type = 'Payment'; $remarks = "Deficit cleared via $ref"; }

                    if ($cat) {
                        $stmt_ledger_bal->execute([$eid, $cat]);
                        $old_bal = (float)($stmt_ledger_bal->fetchColumn() ?: 0);
                        $new_bal = ($cat === 'Savings') ? ($old_bal + $amt) : (($cat === 'Previous_Deficit') ? 0 : max(0, $old_bal - $amt));
                        $stmt_ledger_ins->execute([$eid, $cat, $pid, $ref, $type, $amt, $new_bal, $remarks, $end_date]);
                    }
                }

                // 3. Final Database Sync
                $stmt_upd_fin->execute([':sss'=>$deduct_map['sss'],':pi'=>$deduct_map['pi'],':co'=>$deduct_map['co'],':ca'=>$deduct_map['ca'],':sav'=>$deduct_map['sav'],':eid'=>$eid]);
                $stmt_upd_ca_table->execute([$eid, $end_date]);
                $pdo->prepare("UPDATE tbl_payroll SET status = 1 WHERE id = ?")->execute([$pid]);

                // 4. AUDIT LOG & NOTIFICATION
                // Recording "Who" (admin_emp_id) did "What" (Approved) to "Whom" (eid)
                logAudit($pdo, $admin_emp_id, $admin_type, 'PAYROLL_APPROVE', "Finalized payroll $ref for $eid");
                send_notification($pdo, $eid, 2, 'Payroll', "Your payslip for period ending $end_date is now ready for viewing.", 'user/payslips.php', $pid);

                $approved_count++;
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Successfully approved $approved_count payroll(s)."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => "Approval Error: " . $e->getMessage()]);
    }
    exit;
}