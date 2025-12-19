<?php
/**
 * api/admin/leave_action.php
 * Handles Leave fetching, automated filing, and status updates.
 * Fully integrated with Global Notification and Email systems.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION & GLOBAL MODEL ---
// Access restricted to Superadmin (0) or Admin (1)
if (!isset($_SESSION['usertype']) || !in_array($_SESSION['usertype'], [0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Loads DB connection, send_notification(), and email_handler.php logic
require_once __DIR__ . '/../../app/models/global_app_model.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    // =========================================================================
    // ACTION 1: FETCH DATA (Summary Stats & Table Rows)
    // =========================================================================
    if ($action === 'fetch') {
        // A. Summary Stats
        $stats = ['pending' => 0, 'approved' => 0];
        $stmt = $pdo->query("SELECT 
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as approved
            FROM tbl_leave");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $stats['pending'] = (int)($row['pending'] ?? 0);
            $stats['approved'] = (int)($row['approved'] ?? 0);
        }

        // B. Fetch Detailed Logs joined with Employee Info
        $sql = "SELECT 
                    l.id AS leave_id, l.employee_id, l.leave_type, l.start_date, l.end_date, 
                    l.days_count, l.reason, l.status, l.created_on,
                    e.firstname, e.lastname, e.photo, e.department, e.position
                FROM tbl_leave l
                LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id
                ORDER BY l.created_on DESC";
        $stmt = $pdo->query($sql);
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'stats' => $stats, 'data' => $leaves]);
        exit;
    }

    // =========================================================================
    // ACTION 2: CREATE LEAVE (Filed by Admin - Auto Approved)
    // =========================================================================
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $emp_id     = trim($_POST['employee_id'] ?? '');
        $type       = trim($_POST['leave_type'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date   = trim($_POST['end_date'] ?? '');
        $reason     = trim($_POST['reason'] ?? 'Filed by Admin');
        
        if (empty($emp_id) || empty($type) || empty($start_date) || empty($end_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
            exit;
        }

        $start = new DateTime($start_date);
        $end   = new DateTime($end_date);
        $days  = $start->diff($end)->days + 1;

        $pdo->beginTransaction();

        $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                VALUES (:employee_id, :leave_type, :start_date, :end_date, :days_count, :reason, 1, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':employee_id' => $emp_id,
            ':leave_type'  => $type,
            ':start_date'  => $start_date,
            ':end_date'    => $end_date,
            ':days_count'  => $days,
            ':reason'      => $reason
        ]);
        
        $leave_id = $pdo->lastInsertId();

        // --- ⭐ NOTIFICATION & EMAIL TRIGGER ---
        $notif_msg = "Admin filed and APPROVED a {$type} for you ({$days} days: {$start_date} to {$end_date}).";
        // send_notification(pdo, target_uid, target_role, type, message, link)
        send_notification($pdo, $emp_id, 2, 'Leave Approved', $notif_msg, "pages/my_leaves.php?id={$leave_id}");

        // --- ⭐ AUDIT TRAIL ---
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'CREATE_LEAVE', "Admin manually filed {$type} for Employee: {$emp_id}");

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Leave filed, approved, and employee notified via email.']);
        exit;
    }

    // =========================================================================
    // ACTION 3: UPDATE STATUS (Approve/Reject Requests)
    // =========================================================================
    if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id          = (int)($_POST['id'] ?? 0);
        $status_act  = trim($_POST['status_action'] ?? ''); // 'approve' or 'reject'
        $status_int  = ($status_act === 'approve') ? 1 : 2;

        $pdo->beginTransaction();
        
        $stmt_get = $pdo->prepare("SELECT employee_id, leave_type, start_date FROM tbl_leave WHERE id = ? FOR UPDATE");
        $stmt_get->execute([$id]);
        $leave = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE tbl_leave SET status = ?, updated_on = NOW() WHERE id = ?");
        $stmt->execute([$status_int, $id]);
        
        // --- ⭐ NOTIFICATION & EMAIL TRIGGER ---
        $action_past = ($status_act === 'approve') ? 'APPROVED' : 'REJECTED';
        $notif_msg   = "Your {$leave['leave_type']} request starting {$leave['start_date']} has been {$action_past}.";
        
        send_notification($pdo, $leave['employee_id'], 2, 'Leave Status Update', $notif_msg, "pages/my_leaves.php");

        // --- ⭐ AUDIT TRAIL ---
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_LEAVE_STATUS', "Leave ID {$id} marked as {$action_past}");

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Request has been {$action_past}."]);
        exit;
    }

    // =========================================================================
    // ACTION 4: GET SINGLE DETAILS (For View Modal)
    // =========================================================================
    if ($action === 'get_details' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['leave_id'] ?? 0);
        $sql = "SELECT l.*, e.firstname, e.lastname, e.photo, e.department, e.position 
                FROM tbl_leave l 
                LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id 
                WHERE l.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if($data) {
            echo json_encode(['status' => 'success', 'details' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Details not found.']);
        }
        exit;
    }

} catch (Exception $e) {
    if ($pdo->in_transaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>