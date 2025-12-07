<?php
// user/api/get_balances.php
require_once '../../db_connection.php'; 

session_start();
header('Content-Type: application/json');

// 1. Auth Check
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];

// 2. Default Response Structure
$response = [
    'status' => 'error',
    'message' => 'No data found.',
    'data' => [
        'sss_loan_orig' => 0, 'sss_loan_balance' => 0,
        'pagibig_loan_orig' => 0, 'pagibig_loan_balance' => 0,
        'company_loan_orig' => 0, 'company_loan_balance' => 0,
        'cash_assist_total' => 0, 'cash_assist_deduction' => 0,
        'savings_deduction' => 0, 'outstanding_balance' => 0,
        'last_loan_update' => 'N/A'
    ],
    'ledger' => [] // New array for ledger history
];

try {
    // 3. Fetch Summary Data (Existing)
    $stmt = $pdo->prepare("
        SELECT 
            sss_loan AS sss_loan_orig,
            pagibig_loan AS pagibig_loan_orig,
            company_loan AS company_loan_orig,
            savings_deduction,
            cash_assist_total,
            outstanding_balance,
            cash_assist_deduction,
            sss_loan_balance,
            pagibig_loan_balance,
            company_loan_balance,
            last_loan_update
        FROM tbl_employee_financials
        WHERE employee_id = ?
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $response['status'] = 'success';
        $response['message'] = 'Data loaded successfully.';
        
        if (!empty($data['last_loan_update'])) {
            $data['last_loan_update'] = date('M d, Y', strtotime($data['last_loan_update']));
        } else {
            $data['last_loan_update'] = 'N/A';
        }

        $response['data'] = $data;

        // 4. Fetch Savings Ledger History (New)
        $stmtLedger = $pdo->prepare("
            SELECT transaction_date, ref_no, transaction_type, amount, running_balance, remarks
            FROM tbl_savings_ledger
            WHERE employee_id = ?
            ORDER BY transaction_date DESC, created_at DESC
            LIMIT 50
        ");
        $stmtLedger->execute([$employee_id]);
        $response['ledger'] = $stmtLedger->fetchAll(PDO::FETCH_ASSOC);
    } 

} catch (PDOException $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
}

echo json_encode($response);
?>