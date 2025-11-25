<?php
// models/admin_dashboard_model.php

function get_admin_dashboard_metrics($pdo) {
    $metrics = [
        'active_employees' => 0,
        'new_hires_month' => 0,
        'pending_leave_count' => 0,
        'payroll_status' => 'Pending',
        'attendance_today' => 0 // <--- NEW KEY
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
        
        // 4. Payroll Status
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM tbl_payroll 
                               WHERE MONTH(cut_off_end) = MONTH(NOW()) 
                               AND YEAR(cut_off_end) = YEAR(NOW()) 
                               AND status = 1");
        $stmt->execute();
        $metrics['payroll_status'] = ((int)$stmt->fetchColumn() > 0) ? 'Completed' : 'Pending';

        // 5. ✅ NEW: Today's Attendance
        // Counts distinct employees who have a record for today
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

// ✅ FIXED: Fetch Payroll History based on your table structure
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
            // Format Date (e.g., "Oct 15, 2023")
            $dateLabel = date("M d", strtotime($row['cut_off_end'])); 
            $history['labels'][] = $dateLabel;
            $history['data'][] = (float)$row['total_payout'];
        }
    } catch (PDOException $e) {
        error_log("Payroll History Error: " . $e->getMessage());
    }
    return $history;
}
?>