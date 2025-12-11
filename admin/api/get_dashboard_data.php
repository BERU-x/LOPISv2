<?php
// api/dashboard_data.php
// Set header to JSON so JS knows how to parse it
header('Content-Type: application/json');

// 1. DATABASE CONNECTION
// Adjust this path to point to your actual connection file
require_once __DIR__ . '/../../db_connection.php'; 

// Check for database connection failure immediately
if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// =================================================================================
// 2. HELPER FUNCTIONS (Metrics Retrieval)
// =================================================================================

function get_admin_dashboard_metrics($pdo) {
    $metrics = ['active_employees' => 0, 'new_hires_month' => 0, 'pending_leave_count' => 0, 'pending_ca_count' => 0, 'attendance_today' => 0];
    try {
        // Active Employees (employment_status < 5, excluding resigned/terminated)
        $metrics['active_employees'] = (int)$pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5")->fetchColumn();
        
        // New Hires This Month
        $metrics['new_hires_month'] = (int)$pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE MONTH(created_on) = MONTH(NOW()) AND YEAR(created_on) = YEAR(NOW())")->fetchColumn();
        
        // Pending Leave Count (status = 0)
        $metrics['pending_leave_count'] = (int)$pdo->query("SELECT COUNT(id) FROM tbl_leave WHERE status = 0")->fetchColumn();
        
        // Pending Cash Advance Count
        $metrics['pending_ca_count'] = (int)$pdo->query("SELECT COUNT(id) FROM tbl_cash_advances WHERE status = 'Pending'")->fetchColumn();
        
        // Employees Clocked In Today
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM tbl_attendance WHERE date = CURDATE()");
        $stmt->execute();
        $metrics['attendance_today'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* Log error silently */ }
    return $metrics;
}

function get_dept_distribution_data($pdo) {
    $data = [];
    try {
        $stmt = $pdo->query("SELECT department, COUNT(id) as count FROM tbl_employees WHERE employment_status < 5 GROUP BY department ORDER BY count DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[$row['department']] = (int)$row['count']; // Key: Department Name, Value: Count
        }
    } catch (PDOException $e) { /* Log error silently */ }
    return $data;
}

function get_payroll_history($pdo) {
    $history = ['labels' => [], 'data' => []];
    try {
        // Fetches last 6 paid payroll cut-offs
        $stmt = $pdo->query("SELECT cut_off_end, SUM(net_pay) as total_payout FROM tbl_payroll WHERE status = 1 GROUP BY cut_off_end ORDER BY cut_off_end DESC LIMIT 6");
        $results = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        foreach ($results as $row) {
            $history['labels'][] = date("M d", strtotime($row['cut_off_end']));
            $history['data'][] = (float)$row['total_payout'];
        }
    } catch (PDOException $e) { /* Log error silently */ }
    return $history;
}

function get_upcoming_leaves($pdo, $limit = 5) {
    try {
        $sql = "SELECT t1.start_date, t1.end_date, t1.leave_type, t2.firstname, t2.lastname, t2.photo, t2.employee_id 
                FROM tbl_leave t1 
                JOIN tbl_employees t2 ON t1.employee_id = t2.employee_id 
                WHERE t1.status = 1 AND t1.end_date >= CURDATE() 
                ORDER BY t1.start_date ASC 
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function get_upcoming_holidays($pdo, $limit = 5) {
    try {
        $sql = "SELECT holiday_date, holiday_name, holiday_type FROM tbl_holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

// =================================================================================
// 3. EXECUTE AND RETURN (Standardized Output)
// =================================================================================
try {
    $response = [
        'status' => 'success', // Standard success indicator
        'metrics' => get_admin_dashboard_metrics($pdo),
        'dept_data' => get_dept_distribution_data($pdo), // For pie/donut chart 
        'payroll_history' => get_payroll_history($pdo), // For line/bar chart 
        'upcoming_leaves' => get_upcoming_leaves($pdo),
        'upcoming_holidays' => get_upcoming_holidays($pdo)
    ];
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load dashboard data.', 'details' => $e->getMessage()]);
}
?>