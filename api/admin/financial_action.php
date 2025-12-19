<?php
/**
 * api/admin/financial_action.php
 * Handles Employee Financial Overviews, Ledger Histories, and Manual Adjustments.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

// --- 1. AUTHENTICATION & SECURITY CHECK ---
// Ensure only Superadmins (0) or Admins (1) can access this API
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['usertype'], [0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if (!isset($pdo)) {
    echo json_encode([
        'draw' => 1, 
        'recordsTotal' => 0, 
        'recordsFiltered' => 0, 
        'data' => [], 
        'error' => 'Database connection failed.'
    ]);
    exit;
}

$action = $_GET['action'] ?? '';
$draw = (int)($_GET['draw'] ?? 1);

// Category mapping for automated iteration
$categories = [
    'Savings'      => ['bal_key' => 'savings_bal', 'amort_key' => 'savings_contrib'],
    'SSS_Loan'     => ['bal_key' => 'sss_bal',     'amort_key' => 'sss_amort'],
    'Pagibig_Loan' => ['bal_key' => 'pagibig_bal', 'amort_key' => 'pagibig_amort'],
    'Company_Loan' => ['bal_key' => 'company_bal', 'amort_key' => 'company_amort'],
    'Cash_Assist'  => ['bal_key' => 'cash_bal',    'amort_key' => 'cash_amort'],
];

// =================================================================================
// ACTION: FETCH OVERVIEW (DataTables Server-Side)
// =================================================================================
if ($action === 'fetch_overview') {
    try {
        // Count Total Active Employees
        $totalStmt = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5");
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $search = $_GET['search']['value'] ?? '';
        $start  = (int)($_GET['start'] ?? 0);
        $length = (int)($_GET['length'] ?? 10);

        // Base Query using Correlated Subqueries to get the LATEST balance for each category
        $sql = "SELECT e.id, e.employee_id, e.firstname, e.lastname, e.position,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Savings' ORDER BY id DESC LIMIT 1), 0) as savings_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'SSS_Loan' ORDER BY id DESC LIMIT 1), 0) as sss_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Pagibig_Loan' ORDER BY id DESC LIMIT 1), 0) as pagibig_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Company_Loan' ORDER BY id DESC LIMIT 1), 0) as company_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Cash_Assist' ORDER BY id DESC LIMIT 1), 0) as cash_bal
                FROM tbl_employees e
                WHERE e.employment_status < 5 ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }

        // Count Filtered Records
        $countSql = "SELECT COUNT(id) FROM tbl_employees e WHERE e.employment_status < 5" . (!empty($search) ? " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)" : "");
        $filteredStmt = $pdo->prepare($countSql);
        $filteredStmt->execute($params);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        // Ordering & Final Execution
        $sql .= " ORDER BY e.lastname ASC LIMIT $start, $length";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ]);

    } catch (Exception $e) {
        error_log("Financial Overview Error: " . $e->getMessage());
        echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Fetch error."]);
    }
    exit;
}

// =================================================================================
// ACTION: GET FINANCIAL RECORD (For Modal Population)
// =================================================================================
if ($action === 'get_financial_record') {
    $emp_id = trim($_POST['employee_id'] ?? '');

    if (empty($emp_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID is required.']);
        exit;
    }

    try {
        $sql = "SELECT e.employee_id, e.firstname, e.lastname, 
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Savings' ORDER BY id DESC LIMIT 1), 0) as savings_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'SSS_Loan' ORDER BY id DESC LIMIT 1), 0) as sss_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Pagibig_Loan' ORDER BY id DESC LIMIT 1), 0) as pagibig_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Company_Loan' ORDER BY id DESC LIMIT 1), 0) as company_bal,
                COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Cash_Assist' ORDER BY id DESC LIMIT 1), 0) as cash_bal,
                COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Savings' LIMIT 1), 0) as savings_contrib,
                COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'SSS_Loan' LIMIT 1), 0) as sss_amort,
                COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Pagibig_Loan' LIMIT 1), 0) as pagibig_amort,
                COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Company_Loan' LIMIT 1), 0) as company_amort,
                COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Cash_Assist' LIMIT 1), 0) as cash_amort
                FROM tbl_employees e WHERE e.employee_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$emp_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: UPDATE FINANCIAL RECORD (Manual Adjustment)
// =================================================================================
if ($action === 'update_financial_record' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = trim($_POST['employee_id'] ?? '');

    if (empty($emp_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID required.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Fetch current states for balance comparison and detailed logging
        $checkSql = "SELECT category, running_balance FROM tbl_employee_ledger t1 
                     WHERE id = (SELECT MAX(id) FROM tbl_employee_ledger t2 WHERE t1.category = t2.category AND employee_id = ?)";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$emp_id]);
        $current_balances = $checkStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $remarks = trim($_POST['adjustment_remarks'] ?? 'Manual Admin Adjustment');
        $audit_changes = []; // ⭐ Array to track specific changes for the audit log

        foreach ($categories as $cat_name => $keys) {
            $new_bal   = (float)($_POST[$keys['bal_key']]   ?? 0);
            $new_amort = (float)($_POST[$keys['amort_key']] ?? 0);
            $old_bal   = (float)($current_balances[$cat_name] ?? 0);
            
            // Only log to ledger if the balance itself has changed
            if (round($new_bal, 2) !== round($old_bal, 2)) {
                $diff = $new_bal - $old_bal;
                $led = $pdo->prepare("INSERT INTO tbl_employee_ledger (employee_id, category, transaction_type, amount, amortization, running_balance, transaction_date, remarks) VALUES (?, ?, 'Adjustment', ?, ?, ?, NOW(), ?)");
                $led->execute([$emp_id, $cat_name, abs($diff), $new_amort, $new_bal, $remarks]);
                
                // ⭐ Track change for audit trail
                $audit_changes[] = "$cat_name: " . number_format($old_bal, 2) . " -> " . number_format($new_bal, 2);
            }
            
            // Always sync the Payroll Contributions table for amortization settings
            $pay = $pdo->prepare("INSERT INTO tbl_payroll_contributions (employee_id, category, monthly_amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE monthly_amount = VALUES(monthly_amount)");
            $pay->execute([$emp_id, $cat_name, $new_amort]);
        }

        // ⭐ RECORD AUDIT LOG IF CHANGES OCCURRED
        if (!empty($audit_changes)) {
            $change_details = "Manual financial adjustment for Employee ID: $emp_id. Changes: " . implode(", ", $audit_changes) . ". Remarks: $remarks";
            
            // Assuming logAudit(pdo, user_id, usertype, action_type, details)
            logAudit(
                $pdo, 
                $_SESSION['user_id'], 
                $_SESSION['usertype'], 
                'UPDATE_FINANCIAL_LEDGER', 
                $change_details
            );
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Financial profile updated and audited.']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Financial Update Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: FETCH LEDGER HISTORY
// =================================================================================
if ($action === 'fetch_ledger') {
    $emp_id = trim($_GET['employee_id'] ?? '');
    $filter = trim($_GET['category'] ?? 'All');

    if (empty($emp_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing Employee ID.']);
        exit;
    }

    try {
        $sql = "SELECT * FROM tbl_employee_ledger WHERE employee_id = ?";
        $params = [$emp_id];

        if ($filter !== 'All') {
            $sql .= " AND category = ?";
            $params[] = $filter;
        }

        // Ordered by date and then ID to ensure sequence is correct for same-day logs
        $sql .= " ORDER BY transaction_date DESC, id DESC"; 
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}