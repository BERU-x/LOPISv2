<?php
/**
 * api/admin/overtime_action.php
 * Handles Overtime Server-Side Processing (SSP), details retrieval, and Approval/Rejection logic.
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
require_once __DIR__ . '/../../app/models/global_app_model.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

if (!isset($pdo)) {
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? 'fetch'; 
$draw = (int)($_GET['draw'] ?? 1);

// =================================================================================
// ACTION 1: FETCH OVERTIME REQUESTS (DataTables SSP)
// =================================================================================
if ($action === 'fetch') {

    $columns = [
        0 => 'e.lastname', 
        1 => 'ot.ot_date', 
        2 => 'ot.hours_requested',
        3 => 'ot.status'
    ];

    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    $sql_base = " FROM tbl_overtime ot 
                  LEFT JOIN tbl_employees e ON ot.employee_id = e.employee_id ";
    
    $where_params = [];
    $where_bindings = [];

    if (!empty($search)) {
        $term = '%' . $search . '%';
        $where_params[] = "(ot.employee_id LIKE :search OR e.firstname LIKE :search OR e.lastname LIKE :search OR ot.status LIKE :search)";
        $where_bindings[':search'] = $term;
    }

    if (!empty($start_date) && !empty($end_date)) {
        $where_params[] = "ot.ot_date BETWEEN :start_date AND :end_date";
        $where_bindings[':start_date'] = $start_date;
        $where_bindings[':end_date']   = $end_date;
    }

    $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";

    // Ordering
    $order_sql = " ORDER BY ot.status = 'Pending' DESC, ot.ot_date DESC"; 
    if (isset($_GET['order'])) {
        $col_idx = (int)$_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
        if (isset($columns[$col_idx])) {
            $order_sql = " ORDER BY " . ($columns[$col_idx] === 'e.lastname' ? "e.lastname $dir, e.firstname $dir" : $columns[$col_idx] . " $dir");
        }
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(ot.id) FROM tbl_overtime ot");
        $recordsTotal = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(ot.id) $sql_base $where_sql");
        $stmt->execute($where_bindings);
        $recordsFiltered = (int)$stmt->fetchColumn();

        $sql_data = "SELECT ot.*, e.firstname, e.lastname, e.photo, e.department 
                     $sql_base $where_sql $order_sql LIMIT :start_limit, :length_limit";

        $stmt = $pdo->prepare($sql_data);
        foreach ($where_bindings as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->bindValue(':start_limit', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length_limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    } catch (PDOException $e) {
        echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 2: UPDATE STATUS (Approve/Reject)
// =================================================================================
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status_action = trim($_POST['status_action'] ?? ''); 
    $approved_hrs = (float)($_POST['approved_hours'] ?? 0);

    if ($id <= 0 || !in_array($status_action, ['approve', 'reject'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt_info = $pdo->prepare("SELECT employee_id, ot_date, hours_requested FROM tbl_overtime WHERE id = ? FOR UPDATE");
        $stmt_info->execute([$id]);
        $ot_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ot_info) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
            exit;
        }

        if ($status_action === 'approve') {
            $sql = "UPDATE tbl_overtime SET status = 'Approved', hours_approved = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$approved_hrs, $id]);
            $notif_type = 'APPROVED';
        } else {
            $sql = "UPDATE tbl_overtime SET status = 'Rejected', hours_approved = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $notif_type = 'REJECTED';
        }

        // Audit Trail
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'OVERTIME_ACTION', "OT ID $id marked as $notif_type. Approved hrs: $approved_hrs");

        // Notification Integration
        $date_formatted = date('M d, Y', strtotime($ot_info['ot_date']));
        $notif_msg = "Your Overtime request for {$date_formatted} has been {$notif_type} (" . ($status_action === 'approve' ? $approved_hrs : 0) . " hrs).";
        
        // Use global notification model (Role 2 = Employee)
        send_notification($pdo, $ot_info['employee_id'], 2, 'Overtime Update', $notif_msg, 'pages/my_overtime.php');

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Overtime request processed successfully."]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}