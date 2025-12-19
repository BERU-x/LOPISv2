<?php
// api/admin/attendance_ssp.php
// Handles Server-Side Processing for Attendance Logs with Advanced Filtering
header('Content-Type: application/json');
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

// Mapping DataTables index to DB columns
$columns = array(
    array( 'db' => 'employee_id',       'dt' => 'employee_id' ),
    array( 'db' => 'employee_name',     'dt' => 'employee_name' ),
    array( 'db' => 'date',              'dt' => 'date' ),
    array( 'db' => 'time_in',           'dt' => 'time_in' ),
    array( 'db' => 'attendance_status', 'dt' => 'status' ),
    array( 'db' => 'time_out',          'dt' => 'time_out' ),
    array( 'db' => 'num_hr',            'dt' => 'num_hr' ),
    array( 'db' => 'overtime_hr',       'dt' => 'overtime_hr' )
);

$sql_details = " FROM tbl_attendance a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";

// --- 3. SSP REQUEST PARSING ---
$draw   = (int)($_GET['draw'] ?? 1);
$start  = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$limit_sql = " LIMIT $start, $length";

// Default ordering: Newest logs first
$order_sql = " ORDER BY a.date DESC, TIME(a.time_in) DESC";
$order = $_GET['order'] ?? null;

if ($order) {
    $col_idx = (int)$order[0]['column'];
    $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';

    if (isset($columns[$col_idx])) {
        $db_column = $columns[$col_idx]['db'];

        if ($db_column === 'time_in') {
            $sort_column = "TIME(a.time_in)";
        } elseif ($db_column === 'employee_name') {
            $sort_column = "e.lastname";
        } elseif ($db_column === 'date') {
            $sort_column = "a.date";
        } else {
            $sort_column = "a.$db_column";
        }
        $order_sql .= ", $sort_column $dir"; // Adds to the default
    }
}

// --- 4. FILTERING LOGIC ---
$search = $_GET['search']['value'] ?? '';
$where_params = []; 
$where_bindings = []; 
$where_sql = "";

if (!empty($search)) {
    $val = '%' . $search . '%';
    $conds = ["a.employee_id LIKE :search", "e.firstname LIKE :search", "e.lastname LIKE :search", "a.attendance_status LIKE :search"];
    $where_params[] = "(" . implode(' OR ', $conds) . ")";
    $where_bindings[':search'] = $val;
}

// Custom Date Range Filtering
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;
if ($start_date && $end_date) { 
    $where_params[] = "a.date BETWEEN :sd AND :ed"; 
    $where_bindings[':sd'] = $start_date; 
    $where_bindings[':ed'] = $end_date; 
}

if (!empty($where_params)) $where_sql = " WHERE " . implode(' AND ', $where_params);

try {
    // Total Unfiltered Records
    $stmt = $pdo->query("SELECT COUNT(a.id) FROM tbl_attendance a");
    $recordsTotal = (int)$stmt->fetchColumn();

    // Total Filtered Records
    $stmt = $pdo->prepare("SELECT COUNT(a.id) $sql_details $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = (int)$stmt->fetchColumn();

    // Main Data Fetch
    $sql_select = "SELECT 
        a.id, a.employee_id, 
        CONCAT_WS(' ', e.firstname, e.lastname) AS employee_name, 
        a.date, a.time_in, a.time_out, a.time_out_date, 
        a.attendance_status, a.num_hr, a.overtime_hr, 
        e.photo, e.department, e.employee_id as emp_code
        $sql_details $where_sql $order_sql $limit_sql";
        
    $stmt = $pdo->prepare($sql_select);
    $stmt->execute($where_bindings);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    exit;
}

// --- 5. DATA FORMATTING ---
$formatted_data = [];

foreach ($raw_data as $row) {
    // Clock In Time
    $time_in = date('h:i A', strtotime($row['time_in']));
    
    // Clock Out & Overnight Date Logic
    $time_out = '--';
    if ($row['time_out'] && $row['time_out'] !== '00:00:00') {
        $time_out_str = date('h:i A', strtotime($row['time_out']));
        $date_indicator = '';
        if (!empty($row['time_out_date']) && $row['time_out_date'] !== $row['date']) {
            $date_indicator = '<br><span class="text-danger" style="font-size: 0.8em; font-weight: 600;">' . date('M d', strtotime($row['time_out_date'])) . '</span>';
        }
        $time_out = $time_out_str . $date_indicator;
    }
    
    // UI Badges Generation
    $status_str = $row['attendance_status'];
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

    if ($time_out == '--' && stripos($status_str, 'Forgot') === false) {
        $badges .= '<span class="badge bg-soft-secondary text-secondary border px-2 rounded-pill"><i class="fa-solid fa-circle-play fa-fade me-1"></i>Active</span>';
    }

    // Wrap Employee Details (Photo + Name)
    $photo = !empty($row['photo']) ? $row['photo'] : 'default.png';
    $emp_details = '
        <div class="d-flex align-items-center">
            <img src="../assets/images/users/'.$photo.'" class="rounded-circle me-2 border" style="width: 32px; height: 32px; object-fit: cover;">
            <div>
                <div class="fw-bold text-dark" style="font-size: 0.9em;">' . $row['employee_name'] . '</div>
                <div class="small text-muted" style="font-size: 0.8em;">' . $row['emp_code'] . '</div>
            </div>
        </div>';

    $formatted_data[] = [
        'employee_id'   => $row['employee_id'],
        'employee_name' => $emp_details, 
        'date'          => '<span class="fw-bold">' . date('M d, Y', strtotime($row['date'])) . '</span>',
        'time_in'       => '<span class="text-primary fw-bold">' . $time_in . '</span>',
        'time_out'      => $time_out, 
        'status'        => $badges, 
        'num_hr'        => '<span class="fw-bold">' . number_format($row['num_hr'], 2) . '</span>',
        'overtime_hr'   => (float)$row['overtime_hr'] > 0 ? '<span class="text-primary fw-bold">'.number_format($row['overtime_hr'], 2).'</span>' : '--'
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $formatted_data
]);