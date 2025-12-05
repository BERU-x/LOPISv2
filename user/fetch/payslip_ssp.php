<?php
// user/fetch/payslip_ssp.php - Employee Portal Payslip History
// Custom Server-Side Processing for DataTables

// --- 1. SETUP & SECURITY ---
// Assuming db_connection.php sets up the $pdo object
require_once '../../db_connection.php';
session_start();

// 🛑 SECURITY: Hard Stop if not logged in
if (!isset($_SESSION['employee_id'])) {
    // Return an empty data set and error status to DataTables
    echo json_encode(["draw" => 0, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    exit;
}

$my_id = $_SESSION['employee_id'];

// --- 2. INPUT PARAMETERS ---
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;

// --- 3. COLUMNS FOR SORTING ---
// Matches the payslipTable structure (Cut-Off, Net Pay, Status, Action)
$columns = [
    0 => 'cut_off_end', // Default sort by end date
    1 => 'net_pay',
    2 => 'status',
    3 => 'id' // Action column placeholder (uses ID for view link)
];

$order_col_index = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : 0;
$order_dir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'DESC';
// Ensure the selected column is valid, otherwise default to cut_off_end
$order_column = isset($columns[$order_col_index]) ? $columns[$order_col_index] : 'cut_off_end';

// --- 4. FORCED FILTER & BASE QUERY ---
// Only fetch records for the logged-in employee AND where STATUS = 1 (Approved/Paid)
$sql_base = "FROM tbl_payroll 
             WHERE employee_id = :my_id AND status = 1"; // <--- MODIFICATION HERE

// --- 5. COUNT TOTAL ---
// The count must also use the new filter
$stmt_total = $pdo->prepare("SELECT COUNT(id) $sql_base");
$stmt_total->bindValue(':my_id', $my_id);
$stmt_total->execute();
$total_records = $stmt_total->fetchColumn();
$filtered_records = $total_records; // No search filter applied here

// --- 6. FETCH PAGINATED DATA ---
// The data fetch uses the new filter
$sql_data = "SELECT id, cut_off_start, cut_off_end, net_pay, status 
             $sql_base
             ORDER BY $order_column $order_dir
             LIMIT :start, :length";

$stmt = $pdo->prepare($sql_data);
$stmt->bindValue(':my_id', $my_id);
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 7. FORMAT DATA ---
$response_data = [];
foreach ($data as $row) {
    
    // Status Badge Logic (Only status 1 should appear, but we keep the logic clean)
    $status_badge = '<span class="badge bg-success border border-success px-3 shadow-sm rounded-pill">Paid</span>';
    
    $response_data[] = [
        // Col 0: Cut-Off Period
        'cut_off_start' => date("M d, Y", strtotime($row['cut_off_start'])),
        'cut_off_end'   => date("M d, Y", strtotime($row['cut_off_end'])),

        // Col 1: Net Pay
        'net_pay'       => floatval($row['net_pay']), 
        
        // Col 2: Status (Since we filtered to status=1, it will always be 'Paid')
        'status'        => $status_badge,
        
        // Col 3: Action
        'id'            => $row['id']
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