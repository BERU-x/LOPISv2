<?php
// fetch/cash_advance_ssp.php

require_once '../../db_connection.php'; 

// Check connection
if (!isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['draw' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

// --- 1. DEFINE COLUMNS AND QUERY STRUCTURE ---
// Mapping for sorting: 'dt' key matches the column index sent by DataTables
$columns = array(
    0 => 'employee_name',   // Derived column
    1 => 'date_requested',  // DB column
    2 => 'amount',          // DB column
    3 => 'remarks',         // DB column
    4 => 'amount',          // Placeholder for approved amount logic
    5 => 'status',          // DB column
    6 => 'date_requested'   // DB column
);

// Base Query parts
$sql_details = " FROM tbl_cash_advances a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";

// --- 2. PAGINATION (LIMIT) ---
$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

// --- 3. ORDERING (SORT) ---
$order = $_GET['order'] ?? null;
$order_sql = " ORDER BY a.date_requested DESC, a.id DESC"; // Default sort

if ($order && isset($columns[$order[0]['column']])) {
    $col_idx = $order[0]['column'];
    $dir = $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    
    // Custom handling for derived columns
    if ($col_idx == 0) {
        $sort_column = "CONCAT_WS(' ', e.lastname, e.firstname)";
    } else {
        $sort_column = "a." . $columns[$col_idx];
    }
    
    $order_sql = " ORDER BY $sort_column $dir";
}

// --- 4. SEARCHING & FILTERING ---
$search = $_GET['search']['value'] ?? '';
$where_params = []; 
$where_bindings = []; 
$where_sql = "";

// Global Search
if (!empty($search)) {
    $val = '%' . $search . '%';
    $conds = [
        "a.employee_id LIKE :s", 
        "e.firstname LIKE :s", 
        "e.lastname LIKE :s", 
        "a.status LIKE :s",
        "a.remarks LIKE :s"
    ];
    $where_params[] = "(" . implode(' OR ', $conds) . ")";
    $where_bindings[':s'] = $val;
}

// Custom Date Range Filter
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if ($start_date && $end_date) { 
    $where_params[] = "a.date_requested BETWEEN :sd AND :ed"; 
    $where_bindings[':sd'] = $start_date; 
    $where_bindings[':ed'] = $end_date; 
}

if (!empty($where_params)) {
    $where_sql = " WHERE " . implode(' AND ', $where_params);
}

// --- 5. EXECUTE QUERIES ---

// A. Count Total Records (Without filters)
$stmt = $pdo->query("SELECT COUNT(a.id) $sql_details");
$recordsTotal = $stmt->fetchColumn();

// B. Count Filtered Records (With filters)
$stmt = $pdo->prepare("SELECT COUNT(a.id) $sql_details $where_sql");
$stmt->execute($where_bindings);
$recordsFiltered = $stmt->fetchColumn();

// C. Fetch Data
// We fetch columns needed for display. 
// Note: Your schema doesn't have 'amount_approved' or 'created_at', so we use defaults or re-use existing cols.
$sql_data = "SELECT 
                a.id, 
                a.employee_id, 
                CONCAT(e.lastname, ', ', e.firstname) AS employee_name, 
                a.date_requested, 
                a.amount, 
                a.status, 
                a.remarks
             $sql_details 
             $where_sql 
             $order_sql 
             $limit_sql";

$stmt = $pdo->prepare($sql_data);
$stmt->execute($where_bindings);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 6. FORMAT DATA ---
$formatted_data = [];

foreach ($raw_data as $row) {
    
    // Format Currency
    $amount_req = number_format((float)$row['amount'], 2, '.', ',');
    
    // Logic for "Amount Approved"
    // Since your DB doesn't have an approved column, we assume:
    // If 'Deducted' (Approved), the full amount is approved. Otherwise '--'.
    $amount_app = ($row['status'] === 'Deducted') ? $amount_req : '—';

    // Status Badge Logic
    $status_str = $row['status'];
    $badge_class = 'secondary';
    
    if ($status_str === 'Deducted') {
        $badge_class = 'success'; // Treat Deducted as Approved
    } elseif ($status_str === 'Pending') {
        $badge_class = 'warning';
    } elseif ($status_str === 'Cancelled') {
        $badge_class = 'danger';
    }

    $status_badge = '<span class="badge bg-soft-'.$badge_class.' text-'.$badge_class.' border border-'.$badge_class.' px-2 rounded-pill">'.$status_str.'</span>';

    // Raw data object for the frontend JS buttons
    // Note: JS expects "amount_requested" (from previous code), mapping it here
    $raw_data_obj = [
        'id' => $row['id'],
        'status' => $row['status'],
        'amount_requested' => $row['amount'] 
    ];

    $formatted_data[] = [
        'employee_name'    => $row['employee_name'],
        'employee_id'      => $row['employee_id'], // Needed for JS render
        'date_needed'      => date('M d, Y', strtotime($row['date_requested'])),
        'amount_requested' => $row['amount'], // Send raw number for JS formatting, or pre-format here if you prefer
        'purpose'          => $row['remarks'] ?? '--',
        'amount_approved'  => $amount_app, 
        'status'           => $status_badge,
        'created_at'       => date('M d, Y', strtotime($row['date_requested'])), // Fallback since no created_at
        'raw_data'         => $raw_data_obj
    ];
}

// --- 7. OUTPUT JSON ---
header('Content-Type: application/json');
echo json_encode([
    "draw" => (int)$draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $formatted_data
]);
?>