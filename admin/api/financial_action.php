<?php
// api/financial_action.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../db_connection.php'; // Adjust path if necessary

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';
$draw = (int)($_GET['draw'] ?? 1);

// --- 1. FETCH OVERVIEW (For Main DataTables) ---
if ($action === 'fetch_overview') {
    try {
        // --- 1a. Count Total Employees ---
        $totalStmt = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status != 5 AND employment_status != 6");
        $recordsTotal = $totalStmt->fetchColumn();

        // --- 1b. Build Query ---
        $search = $_GET['search']['value'] ?? '';
        
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
                WHERE e.employment_status != 5 AND e.employment_status != 6 ";

        $params = [];

        // --- 1c. Search Logic ---
        if (!empty($search)) {
            $sql .= " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Count Filtered Records
        $countSql = "SELECT COUNT(id) FROM tbl_employees e WHERE e.employment_status != 5 AND e.employment_status != 6";
        if (!empty($search)) {
            $countSql .= " AND (e.lastname LIKE ? OR e.firstname LIKE ? OR e.employee_id LIKE ?)";
        }
        $filteredStmt = $pdo->prepare($countSql);
        $filteredStmt->execute($params);
        $recordsFiltered = $filteredStmt->fetchColumn();

        // --- 1d. Ordering & Pagination ---
        $sql .= " ORDER BY e.lastname ASC"; 
        
        $start = $_GET['start'] ?? 0;
        $length = $_GET['length'] ?? 10;
        $sql .= " LIMIT :start, :length";

        // --- 1e. Execute ---
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(($key + 1), $val);
        }
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => (int)$recordsTotal,
            "recordsFiltered" => (int)$recordsFiltered,
            "data" => $data
        ]);

    } catch (Exception $e) {
        error_log("Financial Overview Error: " . $e->getMessage());
        echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    }
    exit;
}

// --- 2. FETCH LEDGER HISTORY (For History Modal) ---
if ($action === 'fetch_ledger') {
    $emp_id = $_GET['employee_id'] ?? '';
    $cat_filter = $_GET['category'] ?? 'All';

    if (empty($emp_id)) {
        echo json_encode(['data' => []]);
        exit;
    }

    try {
        $sql = "SELECT * FROM tbl_employee_ledger WHERE employee_id = ?";
        $params = [$emp_id];

        if ($cat_filter !== 'All' && !empty($cat_filter)) {
            $sql .= " AND category = ?";
            $params[] = $cat_filter;
        }

        $sql .= " ORDER BY transaction_date DESC, id DESC"; 

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// --- 3. SAVE TRANSACTION (Updated for Amortization) ---
if ($action === 'save_transaction') {
    
    // Inputs
    $emp_id = $_POST['employee_id'] ?? '';
    $category = $_POST['category'] ?? '';
    $trans_type = $_POST['transaction_type'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    // [UPDATED] Capture Amortization (Default to 0 if empty)
    $amortization = floatval($_POST['amortization'] ?? 0); 
    $date = $_POST['transaction_date'] ?? date('Y-m-d');
    $remarks = $_POST['remarks'] ?? '';

    // Validation
    if (empty($emp_id) || empty($category) || empty($trans_type) || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input data. Amount must be greater than 0.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Get Previous Running Balance
        $stmt = $pdo->prepare("SELECT running_balance FROM tbl_employee_ledger 
                               WHERE employee_id = ? AND category = ? 
                               ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt->execute([$emp_id, $category]);
        $prev_balance = $stmt->fetchColumn(); 
        
        if ($prev_balance === false) { 
            $prev_balance = 0.00; 
        }

        // 2. Calculate New Running Balance
        $new_balance = $prev_balance;

        if ($category === 'Savings') {
            if ($trans_type === 'Deposit') {
                $new_balance += $amount;
            } elseif ($trans_type === 'Withdrawal') {
                $new_balance -= $amount;
            } elseif ($trans_type === 'Adjustment') {
                $new_balance += $amount; 
            }
        } else {
            // Loans
            if ($trans_type === 'Loan_Grant') {
                $new_balance += $amount; 
            } elseif ($trans_type === 'Loan_Payment') {
                $new_balance -= $amount; 
            } elseif ($trans_type === 'Adjustment') {
                $new_balance += $amount;
            }
        }

        // 3. Insert Record [UPDATED SQL]
        // Added 'amortization' column to the query
        $insertSql = "INSERT INTO tbl_employee_ledger 
                      (employee_id, category, transaction_type, amount, amortization, running_balance, transaction_date, remarks) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            $emp_id, 
            $category, 
            $trans_type, 
            $amount, 
            $amortization, // Bind the amortization value here
            $new_balance, 
            $date, 
            $remarks
        ]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Transaction saved successfully. New Balance: ' . number_format($new_balance, 2)]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Error saving transaction: ' . $e->getMessage()]);
    }
    exit;
}
?>