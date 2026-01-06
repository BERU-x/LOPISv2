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
require_once __DIR__ . '/../../helpers/email_handler.php'; 
require_once __DIR__ . '/../../app/models/notification_model.php'; 
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
// ACTION 2: UPDATE STATUS (Handled via Notification Model)
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

        // 1. Fetch Request Details
        $stmt_info = $pdo->prepare("SELECT employee_id, ot_date FROM tbl_overtime WHERE id = ? FOR UPDATE");
        $stmt_info->execute([$id]);
        $ot_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ot_info) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
            exit;
        }

        // 2. Update Database Status
        if ($status_action === 'approve') {
            $sql = "UPDATE tbl_overtime SET status = 'Approved', hours_approved = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$approved_hrs, $id]);
            $status_label = 'APPROVED';
        } else {
            $sql = "UPDATE tbl_overtime SET status = 'Rejected', hours_approved = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $status_label = 'REJECTED';
        }

        // 3. Trigger Notification (Handled by Model)
        $date_formatted = date('M d, Y', strtotime($ot_info['ot_date']));
        $notif_msg = "Your Overtime request for {$date_formatted} has been {$status_label}. " . 
                     ($status_action === 'approve' ? "Approved: {$approved_hrs} hrs." : "");

        /**
         * send_notification handles:
         * 1. Database insertion into tbl_notifications
         * 2. Automatic Email Queueing (if the model's $send_email is true)
         */
        send_notification(
            $pdo, 
            $ot_info['employee_id'], 
            2, 
            'Overtime Update', 
            $notif_msg, 
            'user/file_overtime.php'
        );

        // 4. Audit Trail
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'OVERTIME_ACTION', "OT ID $id marked as $status_label.");

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Request processed successfully."]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 3: GET DETAILS (Updated with Biometric Link)
// =================================================================================
if ($action === 'get_details' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    try {
        // We join tbl_attendance to get the actual biometric 'overtime_hr'
        $sql = "SELECT 
                    ot.*, 
                    e.firstname, e.lastname, e.photo, e.department, e.position,
                    COALESCE(att.overtime_hr, 0) AS raw_biometric_ot
                FROM tbl_overtime ot
                LEFT JOIN tbl_employees e ON ot.employee_id = e.employee_id
                LEFT JOIN tbl_attendance att ON ot.employee_id = att.employee_id 
                    AND ot.ot_date = att.date
                WHERE ot.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($details) {
            echo json_encode(['status' => 'success', 'details' => $details]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Overtime record not found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}