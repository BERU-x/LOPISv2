<?php
// attendance_ssp.php

require_once '../../db_connection.php'; 

if (!isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['draw' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// Columns
$columns = array(
    array( 'db' => 'employee_id',     'dt' => 'employee_id' ),
    array( 'db' => 'employee_name',   'dt' => 'employee_name' ),
    array( 'db' => 'date',            'dt' => 'date' ),
    array( 'db' => 'time_in',         'dt' => 'time_in' ),
    array( 'db' => 'attendance_status', 'dt' => 'status' ),
    array( 'db' => 'time_out',        'dt' => 'time_out' ),
    array( 'db' => 'num_hr',          'dt' => 'num_hr' ),
    array( 'db' => 'overtime_hr',     'dt' => 'overtime_hr' )
);

$sql_details = " FROM tbl_attendance a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";

// --- STANDARD SSP QUERY LOGIC (Condensed for brevity, same as previous) ---
$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

$order = $_GET['order'] ?? null;
$order_sql = " ORDER BY a.date DESC, TIME(a.time_in) DESC"; 
if ($order && isset($columns[$order[0]['column']])) {
    $col_idx = $order[0]['column'];
    $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    if ($col_idx == 3) $sort_column = "TIME(a.time_in)";
    elseif ($col_idx == 2) { $order_sql = " ORDER BY a.date $dir, TIME(a.time_in) DESC"; goto skip_sort; }
    else $sort_column = ($columns[$col_idx]['db'] === 'employee_name') ? "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)" : "a." . $columns[$col_idx]['db'];
    $order_sql = " ORDER BY $sort_column $dir";
}
skip_sort:

$search = $_GET['search']['value'] ?? '';
$where_params = []; $where_bindings = []; $where_sql = "";

if (!empty($search)) {
    $val = '%' . $search . '%';
    $conds = ["a.employee_id LIKE :s", "e.firstname LIKE :s", "e.lastname LIKE :s", "a.attendance_status LIKE :s"];
    $where_params[] = "(" . implode(' OR ', $conds) . ")";
    $where_bindings[':s'] = $val;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
if ($start_date && $end_date) { $where_params[] = "a.date BETWEEN :sd AND :ed"; $where_bindings[':sd'] = $start_date; $where_bindings[':ed'] = $end_date; }

if (!empty($where_params)) $where_sql = " WHERE " . implode(' AND ', $where_params);

// Fetch Data
$stmt = $pdo->query("SELECT COUNT(a.id) $sql_details");
$recordsTotal = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(a.id) $sql_details $where_sql");
$stmt->execute($where_bindings);
$recordsFiltered = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT a.id, a.employee_id, CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) AS employee_name, a.date, a.time_in, a.time_out, a.attendance_status, a.num_hr, a.overtime_hr, e.photo, e.department $sql_details $where_sql $order_sql $limit_sql");
$stmt->execute($where_bindings);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- FORMAT DATA (BADGES & TIME) ---
$formatted_data = [];

foreach ($raw_data as $row) {
    // Times
    $time_in = date('h:i A', strtotime($row['time_in']));
    $time_out = ($row['time_out'] && $row['time_out'] !== '00:00:00') ? date('h:i A', strtotime($row['time_out'])) : '--';
    
    // Status Logic (Multi-Badge)
    $status_str = $row['attendance_status'];
    $badges = '';

    // Check keywords (Case Insensitive)
    if (stripos($status_str, 'Ontime') !== false) 
        $badges .= '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill me-1">Ontime</span>';
    
    if (stripos($status_str, 'Late') !== false) 
        $badges .= '<span class="badge bg-soft-warning text-warning border border-warning px-2 rounded-pill me-1">Late</span>';
    
    if (stripos($status_str, 'Undertime') !== false) 
        $badges .= '<span class="badge bg-soft-danger text-danger border border-danger px-2 rounded-pill me-1">Undertime</span>';
        
    if (stripos($status_str, 'Overtime') !== false) 
        $badges .= '<span class="badge bg-soft-primary text-primary border border-primary px-2 rounded-pill me-1">Overtime</span>';

    // Active State
    if ($time_out == '--') {
        $badges .= '<span class="badge bg-soft-secondary text-secondary border px-2 rounded-pill"><i class="fas fa-spinner fa-spin me-1"></i>Active</span>';
    }

    if(empty($badges)) $badges = '<span class="badge bg-light text-muted border">Unknown</span>';

    $formatted_data[] = [
        'employee_id'   => $row['employee_id'],
        'employee_name' => $row['employee_name'], // You can also pass 'photo' here if you want to use it in JS
        'photo'         => $row['photo'], // Passing photo for the JS render function
        'date'          => date('M d, Y', strtotime($row['date'])),
        'time_in'       => $time_in,
        'time_out'      => $time_out,
        'status'        => $badges, // Send HTML badges
        'num_hr'        => $row['num_hr'],
        'overtime_hr'   => $row['overtime_hr']
    ];
}

echo json_encode([
    "draw" => (int)$draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $formatted_data
]);
?>