<?php
// overtime_ssp.php

// --- 1. CONFIGURATION ---
// IMPORTANT: Adjust these paths as necessary
require_once '../../db_connection.php'; 
require_once '../models/OvertimeRepository.php'; // Include the separate fetching class

// Check for PDO connection
if (!isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// Instantiate the Repository
$repository = new OvertimeRepository($pdo);

// DataTables column mapping
// 🛑 UPDATED: Added raw_ot_hr to the array to make it accessible in the DataTables output
$columns = array(
    array( 'db' => 'employee_id',       'dt' => 'employee_id' ),
    array( 'db' => 'employee_name',     'dt' => 'employee_name' ),
    array( 'db' => 'ot_date',           'dt' => 'ot_date' ),
    array( 'db' => 'hours_requested',   'dt' => 'hours_requested' ),
    array( 'db' => 'hours_approved',    'dt' => 'hours_approved' ),
    array( 'db' => 'status',            'dt' => 'status' ),
    array( 'db' => 'reason',            'dt' => 'reason' ),
    array( 'db' => 'created_at',        'dt' => 'created_at' ),
    array( 'db' => 'overtime_hr',       'dt' => 'raw_ot_hr' ) // 🛑 NEW: Raw OT from tbl_attendance
);


// --- 2. DATA COLLECTION & SANITIZATION (Ordering, Searching, Filtering) ---

$draw = $_GET['draw'] ?? 1;

// Paging
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

// Ordering
$order = $_GET['order'] ?? null;
$order_sql = " ORDER BY ot.ot_date DESC, ot.created_at DESC"; // Default sort

if ($order) {
    $column_index = $order[0]['column'];
    
    // Safety check: Ensure index exists in columns array
    if(isset($columns[$column_index])) {
        $column_name = $columns[$column_index]['db'];
        $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';

        // Determine the column to sort by, referencing table aliases (ot, e, or ta for attendance)
        if ($column_name === 'employee_name') {
            $sort_column = "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)";
        } elseif ($column_name === 'ot_date' || $column_name === 'created_at') {
            $sort_column = "ot." . $column_name;
        } elseif ($column_name === 'overtime_hr') { // Allows sorting by raw calculated OT
            $sort_column = "ta." . $column_name;
        } else {
            // Default to the overtime table alias (ot) for other fields
            $sort_column = "ot." . $column_name;
        }

        $order_sql = " ORDER BY $sort_column $dir";
    }
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
    
    // Define searchable columns (ot = overtime, e = employee)
    // NOTE: Need to use named placeholders for PDO::prepare
    $searchable_columns = [
        "ot.employee_id", "e.firstname", "e.middlename", "e.lastname", 
        "ot.ot_date", "ot.status"
    ];

    foreach ($searchable_columns as $col) {
        $search_conditions[] = "$col LIKE :search_value";
    }
    $where_params[] = "(" . implode(' OR ', $search_conditions) . ")";
    
    // Bind the single search value only once
    $where_bindings[':search_value'] = $search_value; 
}

// 2. Date Range (Filtering by overtime date)
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

// --- 3. EXECUTE SQL using Repository ---
try {
    // Total Records (No filtering)
    $recordsTotal = $repository->getTotalRecords();

    // Filtered Records (Using compiled WHERE and bindings)
    $recordsFiltered = $repository->getFilteredRecords($where_sql, $where_bindings);

    // Data Query (Using all compiled clauses)
    $data = $repository->getPaginatedData($where_sql, $order_sql, $limit_sql, $where_bindings);

} catch (PDOException $e) {
    error_log("Overtime SSP Error: " . $e->getMessage());
    $data = [];
    $recordsTotal = 0;
    $recordsFiltered = 0;
}

// --- 4. DATA PROCESSING AND OUTPUT ---
$processed_data = [];
foreach ($data as $row) {
    $status_class = '';
    
    switch ($row['status']) {
        case 'Approved':
            $status_class = 'success';
            break;
        case 'Rejected':
            $status_class = 'danger';
            break;
        default:
            $status_class = 'warning';
            break;
    }
    
    // 🛑 Final processed row for DataTables (including raw_ot_hr)
    $processed_data[] = [
        'employee_id'       => htmlspecialchars($row['employee_id']),
        'employee_name'     => htmlspecialchars($row['employee_name']),
        'ot_date'           => date('M d, Y', strtotime($row['ot_date'])),
        'hours_requested'   => number_format($row['hours_requested'], 2) . ' hrs',
        'hours_approved'    => ($row['hours_approved'] !== null) ? $row['hours_approved'] . ' hrs' : '—',
        'status'            => "<span class='badge bg-soft-{$status_class} text-{$status_class} rounded-pill px-3'>{$row['status']}</span>",
        'reason'            => htmlspecialchars(substr($row['reason'], 0, 50)) . (strlen($row['reason']) > 50 ? '...' : ''),
        'created_at'        => date('M d, Y g:i A', strtotime($row['created_at'])),
        'raw_ot_hr'         => number_format($row['overtime_hr'] ?? 0, 2) . ' hrs', // 🛑 Use the raw calculated value
        'raw_data'          => $row // Pass the full row for action buttons/modals later if needed
    ];
}


// --- 5. FINAL JSON OUTPUT ---

$output = array(
    "draw" => (int)$draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $processed_data // 🛑 Use the processed data array
);

header('Content-Type: application/json');
echo json_encode($output);
exit;