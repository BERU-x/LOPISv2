<?php
require_once '../../db_connection.php';

session_start();
header('Content-Type: application/json');

// 1. Auth Check
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];

// 2. Initialize Response Structure
$response = [
    'status' => 'error',
    'message' => 'No data found.',
    'data' => [
        'sss' => [
            'total_loan' => 0,
            'balance' => 0
        ],
        'pagibig' => [
            'total_loan' => 0,
            'balance' => 0
        ],
        'company_loan' => [
            'total_loan' => 0,
            'balance' => 0
        ],
        'cash_assistance' => [
            'total_amount' => 0,
            'balance' => 0, // Mapped from outstanding_balance
            'amortization' => 0 // Mapped from cash_assist_deduction
        ],
        'savings' => [
            'monthly_deduction' => 0, // The fixed amount deducted per payroll
            'current_balance' => 0,   // Calculated from the latest ledger entry
            'history' => []           // The ledger array
        ],
        'meta' => [
            'last_update' => 'N/A'
        ]
    ]
];

try {
    // 3. Fetch Summary Balances from tbl_employee_financials
    $stmt = $pdo->prepare("
        SELECT 
            sss_loan, sss_loan_balance,
            pagibig_loan, pagibig_loan_balance,
            company_loan, company_loan_balance,
            cash_assist_total, outstanding_balance, cash_assist_deduction,
            savings_deduction,
            last_loan_update
        FROM tbl_employee_financials
        WHERE employee_id = ?
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);
    $fin = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Fetch Savings Ledger History from tbl_savings_ledger
    $stmtLedger = $pdo->prepare("
        SELECT 
            transaction_date, 
            ref_no, 
            transaction_type, 
            amount, 
            running_balance, 
            remarks
        FROM tbl_savings_ledger
        WHERE employee_id = ?
        ORDER BY transaction_date DESC, created_at DESC
        LIMIT 50
    ");
    $stmtLedger->execute([$employee_id]);
    $ledgerData = $stmtLedger->fetchAll(PDO::FETCH_ASSOC);

    // 5. Populate Response
    if ($fin) {
        $response['status'] = 'success';
        $response['message'] = 'Data loaded successfully.';

        // --- SSS Loan ---
        $response['data']['sss']['total_loan'] = (float)$fin['sss_loan'];
        $response['data']['sss']['balance']    = (float)$fin['sss_loan_balance'];

        // --- Pag-ibig Loan ---
        $response['data']['pagibig']['total_loan'] = (float)$fin['pagibig_loan'];
        $response['data']['pagibig']['balance']    = (float)$fin['pagibig_loan_balance'];

        // --- Company Loan ---
        $response['data']['company_loan']['total_loan'] = (float)$fin['company_loan'];
        $response['data']['company_loan']['balance']    = (float)$fin['company_loan_balance'];

        // --- Cash Assistance ---
        // Note: Assuming 'outstanding_balance' refers to Cash Assist based on table structure
        $response['data']['cash_assistance']['total_amount'] = (float)$fin['cash_assist_total'];
        $response['data']['cash_assistance']['balance']      = (float)$fin['outstanding_balance'];
        $response['data']['cash_assistance']['amortization'] = (float)$fin['cash_assist_deduction'];

        // --- Savings ---
        $response['data']['savings']['monthly_deduction'] = (float)$fin['savings_deduction'];
        $response['data']['savings']['history']           = $ledgerData;

        // Determine current total savings from the latest ledger entry
        if (!empty($ledgerData)) {
            $response['data']['savings']['current_balance'] = (float)$ledgerData[0]['running_balance'];
        } else {
            $response['data']['savings']['current_balance'] = 0.00;
        }

        // --- Meta / Dates ---
        if (!empty($fin['last_loan_update'])) {
            $response['data']['meta']['last_update'] = date('M d, Y', strtotime($fin['last_loan_update']));
        }
    }

} catch (PDOException $e) {
    $response['status'] = 'error';
    $response['message'] = "Database Error: " . $e->getMessage();
}

echo json_encode($response);
?>