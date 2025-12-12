<?php
// api/financial_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {

    // 1. GET DETAILS
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_financial_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return defaults if empty
        if (!$data) {
            $data = [
                'currency_code' => 'PHP', 
                'currency_symbol' => '₱',
                'fiscal_year_start_month' => 'January',
                'fiscal_year_start_day' => 1
            ]; 
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // 2. UPDATE SETTINGS
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $sql = "UPDATE tbl_financial_settings 
                SET currency_code=?, currency_symbol=?, fiscal_year_start_month=?, fiscal_year_start_day=?
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            $_POST['currency_code'],
            $_POST['currency_symbol'],
            $_POST['fiscal_year_start_month'],
            $_POST['fiscal_year_start_day'],
            1 // ID is always 1
        ];
        
        if ($stmt->execute($params)) {
            echo json_encode(['status' => 'success', 'message' => 'Financial settings updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>