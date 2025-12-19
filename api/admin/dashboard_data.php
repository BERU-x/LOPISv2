<?php
/**
 * api/admin/dashboard_data.php
 * Provides aggregated metrics and chart data for the Admin Dashboard.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// 1. AUTHENTICATION CHECK
// Adjust 'usertype' and value (e.g., 1 for Admin) based on your system
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// 2. DATABASE CONNECTION
require_once __DIR__ . '/../../db_connection.php'; 

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// =================================================================================
// 3. HELPER FUNCTIONS
// =================================================================================

/**
 * Fetches top-level numeric metrics
 */
function get_admin_dashboard_metrics($pdo) {
    $metrics = [
        'active_employees' => 0, 
        'new_hires_month' => 0, 
        'pending_leave_count' => 0, 
        'pending_ca_count' => 0, 
        'attendance_today' => 0
    ];
    
    try {
        // Active Employees (employment_status < 5 covers Probationary, Regular, etc.)
        $metrics['active_employees'] = (int)$pdo->query("SELECT COUNT(*) FROM tbl_employees WHERE employment_status < 5")->fetchColumn();
        
        // New Hires This Month
        $metrics['new_hires_month'] = (int)$pdo->query("SELECT COUNT(*) FROM tbl_employees WHERE MONTH(created_on) = MONTH(CURRENT_DATE) AND YEAR(created_on) = YEAR(CURRENT_DATE)")->fetchColumn();
        
        // Pending Leave Count (Assuming 0 is the status for 'Pending')
        $metrics['pending_leave_count'] = (int)$pdo->query("SELECT COUNT(*) FROM tbl_leave WHERE status = 0")->fetchColumn();
        
        // Pending Cash Advance Count
        $metrics['pending_ca_count'] = (int)$pdo->query("SELECT COUNT(*) FROM tbl_cash_advances WHERE status = 'Pending'")->fetchColumn();
        
        // Employees Clocked In Today
        $metrics['attendance_today'] = (int)$pdo->query("SELECT COUNT(DISTINCT employee_id) FROM tbl_attendance WHERE date = CURRENT_DATE")->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Dashboard Metrics Error: " . $e->getMessage());
    }
    return $metrics;
}

/**
 * Data for Department Distribution (Pie/Donut Charts)
 */
function get_dept_distribution_data($pdo) {
    $data = [];
    try {
        $stmt = $pdo->query("SELECT department, COUNT(*) as count FROM tbl_employees WHERE employment_status < 5 GROUP BY department ORDER BY count DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[$row['department']] = (int)$row['count'];
        }
    } catch (PDOException $e) {
        error_log("Dept Distribution Error: " . $e->getMessage());
    }
    return $data;
}

/**
 * Data for Payroll Trends (Line/Bar Charts)
 */
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
    } catch (PDOException $e) {
        error_log("Payroll History Error: " . $e->getMessage());
    }
    return $history;
}

/**
 * Upcoming Leaves for Sidebar List
 */
function get_upcoming_leaves($pdo, $limit = 5) {
    try {
        $sql = "SELECT t1.start_date, t1.end_date, t1.leave_type, t2.firstname, t2.lastname, t2.photo, t2.employee_id 
                FROM tbl_leave t1 
                JOIN tbl_employees t2 ON t1.employee_id = t2.employee_id 
                WHERE t1.status = 1 AND t1.end_date >= CURRENT_DATE 
                ORDER BY t1.start_date ASC 
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Upcoming Holidays for Sidebar List
 */
function get_upcoming_holidays($pdo, $limit = 5) {
    try {
        $sql = "SELECT holiday_date, holiday_name, holiday_type FROM tbl_holidays 
                WHERE holiday_date >= CURRENT_DATE 
                ORDER BY holiday_date ASC 
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// =================================================================================
// 4. EXECUTE AND RETURN
// =================================================================================

try {
    $response = [
        'status' => 'success',
        'metrics' => get_admin_dashboard_metrics($pdo),
        'dept_data' => get_dept_distribution_data($pdo),
        'payroll_history' => get_payroll_history($pdo),
        'upcoming_leaves' => get_upcoming_leaves($pdo),
        'upcoming_holidays' => get_upcoming_holidays($pdo),
        'last_updated' => date('Y-m-d H:i:s')
    ];
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Critical failure in data aggregation.', 
        'details' => $e->getMessage()
    ]);
}
?>