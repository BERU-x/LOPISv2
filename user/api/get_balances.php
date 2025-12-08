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
    'message' => 'Data derived successfully.',
    'data' => [
        'sss' => [
            'total_loan' => 0,
            'balance' => 0,
            'amortization' => 0 
        ],
        'pagibig' => [
            'total_loan' => 0,
            'balance' => 0,
            'amortization' => 0 
        ],
        'company_loan' => [
            'total_loan' => 0,
            'balance' => 0,
            'amortization' => 0 
        ],
        'cash_assistance' => [
            'total_amount' => 0, 
            'balance' => 0,
            'amortization' => 0 
        ],
        'savings' => [
            'monthly_deduction' => 0,
            'current_balance' => 0,
        ],
        'general_ledger' => [],
        'meta' => [
            'last_update' => 'N/A'
        ]
    ]
];

try {
    // --- STEP 3: Fetch Total Granted Amounts from tbl_employee_ledger ---
    $stmtTotals = $pdo->prepare("
        SELECT 
            category, 
            SUM(amount) AS total_granted 
        FROM tbl_employee_ledger
        WHERE employee_id = ?
          AND (transaction_type = 'Loan_Grant' OR (category = 'Cash_Assist' AND transaction_type = 'Deposit'))
        GROUP BY category
    ");
    $stmtTotals->execute([$employee_id]);
    $totalsData = $stmtTotals->fetchAll(PDO::FETCH_KEY_PAIR); // [category => total_granted]

    // --- STEP 4: Fetch ALL Ledger History (For the table display) ---
    $stmtLedger = $pdo->prepare("
        SELECT 
            category, 
            transaction_date, 
            ref_no, 
            transaction_type, 
            amount, 
            amortization,
            running_balance, 
            remarks
        FROM tbl_employee_ledger
        WHERE employee_id = ?
        ORDER BY transaction_date DESC, created_at DESC
        LIMIT 100 
    ");
    $stmtLedger->execute([$employee_id]);
    $ledgerData = $stmtLedger->fetchAll(PDO::FETCH_ASSOC);

    // --- STEP 5: OPTIMIZED Fetch for Current Balances and Amortization Rates ---
    // Using a subquery to find the MAX created_at for each category for reliability.
    $stmtLatestData = $pdo->prepare("
        SELECT 
            t1.category,
            t1.running_balance,
            (SELECT t2.amortization FROM tbl_employee_ledger t2
             WHERE t2.employee_id = t1.employee_id AND t2.category = t1.category 
               AND t2.amortization > 0
             ORDER BY t2.created_at DESC, t2.transaction_date DESC 
             LIMIT 1
            ) AS latest_amortization_rate
        FROM tbl_employee_ledger t1
        WHERE t1.employee_id = :eid
        AND t1.created_at = (
            SELECT MAX(created_at)
            FROM tbl_employee_ledger
            WHERE employee_id = t1.employee_id AND category = t1.category
        )
    ");
    $stmtLatestData->execute([':eid' => $employee_id]);
    $latestData = $stmtLatestData->fetchAll(PDO::FETCH_ASSOC);

    // --- STEP 6: Populate Response (using derived values) ---
    
    // Convert optimized results into lookup arrays
    $latestBalances = [];
    $amortizationRates = [];
    foreach ($latestData as $row) {
        $latestBalances[$row['category']] = (float)$row['running_balance'];
        $amortizationRates[$row['category']] = (float)$row['latest_amortization_rate'];
    }

    $hasData = !empty($latestData);
    $latestTransactionDate = !empty($ledgerData) ? $ledgerData[0]['transaction_date'] : null;


    if ($hasData) {
        $response['status'] = 'success';
        $response['message'] = 'Financial data derived successfully.';

        // --- Loans ---
        $response['data']['sss']['balance']        = $latestBalances['SSS_Loan'] ?? 0.00;
        $response['data']['sss']['amortization']   = $amortizationRates['SSS_Loan'] ?? 0.00;
        $response['data']['sss']['total_loan']     = $totalsData['SSS_Loan'] ?? 0.00;

        $response['data']['pagibig']['balance']    = $latestBalances['Pagibig_Loan'] ?? 0.00;
        $response['data']['pagibig']['amortization'] = $amortizationRates['Pagibig_Loan'] ?? 0.00; 
        $response['data']['pagibig']['total_loan'] = $totalsData['Pagibig_Loan'] ?? 0.00;

        $response['data']['company_loan']['balance'] = $latestBalances['Company_Loan'] ?? 0.00;
        $response['data']['company_loan']['amortization'] = $amortizationRates['Company_Loan'] ?? 0.00;
        $response['data']['company_loan']['total_loan'] = $totalsData['Company_Loan'] ?? 0.00;
        
        // --- Cash Assistance ---
        $response['data']['cash_assistance']['balance']      = $latestBalances['Cash_Assist'] ?? 0.00;
        $response['data']['cash_assistance']['amortization'] = $amortizationRates['Cash_Assist'] ?? 0.00;
        $response['data']['cash_assistance']['total_amount'] = $totalsData['Cash_Assist'] ?? 0.00;

        // --- Savings ---
        $response['data']['savings']['current_balance'] = $latestBalances['Savings'] ?? 0.00;
        $response['data']['savings']['monthly_deduction'] = $amortizationRates['Savings'] ?? 0.00; 

        // --- Ledger History ---
        $response['data']['general_ledger'] = $ledgerData;

        // --- Meta / Dates ---
        if ($latestTransactionDate) {
            $response['data']['meta']['last_update'] = date('M d, Y', strtotime($latestTransactionDate));
        }
    }

} catch (PDOException $e) {
    $response['status'] = 'error';
    $response['message'] = "Database Error: " . $e->getMessage();
}

echo json_encode($response);
?>