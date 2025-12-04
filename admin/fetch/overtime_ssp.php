<?php
// overtime_ssp.php

// --- 1. CONFIGURATION ---
// IMPORTANT: Adjust these paths as necessary
require_once '../../db_connection.php'; 
require_once '../models/OvertimeRepository.php'; 

// Check for PDO connection
if (!isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// Instantiate the Repository
$repository = new OvertimeRepository($pdo);

// DataTables column mapping
// Added photo just for reference, usually not sortable
$columns = array(
    array( 'db' => 'employee_id',       'dt' => 'employee_id' ),
    array( 'db' => 'employee_name',     'dt' => 'employee_name' ),
    array( 'db' => 'photo',             'dt' => 'photo' ), // 🛑 NEW: Mapped for reference
    array( 'db' => 'ot_date',           'dt' => 'ot_date' ),
    array( 'db' => 'hours_requested',   'dt' => 'hours_requested' ),
    array( 'db' => 'hours_approved',    'dt' => 'hours_approved' ),
    array( 'db' => 'status',            'dt' => 'status' ),
    array( 'db' => 'reason',            'dt' => 'reason' ),
    array( 'db' => 'created_at',        'dt' => 'created_at' ),
    array( 'db' => 'overtime_hr',       'dt' => 'raw_ot_hr' )
);


// --- 2. DATA COLLECTION & SANITIZATION ---

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

// Ordering
$order = $_GET['order'] ?? null;
$order_sql = " ORDER BY ot.ot_date DESC, ot.created_at DESC"; // Default sort

if ($order) {
    $column_index = $order[0]['column'];
    
    if(isset($columns[$column_index])) {
        $column_name = $columns[$column_index]['db'];
        $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';

        if ($column_name === 'employee_name') {
            $sort_column = "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)";
        } elseif ($column_name === 'ot_date' || $column_name === 'created_at') {
            $sort_column = "ot." . $column_name;
        } elseif ($column_name === 'overtime_hr') {
            $sort_column = "ta." . $column_name;
        } else {
            // Default to 'ot' alias, but skip if it's 'photo' (not sortable)
            if ($column_name !== 'photo') {
                $sort_column = "ot." . $column_name;
            } else {
                $sort_column = "ot.id"; // Fallback
            }
        }
        $order_sql = " ORDER BY $sort_column $dir";
    }
} 

// Searching
$search = $_GET['search']['value'] ?? '';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$where_params = [];
$where_bindings = [];
$where_sql = "";

// 1. Global Search
if (!empty($search)) {
    $search_value = '%' . $search . '%';
    $search_conditions = [];
    $searchable_columns = ["ot.employee_id", "e.firstname", "e.middlename", "e.lastname", "ot.ot_date", "ot.status"];

    foreach ($searchable_columns as $col) {
        $search_conditions[] = "$col LIKE :search_value";
    }
    $where_params[] = "(" . implode(' OR ', $search_conditions) . ")";
    $where_bindings[':search_value'] = $search_value; 
}

// 2. Date Range
if (!empty($start_date) && !empty($end_date)) {
    $where_params[] = "ot.ot_date BETWEEN :start_date AND :end_date";
    $where_bindings[':start_date'] = $start_date;
    $where_bindings[':end_date'] = $end_date;
} elseif (!empty($start_date)) {
    $where_params[] = "ot.ot_date >= :start_date";
    $where_bindings[':start_date'] = $start_date;
} elseif (!empty($end_date)) {
    $where_params[] = "ot.ot_date <= :end_date";
    $where_bindings[':end_date'] = $end_date;
}

if (!empty($where_params)) {
    $where_sql = " WHERE " . implode(' AND ', $where_params);
}

// --- 3. EXECUTE SQL ---
try {
    $recordsTotal = $repository->getTotalRecords();
    $recordsFiltered = $repository->getFilteredRecords($where_sql, $where_bindings);
    $data = $repository->getPaginatedData($where_sql, $order_sql, $limit_sql, $where_bindings);

} catch (PDOException $e) {
    error_log("Overtime SSP Error: " . $e->getMessage());
    $data = [];
    $recordsTotal = 0;
    $recordsFiltered = 0;
}

// --- 4. DATA PROCESSING ---
$processed_data = [];
foreach ($data as $row) {
    
    // 🛑 KEY FIX: We pass the raw 'photo' field to the JS.
    // The previous version was creating a NEW array and leaving 'photo' out.
    // By merging the original $row into our response, JS gets everything.
    
    // Format numeric values
    $row['hours_requested'] = number_format($row['hours_requested'], 2);
    $row['raw_ot_hr'] = number_format($row['overtime_hr'] ?? 0, 2);
    
    // Pass the raw row data for JS to access (including photo, status, id)
    // We don't need to build HTML strings here anymore because your JS 'render' functions handle that.
    $processed_data[] = $row; 
}

// --- 5. FINAL JSON OUTPUT ---
$output = array(
    "draw" => (int)$draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $processed_data
);

header('Content-Type: application/json');
echo json_encode($output);
exit;