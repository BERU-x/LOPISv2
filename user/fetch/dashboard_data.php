<?php
// dashboard_data.php - Fetches ONLY card data 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);   

// Ensure session and database connection are available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Assuming $pdo is available either globally or required here
require_once __DIR__ . '/../../db_connection.php'; 

$employee_id = $_SESSION['employee_id'] ?? null; 
$current_year = date('Y');

// --- Initialize variables for CARD DATA with safe defaults ---
$vacation_balance = 0.0;
$sick_balance = 0.0; 
$pending_leave_count = 0;
$pending_leave_days = 0.0;
$last_leave_submission = 'N/A';

// Punctuality Card Initialization
$last_time_in = 'N/A'; 
$status_label = 'No Data'; 
$status_color = 'secondary'; 

// Pending OT Initialization
$pending_ot_count = 0;
$pending_ot_hours = 0.0;

// Loan Balance Initialization
$loan_balance = 0.0; 
$loan_type = 'N/A';

if ($employee_id && isset($pdo)) {
    // ----------------------------------------------------------------------------------
    // --- PENDING LEAVE STATS (Section A) ---
    // ----------------------------------------------------------------------------------
    try {
        $stmt = $pdo->prepare("
            SELECT 
                -- REMOVED: Pending tasks subquery (using hardcoded 0 in the view)
                (SELECT COUNT(id) FROM tbl_leave WHERE employee_id = :eid AND status = '0') AS pending_leave_count, 
                (SELECT SUM(days_count) FROM tbl_leave WHERE employee_id = :eid AND status = '0') AS pending_leave_days, 
                (SELECT MAX(created_on) FROM tbl_leave WHERE employee_id = :eid) AS last_leave_submission
            ");
        
        $stmt->execute([':eid' => $employee_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stats) {
            $pending_leave_count = $stats['pending_leave_count'];
            $pending_leave_days = floatval($stats['pending_leave_days'] ?? 0.0);
            $last_leave_submission = $stats['last_leave_submission'] ? date('M d, Y', strtotime($stats['last_leave_submission'])) : 'N/A';
        }
    } catch (PDOException $e) {
        error_log("Dashboard Section A Error: " . $e->getMessage());
    }


    // ----------------------------------------------------------------------------------
    // --- LEAVE BALANCES (Section B) ---
    // ----------------------------------------------------------------------------------
    try {
        // 1. Get total credits for the year
        $stmt_credits = $pdo->prepare("
            SELECT 
                vacation_leave_total, sick_leave_total
            FROM tbl_leave_credits
            WHERE employee_id = :eid AND year = :year
        ");
        $stmt_credits->execute([':eid' => $employee_id, ':year' => $current_year]);
        $credits = $stmt_credits->fetch(PDO::FETCH_ASSOC);
        
        $vacation_credits = floatval($credits['vacation_leave_total'] ?? 0);
        $sick_credits = floatval($credits['sick_leave_total'] ?? 0);
        
        // 2. Get USED days for the year using exact leave types
        $stmt_used = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN leave_type = 'Vacation Leave' THEN days_count ELSE 0 END) AS used_vl,
                SUM(CASE WHEN leave_type = 'Sick Leave' THEN days_count ELSE 0 END) AS used_sl
            FROM tbl_leave
            WHERE employee_id = :eid AND status = 1 AND YEAR(start_date) = :year
        ");
        $stmt_used->execute([':eid' => $employee_id, ':year' => $current_year]);
        $used = $stmt_used->fetch(PDO::FETCH_ASSOC);
        
        $used_vl = floatval($used['used_vl'] ?? 0);
        $used_sl = floatval($used['used_sl'] ?? 0);

        // 3. Calculate remaining balances
        $vacation_balance = max(0, $vacation_credits - $used_vl);
        $sick_balance = max(0, $sick_credits - $used_sl);

    } catch (PDOException $e) {
        error_log("Dashboard Leave Balance Error: " . $e->getMessage());
        $vacation_balance = 'Error';
        $sick_balance = 'Error';
    }
    
    // ----------------------------------------------------------------------------------
    // --- PENDING OVERTIME REQUESTS (Section C) ---
    // ----------------------------------------------------------------------------------
    try {
        $stmt_ot_pending = $pdo->prepare("
            SELECT 
                COUNT(id) AS ot_count, 
                SUM(hours_requested) AS ot_hours
            FROM tbl_overtime
            WHERE employee_id = :eid AND status = 'Pending'
        ");
        $stmt_ot_pending->execute([':eid' => $employee_id]);
        $ot_stats = $stmt_ot_pending->fetch(PDO::FETCH_ASSOC);

        $pending_ot_count = intval($ot_stats['ot_count'] ?? 0);
        $pending_ot_hours = floatval($ot_stats['ot_hours'] ?? 0.0);

    } catch (PDOException $e) {
        error_log("Dashboard Pending OT Error: " . $e->getMessage());
        $pending_ot_count = 'Error';
        $pending_ot_hours = 0.0; 
    }

    // ----------------------------------------------------------------------------------
    // --- LAST ATTENDANCE STATUS (Section D) - FILTERED BY TODAY ---
    // ----------------------------------------------------------------------------------
    try {
        $stmt_last_attn = $pdo->prepare("
            SELECT time_in, attendance_status
            FROM tbl_attendance
            WHERE employee_id = :eid AND date = CURRENT_DATE() 
            ORDER BY time_in DESC
            LIMIT 1
        ");
        $stmt_last_attn->execute([':eid' => $employee_id]);
        $last_attn = $stmt_last_attn->fetch(PDO::FETCH_ASSOC);

        // Process the fetched status
        if ($last_attn && $last_attn['time_in']) {
            $last_time_in = date('h:i A', strtotime($last_attn['time_in']));
            $raw_status = $last_attn['attendance_status'] ?? 'N/A';

            if (strpos($raw_status, 'Late') !== false) {
                $status_label = 'Late';
                $status_color = 'danger'; // Assign color based on logic
            } elseif ($raw_status === 'Ontime' || $raw_status === 'Present' || strpos($raw_status, 'On Time') !== false) {
                $status_label = 'On Time';
                $status_color = 'success'; // Assign color based on logic
            } else {
                $status_label = 'Logged';
                $status_color = 'primary'; // Assign color based on logic
            }
        } else {
            // No log found for TODAY
            $last_time_in = 'No Log';
            $status_label = 'Absent Today';
            $status_color = 'secondary'; // Assign color based on logic
        }
    } catch (PDOException $e) {
        error_log("Dashboard Attendance Status Error: " . $e->getMessage());
        $last_time_in = 'DB Error'; 
        $status_label = 'System Error'; 
        $status_color = 'secondary';
    }

    // ----------------------------------------------------------------------------------
    // --- TOTAL LOAN BALANCES (Section E - Financials) ---
    // ----------------------------------------------------------------------------------
    try {
        // Fetch individual loan balances
        $stmt_financials = $pdo->prepare("
            SELECT 
                sss_loan_balance,
                pagibig_loan_balance,
                company_loan_balance
            FROM tbl_employee_financials
            WHERE employee_id = :eid
            LIMIT 1
        ");
        
        $stmt_financials->execute([':eid' => $employee_id]);
        $financials_data = $stmt_financials->fetch(PDO::FETCH_ASSOC);

        if ($financials_data) {
            $sss = floatval($financials_data['sss_loan_balance'] ?? 0.0);
            $pagibig = floatval($financials_data['pagibig_loan_balance'] ?? 0.0);
            $company = floatval($financials_data['company_loan_balance'] ?? 0.0);

            // Calculate the total of all individual loan balances
            $loan_balance = $sss + $pagibig + $company; 
            
            if ($loan_balance > 0) {
                $loan_type = 'Active Loans';
            } else {
                $loan_type = 'No Active Loans';
            }
            
        } else {
            $loan_balance = 0.0;
            $loan_type = 'No Financial Record';
        }

    } catch (PDOException $e) {
        error_log("Dashboard Loan Balance Error: " . $e->getMessage());
        $loan_balance = 'Error'; 
        $loan_type = 'DB Error';
    }
}
?>