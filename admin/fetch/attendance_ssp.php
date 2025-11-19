<?php
// attendance_ssp.php

// --- 1. CONFIGURATION ---
// Include your database connection script
// NOTE: Assuming '../../db_connection.php' establishes the $pdo connection object.
require_once '../../db_connection.php'; 
// Ensure $pdo is initialized in db_connection.php
if (!isset($pdo)) {
    // Fallback error handling if connection is missing
    header('Content-Type: application/json');
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// DataTables column mapping (must match column order in your JS initialization)
$columns = array(
    array( 'db' => 'employee_id',     'dt' => 'employee_id' ),
    array( 'db' => 'employee_name',   'dt' => 'employee_name' ),
    array( 'db' => 'date',            'dt' => 'date' ),
    array( 'db' => 'time_in',         'dt' => 'time_in' ),
    array( 'db' => 'status',          'dt' => 'status' ),
    array( 'db' => 'time_out',        'dt' => 'time_out' ),
    array( 'db' => 'status_out',      'dt' => 'status_out' ),
    array( 'db' => 'num_hr',          'dt' => 'num_hr' ),
    array( 'db' => 'status_based',    'dt' => 'status_based' )
);

// Primary table
$table = 'tbl_attendance';

// SQL table aliases and joins
$sql_details = "
    FROM tbl_attendance a
    LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id
";

// --- 2. DATA COLLECTION & SANITIZATION (Standard DataTables Parameters) ---

// Draw counter (required by DataTables)
$draw = $_GET['draw'] ?? 1;

// Paging
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

// Ordering
$order = $_GET['order'] ?? null;
$order_sql = "";
if ($order) {
    // 🚨 THIS IS WHERE THE FIX GOES 🚨
    $column_index = $order[0]['column'];
    $column_name = $columns[$column_index]['db'];
    $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';

    // The logic below ensures that if the browser sends *any* instruction that involves time_in, 
    // the complex sort is used.

    // 1. Check if the instruction is for the TIME IN column (index 3)
    if ($column_index == 3) {
        $sort_column = "TIME(a.time_in)";
    } 
    // 2. Check if the instruction is for the DATE column (index 2)
    elseif ($column_index == 2) {
        // When sorting by DATE, we must force the secondary sort by TIME IN!
        // This effectively overrides the single column sort with the desired chained sort.
        $order_sql = " ORDER BY a.date $dir, TIME(a.time_in) DESC";
        // We set $order to null and $sort_column to prevent double ORDER BY clauses
        // but we need to ensure the logic bypasses the final $order_sql construction.
        // The simple way is to use the full SQL directly here and skip the rest.
        // We RETURN from the IF block by setting $order_sql and continuing.
        goto skip_manual_sort; 

    } else {
        // If sorting by any other column (like employee_name, index 1)
        $sort_column = in_array($column_name, ['employee_name']) ? "CONCAT(e.firstname, ' ', e.lastname)" : "a." . $column_name;
    }

    // This is the default SQL construction if column 2 was not clicked
    $order_sql = " ORDER BY $sort_column $dir";
    
    skip_manual_sort:

} else {
    // 🔥 FINAL DEFAULT FIX: This handles the base load when NO instruction is sent (or if JS fails to send index 2)
    // This is the most reliable structure for the base load.
    $order_sql = " ORDER BY a.date DESC, TIME(a.time_in) DESC"; 
}

// Searching (Global search value)
$search = $_GET['search']['value'] ?? '';


// --- 2. DATA COLLECTION & SANITIZATION (Cont. - Filtering Logic) ---

// Custom Date Filtering Parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Combined WHERE clause
$where_params = [];
$where_bindings = [];
$where_sql = "";

// 1. Global Search Condition
if (!empty($search)) {
    // The logic block below was partially defined but relied on the global $search variable
    $search_value = '%' . $search . '%';
    $search_conditions = [];
    $searchable_columns = [
        "a.employee_id", "e.firstname", "e.lastname", "a.date", "a.time_in", "a.time_out",
        "a.status_based", "a.status_out"
    ];

    foreach ($searchable_columns as $col) {
        $search_conditions[] = "$col LIKE :search_value";
    }
    $where_params[] = "(" . implode(' OR ', $search_conditions) . ")";
    $where_bindings[':search_value'] = $search_value;
}

// 2. Date Range Filter Condition
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

// Construct the final WHERE SQL
if (!empty($where_params)) {
    $where_sql = " WHERE " . implode(' AND ', $where_params);
}

// --- 3. EXECUTE SQL QUERIES ---
try {
    // Total Records (No filtering)
    $stmt_total = $pdo->query("SELECT COUNT(a.id) $sql_details");
    $recordsTotal = $stmt_total->fetchColumn();

    // Total Records (After filtering)
    $stmt_filtered_sql = "SELECT COUNT(a.id) $sql_details $where_sql";
    $stmt_filtered = $pdo->prepare($stmt_filtered_sql);
    $stmt_filtered->execute($where_bindings); // Execute with combined bindings
    $recordsFiltered = $stmt_filtered->fetchColumn();

    // Data Query
    $data_fields = "a.id, a.employee_id, CONCAT(e.firstname, ' ', e.lastname) AS employee_name, a.date, a.status_based, a.time_in, a.status, a.time_out, a.status_out, a.num_hr";
    $sql_data = "SELECT $data_fields $sql_details $where_sql $order_sql $limit_sql";
    
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->execute($where_bindings); // Execute with combined bindings
    $data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle database error
    error_log("SSP Error: " . $e->getMessage());
    $data = [];
    $recordsTotal = 0;
    $recordsFiltered = 0;
}

// --- 4. OUTPUT JSON ---

$output = array(
    "draw" => (int)$draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $data
);

header('Content-Type: application/json');
echo json_encode($output);

// IMPORTANT: Do not include template/footer.php or any other HTML content after this script runs.
exit;