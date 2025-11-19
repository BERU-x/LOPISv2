<?php
// models/employee_model.php (or admin_dashboard_model.php)

/**
 * Fetches dynamic metrics for the Admin Dashboard.
 * @param PDO $pdo The database connection object.
 * @param int $company_id The ID of the company being viewed.
 * @return array Metrics array.
 */
function get_admin_dashboard_metrics($pdo, $company_id) {
    $metrics = [
        'active_employees' => 0,
        'new_hires_month' => 0,
        'pending_leave_count' => 0, // Assuming this is calculated elsewhere or kept at 0
        'payroll_pending' => 'N/A' // Status is usually derived from a separate payroll run table
    ];

    try {
        // Metric 1: Active Employees (Assuming employment_status < 5 are active, and status is in tbl_employees)
        // Note: Using 'id' column for count.
        $stmt_active = $pdo->prepare("SELECT COUNT(id) FROM tbl_employees WHERE /*company_id = ? AND*/ employment_status < 5");
        // $stmt_active->execute([$company_id]); 
        // NOTE: Since company_id is not in tbl_employees, we removed the WHERE clause for company_id. 
        // This query counts ALL active employees in the table.
        $stmt_active->execute();
        $metrics['active_employees'] = (int)$stmt_active->fetchColumn();

        // Metric 2: New Hires This Month (Using created_on column for tracking creation date)
        $stmt_new = $pdo->prepare("SELECT COUNT(id) FROM tbl_employees WHERE /*company_id = ? AND*/ MONTH(created_on) = MONTH(NOW()) AND YEAR(created_on) = YEAR(NOW())");
        // $stmt_new->execute([$company_id]); 
        $stmt_new->execute();
        $metrics['new_hires_month'] = (int)$stmt_new->fetchColumn();

        // Metric 3: Pending Leave Requests (Set to 0, as we only have tbl_employees)
        $metrics['pending_leave_count'] = 8; // Keeping the dummy data for now
        
    } catch (PDOException $e) {
        error_log("Admin Dashboard Metrics Error: " . $e->getMessage());
    }

    return $metrics;
}

/**
 * Fetches employee counts grouped by department for the doughnut chart.
 * @param PDO $pdo The database connection object.
 * @param int $company_id The ID of the company being viewed.
 * @return array Department counts [label => count, ...]
 */
function get_dept_distribution_data($pdo, $company_id) {
    // Counts active employees (employment_status < 5) grouped by department.
    $sql = "SELECT department, COUNT(id) as count 
            FROM tbl_employees 
            WHERE /*company_id = ? AND*/ employment_status < 5
            GROUP BY department 
            ORDER BY count DESC";

    $data = [];
    
    try {
        $stmt = $pdo->prepare($sql);
        // $stmt->execute([$company_id]); 
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[htmlspecialchars($row['department'])] = (int)$row['count'];
        }
    } catch (PDOException $e) {
        error_log("Dept Distribution Error: " . $e->getMessage());
    }
    return $data;
}
?>