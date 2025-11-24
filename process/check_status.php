<?php
require '../db_connection.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

$employee_id      = trim($_POST['employee_id'] ?? '');
// We now receive the current location (OFB or WFH) from the AJAX request
$current_location = $_POST['current_location'] ?? ''; 
$today            = date("Y-m-d");

// 1. Basic Validation
if (empty($employee_id)) {
    echo json_encode(['status' => 'empty']);
    exit;
}

try {
    // 2. CHECK IF USER EXISTS AND IS ACTIVE
    $stmt = $pdo->prepare("SELECT id, status FROM tbl_users WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'invalid_id']);
        exit;
    }

    if ($user['status'] == 0) {
        echo json_encode(['status' => 'inactive']);
        exit;
    }

    // 3. CHECK ATTENDANCE STATUS
    // We added 'status_based' to the SELECT query
    $stmt = $pdo->prepare("SELECT time_in, time_out, status_based FROM tbl_attendance WHERE employee_id = ? AND date = ?");
    $stmt->execute([$employee_id, $today]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance) {
        // User exists, no record today -> SHOW TIME IN
        echo json_encode(['status' => 'need_time_in']);
        
    } elseif ($attendance['time_in'] && $attendance['time_out'] == NULL) {
        
        // --- LOCATION CHECK LOGIC ---
        // They are currently Timed In. Check if the locations match.
        // DB says "OFB" but Link says "WFH" (or vice versa)
        if (!empty($current_location) && $attendance['status_based'] !== $current_location) {
            echo json_encode([
                'status' => 'location_mismatch', 
                'required_location' => $attendance['status_based']
            ]);
            exit;
        }

        // If locations match, allow Time Out
        echo json_encode(['status' => 'need_time_out']);

    } else {
        // User exists, both done -> COMPLETED
        echo json_encode(['status' => 'completed']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>