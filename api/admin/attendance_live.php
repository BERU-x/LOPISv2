<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Set Timezone to ensure 6PM check is accurate
date_default_timezone_set('Asia/Manila');

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['usertype']) || ($_SESSION['usertype'] != 1 && $_SESSION['usertype'] != 0)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php'; 

try {
    $today = date('Y-m-d');
    
    // 1. GET TOTAL ACTIVE EMPLOYEES
    $stmt_total = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5");
    $total_employees = (int)$stmt_total->fetchColumn();

    // 2. GET ATTENDANCE LOGS
    // ⭐ Restored: Added 'e.schedule_type' back to the query
    $sql = "SELECT a.*, e.firstname, e.lastname, e.photo, e.department, e.employee_id as emp_code, e.schedule_type
            FROM tbl_attendance a
            LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id
            WHERE a.date = :today
            ORDER BY a.time_in DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':today' => $today]);
    $raw_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed_logs = [];
    $stats = [
        'present' => count($raw_logs),
        'late' => 0, 'ontime' => 0, 'absent' => 0,
        'total_employees' => $total_employees
    ];

    // Get current hour (0-23)
    $current_hour = (int)date('H');

    foreach ($raw_logs as $log) {
        $status_lower = strtolower($log['attendance_status'] ?? '');
        if (strpos($status_lower, 'late') !== false) $stats['late']++;
        if (strpos($status_lower, 'ontime') !== false) $stats['ontime']++;

        $is_active = (empty($log['time_out']) || $log['time_out'] === '00:00:00');
        $full_name = trim(($log['firstname'] ?? '') . ' ' . ($log['lastname'] ?? ''));
        
        $time_in_clean = !empty($log['time_in']) ? date('h:i A', strtotime($log['time_in'])) : '--:--';
        $time_out_clean = $is_active ? 'Working' : date('h:i A', strtotime($log['time_out']));

        // ⭐ RESTORED LOGIC: "Fixed" Schedule Check
        $schedule_type = $log['schedule_type'] ?? 'Flexible'; // Default to Flexible if null
        $is_fixed_schedule = (strcasecmp($schedule_type, 'Fixed') === 0);

        // Logic: Active + Fixed Schedule + Past 6:00 PM
        $is_missing_out = ($is_active && $is_fixed_schedule && $current_hour >= 18);

        // Prepare CSV Output String
        if ($is_missing_out) {
            $csv_clock_out = 'No Time Out';
        } elseif ($is_active) {
            $csv_clock_out = 'Working';
        } else {
            $csv_clock_out = $time_out_clean;
        }

        $processed_logs[] = [
            'photo'       => !empty($log['photo']) ? $log['photo'] : 'default.png',
            'fullname'    => $full_name,
            'department'  => $log['department'] ?? 'Unassigned',
            'emp_code'    => $log['emp_code'] ?? 'N/A',
            'time_in'     => $time_in_clean,
            'time_out'    => $time_out_clean,
            'status'      => $log['attendance_status'] ?? 'Pending',
            'is_active'   => (bool)$is_active,
            'is_missing_out' => (bool)$is_missing_out, // Flag for JS
            'schedule_type' => $schedule_type, // Useful for debugging
            'hours'       => (!$is_active) ? number_format((float)$log['num_hr'], 2) . ' hrs' : '--',
            'overtime'    => (float)($log['overtime_hr'] ?? 0),
            
            // Raw Data for CSV
            'raw_data' => [
                'name'      => $full_name,
                'clock_in'  => $time_in_clean,
                'clock_out' => $csv_clock_out, 
                'status'    => $log['attendance_status'] ?? 'Pending',
                'duration'  => (!$is_active) ? number_format((float)$log['num_hr'], 2) : '0.00'
            ]
        ];
    }

    $stats['absent'] = (int)max(0, $total_employees - $stats['present']);

    echo json_encode([
        'status' => 'success',
        'stats'  => $stats,
        'logs'   => $processed_logs,
        'server_time' => date('h:i A')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
}
?>