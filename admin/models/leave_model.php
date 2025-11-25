<?php
// models/leave_model.php

// 1. Fetch all Leave Requests (Joined with Employee Data)
function get_all_leaves($pdo) {
    // ✅ FIX: Select l.id AS leave_id to prevent conflict with employee table id
    $sql = "SELECT 
                l.id AS leave_id, 
                l.employee_id, l.leave_type, l.start_date, l.end_date, 
                l.days_count, l.reason, l.status, l.created_on,
                e.firstname, e.lastname, e.photo, e.department, e.position
            FROM tbl_leave l
            LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id
            ORDER BY l.created_on DESC";
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching leaves: " . $e->getMessage());
        return [];
    }
}

// 2. Create New Leave Request
function create_leave_request($pdo, $data) {
    // Calculate days difference
    $start = new DateTime($data['start_date']);
    $end = new DateTime($data['end_date']);
    $diff = $start->diff($end);
    $days = $diff->days + 1; // +1 to include the start date

    $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
            VALUES (:employee_id, :leave_type, :start_date, :end_date, :days_count, :reason, 0, NOW())";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':employee_id' => $data['employee_id'],
            ':leave_type'  => $data['leave_type'],
            ':start_date'  => $data['start_date'],
            ':end_date'    => $data['end_date'],
            ':days_count'  => $days,
            ':reason'      => $data['reason']
        ]);
    } catch (PDOException $e) {
        error_log("Error creating leave: " . $e->getMessage());
        return false;
    }
}

// 3. Update Leave Status (Approve/Reject)
function update_leave_status($pdo, $id, $status) {
    // Ensure ID is an integer to prevent SQL issues
    $clean_id = (int)$id; 
    $clean_status = (int)$status;

    $sql = "UPDATE tbl_leave SET status = :status, updated_on = NOW() WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $clean_status, 
            ':id' => $clean_id
        ]);
        
        // Check if any row was actually touched
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error updating leave status: " . $e->getMessage());
        return false;
    }
}

// 4. Helper: Get simple employee list for Dropdown
function get_employee_dropdown($pdo) {
    $stmt = $pdo->query("SELECT employee_id, firstname, lastname FROM tbl_employees WHERE employment_status != 5 ORDER BY lastname ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// NEW FUNCTION: Calculate Balance
function get_leave_balance($pdo, $employee_id) {
    $current_year = date('Y');

    // 1. Get Total Credits from tbl_leave_credits
    $sql_credits = "SELECT * FROM tbl_leave_credits WHERE employee_id = :eid AND year = :year";
    $stmt = $pdo->prepare($sql_credits);
    $stmt->execute([':eid' => $employee_id, ':year' => $current_year]);
    $credits = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no credits set, return defaults (or zeros)
    if (!$credits) {
        return [
            'Vacation Leave' => ['total' => 0, 'used' => 0, 'remaining' => 0],
            'Sick Leave'     => ['total' => 0, 'used' => 0, 'remaining' => 0],
            'Emergency Leave'=> ['total' => 0, 'used' => 0, 'remaining' => 0]
        ];
    }

    // 2. Get Total USED (Approved only) from tbl_leave
    $sql_used = "SELECT leave_type, SUM(days_count) as total_used 
                 FROM tbl_leave 
                 WHERE employee_id = :eid 
                 AND status = 1 -- Only count Approved
                 AND YEAR(start_date) = :year
                 GROUP BY leave_type";
    
    $stmt = $pdo->prepare($sql_used);
    $stmt->execute([':eid' => $employee_id, ':year' => $current_year]);
    $used_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Returns ['Vacation Leave' => 5, 'Sick Leave' => 2]

    // 3. Calculate Remaining
    $balances = [];
    
    // Map database columns to specific text names
    $mapping = [
        'Vacation Leave' => 'vacation_leave_total',
        'Sick Leave'     => 'sick_leave_total',
        'Emergency Leave'=> 'emergency_leave_total'
    ];

    foreach ($mapping as $type_name => $db_column) {
        $total = (float)$credits[$db_column];
        $used  = (float)($used_data[$type_name] ?? 0);
        $remaining = $total - $used;

        $balances[$type_name] = [
            'total'     => $total,
            'used'      => $used,
            'remaining' => max(0, $remaining) // Prevent negative numbers
        ];
    }

    return $balances;
}
?>