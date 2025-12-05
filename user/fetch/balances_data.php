<?php
// fetch/balances_data_ajax.php - AJAX Endpoint for detailed financial data

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Adjust path as necessary
require_once __DIR__ . '/../../db_connection.php'; 

$employee_id = $_SESSION['employee_id'] ?? null; 

// Initialize data structure with null/zero defaults
$response = [
    'status' => 'error',
    'message' => 'Initialization failed.',
    'data' => [
        'sss_loan_orig' => 0.00,
        'pagibig_loan_orig' => 0.00,
        'company_loan_orig' => 0.00,
        'savings_deduction' => 0.00,
        'cash_assist_total' => 0.00,
        'outstanding_balance' => 0.00,
        'cash_assist_deduction' => 0.00,
        'sss_loan_balance' => 0.00,
        'pagibig_loan_balance' => 0.00,
        'company_loan_balance' => 0.00,
        'last_loan_update' => 'N/A'
    ]
];

if (!$employee_id || !isset($pdo)) {
    $response['message'] = "Session ID or database connection missing.";
    echo json_encode($response);
    exit;
}

try {
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
        WHERE employee_id = :eid
        LIMIT 1
    ");
    $stmt->execute([':eid' => $employee_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $response['status'] = 'success';
        $response['message'] = "Financial data loaded.";

        // Map and format fetched data for JSON output
        foreach ($data as $key => $value) {
            if (strpos($key, '_loan_update') !== false && $value) {
                $response['data'][$key] = date('M d, Y', strtotime($value));
            } elseif (is_numeric($value)) {
                $response['data'][$key] = floatval($value);
            } else {
                $response['data'][$key] = $value;
            }
        }
    } else {
        $response['message'] = "No financial record found.";
    }
    
} catch (PDOException $e) {
    error_log("Financial Data Fetch Error: " . $e->getMessage());
    $response['message'] = "Database Error: " . $e->getMessage();
}

echo json_encode($response);
?>