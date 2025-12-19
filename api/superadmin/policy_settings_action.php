<?php
// api/superadmin/policy_settings_action.php
// Handles Core HR Policies: Work Hours, Grace Periods, and Leave Credits
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
        $stmt = $pdo->query("SELECT * FROM tbl_policy_settings WHERE id = 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return defaults if table is empty (Edge case handler)
        if (!$data) {
            $data = [
                'standard_work_hours' => 8.00,
                'attendance_grace_period_mins' => 15,
                'overtime_min_minutes' => 60,
                'annual_vacation_leave' => 15,
                'annual_sick_leave' => 15,
                'max_leave_carry_over' => 5
            ]; 
        }
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // =========================================================================
    // ACTION 2: UPDATE POLICIES
    // =========================================================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Data Validation/Sanitization
        $standard_hours = floatval($_POST['standard_work_hours']);
        $grace_period   = intval($_POST['attendance_grace_period_mins']);
        $min_ot         = intval($_POST['overtime_min_minutes']);
        $vacation       = intval($_POST['annual_vacation_leave']);
        $sick           = intval($_POST['annual_sick_leave']);
        $carry_over     = intval($_POST['max_leave_carry_over']);

        $sql = "UPDATE tbl_policy_settings 
                SET standard_work_hours=?, attendance_grace_period_mins=?, overtime_min_minutes=?,
                    annual_vacation_leave=?, annual_sick_leave=?, max_leave_carry_over=?,
                    updated_at = NOW()
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            $standard_hours, $grace_period, $min_ot, 
            $vacation, $sick, $carry_over, 
            1 // Record ID 1
        ];
        
        if ($stmt->execute($params)) {
            // ⭐ LOG AUDIT TRAIL
            logAudit(
                $pdo, 
                $_SESSION['user_id'], 
                $_SESSION['usertype'], 
                'UPDATE_POLICIES', 
                "Updated HR Policies: Work Hours ($standard_hours), Grace Period ($grace_period min), VL ($vacation), SL ($sick)."
            );

            echo json_encode(['status' => 'success', 'message' => 'Company policies saved successfully.']);
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