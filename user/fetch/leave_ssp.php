<?php
// user/fetch/leave_ssp.php
require_once '../../db_connection.php';
session_start();

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$my_id = $_SESSION['employee_id'];

// --- INPUTS ---
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;

// --- SORTING ---
$columns = [0 => 'leave_type', 1 => 'start_date', 2 => 'days_count', 3 => 'status'];
$order_col_index = isset($_GET['order'][0]['column']) ? $_GET['order'][0]['column'] : 1;
$order_dir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'DESC';
$order_column = isset($columns[$order_col_index]) ? $columns[$order_col_index] : 'start_date';

// --- QUERY ---
$sql_base = "FROM tbl_leave WHERE employee_id = :my_id";

// Count
$stmt_total = $pdo->prepare("SELECT COUNT(*) $sql_base");
$stmt_total->execute([':my_id' => $my_id]);
$total_records = $stmt_total->fetchColumn();

// Fetch
$sql_data = "SELECT * $sql_base ORDER BY $order_column $order_dir LIMIT :start, :length";
$stmt = $pdo->prepare($sql_data);
$stmt->bindValue(':my_id', $my_id);
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- FORMAT ---
$response_data = [];
foreach ($data as $row) {
    // Status Logic
    $status_badge = '<span class="badge bg-warning text-dark">Pending</span>';
    if($row['status'] == 1) $status_badge = '<span class="badge bg-success">Approved</span>';
    if($row['status'] == 2) $status_badge = '<span class="badge bg-danger">Rejected</span>';

    $response_data[] = [
        'leave_type' => $row['leave_type'],
        'dates'      => date("M d", strtotime($row['start_date'])) . ' - ' . date("M d, Y", strtotime($row['end_date'])),
        'days_count' => $row['days_count'],
        'status'     => $status_badge
    ];
}

echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $total_records,
    "data" => $response_data
]);
?>