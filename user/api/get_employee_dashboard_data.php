<?php
// api/get_employee_dashboard_data.php
header('Content-Type: application/json');

// 1. Setup & Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db_connection.php'; 

$response = [
    'status' => 'success',
    'data' => []
];

// Check Authentication
if (!isset($_SESSION['employee_id']) || !isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];
$current_year = date('Y');

// --- Initialize Default Data Structure ---
$data = [
    'leave_stats' => ['pending_count' => 0, 'pending_days' => 0.0, 'last_submission' => 'N/A'],
    'leave_balances' => ['vacation' => 0.0, 'sick' => 0.0],
    'overtime' => ['pending_count' => 0, 'pending_hours' => 0.0],
    'attendance' => ['time_in' => 'N/A', 'status_label' => 'No Data', 'status_color' => 'secondary'],
    'loans' => ['balance' => 0.0, 'label' => 'No Active Loans'],
    'upcoming_holidays' => [],
    'weekly_hours' => [0, 0, 0, 0, 0, 0, 0] // Default 7 days (Sun-Sat)
];

try {
    // 1. PENDING LEAVE
    $stmt = $pdo->prepare("SELECT (SELECT COUNT(id) FROM tbl_leave WHERE employee_id = :eid AND status = '0') AS count, (SELECT SUM(days_count) FROM tbl_leave WHERE employee_id = :eid AND status = '0') AS days, (SELECT MAX(created_on) FROM tbl_leave WHERE employee_id = :eid) AS last_sub");
    $stmt->execute([':eid' => $employee_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    if($stats) {
        $data['leave_stats']['pending_count'] = intval($stats['count']);
        $data['leave_stats']['pending_days'] = floatval($stats['days'] ?? 0);
        $data['leave_stats']['last_submission'] = $stats['last_sub'] ? date('M d, Y', strtotime($stats['last_sub'])) : 'N/A';
    }

    // =================================================================
    // 2. LEAVE CREDITS & BALANCES (Updated for VL + SL + EL)
    // =================================================================
    
    // A. Get Total Credits from tbl_leave_credits
    $stmt_credits = $pdo->prepare("
        SELECT vacation_leave_total, sick_leave_total, emergency_leave_total 
        FROM tbl_leave_credits 
        WHERE employee_id = :eid AND year = :year
    ");
    $stmt_credits->execute([':eid' => $employee_id, ':year' => $current_year]);
    $credits = $stmt_credits->fetch(PDO::FETCH_ASSOC);
    
    $vacation_credits = floatval($credits['vacation_leave_total'] ?? 0);
    $sick_credits = floatval($credits['sick_leave_total'] ?? 0);
    $emergency_credits = floatval($credits['emergency_leave_total'] ?? 0); // ⭐ ADDED
    
    // Total Credits available at start of year
    $total_possible = $vacation_credits + $sick_credits + $emergency_credits; 
    
    // B. Get Used Credits from tbl_leave
    $stmt_used = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN leave_type = 'Vacation Leave' THEN days_count ELSE 0 END) AS used_vl,
            SUM(CASE WHEN leave_type = 'Sick Leave' THEN days_count ELSE 0 END) AS used_sl,
            SUM(CASE WHEN leave_type = 'Emergency Leave' THEN days_count ELSE 0 END) AS used_el  -- ⭐ ADDED
        FROM tbl_leave
        WHERE employee_id = :eid AND status = 1 AND YEAR(start_date) = :year
    ");
    $stmt_used->execute([':eid' => $employee_id, ':year' => $current_year]);
    $used = $stmt_used->fetch(PDO::FETCH_ASSOC);
    
    $used_vl = floatval($used['used_vl'] ?? 0);
    $used_sl = floatval($used['used_sl'] ?? 0);
    $used_el = floatval($used['used_el'] ?? 0); // ⭐ ADDED

    // Total Used so far
    $total_used = $used_vl + $used_sl + $used_el;

    // C. Calculate Remaining for individual types (Optional for cards)
    $rem_vl = max(0, $vacation_credits - $used_vl);
    $rem_sl = max(0, $sick_credits - $used_sl);
    // $rem_el = max(0, $emergency_credits - $used_el); // Logic if you need EL specific card

    // D. Calculate TOTAL REMAINING for the Chart
    $total_remaining = max(0, $total_possible - $total_used);

    // E. Populate Data Array
    $data['leave_balances']['vacation'] = $rem_vl;
    $data['leave_balances']['sick'] = $rem_sl;
    
    // ⭐ TOTALS FOR CHART
    $data['leave_balances']['total_remaining'] = $total_remaining;
    $data['leave_balances']['total_used'] = $total_used;
    // 3. OVERTIME (Pending Stats)
    $stmt_ot = $pdo->prepare("SELECT COUNT(id) as count, SUM(hours_requested) as hours FROM tbl_overtime WHERE employee_id = :eid AND status = 'Pending'");
    $stmt_ot->execute([':eid' => $employee_id]);
    $ot = $stmt_ot->fetch(PDO::FETCH_ASSOC);
    $data['overtime']['pending_count'] = intval($ot['count']??0);
    $data['overtime']['pending_hours'] = floatval($ot['hours']??0);

    // 4. ATTENDANCE (TODAY)
    $stmt_attn = $pdo->prepare("SELECT time_in, attendance_status FROM tbl_attendance WHERE employee_id = :eid AND date = CURRENT_DATE() ORDER BY time_in DESC LIMIT 1");
    $stmt_attn->execute([':eid' => $employee_id]);
    $last = $stmt_attn->fetch(PDO::FETCH_ASSOC);
    if ($last && $last['time_in']) {
        $data['attendance']['time_in'] = date('h:i A', strtotime($last['time_in']));
        $data['attendance']['status_label'] = $last['attendance_status'];
        if(strpos($last['attendance_status'], 'Late')!==false) $data['attendance']['status_color'] = 'danger';
        elseif($last['attendance_status'] == 'Ontime' || $last['attendance_status'] == 'Present') $data['attendance']['status_color'] = 'success';
        else $data['attendance']['status_color'] = 'primary';
    }

    // 5. LOANS
    $stmt_fin = $pdo->prepare("SELECT sss_loan_balance + pagibig_loan_balance + company_loan_balance as total FROM tbl_employee_financials WHERE employee_id = :eid LIMIT 1");
    $stmt_fin->execute([':eid' => $employee_id]);
    $fin = $stmt_fin->fetch(PDO::FETCH_ASSOC);
    $data['loans']['balance'] = floatval($fin['total']??0);
    $data['loans']['label'] = ($data['loans']['balance'] > 0) ? 'Active Loans' : 'No Active Loans';

    // 6. HOLIDAYS
    $stmt_hol = $pdo->prepare("SELECT holiday_date, holiday_name, holiday_type FROM tbl_holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT 5");
    $stmt_hol->execute();
    $data['upcoming_holidays'] = $stmt_hol->fetchAll(PDO::FETCH_ASSOC);

    // =================================================================
    // 7. REAL WEEKLY ATTENDANCE CHART DATA (Updated Logic)
    //    Logic: MAX(num_hr, 8) + hours_approved (from overtime)
    // =================================================================
    
    // A. Determine start (Sunday) and end (Saturday) of current week
    if (date('w') == 0) {
        $start_date = date('Y-m-d'); // Today is Sunday
    } else {
        $start_date = date('Y-m-d', strtotime('last sunday'));
    }
    $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));

    // B. Initialize array [Sun, Mon, Tue, Wed, Thu, Fri, Sat]
    $weekly_hours = [0, 0, 0, 0, 0, 0, 0];

    // C. Fetch Regular Hours (num_hr) from Attendance
    $stmt_week = $pdo->prepare("
        SELECT date, num_hr
        FROM tbl_attendance 
        WHERE employee_id = :eid 
        AND date BETWEEN :start_date AND :end_date
    ");
    $stmt_week->execute([
        ':eid' => $employee_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $attendance_logs = $stmt_week->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attendance_logs as $log) {
        $day_index = date('w', strtotime($log['date']));
        $raw_hours = floatval($log['num_hr']);
        
        // RULE: If raw hours > 8, cap it at 8. Otherwise use actual.
        $capped_hours = ($raw_hours > 8) ? 8.0 : $raw_hours;
        
        $weekly_hours[$day_index] += $capped_hours;
    }

    // D. Fetch APPROVED Overtime from tbl_overtime
    $stmt_approved_ot = $pdo->prepare("
        SELECT ot_date, hours_approved
        FROM tbl_overtime 
        WHERE employee_id = :eid 
        AND status = 'Approved'
        AND ot_date BETWEEN :start_date AND :end_date
    ");
    $stmt_approved_ot->execute([
        ':eid' => $employee_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $ot_logs = $stmt_approved_ot->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ot_logs as $log) {
        $day_index = date('w', strtotime($log['ot_date']));
        // Add approved overtime ON TOP of the regular hours
        $weekly_hours[$day_index] += floatval($log['hours_approved']);
    }
    
    $data['weekly_hours'] = $weekly_hours;


} catch (PDOException $e) {
    error_log("API Error: " . $e->getMessage());
}

$response['data'] = $data;
echo json_encode($response);
?>