<?php
/**
 * api/admin/financial_action.php
 * Strictly uses: tbl_employees, tbl_compensation, tbl_financial_records, tbl_employee_ledger
 * REMOVED: AppUtility dependency to prevent Fatal Errors.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

// --- INTERNAL HELPERS (Replacing AppUtility) ---
function toMoney($amount) {
    return number_format((float)($amount ?? 0), 2, '.', '');
}

// Auth Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['usertype'], [0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_GET['action'] ?? '';

// Mapping: Category Name (for Ledger) => Database Columns (in tbl_financial_records)
$financial_map = [
    'Savings'      => ['bal' => 'savings_bal', 'amort' => 'savings_contrib'],
    'SSS_Loan'     => ['bal' => 'sss_bal',     'amort' => 'sss_amort'],
    'Pagibig_Loan' => ['bal' => 'pagibig_bal', 'amort' => 'pagibig_amort'],
    'Company_Loan' => ['bal' => 'company_bal', 'amort' => 'company_amort'],
    'Cash_Assist'  => ['bal' => 'cash_bal',    'amort' => 'cash_amort'],
];

// =================================================================================
// 1. FETCH OVERVIEW
// =================================================================================
if ($action === 'fetch_overview') {
    try {
        $draw   = (int)($_GET['draw'] ?? 1);
        $start  = (int)($_GET['start'] ?? 0);
        $length = (int)($_GET['length'] ?? 10);
        $search = $_GET['search']['value'] ?? '';

        $sql = "SELECT e.employee_id, e.firstname, e.lastname, e.position,
                c.daily_rate, 
                COALESCE(fr.savings_bal, 0) as savings_bal,
                COALESCE(fr.sss_bal, 0) as sss_bal,
                COALESCE(fr.pagibig_bal, 0) as pagibig_bal,
                COALESCE(fr.company_bal, 0) as company_bal,
                COALESCE(fr.cash_bal, 0) as cash_bal
                FROM tbl_employees e
                LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
                LEFT JOIN tbl_financial_records fr ON e.employee_id = fr.employee_id
                WHERE e.employment_status < 5";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_employees e WHERE e.employment_status < 5" . (!empty($search) ? " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)" : ""));
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();

        $sql .= " ORDER BY e.lastname ASC LIMIT $start, $length";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecords,
            "data" => $data
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// 2. GET SINGLE RECORD
// =================================================================================
if ($action === 'get_financial_record') {
    $emp_id = $_POST['employee_id'] ?? '';

    try {
        $sql = "SELECT 
                e.employee_id, e.firstname, e.lastname,
                c.daily_rate, c.monthly_rate, c.food_allowance, c.transpo_allowance,
                COALESCE(fr.savings_bal, 0) as savings_bal,
                COALESCE(fr.savings_contrib, 0) as savings_contrib,
                COALESCE(fr.sss_bal, 0) as sss_bal,
                COALESCE(fr.sss_amort, 0) as sss_amort,
                COALESCE(fr.pagibig_bal, 0) as pagibig_bal,
                COALESCE(fr.pagibig_amort, 0) as pagibig_amort,
                COALESCE(fr.company_bal, 0) as company_bal,
                COALESCE(fr.company_amort, 0) as company_amort,
                COALESCE(fr.cash_bal, 0) as cash_bal,
                COALESCE(fr.cash_amort, 0) as cash_amort
                FROM tbl_employees e
                LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
                LEFT JOIN tbl_financial_records fr ON e.employee_id = fr.employee_id
                WHERE e.employee_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$emp_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
            exit;
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// 3. UPDATE RECORD
// =================================================================================
if ($action === 'update_financial_record') {
    $emp_id = trim($_POST['employee_id'] ?? '');
    $remarks = trim($_POST['adjustment_remarks'] ?? 'Manual Adjustment');

    if (empty($emp_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID missing.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // A. Update Compensation
        $compSql = "INSERT INTO tbl_compensation (employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    daily_rate=VALUES(daily_rate), monthly_rate=VALUES(monthly_rate), 
                    food_allowance=VALUES(food_allowance), transpo_allowance=VALUES(transpo_allowance)";
        $stmt = $pdo->prepare($compSql);
        $stmt->execute([
            $emp_id, 
            toMoney($_POST['daily_rate']), 
            toMoney($_POST['monthly_rate']), 
            toMoney($_POST['food_allowance']), 
            toMoney($_POST['transpo_allowance'])
        ]);

        // B. Update Financial Records
        $oldStmt = $pdo->prepare("SELECT * FROM tbl_financial_records WHERE employee_id = ?");
        $oldStmt->execute([$emp_id]);
        $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $finSql = "INSERT INTO tbl_financial_records (employee_id, savings_bal, savings_contrib, sss_bal, sss_amort, pagibig_bal, pagibig_amort, company_bal, company_amort, cash_bal, cash_amort)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE 
                   savings_bal=VALUES(savings_bal), savings_contrib=VALUES(savings_contrib),
                   sss_bal=VALUES(sss_bal), sss_amort=VALUES(sss_amort),
                   pagibig_bal=VALUES(pagibig_bal), pagibig_amort=VALUES(pagibig_amort),
                   company_bal=VALUES(company_bal), company_amort=VALUES(company_amort),
                   cash_bal=VALUES(cash_bal), cash_amort=VALUES(cash_amort)";
        
        $finParams = [$emp_id];
        
        foreach ($financial_map as $cat => $keys) {
            $new_bal   = toMoney($_POST['adjust_' . $keys['bal']] ?? 0);
            $new_amort = toMoney($_POST['adjust_' . $keys['amort']] ?? 0);
            
            $finParams[] = $new_bal;
            $finParams[] = $new_amort;

            $old_bal = (float)($oldData[$keys['bal']] ?? 0);
            if (abs($new_bal - $old_bal) > 0.01) {
                $diff = $new_bal - $old_bal;
                $transType = ($diff > 0) ? 'Loan_Grant' : 'Loan_Payment'; 
                if($cat == 'Savings') $transType = ($diff > 0) ? 'Deposit' : 'Withdrawal';

                $ledSql = "INSERT INTO tbl_employee_ledger (employee_id, category, transaction_type, amount, amortization, running_balance, transaction_date, remarks)
                           VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
                $ledStmt = $pdo->prepare($ledSql);
                $ledStmt->execute([$emp_id, $cat, $transType, abs($diff), $new_amort, $new_bal, $remarks]);
            }
        }

        $stmt = $pdo->prepare($finSql);
        $stmt->execute($finParams);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Record updated successfully.']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// 4. FETCH LEDGER HISTORY
// =================================================================================
if ($action === 'fetch_ledger') {
    $emp_id = $_GET['employee_id'] ?? '';
    $cat    = $_GET['category'] ?? 'All';

    $sql = "SELECT * FROM tbl_employee_ledger WHERE employee_id = ?";
    $params = [$emp_id];

    if ($cat !== 'All') {
        $sql .= " AND category = ?";
        $params[] = $cat;
    }

    $sql .= " ORDER BY transaction_date DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}
?>