<?php
// attendance_ssp.php

// --- 1. CONFIGURATION ---
require_once '../../db_connection.php'; 

if (!isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// DataTables column mapping
$columns = array(
    array( 'db' => 'employee_id',     'dt' => 'employee_id' ),
    array( 'db' => 'employee_name',   'dt' => 'employee_name' ),
    array( 'db' => 'date',            'dt' => 'date' ),
    array( 'db' => 'time_in',         'dt' => 'time_in' ),
    array( 'db' => 'status',          'dt' => 'status' ),
    array( 'db' => 'time_out',        'dt' => 'time_out' ),
    array( 'db' => 'status_out',      'dt' => 'status_out' ),
    array( 'db' => 'num_hr',          'dt' => 'num_hr' ),
    array( 'db' => 'overtime_hr',     'dt' => 'overtime_hr' ), 
    array( 'db' => 'status_based',    'dt' => 'status_based' )
);

// Primary table
$table = 'tbl_attendance';

// SQL table aliases and joins
$sql_details = "
    FROM tbl_attendance a
    LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id
";

// --- 2. DATA COLLECTION & SANITIZATION ---

$draw = $_GET['draw'] ?? 1;

// Paging
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

// Ordering
$order = $_GET['order'] ?? null;
$order_sql = "";

if ($order) {
    $column_index = $order[0]['column'];
    
    // Safety check: Ensure index exists in columns array
    if(isset($columns[$column_index])) {
        $column_name = $columns[$column_index]['db'];
        $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';

        // 1. Check if instruction is for TIME IN (index 3)
        if ($column_index == 3) {
            $sort_column = "TIME(a.time_in)";
        } 
        // 2. Check if instruction is for DATE (index 2)
        elseif ($column_index == 2) {
            $order_sql = " ORDER BY a.date $dir, TIME(a.time_in) DESC";
            goto skip_manual_sort; 
        } else {
            // General sorting
            // [UPDATED] Use CONCAT_WS to handle Middle Name in sorting
            $sort_column = ($column_name === 'employee_name') 
                ? "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)" 
                : "a." . $column_name;
        }

        $order_sql = " ORDER BY $sort_column $dir";
    }
    
    skip_manual_sort:

} else {
    // Default load sort
    $order_sql = " ORDER BY a.date DESC, TIME(a.time_in) DESC"; 
}

// Searching
$search = $_GET['search']['value'] ?? '';

// --- FILTERING LOGIC ---

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$where_params = [];
$where_bindings = [];
$where_sql = "";

// 1. Global Search
if (!empty($search)) {
    $search_value = '%' . $search . '%';
    $search_conditions = [];
    
    // [UPDATED] Added "e.middlename" to search
    $searchable_columns = [
        "a.employee_id", "e.firstname", "e.middlename", "e.lastname", 
        "a.date", "a.time_in", "a.time_out", "a.status_based", "a.status_out"
    ];

    foreach ($searchable_columns as $col) {
        $search_conditions[] = "$col LIKE :search_value";
    }
    $where_params[] = "(" . implode(' OR ', $search_conditions) . ")";
    $where_bindings[':search_value'] = $search_value;
}

// 2. Date Range
if (!empty($start_date) && !empty($end_date)) {
    $where_params[] = "a.date BETWEEN :start_date AND :end_date";
    $where_bindings[':start_date'] = $start_date;
    $where_bindings[':end_date'] = $end_date;
} elseif (!empty($start_date)) {
    $where_params[] = "a.date >= :start_date";
    $where_bindings[':start_date'] = $start_date;
} elseif (!empty($end_date)) {
    $where_params[] = "a.date <= :end_date";
    $where_bindings[':end_date'] = $end_date;
}

if (!empty($where_params)) {
    $where_sql = " WHERE " . implode(' AND ', $where_params);
}

// --- 3. EXECUTE SQL ---
try {
    // Total Records
    $stmt_total = $pdo->query("SELECT COUNT(a.id) $sql_details");
    $recordsTotal = $stmt_total->fetchColumn();

    // Filtered Records
    $stmt_filtered_sql = "SELECT COUNT(a.id) $sql_details $where_sql";
    $stmt_filtered = $pdo->prepare($stmt_filtered_sql);
    $stmt_filtered->execute($where_bindings); 
    $recordsFiltered = $stmt_filtered->fetchColumn();

    // Data Query
    // [UPDATED] Used CONCAT_WS for employee_name to include middlename cleanly
    $data_fields = "a.id, a.employee_id, CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) AS employee_name, a.date, a.status_based, a.time_in, a.status, a.time_out, a.status_out, a.num_hr, a.overtime_hr";
    
    $sql_data = "SELECT $data_fields $sql_details $where_sql $order_sql $limit_sql";
    
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->execute($where_bindings);
    $data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("SSP Error: " . $e->getMessage());
    $data = [];
    $recordsTotal = 0;
    $recordsFiltered = 0;
}

// --- 4. OUTPUT ---

$output = array(
    "draw" => (int)$draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $data
);

header('Content-Type: application/json');
echo json_encode($output);
exit;
?>