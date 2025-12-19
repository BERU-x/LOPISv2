<?php
// api/admin/attendance_live.php
// Provides real-time attendance tracking and summary stats for the dashboard.
header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION (Admin Only) ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php'; 

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

try {
    $today = date('Y-m-d');
    
    // 1. GET TOTAL ACTIVE EMPLOYEES
    $stmt_total = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5");
    $total_employees = (int)$stmt_total->fetchColumn();

    // 2. GET ATTENDANCE LOGS FOR TODAY
    // Joining with employees to get profile details for the live feed
    $sql = "SELECT 
                a.*, 
                e.firstname, e.lastname, e.photo, e.department, e.employee_id as emp_code
            FROM tbl_attendance a
            LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id
            WHERE a.date = :today
            ORDER BY a.time_in DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':today' => $today]);
    $raw_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. PROCESS DATA & CALCULATE STATS
    $processed_logs = [];
    $stats = [
        'present' => count($raw_logs),
        'late' => 0,
        'ontime' => 0,
        'absent' => 0,
        'total_employees' => $total_employees
    ];

    foreach ($raw_logs as $log) {
        // --- Calculate Stats ---
        $status_lower = strtolower($log['attendance_status'] ?? '');
        if (strpos($status_lower, 'late') !== false) $stats['late']++;
        if (strpos($status_lower, 'ontime') !== false) $stats['ontime']++;

        // --- Logic for JSON Formatting ---
        
        // Handle Photo
        $photo = (!empty($log['photo']) && file_exists(__DIR__ . '/../../assets/images/users/' . $log['photo'])) 
                 ? $log['photo'] 
                 : 'default.png';
        
        // Handle Clock-In Time
        $time_in = !empty($log['time_in']) ? date('h:i A', strtotime($log['time_in'])) : '--:--';
        
        // Determine if employee is still clocked in
        $is_active = (empty($log['time_out']) || $log['time_out'] === '00:00:00');
        $time_out = $is_active ? 'In Progress' : date('h:i A', strtotime($log['time_out']));
        
        // Calculate Hours Worked
        $hours_str = '--';
        if (!$is_active) {
            if (!empty($log['num_hr']) && $log['num_hr'] > 0) {
                $hours_str = number_format((float)$log['num_hr'], 2) . ' hrs';
            } else {
                // Calculation fallback if num_hr column is empty
                $diff = strtotime($log['time_out']) - strtotime($log['time_in']);
                $hours_str = number_format($diff / 3600, 2) . ' hrs';
            }
        }

        $processed_logs[] = [
            'log_id'      => (int)$log['id'],
            'photo'       => $photo,
            'fullname'    => trim($log['firstname'] . ' ' . $log['lastname']),
            'department'  => $log['department'] ?? 'Unassigned',
            'emp_code'    => $log['emp_code'],
            'time_in'     => $time_in,
            'time_out'    => $time_out,
            'date_in'     => $log['date'],
            'status'      => $log['attendance_status'] ?? 'Unknown',
            'is_active'   => $is_active,
            'hours'       => $hours_str
        ];
    }

    // Calculate Final Stats
    $stats['absent'] = max(0, $total_employees - $stats['present']);

    // 4. STANDARDIZED OUTPUT
    echo json_encode([
        'status' => 'success',
        'stats'  => $stats,
        'logs'   => $processed_logs,
        'server_time' => date('h:i A')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Query failed.',
        'details' => $e->getMessage()
    ]);
}
?>