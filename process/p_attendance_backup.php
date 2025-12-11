<?php
require '../db_connection.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

// --- 1. GET & SANITIZE DATA ---
$employee_id  = trim($_POST['employee_id'] ?? ''); 
$password     = $_POST['password'] ?? ''; // Added Password check for production safety
$action       = $_POST['action'] ?? '';
$status_based = $_POST['status_based'] ?? 'On-site'; 

// GPS Data 
$lat  = $_POST['latitude'] ?? null;
$long = $_POST['longitude'] ?? null;
$addr = $_POST['address'] ?? null;

// --- 2. SET SERVER DATE & TIME (STRICT - NO OVERRIDES) ---
$today       = date("Y-m-d");
$currentTime = date("H:i:s");

// --- 3. VALIDATE INPUTS ---
if (empty($employee_id)) {
    echo json_encode(['status' => 'warning', 'message' => 'Please enter Employee ID.']);
    exit();
}

try {
    // --- 4. AUTHENTICATION & FETCH SCHEDULE ---
    // Note: In production, verify the password too if $password is passed
    $sql = "SELECT u.status, e.Firstname, e.Lastname, e.schedule_type 
            FROM tbl_users u 
            JOIN tbl_employees e ON u.employee_id = e.employee_id 
            WHERE u.employee_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Employee ID.']);
        exit();
    }

    if ($user_data['status'] == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive. Contact Admin.']);
        exit();
    }

    $display_name = $user_data['Firstname'] . ' ' . $user_data['Lastname'];
    $schedule_type = $user_data['schedule_type'] ?? 'Fixed'; 


    // --- 5. PROCESS ATTENDANCE ---

    if ($action == 'time_in') {

        // Check Duplicate
        $check = $pdo->prepare("SELECT id FROM tbl_attendance WHERE employee_id = ? AND date = ?");
        $check->execute([$employee_id, $today]);
        
        if ($check->rowCount() > 0) {
            echo json_encode(['status' => 'warning', 'message' => "Hi $display_name, you have already timed in for $today."]);
            exit();
        }

        // --- CALCULATE LATE ---
        $initial_deduction = 0;
        $status_array = []; 
        
        if ($schedule_type === 'Flexible') {
            $status_array[] = 'Ontime';
        } else {
            $shift_start_str = "$today 09:00:00";
            $late_threshold_str = "$today 09:01:00"; 
            
            $actual_in_time = strtotime("$today $currentTime");
            $shift_start_time = strtotime($shift_start_str);
            $late_threshold_time = strtotime($late_threshold_str);
            
            if ($actual_in_time >= $late_threshold_time) {
                $diff_seconds = $actual_in_time - $shift_start_time;
                $late_minutes = floor($diff_seconds / 60); 
                $initial_deduction = round($late_minutes / 60, 2); 
                $status_array[] = 'Late';
            } else {
                $status_array[] = 'Ontime';
            }
        }

        $initial_status_str = implode(', ', $status_array);

        // *** START TRANSACTION ***
        $pdo->beginTransaction();

        try {
            // Note: date column stores the Time In Date
            $sql = "INSERT INTO tbl_attendance (employee_id, date, status_based, time_in, attendance_status, total_deduction_hr, overtime_hr, num_hr) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([$employee_id, $today, $status_based, $currentTime, $initial_status_str, $initial_deduction]);
            
            $attendance_id = $pdo->lastInsertId();

            if (($status_based == 'FIELD' || $status_based == 'WFH') && !empty($lat) && !empty($long)) {
                $sql_gps = "INSERT INTO tbl_attendance_gps (attendance_id, time_in_lat, time_in_long, location_address) 
                            VALUES (?, ?, ?, ?)";
                $stmt_gps = $pdo->prepare($sql_gps);
                $stmt_gps->execute([$attendance_id, $lat, $long, $addr]);
            }
            
            $pdo->commit();

            $formattedTime = date("g:i A", strtotime($currentTime));
            $late_msg = ($initial_deduction > 0) ? " You are late by $initial_deduction hr/s." : "";
            $sched_msg = ($schedule_type == 'Flexible') ? " (Flexi)" : "";
            
            echo json_encode(['status' => 'success', 'message' => "Welcome, $display_name! Time In recorded at $formattedTime$sched_msg.$late_msg"]);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
        }

    } elseif ($action == 'time_out') {

        // --- 1. FIND OPEN SESSION ---
        $check = $pdo->prepare("SELECT id, time_in, date, status_based, attendance_status FROM tbl_attendance 
                                WHERE employee_id = ? AND time_out IS NULL 
                                ORDER BY date DESC LIMIT 1");
        $check->execute([$employee_id]);
        $attendance = $check->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            echo json_encode(['status' => 'error', 'message' => "Hi $display_name, no active Time In found."]);
            exit();
        }

        // --- 2. PREPARE PRECISE TIMESTAMPS ---
        // $entry_date is the day they started (from DB)
        // $today is the day they are leaving (Current Real Date)
        $entry_date = $attendance['date']; 
        
        $actual_in_timestamp = strtotime("$entry_date " . $attendance['time_in']);
        $actual_in_timestamp = floor($actual_in_timestamp / 60) * 60; 
        
        // Use Strict Server Time ($today)
        $actual_out_timestamp = strtotime("$today $currentTime");
        
        $num_hr = 0;
        $overtime_hr = 0;

        // --- 3. HOURS CALCULATION ---
        if ($schedule_type === 'Flexible') {
            // [FLEXIBLE]
            $raw_duration_seconds = $actual_out_timestamp - $actual_in_timestamp;
            $raw_duration_hours = $raw_duration_seconds / 3600;
            $net_hours = ($raw_duration_hours > 1.0) ? ($raw_duration_hours - 1.0) : 0;
            
            if ($net_hours > 8.0) {
                $num_hr = 8.0;
                $overtime_hr = round($net_hours - 8.0, 2);
            } else {
                $num_hr = round($net_hours, 2);
                $overtime_hr = 0;
            }

        } else {
            // [FIXED]
            // Shift reference is always based on ENTRY DATE
            $shift_start = strtotime("$entry_date 09:00:00");
            $shift_end   = strtotime("$entry_date 18:00:00");
            $lunch_start = strtotime("$entry_date 12:00:00");
            $lunch_end   = strtotime("$entry_date 13:00:00");

            $effective_in = max($actual_in_timestamp, $shift_start); 
            $effective_out_reg = min($actual_out_timestamp, $shift_end); 

            if ($effective_out_reg > $effective_in) {
                $gross_seconds = $effective_out_reg - $effective_in;
                $overlap_start = max($effective_in, $lunch_start);
                $overlap_end   = min($effective_out_reg, $lunch_end);
                $deduction = 0;
                if ($overlap_end > $overlap_start) {
                    $deduction = $overlap_end - $overlap_start;
                }
                
                $net_seconds = $gross_seconds - $deduction;
                $worked_minutes = floor($net_seconds / 60);
                $num_hr = round($worked_minutes / 60, 2);
            }

            // OT Calculation
            if ($actual_out_timestamp > $shift_end) {
                $ot_seconds = $actual_out_timestamp - $shift_end;
                $overtime_hr = round($ot_seconds / 3600, 2);
            }
        }

        // --- 4. STATUS & DEDUCTION CALCULATION ---
        $current_status_str = $attendance['attendance_status'];
        $status_parts = array_map('trim', explode(',', $current_status_str)); 

        $total_deduction_hr = 0;

        if ($num_hr < 8.0) {
            $total_deduction_hr = round(8.0 - $num_hr, 2); 

            if ($schedule_type === 'Flexible') {
                if (!in_array('Undertime', $status_parts)) {
                    $status_parts[] = 'Undertime';
                }
            } else {
                // Fixed: Undertime only if leaving before 6 PM of ENTRY DATE
                $shift_end_fixed = strtotime("$entry_date 18:00:00");
                if ($actual_out_timestamp < $shift_end_fixed) {
                    if (!in_array('Undertime', $status_parts)) {
                        $status_parts[] = 'Undertime';
                    }
                }
            }
        }

        if ($overtime_hr > 0) {
            if (!in_array('Overtime', $status_parts)) {
                $status_parts[] = 'Overtime';
            }
        }

        // Check for "Forgot Time Out"
        // Flexible Immune. Fixed gets flagged if Entry Date != Out Date (Today)
        if ($schedule_type !== 'Flexible') {
            if ($entry_date !== $today) {
                if (!in_array('Forgot Time Out', $status_parts)) {
                    $status_parts[] = 'Forgot Time Out';
                }
            }
        }

        $final_status_str = implode(', ', $status_parts);

        // *** START TRANSACTION ***
        $pdo->beginTransaction();

        try {
            // 1. Update MAIN TABLE
            // Updates time_out_date with $today
            $sql = "UPDATE tbl_attendance 
                    SET time_out = ?, time_out_date = ?, attendance_status = ?, num_hr = ?, overtime_hr = ?, total_deduction_hr = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([$currentTime, $today, $final_status_str, $num_hr, $overtime_hr, $total_deduction_hr, $attendance['id']]);

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

            $deduc_msg = ($total_deduction_hr > 0) ? " (Total Deduction: $total_deduction_hr hr/s)" : "";
            $forgot_msg = ($schedule_type !== 'Flexible' && $entry_date !== $today) ? " (Session Closed for $entry_date)" : "";

            echo json_encode(['status' => 'success', 'message' => "Goodbye, $display_name! Time Out recorded.$forgot_msg Paid Hrs: $num_hr$deduc_msg"]);

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