<?php
// attendance_ssp.php
header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connection.php'; 

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['draw' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// Columns definition for mapping DataTables index to DB columns
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

// --- STANDARD SSP REQUEST PARSING ---
$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$limit_sql = " LIMIT $start, $length";

$order_sql = " ORDER BY a.date DESC, TIME(a.time_in) DESC"; // Default ordering
$order = $_GET['order'] ?? null;

// --- Ordering Logic (Cleaned up by removing goto) ---
if ($order) {
    $col_idx = (int)$order[0]['column'];
    $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    
    if (isset($columns[$col_idx])) {
        $db_column = $columns[$col_idx]['db'];
        
        if ($col_idx == 3) { // time_in
            $sort_column = "TIME(a.time_in)";
        } elseif ($col_idx == 2) { // date (Primary sort)
             $order_sql = " ORDER BY a.date $dir, TIME(a.time_in) DESC";
        } elseif ($db_column === 'employee_name') {
            $sort_column = "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)";
        } else {
            $sort_column = "a.$db_column";
        }
        
        if ($col_idx != 2) { // Apply dynamic sort if not relying on the primary date sort
            $order_sql = " ORDER BY $sort_column $dir";
        }
    }
}

// --- Filtering Logic ---
$search = $_GET['search']['value'] ?? '';
$where_params = []; $where_bindings = []; $where_sql = "";

if (!empty($search)) {
    $val = '%' . $search . '%';
    // Use named parameters for safety and clarity
    $conds = ["a.employee_id LIKE :search_val", "e.firstname LIKE :search_val", "e.lastname LIKE :search_val", "a.attendance_status LIKE :search_val"];
    $where_params[] = "(" . implode(' OR ', $conds) . ")";
    $where_bindings[':search_val'] = $val;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
if ($start_date && $end_date) { 
    $where_params[] = "a.date BETWEEN :sd AND :ed"; 
    $where_bindings[':sd'] = $start_date; 
    $where_bindings[':ed'] = $end_date; 
}

if (!empty($where_params)) $where_sql = " WHERE " . implode(' AND ', $where_params);

try {
    // 1. Fetch Total Records (Unfiltered)
    $stmt = $pdo->query("SELECT COUNT(a.id) $sql_details");
    $recordsTotal = (int)$stmt->fetchColumn();

    // 2. Fetch Filtered Records Count
    $stmt = $pdo->prepare("SELECT COUNT(a.id) $sql_details $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = (int)$stmt->fetchColumn();

    // 3. Fetch Data
    $sql_select = "SELECT 
        a.id, a.employee_id, 
        CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) AS employee_name, 
        a.date, a.time_in, a.time_out, a.time_out_date, 
        a.attendance_status, a.num_hr, a.overtime_hr, 
        e.photo, e.department, e.employee_id as emp_code
        $sql_details $where_sql $order_sql $limit_sql";
        
    $stmt = $pdo->prepare($sql_select);
    $stmt->execute($where_bindings);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Standardized SSP Error Response
    http_response_code(500);
    echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Database query failed: " . $e->getMessage()]);
    exit;
}

// --- FORMAT DATA (BADGES & TIME) ---
$formatted_data = [];

foreach ($raw_data as $row) {
    // Time In
    $time_in = date('h:i A', strtotime($row['time_in']));
    
    // Time Out Logic (Simplified and Cleaned)
    $time_out = '--';
    if ($row['time_out'] && $row['time_out'] !== '00:00:00') {
        $time_out_str = date('h:i A', strtotime($row['time_out']));
        $date_str = '';
        if (!empty($row['time_out_date']) && $row['time_out_date'] !== '0000-00-00' && $row['time_out_date'] !== $row['date']) {
            $date_str = '<br><small class="text-muted" style="font-size: 0.85em;">' . date('M d', strtotime($row['time_out_date'])) . '</small>';
        }
        $time_out = $time_out_str . $date_str;
    }
    
    // Status Logic (Case Insensitive Keywords)
    $status_str = $row['attendance_status'];
    $badges = '';

    if (stripos($status_str, 'Ontime') !== false) 
        $badges .= '<span class="badge bg-soft-success text-success border border-success px-2 rounded-pill me-1">Ontime</span>';
    
    if (stripos($status_str, 'Late') !== false) 
        $badges .= '<span class="badge bg-soft-warning text-warning border border-warning px-2 rounded-pill me-1">Late</span>';
    
    if (stripos($status_str, 'Undertime') !== false) 
        $badges .= '<span class="badge bg-soft-info text-info border border-info px-2 rounded-pill me-1">Undertime</span>';
        
    if (stripos($status_str, 'Overtime') !== false) 
        $badges .= '<span class="badge bg-soft-primary text-primary border border-primary px-2 rounded-pill me-1">Overtime</span>';
    
    if (stripos($status_str, 'Forgot Time Out') !== false) 
        $badges .= '<span class="badge bg-soft-danger text-danger border border-danger px-2 rounded-pill me-1">FTO</span>';

    // Active State (FA6 Update)
    if ($time_out == '--') {
        $badges .= '<span class="badge bg-soft-secondary text-secondary border px-2 rounded-pill"><i class="fa-solid fa-spinner fa-spin me-1"></i>Active</span>';
    }

    if(empty($badges)) $badges = '<span class="badge bg-light text-muted border">Unknown</span>';

    $formatted_data[] = [
        'employee_id'   => $row['employee_id'],
        'employee_name' => $row['employee_name'], 
        'photo'         => $row['photo'], 
        'date'          => date('M d, Y', strtotime($row['date'])),
        'time_in'       => $time_in,
        'time_out'      => $time_out, 
        'status'        => $badges, 
        'num_hr'        => $row['num_hr'],
        'overtime_hr'   => $row['overtime_hr']
    ];
}

// Final Success Output for DataTables SSP
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $formatted_data
]);
?>