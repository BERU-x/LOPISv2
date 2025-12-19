<?php
/**
 * api/employee/get_financial_ledger_data.php
 * Derived Financial Controller: Aggregates Loan Balances, Amortization, and Savings 
 * directly from the running Ledger to ensure absolute data integrity.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_start();

// 1. SECURITY & AUTHENTICATION
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php'; 

$employee_id = $_SESSION['employee_id'];

// Initialize Response with fallback values
$response = [
    'status' => 'error',
    'message' => 'No financial records found.',
    'data' => [
        'sss'             => ['total_loan' => 0, 'balance' => 0, 'amortization' => 0],
        'pagibig'         => ['total_loan' => 0, 'balance' => 0, 'amortization' => 0],
        'company_loan'    => ['total_loan' => 0, 'balance' => 0, 'amortization' => 0],
        'cash_assistance' => ['total_amount' => 0, 'balance' => 0, 'amortization' => 0],
        'savings'         => ['monthly_deduction' => 0, 'current_balance' => 0],
        'general_ledger'  => [],
        'meta'            => ['last_update' => 'N/A']
    ]
];

try {
    // --- STEP 1: Fetch Total Granted Amounts (Initial Principal) ---
    // Logic: Sum up all Loan_Grant transactions or Initial Deposits for Cash Assistance
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
    $totalsData = $stmtTotals->fetchAll(PDO::FETCH_KEY_PAIR); 

    // --- STEP 2: Fetch Current Balances and Active Amortization Rates ---
    // Optimization: Uses a Correlated Subquery to find the absolute latest entry per category
    $stmtLatestData = $pdo->prepare("
        SELECT 
            t1.category,
            t1.running_balance,
            t1.transaction_date,
            (SELECT t2.amortization FROM tbl_employee_ledger t2
             WHERE t2.employee_id = t1.employee_id AND t2.category = t1.category 
               AND t2.amortization > 0
             ORDER BY t2.created_at DESC, t2.transaction_date DESC 
             LIMIT 1
            ) AS latest_amortization_rate
        FROM tbl_employee_ledger t1
        WHERE t1.employee_id = :eid
        AND t1.id = (
            SELECT MAX(id)
            FROM tbl_employee_ledger
            WHERE employee_id = t1.employee_id AND category = t1.category
        )
    ");
    $stmtLatestData->execute([':eid' => $employee_id]);
    $latestEntries = $stmtLatestData->fetchAll(PDO::FETCH_ASSOC);

    // --- STEP 3: Fetch Full Ledger History for Table Display ---
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
        ORDER BY transaction_date DESC, id DESC
        LIMIT 150 
    ");
    $stmtLedger->execute([$employee_id]);
    $ledgerData = $stmtLedger->fetchAll(PDO::FETCH_ASSOC);

    // --- STEP 4: Populate Response Structure ---
    if (!empty($latestEntries)) {
        $response['status'] = 'success';
        $response['message'] = 'Financial data derived successfully.';

        // Helper Lookup Arrays
        $balances = [];
        $rates = [];
        $lastDate = 'N/A';

        foreach ($latestEntries as $row) {
            $balances[$row['category']] = (float)$row['running_balance'];
            $rates[$row['category']] = (float)$row['latest_amortization_rate'];
            // Track the most recent overall transaction date
            if ($lastDate === 'N/A' || strtotime($row['transaction_date']) > strtotime($lastDate)) {
                $lastDate = $row['transaction_date'];
            }
        }

        // Map Category keys to Response Structure
        $map = [
            'SSS_Loan'    => 'sss',
            'Pagibig_Loan'=> 'pagibig',
            'Company_Loan'=> 'company_loan',
            'Cash_Assist' => 'cash_assistance'
        ];

        foreach ($map as $ledgerKey => $respKey) {
            $response['data'][$respKey]['balance']      = $balances[$ledgerKey] ?? 0.00;
            $response['data'][$respKey]['amortization'] = $rates[$ledgerKey] ?? 0.00;
            $response['data'][$respKey]['total_loan']   = (float)($totalsData[$ledgerKey] ?? 0.00);
            
            // Special key name for cash assistance total
            if ($respKey === 'cash_assistance') {
                $response['data'][$respKey]['total_amount'] = (float)($totalsData[$ledgerKey] ?? 0.00);
            }
        }

        // Map Savings
        $response['data']['savings']['current_balance']   = $balances['Savings'] ?? 0.00;
        $response['data']['savings']['monthly_deduction'] = $rates['Savings'] ?? 0.00; 

        // Ledger List & Meta
        $response['data']['general_ledger'] = $ledgerData;
        $response['data']['meta']['last_update'] = ($lastDate !== 'N/A') ? date('M d, Y', strtotime($lastDate)) : 'N/A';
    }

} catch (PDOException $e) {
    http_response_code(500);
    $response['status'] = 'error';
    $response['message'] = "Critical Ledger Error: " . $e->getMessage();
}

echo json_encode($response);