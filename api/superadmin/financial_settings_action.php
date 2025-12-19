<?php
// api/superadmin/financial_settings_action.php
// Handles Currency and Fiscal Year Configurations (Super Admin Only)
header('Content-Type: application/json');
session_start();

// --- 1. AUTHENTICATION (Super Admin Only) ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DEPENDENCIES ---
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

$action = $_REQUEST['action'] ?? '';

try {

    // =========================================================================
    // ACTION 1: GET DETAILS
    // =========================================================================
    if ($action === 'get_details') {
        $stmt = $pdo->query("SELECT * FROM tbl_financial_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return defaults if empty (first-time setup)
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

    // =========================================================================
    // ACTION 2: UPDATE SETTINGS
    // =========================================================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Sanitize inputs
        $currency_code = strtoupper(trim($_POST['currency_code']));
        $currency_symbol = trim($_POST['currency_symbol']);
        $fiscal_month = $_POST['fiscal_year_start_month'];
        $fiscal_day = intval($_POST['fiscal_year_start_day']);

        $sql = "UPDATE tbl_financial_settings 
                SET currency_code=?, currency_symbol=?, fiscal_year_start_month=?, fiscal_year_start_day=?, updated_at=NOW()
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            $currency_code,
            $currency_symbol,
            $fiscal_month,
            $fiscal_day,
            1 // Record ID is fixed at 1
        ];
        
        if ($stmt->execute($params)) {
            // ⭐ LOG AUDIT TRAIL
            logAudit(
                $pdo, 
                $_SESSION['user_id'], 
                $_SESSION['usertype'], 
                'UPDATE_FINANCIAL', 
                "Updated financial config: Currency set to $currency_code ($currency_symbol), Fiscal Year starts $fiscal_month $fiscal_day"
            );

            echo json_encode(['status' => 'success', 'message' => 'Financial settings updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>