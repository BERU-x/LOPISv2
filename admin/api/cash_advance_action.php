<?php
// api/cash_advance_action.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../models/global_model.php'; 

if (!isset($pdo)) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? 'fetch';

// =================================================================================
// ACTION: FETCH DATA (For DataTables)
// =================================================================================
if ($action === 'fetch') {

    // --- A. COLUMNS & SORTING ---
    // Updated Column Mapping: 0:Employee, 1:Date, 2:Amount, 3:Status
    $columns = [
        0 => 'employee_name', 
        1 => 'a.date_requested', 
        2 => 'a.amount', 
        3 => 'a.status'
    ];

    $draw   = $_GET['draw'] ?? 1;
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = $_GET['search']['value'] ?? '';
    
    // Custom Filters
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    // --- B. BUILD QUERY ---
    $sql_base = " FROM tbl_cash_advances a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";
    
    $where_params = [];
    $where_bindings = [];

    // Global Search
    if (!empty($search)) {
        $term = '%' . $search . '%';
        $where_params[] = "(
            a.employee_id LIKE :search OR 
            e.firstname LIKE :search OR 
            e.lastname LIKE :search OR 
            a.status LIKE :search
        )";
        $where_bindings[':search'] = $term;
    }

    // Date Range
    if (!empty($start_date) && !empty($end_date)) {
        $where_params[] = "a.date_requested BETWEEN :start AND :end";
        $where_bindings[':start'] = $start_date;
        $where_bindings[':end']   = $end_date;
    }

    $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";

    // Ordering
    $order_sql = " ORDER BY a.date_requested DESC, a.created_at DESC"; // Default
    if (isset($_GET['order'])) {
        $col_idx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        
        if (isset($columns[$col_idx])) {
            $colName = $columns[$col_idx];
            // Handle custom sort for Name column
            if ($colName === 'employee_name') {
                $order_sql = " ORDER BY e.lastname $dir, e.firstname $dir";
            } else {
                $order_sql = " ORDER BY $colName $dir";
            }
        }
    }

    $limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

    // --- C. EXECUTE COUNT QUERIES ---
    $stmt = $pdo->query("SELECT COUNT(a.id) $sql_base");
    $recordsTotal = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(a.id) $sql_base $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = $stmt->fetchColumn();

    // --- D. FETCH DATA ---
    $sql_data = "SELECT 
                    a.id, 
                    a.employee_id, 
                    a.date_requested, 
                    a.amount, 
                    a.status, 
                    a.remarks,
                    e.firstname, e.lastname, e.photo, e.department 
                 $sql_base $where_sql $order_sql $limit_sql";

    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($where_bindings);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "draw" => (int)$draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $data
    ]);
    exit;
}

// =================================================================================
// ACTION: GET DETAILS (For View Modal)
// =================================================================================
if ($action === 'get_details') {
    $id = $_POST['id'] ?? 0;

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   e.firstname, e.lastname, e.photo, e.department, e.position
            FROM tbl_cash_advances a
            LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($data ?: []);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: PROCESS (Approve/Reject)
// =================================================================================
if ($action === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = $_POST['id'];
    $type   = $_POST['type']; 
    $amount = $_POST['amount'] ?? 0;

    try {
        $stmt_info = $pdo->prepare("SELECT employee_id, date_requested FROM tbl_cash_advances WHERE id = ?");
        $stmt_info->execute([$id]);
        $ca_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($type === 'approve') {
            $sql = "UPDATE tbl_cash_advances SET status = 'Deducted', amount = ?, date_updated = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$amount, $id]);
            $msg = "Cash Advance approved (₱" . number_format($amount, 2) . ").";
            $notif_msg = "Your Cash Advance request has been APPROVED.";
        } else {
            $sql = "UPDATE tbl_cash_advances SET status = 'Cancelled', date_updated = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $msg = "Cash Advance rejected.";
            $notif_msg = "Your Cash Advance request has been REJECTED.";
        }

        if ($ca_info) {
            send_notification($pdo, $ca_info['employee_id'], 'Employee', 'cash_advance', $notif_msg, 'my_cash_advance.php', null);
        }

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>