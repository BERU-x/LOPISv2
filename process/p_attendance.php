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
$lat  = $_POST['latitude'] ?? null;
$long = $_POST['longitude'] ?? null;
$addr = $_POST['address'] ?? null;

$today        = date("Y-m-d");

// CHECK FOR TEST OVERRIDE
if (!empty($_POST['custom_time'])) {
    $currentTime = $_POST['custom_time'];
} else {
    $currentTime = date("H:i:s");
}

// --- 2. VALIDATE INPUTS ---
if (empty($employee_id) || empty($password)) {
    echo json_encode(['status' => 'warning', 'message' => 'Please enter both ID and Password.']);
    exit();
}

try {
    // --- 3. AUTHENTICATION ---
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

        // --- CALCULATE LATE HOURS ---
        $shift_start_str = "$today 09:00:00";
        $late_threshold_str = "$today 09:01:00"; // 🛑 Late starts at 9:01:00 exactly
        
        $actual_in_time = strtotime($currentTime);
        $shift_start_time = strtotime($shift_start_str);
        $late_threshold_time = strtotime($late_threshold_str);
        
        $total_late_hr = 0;
        $status_array = []; 

        // 🛑 Rule: 9:00:59 is Ontime, 9:01:00 is Late
        if ($actual_in_time >= $late_threshold_time) {
            
            // Calculate difference from the OFFICIAL start (9:00), not the threshold
            $diff_seconds = $actual_in_time - $shift_start_time;
            
            // Convert to minutes (stripping seconds)
            $late_minutes = floor($diff_seconds / 60); 
            $total_late_hr = round($late_minutes / 60, 2); 
            
            $status_array[] = 'Late';
        } else {
            $status_array[] = 'Ontime';
        }

        $initial_status_str = implode(', ', $status_array);

        // *** START TRANSACTION ***
        $pdo->beginTransaction();

        try {
            // 1. Insert into MAIN TABLE
            $sql = "INSERT INTO tbl_attendance (employee_id, date, status_based, time_in, attendance_status, total_late_hr, overtime_hr, undertime_hr, num_hr) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employee_id, $today, $status_based, $currentTime, $initial_status_str, $total_late_hr]);
            
            $attendance_id = $pdo->lastInsertId();

            // 2. Insert into GPS TABLE
            if (($status_based == 'FIELD' || $status_based == 'WFH') && !empty($lat) && !empty($long)) {
                $sql_gps = "INSERT INTO tbl_attendance_gps (attendance_id, time_in_lat, time_in_long, location_address) 
                            VALUES (?, ?, ?, ?)";
                $stmt_gps = $pdo->prepare($sql_gps);
                $stmt_gps->execute([$attendance_id, $lat, $long, $addr]);
            }
            
            $pdo->commit();

            $formattedTime = date("g:i A", strtotime($currentTime));
            $late_msg = ($total_late_hr > 0) ? " You are late by $total_late_hr hours." : "";
            
            echo json_encode(['status' => 'success', 'message' => "Welcome, $display_name! Time In at $formattedTime.$late_msg"]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
        }

    } elseif ($action == 'time_out') {

        // Find Session
        $check = $pdo->prepare("SELECT id, time_in, status_based, attendance_status FROM tbl_attendance 
                                WHERE employee_id = ? AND date = ? AND time_out IS NULL");
        $check->execute([$employee_id, $today]);
        $attendance = $check->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            echo json_encode(['status' => 'error', 'message' => "Hi $display_name, you are not timed in yet or already timed out."]);
            exit();
        }

        // --- HOURS CALCULATION ---
        $date_today  = date("Y-m-d");
        $shift_start = strtotime("$date_today 09:00:00");
        $shift_end   = strtotime("$date_today 18:00:00");
        $lunch_start = strtotime("$date_today 12:00:00");
        $lunch_end   = strtotime("$date_today 13:00:00");

        $actual_in_time_str = $attendance['time_in'];
        // 🛑 CHANGE: Calculate IN time based on minute boundary (09:00:59 becomes 09:00:00 for duration calc)
        $actual_in_minutes = floor(strtotime($actual_in_time_str) / 60) * 60; 
        $actual_in         = $actual_in_minutes; 

        $actual_out  = strtotime($currentTime);

        // 1. Calculate Regular Hours (Capped at 9am - 6pm)
        $effective_in = max($actual_in, $shift_start); 
        $effective_out_reg = min($actual_out, $shift_end); 

        $num_hr = 0;
        
        if ($effective_out_reg > $effective_in) {
            $gross_seconds = $effective_out_reg - $effective_in;
            
            // Lunch Deduction
            $overlap_start = max($effective_in, $lunch_start);
            $overlap_end   = min($effective_out_reg, $lunch_end);
            $deduction = 0;
            if ($overlap_end > $overlap_start) {
                $deduction = $overlap_end - $overlap_start;
            }
            
            $net_seconds = $gross_seconds - $deduction;
            
            // 🛑 Rule: "Regardless of seconds" for Undertime
            // 'floor' drops seconds (17:59:59 becomes 17:59)
            $worked_minutes = floor($net_seconds / 60);
            $num_hr = round($worked_minutes / 60, 2);
        }

        // 2. Calculate Overtime (After 6pm)
        $overtime_hr = 0;
        if ($actual_out > $shift_end) {
            $ot_seconds = $actual_out - $shift_end;
            // Usually OT allows seconds or minutes, keeping it standard here
            $overtime_hr = round($ot_seconds / 3600, 2);
        }

        // --- UPDATE STATUS & UNDERTIME ---
        $current_status_str = $attendance['attendance_status'];
        $status_parts = array_map('trim', explode(',', $current_status_str)); 

        $undertime_hr = 0;

        // 🛑 Check Undertime (8.0 hrs vs Worked)
        if ($num_hr < 8.0) {
            $undertime_hr = round(8.0 - $num_hr, 2); 

            if (!in_array('Undertime', $status_parts)) {
                $status_parts[] = 'Undertime';
            }
        }

        if ($overtime_hr > 0) {
            if (!in_array('Overtime', $status_parts)) {
                $status_parts[] = 'Overtime';
            }
        }

        $final_status_str = implode(', ', $status_parts);


        // *** START TRANSACTION ***
        $pdo->beginTransaction();

        try {
            // 1. Update MAIN TABLE
            $sql = "UPDATE tbl_attendance 
                    SET time_out = ?, attendance_status = ?, num_hr = ?, overtime_hr = ?, undertime_hr = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([$currentTime, $final_status_str, $num_hr, $overtime_hr, $undertime_hr, $attendance['id']]);

            // 2. Update GPS TABLE
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
            $ot_msg = ($overtime_hr > 0) ? " (OT: $overtime_hr hrs)" : "";
            $ut_msg = ($undertime_hr > 0) ? " (Undertime: $undertime_hr hrs)" : "";

            echo json_encode(['status' => 'success', 'message' => "Goodbye, $display_name! Time Out at $formattedTime. Status: $final_status_str. Paid Hrs: $num_hr$ot_msg$ut_msg"]);

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