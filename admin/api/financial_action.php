<?php
// api/financial_action.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../db_connection.php'; // Adjust path if necessary

if (!isset($pdo)) {
    // Standardized fatal error response for the SSP endpoint
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

// --- CATEGORY MAPPING for easy iteration/lookup ---
$categories = [
    'Savings' => ['bal_key' => 'savings_bal', 'amort_key' => 'savings_contrib'],
    'SSS_Loan' => ['bal_key' => 'sss_bal', 'amort_key' => 'sss_amort'],
    'Pagibig_Loan' => ['bal_key' => 'pagibig_bal', 'amort_key' => 'pagibig_amort'],
    'Company_Loan' => ['bal_key' => 'company_bal', 'amort_key' => 'company_amort'],
    'Cash_Assist' => ['bal_key' => 'cash_bal', 'amort_key' => 'cash_amort'],
];


// =================================================================================
// ACTION: FETCH OVERVIEW (For Main DataTables) - UNCHANGED
// =================================================================================
if ($action === 'fetch_overview') {
    try {
        // --- 1a. Count Total Employees (Active: Status < 5) ---
        $totalStmt = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5");
        $recordsTotal = (int)$totalStmt->fetchColumn();

        // --- 1b. Build Query ---
        $search = $_GET['search']['value'] ?? '';
        
        // Note: The subqueries rely on the latest running_balance in tbl_employee_ledger for each category.
        $sql = "SELECT 
                    e.id, 
                    e.employee_id, 
                    e.firstname, 
                    e.lastname, 
                    e.position,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Savings' ORDER BY id DESC LIMIT 1), 0) as savings_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'SSS_Loan' ORDER BY id DESC LIMIT 1), 0) as sss_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Pagibig_Loan' ORDER BY id DESC LIMIT 1), 0) as pagibig_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Company_Loan' ORDER BY id DESC LIMIT 1), 0) as company_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Cash_Assist' ORDER BY id DESC LIMIT 1), 0) as cash_bal
                FROM tbl_employees e
                WHERE e.employment_status < 5 ";

        $params = [];

        // --- 1c. Search Logic ---
        if (!empty($search)) {
            $sql .= " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Count Filtered Records
        $countSql = "SELECT COUNT(id) FROM tbl_employees e WHERE e.employment_status < 5";
        
        if (!empty($search)) {
            $countSql .= " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)";
        }
        $filteredStmt = $pdo->prepare($countSql);
        $filteredStmt->execute($params);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        // --- 1d. Ordering & Pagination ---
        $sql .= " ORDER BY e.lastname ASC"; 
        
        $start = $_GET['start'] ?? 0;
        $length = $_GET['length'] ?? 10;
        $sql .= " LIMIT :start, :length";

        // --- 1e. Execute ---
        $stmt = $pdo->prepare($sql);
        // Bind search parameters first
        foreach ($params as $key => $val) {
            $stmt->bindValue(($key + 1), $val);
        }
        // Bind LIMIT clauses
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Final SSP Response
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => (int)$recordsTotal,
            "recordsFiltered" => (int)$recordsFiltered,
            "data" => $data
        ]);

    } catch (Exception $e) {
        // Return structured error response if fetching fails
        error_log("Financial Overview Error: " . $e->getMessage());
        echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Failed to fetch data."]);
    }
    exit;
}

// =================================================================================
// ACTION: GET FINANCIAL RECORD (For Edit/Adjust Modal)
// =================================================================================
if ($action === 'get_financial_record') {
    $emp_id = trim($_POST['employee_id'] ?? '');

    if (empty($emp_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID missing.']);
        exit;
    }

    try {
        // Fetch Employee Name and Current Balances (similar to overview, but for one employee)
        $sql = "SELECT 
                    e.employee_id, 
                    e.firstname, 
                    e.lastname, 
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Savings' ORDER BY id DESC LIMIT 1), 0) as savings_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'SSS_Loan' ORDER BY id DESC LIMIT 1), 0) as sss_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Pagibig_Loan' ORDER BY id DESC LIMIT 1), 0) as pagibig_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Company_Loan' ORDER BY id DESC LIMIT 1), 0) as company_bal,
                    COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = e.employee_id AND category = 'Cash_Assist' ORDER BY id DESC LIMIT 1), 0) as cash_bal,
                    
                    -- Fetch current monthly contribution/amortization values
                    COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Savings' LIMIT 1), 0) as savings_contrib,
                    COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'SSS_Loan' LIMIT 1), 0) as sss_amort,
                    COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Pagibig_Loan' LIMIT 1), 0) as pagibig_amort,
                    COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Company_Loan' LIMIT 1), 0) as company_amort,
                    COALESCE((SELECT monthly_amount FROM tbl_payroll_contributions WHERE employee_id = e.employee_id AND category = 'Cash_Assist' LIMIT 1), 0) as cash_amort

                FROM tbl_employees e
                WHERE e.employee_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$emp_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found.', 'data' => []]);
        }
        
    } catch (Exception $e) {
        // CRITICAL: Log and return the error details for debugging the AJAX call
        error_log("get_financial_record Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'DB Error fetching record: ' . $e->getMessage(), 'data' => []]);
    }
    exit;
}

// =================================================================================
// ACTION: UPDATE FINANCIAL RECORD (For Adjustment Form Submission)
// =================================================================================
if ($action === 'update_financial_record' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CRITICAL FIX: The HTML form input name is 'employee_id'
    $emp_id = trim($_POST['employee_id'] ?? '');

    if (empty($emp_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID is required for update.']);
        exit;
    }

    // 1. Fetch old balances to calculate adjustment amounts
    try {
        $old_balances_sql = "SELECT 
            COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = :empid AND category = 'Savings' ORDER BY id DESC LIMIT 1), 0) as Savings,
            COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = :empid AND category = 'SSS_Loan' ORDER BY id DESC LIMIT 1), 0) as SSS_Loan,
            COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = :empid AND category = 'Pagibig_Loan' ORDER BY id DESC LIMIT 1), 0) as Pagibig_Loan,
            COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = :empid AND category = 'Company_Loan' ORDER BY id DESC LIMIT 1), 0) as Company_Loan,
            COALESCE((SELECT running_balance FROM tbl_employee_ledger WHERE employee_id = :empid AND category = 'Cash_Assist' ORDER BY id DESC LIMIT 1), 0) as Cash_Assist";
            
        $stmt = $pdo->prepare($old_balances_sql);
        $stmt->execute([':empid' => $emp_id]);
        $old_balances = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Financial Update Pre-check Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'DB Error during pre-check for update.']);
        exit;
    }

    $adjustment_remarks = trim($_POST['adjustment_remarks'] ?? 'Manual balance/amortization adjustment via admin panel.');
    $insertLedgerSql = "INSERT INTO tbl_employee_ledger (employee_id, category, transaction_type, amount, amortization, running_balance, transaction_date, remarks) VALUES (?, ?, 'Adjustment', ?, ?, ?, NOW(), ?)";
    $updatePayrollSql = "INSERT INTO tbl_payroll_contributions (employee_id, category, monthly_amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE monthly_amount = VALUES(monthly_amount)";

    try {
        $pdo->beginTransaction();
        $ledStmt = $pdo->prepare($insertLedgerSql);
        $payStmt = $pdo->prepare($updatePayrollSql);
        
        $updates_made = 0;

        foreach ($categories as $category_name => $keys) {
            $new_balance = (float)($_POST[$keys['bal_key']] ?? 0);
            $new_amort = (float)($_POST[$keys['amort_key']] ?? 0);
            $old_balance = (float)($old_balances[$category_name] ?? 0);
            
            // Calculate adjustment amount (difference in balance)
            $balance_difference = round($new_balance - $old_balance, 2);
            
            // 2. Insert Adjustment into Ledger only if the balance changed
            if ($balance_difference !== 0.00) {
                // The balance difference is the adjustment amount recorded in the ledger
                $ledStmt->execute([
                    $emp_id, 
                    $category_name, 
                    abs($balance_difference), // Amount column logs the magnitude of change
                    $new_amort,             // Record the new amortization with the adjustment
                    $new_balance,           // Record the new running balance
                    $adjustment_remarks     
                ]);
                $updates_made++;
            }
            
            // 3. Update Payroll Contributions (Amortization/Contribution)
            // Always update contributions regardless of balance change
            $payStmt->execute([$emp_id, $category_name, $new_amort]);
            $updates_made++;
        }

        if ($updates_made > 0) {
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Financial record successfully updated.']);
        } else {
             // If no balance difference was found, we still commit the amortization update if it ran
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'No balance change detected, but amortization amounts have been saved.']);
        }
        

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Financial Update Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'DB Transaction Error during update: ' . $e->getMessage()]);
    }
    exit;
}
// =================================================================================
// ACTION: FETCH LEDGER HISTORY (For History Modal) - UNCHANGED
// =================================================================================
if ($action === 'fetch_ledger') {
    $emp_id = trim($_GET['employee_id'] ?? '');
    $cat_filter = trim($_GET['category'] ?? 'All');

    if (empty($emp_id)) {
        echo json_encode(['data' => [], 'message' => 'Employee ID missing.']);
        exit;
    }

    try {
        $sql = "SELECT * FROM tbl_employee_ledger WHERE employee_id = ?";
        $params = [$emp_id];

        if ($cat_filter !== 'All' && !empty($cat_filter)) {
            $sql .= " AND category = ?";
            $params[] = $cat_filter;
        }

        // Always order by transaction date and then by ID (for sequential balance)
        $sql .= " ORDER BY transaction_date DESC, id DESC"; 

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        error_log("Ledger Fetch Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error fetching ledger: ' . $e->getMessage(), 'data' => []]);
    }
    exit;
}
?>