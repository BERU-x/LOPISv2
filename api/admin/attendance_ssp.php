<?php
/**
 * api/admin/attendance_ssp.php
 * Handles Server-Side Processing for Attendance Logs.
 * Strictly uses tbl_attendance and tbl_employees.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION CHECK ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] > 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

// --- 2. DATABASE CONNECTION ---
require_once __DIR__ . '/../../db_connection.php'; 

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['draw' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// --- 3. SSP REQUEST PARSING ---
$draw   = (int)($_GET['draw'] ?? 1);
$start  = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$search = $_GET['search']['value'] ?? '';
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

$limit_sql = " LIMIT $start, $length";

// Column mapping for ordering
$columns = [
    0 => 'e.lastname',        // Employee Name
    1 => 'a.date',            // Date (Time In Date)
    2 => 'a.time_in',         // Clock In
    3 => 'a.time_out',        // Clock Out
    4 => 'a.attendance_status',
    5 => 'a.num_hr',
    6 => 'a.overtime_hr'
];

$order_sql = " ORDER BY a.date DESC, a.time_in DESC"; // Default
if (isset($_GET['order'][0])) {
    $col = (int)$_GET['order'][0]['column'];
    $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    if (isset($columns[$col])) {
        $order_sql = " ORDER BY " . $columns[$col] . " $dir";
    }
}

// --- 4. FILTERING LOGIC ---
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

$where_sql = " WHERE " . implode(' AND ', $where_params);
$join_sql = " FROM tbl_attendance a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";

try {
    // Total Unfiltered
    $recordsTotal = $pdo->query("SELECT COUNT(id) FROM tbl_attendance")->fetchColumn();

    // Total Filtered
    $stmt = $pdo->prepare("SELECT COUNT(a.id) $join_sql $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = $stmt->fetchColumn();

    // Data Fetch
    $sql_select = "SELECT 
        a.*, 
        e.firstname, e.lastname, e.photo, e.department
        $join_sql $where_sql $order_sql $limit_sql";
        
    $stmt = $pdo->prepare($sql_select);
    $stmt->execute($where_bindings);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    exit;
}

// --- 5. DATA FORMATTING ---
$formatted_data = [];



foreach ($raw_data as $row) {
    // Clock In
    $time_in = date('h:i A', strtotime($row['time_in']));
    
    // Clock Out + Overnight Logic
    $time_out = '<span class="text-muted">--</span>';
    if ($row['time_out'] && $row['time_out'] !== '00:00:00') {
        $time_out_str = date('h:i A', strtotime($row['time_out']));
        
        // If they clocked out on a different day (Overnight shift)
        if (!empty($row['time_out_date']) && $row['time_out_date'] !== $row['date']) {
            $time_out_str .= '<br><span class="badge bg-light text-danger border-0 pt-0" style="font-size: 10px;">' . date('M d', strtotime($row['time_out_date'])) . '</span>';
        }
        $time_out = '<span class="fw-bold">' . $time_out_str . '</span>';
    }
    
    // Status Badges
    $status_str = $row['attendance_status'] ?? '';
    $badges = '';
    $status_map = [
        'Ontime'    => 'success',
        'Late'      => 'warning',
        'Undertime' => 'info',
        'Overtime'  => 'primary',
        'Forgot'    => 'danger'
    ];

    foreach ($status_map as $key => $color) {
        if (stripos($status_str, $key) !== false) {
            $label = ($key === 'Forgot') ? 'Forgot Out' : $key;
            $badges .= "<span class='badge bg-soft-$color text-$color border border-$color px-2 rounded-pill me-1'>$label</span>";
        }
    }

    // Active Status (If no time out and not marked as forgot)
    if (($row['time_out'] == null || $row['time_out'] == '00:00:00') && stripos($status_str, 'Forgot') === false) {
        $badges .= '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill"><i class="fa-solid fa-circle-play fa-fade me-1"></i>Active</span>';
    }

    // Employee column with Photo
    $photo = !empty($row['photo']) ? $row['photo'] : 'default.png';
    $emp_display = '
        <div class="d-flex align-items-center">
            <img src="../assets/images/users/'.$photo.'" class="rounded-circle me-2 border shadow-sm" style="width: 35px; height: 35px; object-fit: cover;">
            <div>
                <div class="fw-bold text-dark" style="line-height: 1.2;">' . $row['firstname'] . ' ' . $row['lastname'] . '</div>
                <div class="text-muted font-monospace" style="font-size: 11px;">ID: ' . $row['employee_id'] . '</div>
            </div>
        </div>';

    $formatted_data[] = [
        'employee_id'   => $row['employee_id'],
        'employee_name' => $emp_display, 
        'date'          => '<span class="fw-bold">' . date('M d, Y', strtotime($row['date'])) . '</span>',
        'time_in'       => '<span class="fw-bold">' . $time_in . '</span>',
        'time_out'      => $time_out, 
        'status'        => $badges, 
        'num_hr'        => '<span class="fw-bold">' . number_format($row['num_hr'], 2) . ' hr</span>',
        'overtime_hr'   => (float)$row['overtime_hr'] > 0 ? '<span class="text-primary fw-bold">'.number_format($row['overtime_hr'], 2).' hr</span>' : '--'
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $formatted_data
]);