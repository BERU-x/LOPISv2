<?php
// user/fetch/attendance_ssp.php - Employee Portal Attendance Logs
// Custom Server-Side Processing for DataTables

// --- 1. SETUP & SECURITY ---
require_once '../../db_connection.php'; 
session_start();

// 🛑 SECURITY: Hard Stop if not logged in or missing ID
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(["draw" => 0, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    exit;
}

$my_id = $_SESSION['employee_id'];

// --- 2. INPUT PARAMETERS ---
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;

// Date Filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// --- 3. COLUMNS FOR SORTING ---
$columns = [
    0 => 'date', 
    1 => 'time_in',
    2 => 'attendance_status',
    3 => 'time_out',
    4 => 'num_hr',
    5 => 'overtime_hr'
];

$order_col_index = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : 0;
$order_dir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'DESC';
$order_column = $columns[$order_col_index] ?? 'date';

// --- 4. BASE QUERY CONDITION BUILDING ---
$conditions = ["employee_id = :my_id"]; // Start with the mandatory filter
$bindings = [':my_id' => $my_id];

// Date Range Filter
if (!empty($start_date) && !empty($end_date)) {
    $conditions[] = "date BETWEEN :start_date AND :end_date";
    $bindings[':start_date'] = $start_date;
    $bindings[':end_date'] = $end_date;
}

// Final WHERE clause prefix
$where_prefix = "WHERE " . implode(' AND ', $conditions);

// --- 5. COUNT TOTAL & FILTERED RECORDS ---
// Total Records (filtered by employee ID only)
$sql_total = "SELECT COUNT(id) FROM tbl_attendance WHERE employee_id = :my_id";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->bindValue(':my_id', $my_id);
$stmt_total->execute();
$total_records = $stmt_total->fetchColumn();


// Filtered Records (filtered by employee ID AND date range)
$sql_filtered = "SELECT COUNT(id) FROM tbl_attendance $where_prefix";
$stmt_filtered = $pdo->prepare($sql_filtered);

// Bind all parameters for the filtered count query
foreach ($bindings as $key => &$value) {
    $stmt_filtered->bindValue($key, $value);
}
$stmt_filtered->execute();
$filtered_records = $stmt_filtered->fetchColumn();


// --- 6. FETCH PAGINATED DATA ---
$sql_data = "SELECT id, employee_id, date, time_in, time_out, num_hr, overtime_hr, attendance_status
             FROM tbl_attendance 
             $where_prefix
             ORDER BY $order_column $order_dir
             LIMIT :start, :length";

$stmt = $pdo->prepare($sql_data);

// Bind all parameters (Employee ID + Date Filters)
foreach ($bindings as $key => &$value) {
    $stmt->bindValue($key, $value);
}

// Bind pagination
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 7. FORMAT DATA ---
$response_data = [];
$status_map = [
    // Highest priority first (will be checked first for single field storage)
    'Late'          => ['text' => 'Late', 'color' => 'danger'],
    'Undertime'     => ['text' => 'Undertime', 'color' => 'warning'],
    'Forgot Time Out' => ['text' => 'Forgot Out', 'color' => 'secondary'], // Secondary/Gray color
    'Overtime'      => ['text' => 'Overtime', 'color' => 'primary'],
    'On Time'       => ['text' => 'On Time', 'color' => 'success'],
    'Ontime'        => ['text' => 'On Time', 'color' => 'success'],
    'Absent'        => ['text' => 'Absent', 'color' => 'dark'],
];

foreach ($data as $row) {
    
    $status_string = $row['attendance_status'] ?? '';
    $status_string = strtolower($status_string); // Normalize for checking
    $badge_html = [];
    $found_status = false;

    // Check for each defined status within the status string
    foreach ($status_map as $key => $map) {
        $search_key = strtolower($key);
        // Use a word boundary check to avoid false matches (e.g., 'Late' matching 'related')
        if (strpos($status_string, strtolower($key)) !== false) {
            $badge_html[] = '<span class="badge bg-' . $map['color'] . ' text-white px-3 shadow-sm rounded-pill small">' . $map['text'] . '</span>';
            $found_status = true;
        }
    }
    
    // If no specific status was found, provide a generic N/A badge
    if (!$found_status) {
        $badge_html[] = '<span class="badge bg-light text-muted px-3 shadow-sm rounded-pill small">N/A</span>';
    }
    
    // Combine all badges into a single string for the table column
    $final_badge_output = implode(' ', $badge_html);
    
    // Format Time In/Out
    $time_in_display = !empty($row['time_in']) ? date('h:i A', strtotime($row['time_in'])) : '—';
    $time_out_display = !empty($row['time_out']) ? date('h:i A', strtotime($row['time_out'])) : '—';

    $response_data[] = [
        'date'          => date('M d, Y', strtotime($row['date'])),
        'time_in'       => $time_in_display,
        'status'        => $final_badge_output,
        'time_out'      => $time_out_display,
        'num_hr'        => floatval($row['num_hr']),
        'overtime_hr'   => floatval($row['overtime_hr']),
    ];
}

// --- 8. OUTPUT JSON ---
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $filtered_records,
    "data" => $response_data
]);
?>