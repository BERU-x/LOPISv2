<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db_connection.php'; // Adjust path as needed

try {
    $today = date('Y-m-d');
    
    // 1. GET TOTAL EMPLOYEES (For "Absent" calculation)
    $stmt_total = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5");
    $total_employees = (int)$stmt_total->fetchColumn();

    // 2. GET ATTENDANCE LOGS
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
        'absent' => 0 // Calculated later
    ];

    foreach ($raw_logs as $log) {
        // --- Calculate Stats ---
        $status_lower = strtolower($log['attendance_status']);
        if (strpos($status_lower, 'late') !== false) $stats['late']++;
        if (strpos($status_lower, 'ontime') !== false) $stats['ontime']++;

        // --- Process Logic for JSON (Formatting data for JS) ---
        
        // 1. Photo
        $photo = !empty($log['photo']) ? $log['photo'] : 'default.png';
        
        // 2. Times
        $time_in = date('h:i A', strtotime($log['time_in']));
        $is_active = ($log['time_out'] === null || $log['time_out'] === '00:00:00');
        $time_out = $is_active ? null : date('h:i A', strtotime($log['time_out']));
        
        // 3. Hours Worked
        $hours_str = '--';
        if (!$is_active) {
            if (!empty($log['num_hr']) && $log['num_hr'] > 0) {
                $hours_str = $log['num_hr'] . ' hrs';
            } else {
                $diff = strtotime($log['time_out']) - strtotime($log['time_in']);
                $hours_str = number_format($diff / 3600, 2) . ' hrs';
            }
        }

        // Build the row object
        $processed_logs[] = [
            'photo' => $photo,
            'fullname' => $log['firstname'] . ' ' . $log['lastname'],
            'department' => $log['department'],
            'emp_code' => $log['emp_code'],
            'time_in' => $time_in,
            'date_in' => $log['date'],
            'time_out' => $time_out, // Null if active
            'date_out' => !empty($log['time_out_date']) ? $log['time_out_date'] : '--',
            'status_raw' => $log['attendance_status'], // Send raw status, we render badges in JS
            'is_active' => $is_active,
            'hours' => $hours_str
        ];
    }

    // Final Math
    $stats['absent'] = max(0, $total_employees - $stats['present']);
    $stats['total_employees'] = $total_employees;

    echo json_encode([
        'stats' => $stats,
        'logs' => $processed_logs
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>