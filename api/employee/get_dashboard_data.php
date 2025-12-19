<?php
/**
 * api/employee/get_dashboard_data.php
 * Fetches all real-time stats for the Employee ESS Dashboard.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff'); // Security header

// 1. SESSION & AUTHENTICATION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php'; 

$employee_id = $_SESSION['employee_id'];
$current_year = date('Y');

// --- Initialize Default Data Structure ---
$data = [
    'leave_stats' => [
    'pending_count' => 0, 
    'pending_days' => 0.0, 
    'last_submission' => 'N/A'
    ],
    'leave_balances' => [
    'vacation' => 0.0, 
    'sick' => 0.0, 
    'emergency' => 0.0,
    'total_remaining' => 0.0, 
    'total_used' => 0.0
    ],
    'overtime' => [
    'pending_count' => 0, 
    'pending_hours' => 0.0
    ],
    'attendance' => [
    'time_in' => 'N/A', 
    'status_label' => 'No Data', 
    'status_color' => 'secondary'
    ],
    'loans' => [
    'balance' => 0.0, 
    'label' => 'No Active Loans'
    ],
    'upcoming_holidays' => [],
    'weekly_hours' => [0, 0, 0, 0, 0, 0, 0] // Sun to Sat
];

try {
    // =================================================================
    // 1. LEAVE SUMMARY (Pending & Last Activity)
    // =================================================================
    $stmt = $pdo->prepare("
    SELECT 
        COUNT(id) AS count, 
        SUM(CASE WHEN status = 0 THEN days_count ELSE 0 END) AS days, 
        MAX(created_on) AS last_sub 
    FROM tbl_leave 
    WHERE employee_id = :eid AND YEAR(start_date) = :year
    ");
    $stmt->execute([':eid' => $employee_id, ':year' => $current_year]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    if($stats) {
    $data['leave_stats']['pending_count'] = (int)$stats['count'];
    $data['leave_stats']['pending_days'] = (float)($stats['days'] ?? 0);
    $data['leave_stats']['last_submission'] = $stats['last_sub'] ? date('M d, Y', strtotime($stats['last_sub'])) : 'N/A';
    }

    // =================================================================
    // 2. LEAVE CREDITS & BALANCES (VL + SL + EL)
    // =================================================================
    // A. Total Credits
    $stmt_credits = $pdo->prepare("
    SELECT vacation_leave_total, sick_leave_total, emergency_leave_total 
    FROM tbl_leave_credits 
    WHERE employee_id = :eid AND year = :year
    ");
    $stmt_credits->execute([':eid' => $employee_id, ':year' => $current_year]);
    $credits = $stmt_credits->fetch(PDO::FETCH_ASSOC);
    
    $v_total = (float)($credits['vacation_leave_total'] ?? 0);
    $s_total = (float)($credits['sick_leave_total'] ?? 0);
    $e_total = (float)($credits['emergency_leave_total'] ?? 0);
    
    // B. Total Used (Approved status = 1)
    $stmt_used = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN leave_type = 'Vacation Leave' THEN days_count ELSE 0 END) AS used_vl,
        SUM(CASE WHEN leave_type = 'Sick Leave' THEN days_count ELSE 0 END) AS used_sl,
        SUM(CASE WHEN leave_type = 'Emergency Leave' THEN days_count ELSE 0 END) AS used_el
    FROM tbl_leave
    WHERE employee_id = :eid AND status = 1 AND YEAR(start_date) = :year
    ");
    $stmt_used->execute([':eid' => $employee_id, ':year' => $current_year]);
    $used = $stmt_used->fetch(PDO::FETCH_ASSOC);
    
    $u_vl = (float)($used['used_vl'] ?? 0);
    $u_sl = (float)($used['used_sl'] ?? 0);
    $u_el = (float)($used['used_el'] ?? 0);

    $data['leave_balances']['vacation'] = max(0, $v_total - $u_vl);
    $data['leave_balances']['sick'] = max(0, $s_total - $u_sl);
    $data['leave_balances']['emergency'] = max(0, $e_total - $u_el);
    $data['leave_balances']['total_used'] = $u_vl + $u_sl + $u_el;
    $data['leave_balances']['total_remaining'] = $data['leave_balances']['vacation'] + $data['leave_balances']['sick'] + $data['leave_balances']['emergency'];

    // =================================================================
    // 3. OVERTIME (Pending Stats)
    // =================================================================
    $stmt_ot = $pdo->prepare("SELECT COUNT(id) as count, SUM(hours_requested) as hours FROM tbl_overtime WHERE employee_id = :eid AND status = 'Pending'");
    $stmt_ot->execute([':eid' => $employee_id]);
    $ot = $stmt_ot->fetch(PDO::FETCH_ASSOC);
    $data['overtime']['pending_count'] = (int)($ot['count'] ?? 0);
    $data['overtime']['pending_hours'] = (float)($ot['hours'] ?? 0);

    // =================================================================
    // 4. ATTENDANCE (Today)
    // =================================================================
    $stmt_attn = $pdo->prepare("SELECT time_in, attendance_status FROM tbl_attendance WHERE employee_id = :eid AND date = CURRENT_DATE() LIMIT 1");
    $stmt_attn->execute([':eid' => $employee_id]);
    $last = $stmt_attn->fetch(PDO::FETCH_ASSOC);
    if ($last) {
    $data['attendance']['time_in'] = date('h:i A', strtotime($last['time_in']));
    $data['attendance']['status_label'] = $last['attendance_status'];
            
    $status_map = ['Late' => 'danger', 'Ontime' => 'success', 'Present' => 'primary'];
    $data['attendance']['status_color'] = $status_map[$last['attendance_status']] ?? 'secondary';
    }

    // =================================================================
    // 5. LOANS (Running Balance from Ledger)
    // =================================================================
    $stmt_fin = $pdo->prepare("
    SELECT SUM(running_balance) as total 
    FROM (
        SELECT running_balance FROM tbl_employee_ledger 
        WHERE employee_id = :eid AND category IN ('SSS_Loan', 'Pagibig_Loan', 'Company_Loan')
        AND id IN (SELECT MAX(id) FROM tbl_employee_ledger WHERE employee_id = :eid GROUP BY category)
    ) as current_loans
    ");
    $stmt_fin->execute([':eid' => $employee_id]);
    $fin = $stmt_fin->fetch(PDO::FETCH_ASSOC);
    $data['loans']['balance'] = (float)($fin['total'] ?? 0);
    $data['loans']['label'] = ($data['loans']['balance'] > 0) ? 'Active Debt Balance' : 'No Active Loans';

    // =================================================================
    // 6. UPCOMING HOLIDAYS
    // =================================================================
    $stmt_hol = $pdo->prepare("SELECT holiday_date, holiday_name, holiday_type FROM tbl_holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT 5");
    $stmt_hol->execute();
    $data['upcoming_holidays'] = $stmt_hol->fetchAll(PDO::FETCH_ASSOC);

// =================================================================
    // 7. WEEKLY PRODUCTIVITY CHART DATA (FIXED)
    // =================================================================
    
    // A. Generate the Date Keys for the Current Week (Sun - Sat) in PHP
    $weekly_data = [];
    $today_date = new DateTime();
    
    // Find the previous Sunday (or today if it is Sunday)
    $start_of_week = clone $today_date;
    if ($today_date->format('w') != 0) {
        $start_of_week->modify('last sunday');
    }
    
    // Initialize array with 0.0 for all 7 days
    for ($i = 0; $i < 7; $i++) {
        $d = clone $start_of_week;
        $d->modify("+$i days");
        $weekly_data[$d->format('Y-m-d')] = 0.0;
    }

    $start_date_str = array_key_first($weekly_data);
    $end_date_str = array_key_last($weekly_data);

    // B. Fetch Regular Attendance Data
    $stmt_att = $pdo->prepare("
        SELECT date, num_hr 
        FROM tbl_attendance 
        WHERE employee_id = :eid 
        AND date BETWEEN :start AND :end
    ");
    $stmt_att->execute([
        ':eid' => $employee_id, 
        ':start' => $start_date_str, 
        ':end' => $end_date_str
    ]);
    while ($row = $stmt_att->fetch(PDO::FETCH_ASSOC)) {
        if (isset($weekly_data[$row['date']])) {
            // Cap regular hours at 8 per day (optional business rule)
            $hours = (float)$row['num_hr'];
            $weekly_data[$row['date']] += ($hours > 8 ? 8 : $hours);
        }
    }

    // C. Fetch Overtime Data
    $stmt_ot = $pdo->prepare("
        SELECT ot_date, hours_approved 
        FROM tbl_overtime 
        WHERE employee_id = :eid 
        AND status = 'Approved'
        AND ot_date BETWEEN :start AND :end
    ");
    $stmt_ot->execute([
        ':eid' => $employee_id, 
        ':start' => $start_date_str, 
        ':end' => $end_date_str
    ]);
    while ($row = $stmt_ot->fetch(PDO::FETCH_ASSOC)) {
        if (isset($weekly_data[$row['ot_date']])) {
            $weekly_data[$row['ot_date']] += (float)$row['hours_approved'];
        }
    }

    // D. Flatten array for Chart.js (just the values)
    $data['weekly_hours'] = array_values($weekly_data);

} catch (PDOException $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
    exit;
}

echo json_encode(['status' => 'success', 'data' => $data]);