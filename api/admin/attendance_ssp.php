<?php
/**
 * api/admin/attendance_ssp.php
 * Updated with Multi-Status support and Bulk-Selection compatibility.
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

// Column mapping (Shifted by 1 due to checkbox column at index 0)
$columns = [
    0 => null,                // Checkbox
    1 => 'e.lastname',        // Employee Name
    2 => 'a.date',            // Date
    3 => 'a.status_based',    // Work Type
    4 => 'a.time_in',         // Clock In
    5 => 'a.time_out',        // Clock Out
    6 => 'a.attendance_status',
    7 => 'a.num_hr',
    8 => 'a.overtime_hr',
    9 => 'a.total_deduction_hr'
];

$order_sql = " ORDER BY a.date DESC, a.time_in DESC"; // Default
if (isset($_GET['order'][0])) {
    $col = (int)$_GET['order'][0]['column'];
    $dir = $_GET['order'][0]['dir'] === 'ASC' ? 'ASC' : 'DESC';
    if (isset($columns[$col]) && $columns[$col] !== null) {
        $order_sql = " ORDER BY " . $columns[$col] . " $dir, a.time_in DESC";
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
    $recordsTotal = $pdo->query("SELECT COUNT(id) FROM tbl_attendance")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(a.id) $join_sql $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = $stmt->fetchColumn();

    $sql_select = "SELECT a.*, e.firstname, e.lastname, e.photo, e.department $join_sql $where_sql $order_sql $limit_sql";
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
    // Basic Time Formatting
    $time_in = date('h:i A', strtotime($row['time_in']));
    $time_out = '<span class="text-muted">--</span>';
    $is_active = ($row['time_out'] == null || $row['time_out'] == '00:00:00');

    if (!$is_active) {
        $time_out_str = date('h:i A', strtotime($row['time_out']));
        if (!empty($row['time_out_date']) && $row['time_out_date'] !== $row['date']) {
            $time_out_str .= '<br><span class="badge bg-light text-danger border-0 pt-0" style="font-size: 10px;">' . date('M d', strtotime($row['time_out_date'])) . '</span>';
        }
        $time_out = '<span class="fw-bold">' . $time_out_str . '</span>';
    }
    
    // Status Badges (Strict: Ontime, Late, Undertime, Overtime)
    $status_str = $row['attendance_status'] ?? '';
    $badges = '';
    $status_map = ['Ontime' => 'success', 'Late' => 'warning', 'Undertime' => 'info', 'Overtime' => 'primary'];

    foreach ($status_map as $key => $color) {
        if (stripos($status_str, $key) !== false) {
            $badges .= "<span class='badge bg-soft-$color text-$color border border-$color px-2 rounded-pill me-1'>$key</span>";
        }
    }

    if ($is_active) {
        $badges .= '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill"><i class="fa-solid fa-circle-play fa-fade me-1"></i>Active</span>';
    }

    // Work Type Badge
    $wt_color = ($row['status_based'] === 'WFH') ? 'info' : (($row['status_based'] === 'FIELD') ? 'warning' : 'teal');
    $work_type = "<span class='badge bg-soft-$wt_color text-$wt_color border border-$wt_color px-2 rounded-pill'>{$row['status_based']}</span>";

    // Employee Photo/Name
    $photo = !empty($row['photo']) ? $row['photo'] : 'default.png';
    $emp_display = '
        <div class="d-flex align-items-center">
            <img src="../assets/images/users/'.$photo.'" class="rounded-circle me-2 border shadow-sm" style="width: 35px; height: 35px; object-fit: cover;">
            <div>
                <div class="fw-bold text-dark" style="line-height: 1.2;">' . $row['firstname'] . ' ' . $row['lastname'] . '</div>
                <div class="text-muted font-monospace" style="font-size: 11px;">' . $row['employee_id'] . '</div>
            </div>
        </div>';

    $formatted_data[] = [
        'id'            => $row['id'], // Required for Checkbox value
        'employee_name' => $emp_display, 
        'date'          => '<span class="fw-bold">' . date('M d, Y', strtotime($row['date'])) . '</span>',
        'status_based'  => $work_type,
        'time_in'       => '<span class="fw-bold">' . $time_in . '</span>',
        'time_out'      => $time_out, 
        'time_out_date' => $row['time_out_date'],
        'status'        => $badges, 
        'num_hr'        => '<span class="fw-bold">' . number_format($row['num_hr'], 2) . '</span>',
        'overtime_hr'   => '<span class="text-primary fw-bold">' . number_format($row['overtime_hr'], 2) . '</span>',
        'deduct_hr'     => '<span class="text-danger fw-bold">' . number_format($row['total_deduction_hr'], 2) . '</span>',
        // Raw values for front-end logic (checkboxes/modals)
        'raw_out'       => $row['time_out'],
        'raw_in'        => $row['time_in']
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $formatted_data
]);