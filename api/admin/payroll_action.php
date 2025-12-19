<?php
/**
 * api/admin/payroll_action.php
 * Handles Payroll Stats, SSP Fetching, and Batch Approval Logic.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connection.php';
require_once __DIR__ . '/../../app/models/global_app_model.php'; // For Notifications
require_once __DIR__ . '/../../helpers/audit_helper.php';       // For Audit Trail

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

    $payroll_ids = array_map('intval', $ids);
    try {
        $pdo->beginTransaction();
        $approved_count = 0;

        // Prepared Statements for high-performance looping
        $stmt_ledger_bal = $pdo->prepare("SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = ? AND category = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt_ledger_ins = $pdo->prepare("INSERT INTO tbl_employee_ledger (employee_id, category, payroll_id, ref_no, transaction_type, amount, running_balance, remarks, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_deductions = $pdo->prepare("SELECT item_name, amount FROM tbl_payroll_items WHERE payroll_id = ? AND item_type = 'deduction'");
        $stmt_upd_payroll = $pdo->prepare("UPDATE tbl_payroll SET status = 1 WHERE id = ? AND status = 0");

        foreach ($payroll_ids as $pid) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_payroll WHERE id = ? FOR UPDATE");
            $stmt->execute([$pid]);
            $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payroll && (int)$payroll['status'] === 0) {
                $eid = $payroll['employee_id'];
                $net = (float)$payroll['net_pay'];
                $ref = $payroll['ref_no'];
                $end_date = $payroll['cut_off_end'];

                // 1. Handle Negative Pay (Create Deficit)
                if ($net < 0) {
                    $deficit = abs($net);
                    $stmt_ledger_bal->execute([$eid, 'Previous_Deficit']);
                    $cur_bal = (float)($stmt_ledger_bal->fetchColumn() ?: 0);
                    $new_bal = round($cur_bal + $deficit, 2);

                    $stmt_ledger_ins->execute([$eid, 'Previous_Deficit', $pid, $ref, 'Deficit', $deficit, $new_bal, "Payroll Deficit for $ref", $end_date]);
                }

                // 2. Sync Deductions to Ledger (Loans/Savings)
                $stmt_deductions->execute([$pid]);
                $items = $stmt_deductions->fetchAll(PDO::FETCH_ASSOC);

                foreach ($items as $item) {
                    $amt = (float)$item['amount'];
                    if ($amt <= 0) continue;

                    $category = null;
                    $type = 'Payment';
                    $is_savings = false;

                    // Mapping Item Names to Categories
                    if (stripos($item['item_name'], 'SSS Loan') !== false) $category = 'SSS_Loan';
                    elseif (stripos($item['item_name'], 'Pag-IBIG Loan') !== false) $category = 'Pagibig_Loan';
                    elseif (stripos($item['item_name'], 'Company Loan') !== false) $category = 'Company_Loan';
                    elseif (stripos($item['item_name'], 'Savings') !== false) { $category = 'Savings'; $type = 'Deposit'; $is_savings = true; }

                    if ($category) {
                        $stmt_ledger_bal->execute([$eid, $category]);
                        $old_bal = (float)($stmt_ledger_bal->fetchColumn() ?: 0);
                        $new_bal = $is_savings ? round($old_bal + $amt, 2) : round($old_bal - $amt, 2);

                        $stmt_ledger_ins->execute([$eid, $category, $pid, $ref, $type, $amt, max(0, $new_bal), "Payroll Deduction $ref", $end_date]);
                    }
                }

                // 3. Update Status & Notify Employee
                if ($stmt_upd_payroll->execute([$pid])) {
                    $approved_count++;
                    logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'APPROVE_PAYROLL', "Approved payroll ref: $ref for Emp: $eid");
                    
                    $msg = "Your payslip for period " . date("M d", strtotime($payroll['cut_off_start'])) . " - " . date("M d", strtotime($end_date)) . " is now available.";
                    send_notification($pdo, $eid, 2, 'Payroll', $msg, 'payslips.php', $pid);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "$approved_count records finalized and ledgers updated."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}