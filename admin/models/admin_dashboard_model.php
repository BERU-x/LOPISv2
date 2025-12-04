<?php
// models/admin_dashboard_model.php

function get_admin_dashboard_metrics($pdo) {
    $metrics = [
        'active_employees' => 0,
        'new_hires_month' => 0,
        'pending_leave_count' => 0,
        'pending_ca_count' => 0, // <--- CHANGED KEY
        'attendance_today' => 0 
    ];

    try {
        // 1. Active Employees
        $stmt = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE employment_status < 5");
        $metrics['active_employees'] = (int)$stmt->fetchColumn();

        // 2. New Hires This Month
        $stmt = $pdo->query("SELECT COUNT(id) FROM tbl_employees WHERE MONTH(created_on) = MONTH(NOW()) AND YEAR(created_on) = YEAR(NOW())");
        $metrics['new_hires_month'] = (int)$stmt->fetchColumn();

        // 3. Pending Leave Requests
        $stmt = $pdo->query("SELECT COUNT(id) FROM tbl_leave WHERE status = 0");
        $metrics['pending_leave_count'] = (int)$stmt->fetchColumn();
        
        // 4. ✅ REPLACED: Pending Cash Advances
        // Based on tbl_cash_advances structure provided
        $stmt = $pdo->query("SELECT COUNT(id) FROM tbl_cash_advances WHERE status = 'Pending'");
        $metrics['pending_ca_count'] = (int)$stmt->fetchColumn();

        // 5. Today's Attendance
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) FROM tbl_attendance WHERE date = CURDATE()");
        $stmt->execute();
        $metrics['attendance_today'] = (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Dashboard Metrics Error: " . $e->getMessage());
    }

    return $metrics;
}

// Fetch Department Distribution
function get_dept_distribution_data($pdo) {
    $data = [];
    try {
        $stmt = $pdo->query("SELECT department, COUNT(id) as count FROM tbl_employees WHERE employment_status < 5 GROUP BY department ORDER BY count DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[$row['department']] = (int)$row['count'];
        }
    } catch (PDOException $e) {
        error_log("Dept Data Error: " . $e->getMessage());
    }
    return $data;
}

// Fetch Payroll History based on your table structure
function get_payroll_history($pdo) {
    $history = ['labels' => [], 'data' => []];
    try {
        // We SUM() the net_pay for everyone sharing the same cut_off_end date
        // This groups individual employee payslips into one big "Pay Run" total
        $sql = "SELECT cut_off_end, SUM(net_pay) as total_payout 
                 FROM tbl_payroll 
                 WHERE status = 1 
                 GROUP BY cut_off_end
                 ORDER BY cut_off_end DESC 
                 LIMIT 6";
                
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sort ascending for the chart (Oldest -> Newest)
        $results = array_reverse($results);

        foreach ($results as $row) {
            // Format Date (e.g., "Oct 15")
            $dateLabel = date("M d", strtotime($row['cut_off_end'])); 
            $history['labels'][] = $dateLabel;
            $history['data'][] = (float)$row['total_payout'];
        }
    } catch (PDOException $e) {
        error_log("Payroll History Error: " . $e->getMessage());
    }
    return $history;
}

// ✅ NEW: Fetch Upcoming Leaves
function get_upcoming_leaves($pdo, $limit = 5) {
    try {
        // Get approved leaves that start today or in the future, ordered soonest first
        $sql = "SELECT t1.start_date, t1.end_date, t1.leave_type, t1.days_count, t2.firstname, t2.lastname
                FROM tbl_leave t1
                JOIN tbl_employees t2 ON t1.employee_id = t2.employee_id
                WHERE t1.status = 1 AND t1.end_date >= CURDATE()
                ORDER BY t1.start_date ASC
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Upcoming Leaves Error: " . $e->getMessage());
        return [];
    }
}

// ✅ NEW: Fetch Upcoming Holidays
function get_upcoming_holidays($pdo, $limit = 5) {
    try {
        // Get holidays that are today or in the future, ordered soonest first
        $sql = "SELECT holiday_date, holiday_name, holiday_type
                FROM tbl_holidays
                WHERE holiday_date >= CURDATE()
                ORDER BY holiday_date ASC
                LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Upcoming Holidays Error: " . $e->getMessage());
        return [];
    }
}
?>