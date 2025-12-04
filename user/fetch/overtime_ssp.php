<?php
// user/fetch/overtime_ssp.php
require_once '../../db_connection.php';
session_start();

// 🛑 SECURITY: Hard Stop if not logged in
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$my_id = $_SESSION['employee_id'];

// --- INPUT PARAMETERS ---
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;

// --- COLUMNS FOR SORTING ---
// Matches the Employee Table structure
$columns = [
    0 => 'ot_date',
    1 => 'raw_ot',  // Placeholder, not a DB column
    2 => 'reason',
    3 => 'hours_requested',
    4 => 'hours_approved',
    5 => 'status',
    6 => 'status'   // Action column placeholder
];

$order_col_index = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : 0;
$order_dir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'DESC';
$order_column = isset($columns[$order_col_index]) ? $columns[$order_col_index] : 'ot_date';

// 🛑 FORCED FILTER: Only fetch MY records
$sql_base = "FROM tbl_overtime t1 
             LEFT JOIN tbl_attendance t2 ON t1.employee_id = t2.employee_id AND t1.ot_date = t2.date
             WHERE t1.employee_id = :my_id";

// --- COUNT TOTAL ---
$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM tbl_overtime WHERE employee_id = :my_id");
$stmt_total->bindValue(':my_id', $my_id); // Bind the user ID here
$stmt_total->execute();
$total_records = $stmt_total->fetchColumn();
$filtered_records = $total_records; // No search bar for now, so total = filtered

// --- FETCH DATA ---
// We join with tbl_attendance to get the RAW LOG time for comparison
$sql_data = "SELECT t1.id, t1.ot_date, t1.hours_requested, t1.hours_approved, t1.reason, t1.status,
                    t2.overtime_hr as raw_log_ot
             $sql_base
             ORDER BY $order_column $order_dir
             LIMIT :start, :length";

$stmt = $pdo->prepare($sql_data);
$stmt->bindValue(':my_id', $my_id);
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- FORMAT DATA ---
$response_data = [];
foreach ($data as $row) {
    
    // Format Raw OT
    $raw_display = ($row['raw_log_ot'] > 0) ? $row['raw_log_ot'] . ' hrs' : '—';
    
    $response_data[] = [
        'ot_date'         => date("M d, Y", strtotime($row['ot_date'])),
        'raw_ot_hr'       => $raw_display,
        'reason'          => htmlspecialchars($row['reason']),
        'hours_requested' => $row['hours_requested'] . ' hrs',
        'hours_approved'  => ($row['hours_approved'] > 0) ? $row['hours_approved'] . ' hrs' : '—',
        
        // Status Badge
        'status'          => ($row['status'] == 'Pending') ? '<span class="badge bg-warning text-dark">Pending</span>' : 
                             (($row['status'] == 'Approved') ? '<span class="badge bg-success">Approved</span>' : '<span class="badge bg-danger">Rejected</span>'),
        
        // Action Button Data (Pass full row object)
        'raw_data'        => $row 
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $filtered_records,
    "data" => $response_data
]);
?>