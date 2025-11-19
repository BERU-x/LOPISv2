<?php
// models/superadmin_model.php

/**
 * Fetches aggregate counts for the dashboard metrics.
 */
function get_dashboard_metrics($pdo) {
    $metrics = [
        'total_companies' => 0,
        'active_users' => 0,
        'total_employees' => 0, // 🔥 Added Total Employees Metric
        'monthly_payrolls' => 0,
        'audit_alerts' => 0,
    ];

    try {
        // Metric 1: Total Employees (All records in the employee table)
        $stmt_emp = $pdo->query("SELECT COUNT(id) FROM tbl_employees");
        $metrics['total_employees'] = $stmt_emp->fetchColumn();

        // Metric 2: Active Users (users with status = 1)
        $stmt_users = $pdo->query("SELECT COUNT(id) FROM tbl_users WHERE status = 1");
        $metrics['active_users'] = $stmt_users->fetchColumn();

        // Metric 3: Total Companies (COMMENTED OUT: Requires tbl_companies)
        /*
        $stmt_comp = $pdo->query("SELECT COUNT(id) FROM tbl_companies");
        $metrics['total_companies'] = $stmt_comp->fetchColumn();
        */

        // Metric 4: Payrolls Processed This Month (COMMENTED OUT: Requires tbl_payrolls)
        /*
        $stmt_payrolls = $pdo->query("SELECT COUNT(id) FROM tbl_payrolls WHERE MONTH(processed_date) = MONTH(NOW()) AND YEAR(processed_date) = YEAR(NOW())");
        $metrics['monthly_payrolls'] = $stmt_payrolls->fetchColumn();
        */

        // Metric 5: Audit Alerts (COMMENTED OUT: Requires tbl_logs)
        /*
        $stmt_alerts = $pdo->query("SELECT COUNT(id) FROM tbl_logs WHERE log_level = 'CRITICAL' AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $metrics['audit_alerts'] = $stmt_alerts->fetchColumn();
        */
        
    } catch (PDOException $e) {
        error_log("Dashboard Metrics Error: " . $e->getMessage());
    }

    // Return metrics. Metrics not fetched will remain 0.
    return $metrics;
}

/**
 * Fetches counts of users grouped by usertype for the pie chart.
 */
function get_user_role_counts($pdo) {
    // Assuming 0=Superadmin, 1=Management/Admin, 2=Employee
    $sql = "SELECT usertype, COUNT(id) as count FROM tbl_users GROUP BY usertype";
    
    // Initialize structure to guarantee all keys exist for Chart.js
    $roles_data = [
        0 => 0, // Superadmin
        1 => 0, // Management/Admin
        2 => 0  // Employee
    ];
    
    try {
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $type = (int)$row['usertype'];
            if (isset($roles_data[$type])) {
                $roles_data[$type] = (int)$row['count'];
            }
        }
    } catch (PDOException $e) {
        error_log("User Role Count Error: " . $e->getMessage());
    }
    
    // Return counts in a standard order [Superadmin, Management, Employee] for Chart.js
    return [$roles_data[0], $roles_data[1], $roles_data[2]]; 
}
// NOTE: You would add functions here for fetching the growth chart data and recent activities.

?>