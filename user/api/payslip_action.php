<?php
// api/payslip_action.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db_connection.php';

// Security Check
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];
$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION: FETCH STATS (Latest Pay & Count)
// =================================================================================
if ($action === 'stats') {
    try {
        $response = [];

        // 1. Latest Net Pay (Most recent paid slip)
        $stmt = $pdo->prepare("SELECT net_pay FROM tbl_payroll WHERE employee_id = ? AND status = 1 ORDER BY cut_off_end DESC LIMIT 1");
        $stmt->execute([$employee_id]);
        $last_pay = $stmt->fetchColumn();
        $response['last_net_pay'] = $last_pay ? number_format($last_pay, 2) : '0.00';

        // 2. Total Payslips Count
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM tbl_payroll WHERE employee_id = ? AND status = 1");
        $stmt->execute([$employee_id]);
        $response['count'] = $stmt->fetchColumn();

        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: FETCH TABLE DATA (DataTables SSP)
// =================================================================================
if ($action === 'fetch') {
    // 1. Columns for Ordering
    $columns = [
        0 => 'cut_off_start',
        1 => 'net_pay',
        2 => 'status',
        3 => 'id'
    ];

    // 2. SSP Parameters
    $draw = $_GET['draw'] ?? 1;
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    
    // 3. Base Query (Only Paid/Approved Payslips for Employee)
    $sql_base = " FROM tbl_payroll WHERE employee_id = :emp_id AND status = 1";
    $params = [':emp_id' => $employee_id];

    // 4. Counting
    $stmt_total = $pdo->prepare("SELECT COUNT(id) " . $sql_base);
    $stmt_total->execute($params);
    $recordsTotal = $stmt_total->fetchColumn();
    $recordsFiltered = $recordsTotal; // No search filter in this view, so they are same

    // 5. Ordering
    $order_sql = " ORDER BY cut_off_end DESC"; // Default
    if (isset($_GET['order'])) {
        $col_idx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        if (isset($columns[$col_idx])) {
            $order_sql = " ORDER BY " . $columns[$col_idx] . " " . $dir;
        }
    }

    // 6. Pagination
    $limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

    // 7. Fetch Data
    $sql_data = "SELECT id, cut_off_start, cut_off_end, net_pay, status " . $sql_base . $order_sql . $limit_sql;
    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Format Data
    $formatted_data = [];
    foreach ($data as $row) {
        $formatted_data[] = [
            'id' => $row['id'],
            'cut_off_start' => date('M d, Y', strtotime($row['cut_off_start'])),
            'cut_off_end'   => date('M d, Y', strtotime($row['cut_off_end'])),
            'net_pay'       => $row['net_pay'],
            'status'        => $row['status'] // Will be formatted in JS
        ];
    }

    echo json_encode([
        "draw" => (int)$draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $formatted_data
    ]);
    exit;
}
?>