<?php
/**
 * api/admin/cash_advance_action.php
 * Handles Cash Advance Server-Side Processing (SSP) and Approval/Cancellation logic.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION & SECURITY ---
// Access restricted to Superadmin (0) or Admin (1)
if (!isset($_SESSION['usertype']) || !in_array($_SESSION['usertype'], [0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../app/models/global_app_model.php'; // Integrated notification model
require_once __DIR__ . '/../../helpers/audit_helper.php';

if (!isset($pdo)) {
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? 'fetch';
$draw = (int)($_GET['draw'] ?? 1);

// =================================================================================
// ACTION 1: FETCH DATA (For DataTables SSP)
// =================================================================================
if ($action === 'fetch') {

    $columns = [
        0 => 'e.lastname', 
        1 => 'a.date_requested', 
        2 => 'a.amount', 
        3 => 'a.status'
    ];

    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    $sql_base = " FROM tbl_cash_advances a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";
    
    $where_params = [];
    $where_bindings = [];

    if (!empty($search)) {
        $term = '%' . $search . '%';
        $where_params[] = "(a.employee_id LIKE :search OR e.firstname LIKE :search OR e.lastname LIKE :search OR a.status LIKE :search)";
        $where_bindings[':search'] = $term;
    }

    if (!empty($start_date) && !empty($end_date)) {
        $where_params[] = "a.date_requested BETWEEN :start_date AND :end_date";
        $where_bindings[':start_date'] = $start_date;
        $where_bindings[':end_date']   = $end_date;
    }

    $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";

    // Default Ordering: Pending first, then newest
    $order_sql = " ORDER BY CASE WHEN a.status = 'Pending' THEN 0 ELSE 1 END, a.date_requested DESC"; 
    
    if (isset($_GET['order'])) {
        $col_idx = (int)$_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
        if (isset($columns[$col_idx])) {
            $colName = $columns[$col_idx];
            $order_sql = " ORDER BY " . ($colName === 'e.lastname' ? "e.lastname $dir, e.firstname $dir" : "$colName $dir");
        }
    }

    try {
        $recordsTotal = (int)$pdo->query("SELECT COUNT(a.id) FROM tbl_cash_advances a")->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(a.id) $sql_base $where_sql");
        $stmt->execute($where_bindings);
        $recordsFiltered = (int)$stmt->fetchColumn();

        $sql_data = "SELECT a.id, a.employee_id, a.date_requested, a.amount, a.status, a.remarks,
                            e.firstname, e.lastname, e.photo, e.department 
                     $sql_base $where_sql $order_sql LIMIT :start_limit, :length_limit";

        $stmt = $pdo->prepare($sql_data);
        foreach ($where_bindings as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->bindValue(':start_limit', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length_limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ]);

    } catch (PDOException $e) {
        echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 2: PROCESS (Approve/Reject)
// =================================================================================
if ($action === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $type   = trim($_POST['type'] ?? ''); 
    $amount = (float)($_POST['amount'] ?? 0);

    if ($id <= 0 || !in_array($type, ['approve', 'reject'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid processing parameters.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        // Use FOR UPDATE to prevent race conditions during financial processing
        $stmt_info = $pdo->prepare("SELECT employee_id, amount FROM tbl_cash_advances WHERE id = ? FOR UPDATE");
        $stmt_info->execute([$id]);
        $ca_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ca_info) {
             $pdo->rollBack();
             echo json_encode(['status' => 'error', 'message' => 'Cash Advance record not found.']);
             exit;
        }

        if ($type === 'approve') {
            // "Deducted" status indicates it is ready for payroll deduction
            $sql = "UPDATE tbl_cash_advances SET status = 'Deducted', amount = ?, date_updated = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$amount, $id]);
            
            $audit_action = "APPROVE_CASH_ADVANCE";
            $audit_msg = "Approved amount ₱" . number_format($amount, 2) . " for ID: $id";
            $notif_msg = "Your Cash Advance request for ₱" . number_format($amount, 2) . " has been APPROVED.";
            $notif_type = "Cash Advance Approved";
        } else {
            $sql = "UPDATE tbl_cash_advances SET status = 'Cancelled', date_updated = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $audit_action = "REJECT_CASH_ADVANCE";
            $audit_msg = "Rejected request for ID: $id";
            $notif_msg = "Your Cash Advance request has been CANCELLED/REJECTED.";
            $notif_type = "Cash Advance Cancelled";
        }
        
        // 1. Log Audit for Financial Accountability
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], $audit_action, $audit_msg);

        // 2. Trigger Global Notification (Database + Email via global_app_model)
        // target_user_id, target_role (2=Employee), type, message, link
        send_notification($pdo, $ca_info['employee_id'], 2, $notif_type, $notif_msg, 'pages/my_cash_advance.php');

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => ($type === 'approve' ? 'Request Approved' : 'Request Cancelled')]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => 'Processing Error: ' . $e->getMessage()]);
    }
    exit;
}