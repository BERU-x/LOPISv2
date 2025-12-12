<?php
// api/policy_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {

    // 1. GET DETAILS
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

    // 2. UPDATE POLICIES
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $sql = "UPDATE tbl_policy_settings 
                SET standard_work_hours=?, attendance_grace_period_mins=?, overtime_min_minutes=?,
                    annual_vacation_leave=?, annual_sick_leave=?, max_leave_carry_over=?
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            $_POST['standard_work_hours'],
            $_POST['attendance_grace_period_mins'],
            $_POST['overtime_min_minutes'],
            $_POST['annual_vacation_leave'],
            $_POST['annual_sick_leave'],
            $_POST['max_leave_carry_over'],
            1 // Always ID 1
        ];
        
        if ($stmt->execute($params)) {
            echo json_encode(['status' => 'success', 'message' => 'Company policies saved successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>