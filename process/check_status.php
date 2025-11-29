<?php
require '../db_connection.php'; // Check your path
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

$employee_id      = trim($_POST['employee_id'] ?? '');
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

    // 3. PRIORITY CHECK: FIND *ANY* OPEN SESSION (Today OR Past)
    // We explicitly look for time_out IS NULL. 
    // We order by date DESC to handle the most recent open session first.
    $stmt = $pdo->prepare("SELECT date, status_based FROM tbl_attendance 
                           WHERE employee_id = ? AND time_out IS NULL 
                           ORDER BY date DESC LIMIT 1");
    $stmt->execute([$employee_id]);
    $open_session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($open_session) {
        // --- CASE: FOUND AN OPEN SESSION ---
        
        // Check Location mismatch (if your system requires location locking)
        if (!empty($current_location) && $open_session['status_based'] !== $current_location) {
            echo json_encode([
                'status' => 'location_mismatch', 
                'required_location' => $open_session['status_based']
            ]);
            exit;
        }

        // Determine if this is a "Forgot Time Out" scenario
        $is_past_date = ($open_session['date'] !== $today);
        $message = $is_past_date ? "You forgot to Time Out on " . date("M d, Y", strtotime($open_session['date'])) . ". Please Time Out now." : "";

        echo json_encode([
            'status'  => 'need_time_out',
            'message' => $message // Send this warning to the frontend
        ]);
        exit;
    }

    // 4. SECONDARY CHECK: IS TODAY COMPLETED?
    // If no open sessions found, we check if they already finished TODAY.
    $stmt = $pdo->prepare("SELECT id FROM tbl_attendance 
                           WHERE employee_id = ? AND date = ? AND time_out IS NOT NULL");
    $stmt->execute([$employee_id, $today]);
    $completed_today = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($completed_today) {
        // --- CASE: DONE FOR TODAY ---
        echo json_encode(['status' => 'completed']);
    } else {
        // --- CASE: NEW DAY, NO LOGS YET ---
        echo json_encode(['status' => 'need_time_in']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>