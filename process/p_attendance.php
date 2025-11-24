<?php
require '../db_connection.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

// --- 1. GET & SANITIZE DATA ---
$employee_id  = trim($_POST['employee_id'] ?? ''); 
$password     = $_POST['password'] ?? '';
$action       = $_POST['action'] ?? '';
$status_based = $_POST['status_based'] ?? 'On-site'; 

// GPS Data 
$lat          = $_POST['latitude'] ?? null;
$long         = $_POST['longitude'] ?? null;
$addr         = $_POST['address'] ?? null;

$today        = date("Y-m-d");
$currentTime  = date("H:i:s");

// --- 2. VALIDATE INPUTS ---
if (empty($employee_id) || empty($password)) {
    echo json_encode(['status' => 'warning', 'message' => 'Please enter both ID and Password.']);
    exit();
}

try {
    // --- 3. AUTHENTICATION ---
    // Simplified structure based on previous context:
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Employee ID or Password.']);
        exit();
    }

    if ($user['status'] == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive. Contact Admin.']);
        exit();
    }

    // Get Name
    $stmt_name = $pdo->prepare("SELECT Firstname, Lastname FROM tbl_employees WHERE employee_id = ?");
    $stmt_name->execute([$employee_id]);
    $emp_data = $stmt_name->fetch(PDO::FETCH_ASSOC);
    $display_name = $emp_data ? $emp_data['Firstname'] . ' ' . $emp_data['Lastname'] : $employee_id;


    // --- 4. PROCESS ATTENDANCE ---

    if ($action == 'time_in') {

        // Check Duplicate
        $check = $pdo->prepare("SELECT id FROM tbl_attendance WHERE employee_id = ? AND date = ?");
        $check->execute([$employee_id, $today]);
        
        if ($check->rowCount() > 0) {
            echo json_encode(['status' => 'warning', 'message' => "Hi $display_name, you have already timed in today."]);
            exit();
        }

        // *** START TRANSACTION ***
        $pdo->beginTransaction();

        try {
            // 1. Insert into MAIN TABLE
            $sql = "INSERT INTO tbl_attendance (employee_id, date, status_based, time_in, status) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employee_id, $today, $status_based, $currentTime, 1]);
            
            // 2. Get the ID of the row we just made
            $attendance_id = $pdo->lastInsertId();

            // 3. Insert into GPS TABLE (CONDITIONAL - GPS is now OPTIONAL)
            // Only insert if the status is remote AND both lat/long fields are not empty.
            if (($status_based == 'FIELD' || $status_based == 'WFH') && !empty($lat) && !empty($long)) {
                
                $sql_gps = "INSERT INTO tbl_attendance_gps (attendance_id, time_in_lat, time_in_long, location_address) 
                            VALUES (?, ?, ?, ?)";
                $stmt_gps = $pdo->prepare($sql_gps);
                $stmt_gps->execute([$attendance_id, $lat, $long, $addr]);
            }
            
            // 4. Commit changes (The main attendance record is saved even without GPS)
            $pdo->commit();

            $formattedTime = date("g:i A", strtotime($currentTime));
            
            // Note: If GPS was missing but optional, the message is still successful.
            $message = ($status_based != 'On-site' && (empty($lat) || empty($long))) 
                       ? "Time In recorded! Location data was not captured." 
                       : "Time In recorded at $formattedTime.";
                       
            echo json_encode(['status' => 'success', 'message' => "Welcome, $display_name! $message"]);

        } catch (Exception $e) {
            $pdo->rollBack();
            // Database errors (not user errors) are still caught here
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
        }

    } elseif ($action == 'time_out') {

        // Find Session
        $check = $pdo->prepare("SELECT id, time_in, status_based FROM tbl_attendance 
                                WHERE employee_id = ? AND date = ? AND time_out IS NULL");
        $check->execute([$employee_id, $today]);
        $attendance = $check->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            echo json_encode(['status' => 'error', 'message' => "Hi $display_name, you are not timed in yet or already timed out."]);
            exit();
        }

        // --- STRICT CALCULATION (9AM - 6PM) ---
        $date_today  = date("Y-m-d");
        $shift_start = strtotime("$date_today 09:00:00");
        $shift_end   = strtotime("$date_today 18:00:00");
        $lunch_start = strtotime("$date_today 12:00:00");
        $lunch_end   = strtotime("$date_today 13:00:00");

        $actual_in   = strtotime($attendance['time_in']);
        $actual_out  = strtotime($currentTime);

        // Clamp Times
        $effective_in = max($actual_in, $shift_start);
        $effective_out = min($actual_out, $shift_end);

        if ($effective_out > $effective_in) {
            $gross_seconds = $effective_out - $effective_in;

            // Lunch Deduction
            $overlap_start = max($effective_in, $lunch_start);
            $overlap_end   = min($effective_out, $lunch_end);
            $deduction = 0;
            if ($overlap_end > $overlap_start) {
                $deduction = $overlap_end - $overlap_start;
            }

            $net_seconds = $gross_seconds - $deduction;
            $num_hr = round($net_seconds / 3600, 2);
        } else {
            $num_hr = 0.00;
        }

        // *** START TRANSACTION ***
        $pdo->beginTransaction();

        try {
            // 1. Update MAIN TABLE
            $sql = "UPDATE tbl_attendance 
                    SET time_out = ?, status_out = ?, num_hr = ?, status = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            $status_text = ($num_hr >= 8) ? 'Regular Duty' : 'Undertime';
            $stmt->execute([$currentTime, $status_text, $num_hr, 2, $attendance['id']]);

            // 2. If FIELD/WFH, Update GPS TABLE (Time Out Location) - Already optional check included
            if ($attendance['status_based'] == 'FIELD' || $attendance['status_based'] == 'WFH') {
                if (!empty($lat) && !empty($long)) {
                    $sql_gps = "UPDATE tbl_attendance_gps 
                                SET time_out_lat = ?, time_out_long = ? 
                                WHERE attendance_id = ?";
                    $stmt_gps = $pdo->prepare($sql_gps);
                    $stmt_gps->execute([$lat, $long, $attendance['id']]);
                }
            }

            $pdo->commit();

            $formattedTime = date("g:i A", strtotime($currentTime));
            $message = ($attendance['status_based'] != 'On-site' && (empty($lat) || empty($long))) 
                       ? "Time Out recorded! Location data was not captured." 
                       : "Time Out recorded at $formattedTime.";
                       
            echo json_encode(['status' => 'success', 'message' => "Goodbye, $display_name! $message Paid Hours: $num_hr"]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
        }

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>