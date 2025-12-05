<?php
// fetch/chart_data.php - AJAX Endpoint for Employee Dashboard Charts (Total Hours + Approved OT)

// Assume standard includes and session setup
require_once '../../db_connection.php'; 
session_start();

$employee_id = $_SESSION['employee_id'] ?? null; 
$current_year = date('Y'); // Use current year for leave calculations

$response = [
    'attendance_labels' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    'attendance_data' => [0, 0, 0, 0, 0, 0, 0], 
    'leave_available' => 0,
    'leave_used' => 0,
    'MAX_LEAVE' => 0, // Dynamic maximum will be calculated
    'STANDARD_HOURS' => 8 // Standard daily hours for visualization
];

if (!$employee_id || !isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

try {
    // --- 1. LEAVE BALANCES (Doughnut Chart Data) ---
    // (This section remains largely the same, ensuring robust calculation)
    
    // 1A. Fetch total leave credits for the current year
    $stmt_credits = $pdo->prepare("
        SELECT 
            (vacation_leave_total + sick_leave_total + emergency_leave_total) AS total_credits
        FROM tbl_leave_credits
        WHERE employee_id = :eid AND year = :year
    ");
    $stmt_credits->execute([':eid' => $employee_id, ':year' => $current_year]);
    $credits = $stmt_credits->fetch(PDO::FETCH_ASSOC);
    
    $total_credits = floatval($credits['total_credits'] ?? 0);
    $response['MAX_LEAVE'] = $total_credits;

    // 1B. Fetch used leave days (status=1 means approved/used)
    $stmt_used = $pdo->prepare("
        SELECT SUM(days_count) AS used_days
        FROM tbl_leave
        WHERE employee_id = :eid AND status = 1 
          AND YEAR(start_date) = :year
    ");
    $stmt_used->execute([':eid' => $employee_id, ':year' => $current_year]);
    $used_days = floatval($stmt_used->fetchColumn() ?? 0);

    // 1C. Calculate available and used for the chart
    $total_available_leave = max(0, $total_credits - $used_days);
    
    $response['leave_available'] = floatval($total_available_leave);
    $response['leave_used'] = $used_days;

    // --- 2. WEEKLY ATTENDANCE DATA (Line Chart: Total Hours + Approved OT) ---
    
    // 1. Define the week boundaries (Fixing the previous date inversion issue)
    $start_of_week_date = new DateTime(date('Y-m-d', strtotime('last sunday')));
    $end_of_week_date = new DateTime(date('Y-m-d')); 
    
    $start_date_str = $start_of_week_date->format('Y-m-d');
    $end_date_str = $end_of_week_date->format('Y-m-d');
    
    // 2. Aggregate Attendance Hours (num_hr)
    $stmt_attn = $pdo->prepare("
        SELECT 
            date,
            SUM(num_hr) as daily_attendance_hours
        FROM tbl_attendance
        WHERE employee_id = :eid 
          AND date >= :start_date AND date <= :end_date
        GROUP BY date 
    ");
    $stmt_attn->execute([
        ':eid' => $employee_id, 
        ':start_date' => $start_date_str, 
        ':end_date' => $end_date_str
    ]);
    // Map data to date array: [YYYY-MM-DD => hours]
    $attendance_map = array_column($stmt_attn->fetchAll(PDO::FETCH_ASSOC), 'daily_attendance_hours', 'date');

    // 3. Aggregate Approved Overtime Hours (hours_approved)
    $stmt_ot = $pdo->prepare("
        SELECT 
            ot_date as date,
            SUM(hours_approved) as daily_ot_hours
        FROM tbl_overtime
        WHERE employee_id = :eid 
          AND status = 'Approved'
          AND ot_date >= :start_date AND ot_date <= :end_date
        GROUP BY ot_date
    ");
    $stmt_ot->execute([
        ':eid' => $employee_id, 
        ':start_date' => $start_date_str, 
        ':end_date' => $end_date_str
    ]);
    // Map data to date array: [YYYY-MM-DD => hours]
    $ot_map = array_column($stmt_ot->fetchAll(PDO::FETCH_ASSOC), 'daily_ot_hours', 'date');
    
    // 4. Combine and Prepare Chart Data
    
    $chart_data = [];
    $all_days = $response['attendance_labels']; 
    $today_dt = new DateTime();
    
    // Iterate through the 7 days of the week
    foreach ($all_days as $day_abbr) {
        $day_dt = new DateTime(date('Y-m-d', strtotime($day_abbr . ' this week')));
        $day_date_str = $day_dt->format('Y-m-d');
        $is_today_or_past = ($day_dt <= $today_dt);
        
        if ($is_today_or_past) {
            // Get hours from attendance and approved overtime
            $attn_hours = floatval($attendance_map[$day_date_str] ?? 0);
            $ot_hours = floatval($ot_map[$day_date_str] ?? 0);
            
            $total_hours = $attn_hours + $ot_hours;
            
            // Map day abbreviation (D) to total hours
            $chart_data[] = $total_hours;
        } else {
            // Future days are null to stop the chart line
            $chart_data[] = null; 
        }
    }

    $response['attendance_data'] = $chart_data;

} catch (PDOException $e) {
    error_log("Chart Data Fetch Error: " . $e->getMessage());
    // Fallback: send default zero data on DB error
}

header('Content-Type: application/json');
echo json_encode($response);
?>