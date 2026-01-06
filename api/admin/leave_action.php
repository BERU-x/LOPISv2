<?php
/**
 * api/admin/leave_action.php
 * Handles Leave fetching, automated filing, and status updates.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['usertype']) || !in_array($_SESSION['usertype'], [0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DEPENDENCIES ---
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/email_handler.php'; 
require_once __DIR__ . '/../../app/models/notification_model.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';
$current_year = date('Y');

try {
    // =========================================================================
    // ACTION: FETCH DATA (Table & Stats)
    // =========================================================================
    if ($action === 'fetch') {
        $stats = ['pending' => 0, 'approved' => 0];
        $stmt = $pdo->query("SELECT 
            COUNT(CASE WHEN status = 0 THEN 1 END) as pending,
            COUNT(CASE WHEN status = 1 THEN 1 END) as approved
            FROM tbl_leave");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $stats['pending'] = (int)($row['pending'] ?? 0);
            $stats['approved'] = (int)($row['approved'] ?? 0);
        }

        $sql = "SELECT l.id AS leave_id, l.*, e.firstname, e.lastname, e.photo, e.department, e.position
                FROM tbl_leave l
                LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id
                ORDER BY l.created_on DESC";
        $stmt = $pdo->query($sql);
        echo json_encode(['status' => 'success', 'stats' => $stats, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // =========================================================================
    // ACTION: FETCH EMPLOYEES (RESTORED - For Modal Dropdown)
    // =========================================================================
    if ($action === 'fetch_employees') {
        // We only fetch active employees (status < 5)
        $stmt = $pdo->query("SELECT employee_id, firstname, lastname FROM tbl_employees WHERE employment_status < 5 ORDER BY lastname ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // =========================================================================
    // ACTION: GET EMPLOYEE CREDITS (For Modal Display)
    // =========================================================================
    if ($action === 'get_employee_credits') {
        $emp_id = $_POST['employee_id'] ?? '';
        $stmt = $pdo->prepare("SELECT vacation_leave_total, sick_leave_total, emergency_leave_total 
                                FROM tbl_leave_credits WHERE employee_id = ? AND year = ?");
        $stmt->execute([$emp_id, $current_year]);
        $credits = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'credits' => $credits ?: ['vacation_leave_total' => 0, 'sick_leave_total' => 0, 'emergency_leave_total' => 0]
        ]);
        exit;
    }

    // =========================================================================
    // ACTION: CREATE LEAVE (Admin Filed - Auto Deduct)
    // =========================================================================
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $emp_id = trim($_POST['employee_id'] ?? '');
        $type   = trim($_POST['leave_type'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date   = trim($_POST['end_date'] ?? '');
        $reason     = trim($_POST['reason'] ?? 'Manual filing');

        $start = new DateTime($start_date);
        $end   = new DateTime($end_date);
        $days  = $start->diff($end)->days + 1;

        // Strip " Leave" suffix to match column mapping
        $type_clean = str_replace(' Leave', '', $type);
        $credit_column = match($type_clean) {
            'Vacation'  => 'vacation_leave_total',
            'Sick'      => 'sick_leave_total',
            'Emergency' => 'emergency_leave_total',
            default     => null
        };

        $pdo->beginTransaction();

        if ($credit_column) {
            $stmt_check = $pdo->prepare("SELECT $credit_column FROM tbl_leave_credits WHERE employee_id = ? AND year = ? FOR UPDATE");
            $stmt_check->execute([$emp_id, $current_year]);
            $bal = $stmt_check->fetchColumn();

            if ($bal === false) throw new Exception("No leave credits found for employee $emp_id for year $current_year.");
            if ($bal < $days) throw new Exception("Insufficient credits. Available: $bal, Requested: $days");

            $pdo->prepare("UPDATE tbl_leave_credits SET $credit_column = $credit_column - ? WHERE employee_id = ? AND year = ?")
                ->execute([$days, $emp_id, $current_year]);
        }

        $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
        $pdo->prepare($sql)->execute([$emp_id, $type, $start_date, $end_date, $days, $reason]);

        send_notification($pdo, $emp_id, 2, 'Leave Filed', "Admin filed {$type} for you. Deducted: {$days} days.", "user/request_leave.php");
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'CREATE_LEAVE', "Admin filed {$type} for {$emp_id}");

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Leave filed and $days day(s) deducted from $type_clean credits."]);
        exit;
    }

    // =========================================================================
    // ACTION: UPDATE STATUS (Approve & Deduct / Reject)
    // =========================================================================
    if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)$_POST['id'];
        $status_act = $_POST['status_action'];
        $status_int = ($status_act === 'approve') ? 1 : 2;

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM tbl_leave WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) throw new Exception("Record not found.");
        if ($leave['status'] != 0) throw new Exception("This request has already been processed.");

        if ($status_act === 'approve') {
            $type_clean = str_replace(' Leave', '', $leave['leave_type']);
            $credit_column = match($type_clean) {
                'Vacation'  => 'vacation_leave_total',
                'Sick'      => 'sick_leave_total',
                'Emergency' => 'emergency_leave_total',
                default     => null
            };

            if ($credit_column) {
                $stmt_check = $pdo->prepare("SELECT $credit_column FROM tbl_leave_credits WHERE employee_id = ? AND year = ? FOR UPDATE");
                $stmt_check->execute([$leave['employee_id'], $current_year]);
                $bal = $stmt_check->fetchColumn();

                if ($bal < $leave['days_count']) throw new Exception("Insufficient credits to approve. Available: $bal");

                $pdo->prepare("UPDATE tbl_leave_credits SET $credit_column = $credit_column - ? WHERE employee_id = ? AND year = ?")
                    ->execute([$leave['days_count'], $leave['employee_id'], $current_year]);
            }
        }

        $pdo->prepare("UPDATE tbl_leave SET status = ?, updated_on = NOW() WHERE id = ?")->execute([$status_int, $id]);

        $verb = ($status_act === 'approve') ? 'APPROVED' : 'REJECTED';
        send_notification($pdo, $leave['employee_id'], 2, 'Leave Update', "Your {$leave['leave_type']} was {$verb}.", "pages/my_leaves.php");
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_LEAVE', "Leave ID {$id} marked {$verb}");

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Request has been {$verb}."]);
        exit;
    }

    // =========================================================================
    // ACTION: GET DETAILS
    // =========================================================================
    if ($action === 'get_details') {
        $id = (int)$_POST['leave_id'];
        $stmt = $pdo->prepare("SELECT l.*, e.firstname, e.lastname, e.photo, e.department, e.position 
                                FROM tbl_leave l 
                                LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id 
                                WHERE l.id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'details' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}