<?php
/**
 * api/superadmin/attendance_ssp.php
 * Updated: Manual/Bulk Calculations for Hours, OT, and Late/Undertime Deductions.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

// --- 1. ACCESS CONTROL ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$admin_emp_id = $_SESSION['employee_id'];

// =====================================================================
// HELPER: UNIFIED CALCULATION FUNCTION (Matches Kiosk Logic)
// =====================================================================
function calculateAttendance($shift_date, $time_in, $time_out, $out_date, $schedule_type) {
    $actual_in  = strtotime("$shift_date $time_in");
    $actual_out = strtotime("$out_date $time_out");
    
    if ($actual_out <= $actual_in) return [0, 0, 8.0];

    $num_hr = 0;
    $overtime_hr = 0;
    $shift_end = strtotime("$shift_date 18:00:00");

    if ($schedule_type === 'Flexible') {
        $raw_duration_hours = ($actual_out - $actual_in) / 3600;
        $net_hours = ($raw_duration_hours > 1.0) ? ($raw_duration_hours - 1.0) : 0;
        
        if ($net_hours > 8.0) {
            $num_hr = 8.0;
            $overtime_hr = round($net_hours - 8.0, 2);
        } else {
            $num_hr = round($net_hours, 2);
        }
    } else {
        $shift_start = strtotime("$shift_date 09:00:00");
        $lunch_start = strtotime("$shift_date 12:00:00");
        $lunch_end   = strtotime("$shift_date 13:00:00");

        // Use 6:00 PM as the strict boundary for regular hours
        $effective_in  = max($actual_in, $shift_start); 
        $effective_out = min($actual_out, $shift_end); 

        if ($effective_out > $effective_in) {
            $gross_seconds = $effective_out - $effective_in;
            $overlap_start = max($effective_in, $lunch_start);
            $overlap_end   = min($effective_out, $lunch_end);
            $lunch_deduction = ($overlap_end > $overlap_start) ? ($overlap_end - $overlap_start) : 0;
            
            $num_hr = round(($gross_seconds - $lunch_deduction) / 3600, 2);
        }

        // Overtime is any work AFTER 6:00 PM
        if ($actual_out > $shift_end) {
            $overtime_hr = round(($actual_out - $shift_end) / 3600, 2);
        }
    }

    // Undertime occurs if they clock out before 18:00 (Fixed) 
    // or work less than 8 hours (Flexible)
    $total_deduction_hr = ($num_hr < 8.0) ? round(8.0 - $num_hr, 2) : 0;
    
    return [$num_hr, $overtime_hr, $total_deduction_hr];
}

/**
 * Helper to build the status string based on calculated metrics
 */
function buildStatusString($num_hr, $ot_hr, $deduct_hr, $time_in, $time_out, $shift_date, $schedule_type) {
    $st_arr = [];
    
    // 1. LATE CHECK (Based on entry time)
    // Threshold is 09:00:59. 09:01:00 is considered Late.
    if ($schedule_type !== 'Flexible') {
        $late_threshold = strtotime("$shift_date 09:00:59");
        $actual_in_time = strtotime("$shift_date $time_in");
        
        if ($actual_in_time > $late_threshold) {
            $st_arr[] = 'Late';
        }
    }

    // 2. EXIT STATUS (Based on 6:00 PM / 18:00 threshold)
    $actual_out_time = strtotime("$shift_date $time_out");
    $shift_end_threshold = strtotime("$shift_date 18:00:00");

    if ($schedule_type !== 'Flexible') {
        // FIXED: Undertime if leaving before 6:00 PM
        if ($actual_out_time < $shift_end_threshold) {
            $st_arr[] = 'Undertime';
        }
        // FIXED: Overtime if leaving after 6:00 PM
        if ($ot_hr > 0) {
            $st_arr[] = 'Overtime';
        }
    } else {
        // FLEXIBLE: Based strictly on 8-hour duration
        if ($deduct_hr > 0) {
            $st_arr[] = 'Undertime';
        }
        if ($ot_hr > 0) {
            $st_arr[] = 'Overtime';
        }
    }

    // 3. ONTIME CHECK (The Fallback)
    // If they aren't Late and they aren't Undertime, they are Ontime.
    // Note: They can be 'Ontime, Overtime' but NOT 'Late, Ontime'.
    if (!in_array('Late', $st_arr) && !in_array('Undertime', $st_arr)) {
        // If they worked at least the minimum required or are flexible and met 8hrs
        if ($num_hr >= 8.0 || ($schedule_type !== 'Flexible' && $actual_out_time >= $shift_end_threshold)) {
             $st_arr[] = 'Ontime';
        }
    }
    
    // Sort to keep "Ontime" or "Late" first for better UI
    return implode(', ', array_unique($st_arr));
}

// =====================================================================
// HANDLE POST: ACTIONS
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sub_action = $_POST['sub_action'] ?? 'update';

    try {
        // --- CASE A & C: MANUAL ADD / UPDATE ---
        if ($sub_action === 'add_manual' || $sub_action === 'update') {
            $eid      = trim($_POST['employee_id'] ?? '');
            $log_id   = $_POST['log_id'] ?? null;
            $time_in  = $_POST['time_in'] ?? '';
            $time_out = $_POST['time_out'] ?? null; // Argument #5
            $date     = $_POST['date'] ?? null;

            if ($sub_action === 'update') {
                $stmt_orig = $pdo->prepare("SELECT employee_id, date FROM tbl_attendance WHERE id = ?");
                $stmt_orig->execute([$log_id]);
                $orig = $stmt_orig->fetch();
                if (!$orig) throw new Exception("Record not found.");
                $date = $orig['date'];
                $eid  = $orig['employee_id'];
            }

            $emp_stmt = $pdo->prepare("SELECT schedule_type FROM tbl_employees WHERE employee_id = ?");
            $emp_stmt->execute([$eid]);
            $schedule_type = $emp_stmt->fetchColumn() ?: 'Fixed';
            $out_date = !empty($_POST['time_out_date']) ? $_POST['time_out_date'] : $date;

            // Calculate metrics
            [$n, $o, $d] = calculateAttendance($date, $time_in, $time_out, $out_date, $schedule_type);
            
            // ⭐ FIXED: Passing 7 arguments now
            $status_str = buildStatusString($n, $o, $d, $time_in, $time_out, $date, $schedule_type);

            if ($sub_action === 'add_manual') {
                $sql = "INSERT INTO tbl_attendance (employee_id, date, time_in, time_out, time_out_date, status_based, num_hr, overtime_hr, total_deduction_hr, attendance_status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$eid, $date, $time_in, $time_out, $out_date, $_POST['status_based'], $n, $o, $d, $status_str]);
            } else {
                $sql = "UPDATE tbl_attendance SET time_in=?, time_out=?, time_out_date=?, attendance_status=?, num_hr=?, overtime_hr=?, total_deduction_hr=? WHERE id=?";
                $pdo->prepare($sql)->execute([$time_in, $time_out, $out_date, $status_str, $n, $o, $d, $log_id]);
            }

            echo json_encode(['status' => 'success', 'message' => "Update successful."]);
            exit;
        }

        // --- CASE B: BULK TIME OUT ---
        if ($sub_action === 'bulk_timeout') {
            $ids      = $_POST['ids'] ?? []; 
            $time_out = $_POST['time_out'] ?? null;
            
            if (empty($ids) || !$time_out) throw new Exception("Missing IDs or Time.");

            $pdo->beginTransaction();
            foreach ($ids as $id) {
                $stmt = $pdo->prepare("SELECT a.date, a.time_in, e.schedule_type FROM tbl_attendance a JOIN tbl_employees e ON a.employee_id = e.employee_id WHERE a.id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch();

                if ($row) {
                    $final_out_date = $row['date'];
                    [$n, $o, $d] = calculateAttendance($row['date'], $row['time_in'], $time_out, $final_out_date, $row['schedule_type']);
                    
                    // ⭐ FIXED: Passing 7 arguments now
                    $status_str = buildStatusString($n, $o, $d, $row['time_in'], $time_out, $row['date'], $row['schedule_type']);

                    $upd = $pdo->prepare("UPDATE tbl_attendance SET time_out = ?, time_out_date = ?, num_hr = ?, overtime_hr = ?, total_deduction_hr = ?, attendance_status = ? WHERE id = ?");
                    $upd->execute([$time_out, $final_out_date, $n, $o, $d, $status_str, $id]);
                }
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => "Bulk update successful."]);
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// =====================================================================
// HANDLE GET: FETCH DATATABLE (SSP)
// =====================================================================
$draw   = (int)($_GET['draw'] ?? 1);
$start  = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$search = $_GET['search']['value'] ?? '';
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;
$dept_filter = $_GET['department'] ?? null;
$emp_filter  = $_GET['employee_id'] ?? null; 

$columns = [
    0 => null, 1 => 'e.lastname', 2 => 'a.date', 3 => 'a.status_based', 
    4 => 'a.time_in', 5 => 'a.time_out', 6 => 'a.time_out_date', 
    7 => 'a.attendance_status', 8 => 'a.num_hr', 9 => 'a.overtime_hr', 10 => 'a.total_deduction_hr'
];

$order_sql = " ORDER BY a.date DESC, a.time_in DESC"; 
if (isset($_GET['order'][0])) {
    $col = (int)$_GET['order'][0]['column'];
    $dir = $_GET['order'][0]['dir'] === 'ASC' ? 'ASC' : 'DESC';
    if (isset($columns[$col]) && $columns[$col] !== null) {
        $order_sql = " ORDER BY " . $columns[$col] . " $dir, a.time_in DESC";
    }
}

$where_params = ["1=1"]; 
$where_bindings = [];

if (!empty($search)) {
    $where_params[] = "(a.employee_id LIKE :search OR e.firstname LIKE :search OR e.lastname LIKE :search OR a.attendance_status LIKE :search)";
    $where_bindings[':search'] = "%$search%";
}
if (!empty($start_date) && !empty($end_date)) { 
    $where_params[] = "a.date BETWEEN :sd AND :ed"; 
    $where_bindings[':sd'] = $start_date; 
    $where_bindings[':ed'] = $end_date; 
}
if (!empty($emp_filter)) {
    $where_params[] = "a.employee_id = :empid";
    $where_bindings[':empid'] = $emp_filter;
}
if (!empty($dept_filter)) {
    $where_params[] = "e.department = :dept";
    $where_bindings[':dept'] = $dept_filter;
}

$where_sql = " WHERE " . implode(' AND ', $where_params);
$join_sql = " FROM tbl_attendance a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";

try {
    $recordsTotal = $pdo->query("SELECT COUNT(*) FROM tbl_attendance")->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(a.id) $join_sql $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = $stmt->fetchColumn();

    $sql_select = "SELECT a.*, e.firstname, e.lastname, e.photo, e.department $join_sql $where_sql $order_sql LIMIT $start, $length";
    $stmt = $pdo->prepare($sql_select);
    $stmt->execute($where_bindings);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    exit;
}

$formatted_data = [];
foreach ($raw_data as $row) {
    $fmt_in = date('h:i A', strtotime($row['time_in']));
    $fmt_out = $row['time_out'] && $row['time_out'] != '00:00:00' ? date('h:i A', strtotime($row['time_out'])) : '--';
    $out_date_display = $row['time_out_date'] ? date('M d, Y', strtotime($row['time_out_date'])) : '--';
    $is_overnight = $row['time_out_date'] && $row['time_out_date'] != $row['date'];

    $formatted_data[] = [
        'id'            => $row['id'],
        'employee_id'   => $row['employee_id'],
        'employee_name' => "{$row['firstname']} {$row['lastname']}",
        'department'    => $row['department'],
        'date'          => date('M d, Y', strtotime($row['date'])),
        'status_based'  => $row['status_based'] ?? '--',
        'time_in'       => "<b>{$fmt_in}</b>",
        'time_out'      => "<b>{$fmt_out}</b>",
        'time_out_date' => $is_overnight ? "<span class=\"text-danger fw-bold\">{$out_date_display}</span>" : $out_date_display,
        'attendance_status' => $row['attendance_status'],
        'num_hr'        => '<b>'.number_format($row['num_hr'], 2) . '</b>',
        'overtime_hr'   => '<b>'.number_format($row['overtime_hr'], 2) . '</b>',
        'total_deduction_hr' => '<b>'.number_format($row['total_deduction_hr'], 2) . '</b>',
        'photo'         => !empty($row['photo']) ? $row['photo'] : 'default.png',
        'raw_in'        => $row['time_in'],
        'raw_out'       => $row['time_out'],
        'raw_out_date'  => $row['time_out_date']
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $formatted_data
]);