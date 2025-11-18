<?php
// p_attendance.php
require '../db_connection.php';
date_default_timezone_set('Asia/Manila'); 
header('Content-Type: application/json');

// --- 1. GET ALL POST DATA ---
$employee_id_varchar = $_POST['employee_id'] ?? ''; // This is the varchar ID (e.g., "LEN-001")
$password = $_POST['password'] ?? '';
$action = $_POST['action'] ?? '';
$status_based = $_POST['status_based'] ?? 'On-site';
$today = date("Y-m-d");
$currentTime = date("H:i:s");

// --- 2. VALIDATE CREDENTIALS ---
if (empty($employee_id_varchar) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Employee ID and Password are required.']);
    exit();
}

try {
    // --- Step A: Find the employee in tbl_employees using their varchar ID ---
    $stmt = $pdo->prepare("SELECT id, fullname FROM tbl_employees WHERE employee_id = ?");
    $stmt->execute([$employee_id_varchar]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID not found.']);
        exit;
    }

    // Now we have the employee's internal primary key (int)
    $employee_pk_id = $employee['id']; 
    $employee_name = $employee['fullname'];

    // --- Step B: Find the matching user in tbl_users ---
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE employee_id = ?");
    $stmt->execute([$employee_pk_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Step C: Verify password ---
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid password for this Employee ID.']);
        exit;
    }
    
    // Check if account is active
    if ($user['status'] == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Your account is inactive. Please contact admin.']);
        exit;
    }

    // --- 3. AUTHENTICATION SUCCESSFUL ---
    // All checks passed. Proceed with attendance.

    // --- 4. PROCESS THE ATTENDANCE ACTION ---
    
    if ($action == 'time_in') {
        
        // Check if already timed in
        $stmt = $pdo->prepare("SELECT id FROM tbl_attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee_pk_id, $today]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => "Welcome, $employee_name. You have already timed in today."]);
            exit();
        }
        
        // Insert new time-in record
        $stmt = $pdo->prepare(
            "INSERT INTO tbl_attendance (employee_id, date, status_based, time_in, status) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$employee_pk_id, $today, $status_based, $currentTime, 1]); 
        
        $timeInFormatted = date("g:i A", strtotime($currentTime));
        echo json_encode(['status' => 'success', 'message' => "Welcome, $employee_name! Successfully timed in at $timeInFormatted."]);
    
    } elseif ($action == 'time_out') {
        
        // Find the open time-in record
        $stmt = $pdo->prepare("SELECT id, time_in FROM tbl_attendance WHERE employee_id = ? AND date = ? AND time_out IS NULL");
        $stmt->execute([$employee_pk_id, $today]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            echo json_encode(['status' => 'error', 'message' => "Error, $employee_name: You have not timed in yet or have already timed out."]);
            exit();
        }
        
        // Calculate num_hr
        $time_in_obj = new DateTime($attendance['time_in']);
        $time_out_obj = new DateTime($currentTime);
        $interval = $time_in_obj->diff($time_out_obj);
        $num_hr = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        $num_hr = round($num_hr, 2); 

        // Update the record with time-out info
        $stmt = $pdo->prepare(
            "UPDATE tbl_attendance 
             SET time_out = ?, status_out = ?, num_hr = ?, status = ? 
             WHERE id = ?"
        );
        $stmt->execute([$currentTime, 'Logged Out', $num_hr, 2, $attendance['id']]); 
        
        $timeOutFormatted = date("g:i A", strtotime($currentTime));
        echo json_encode(['status' => 'success', 'message' => "Goodbye, $employee_name! Successfully timed out at $timeOutFormatted. Hours worked: $num_hr"]);
    
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>