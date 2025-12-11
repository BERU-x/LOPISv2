<?php
// api/leave_action.php
header('Content-Type: application/json');

// 1. INCLUDES
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../models/global_model.php'; // REQUIRED for notifications

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION 1: FETCH ALL DATA (Table + Stats)
// NOTE: This uses client-side pagination, NOT true DataTables SSP.
// =================================================================================
if ($action === 'fetch') {
    try {
        // A. Get Stats
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

        // B. Get Table Data
        $sql = "SELECT 
                l.id AS leave_id, 
                l.employee_id, l.leave_type, l.start_date, l.end_date, 
                l.days_count, l.reason, l.status, l.created_on,
                e.firstname, e.lastname, e.photo, e.department, e.position
            FROM tbl_leave l
            LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id
            ORDER BY l.created_on DESC";
        $stmt = $pdo->query($sql);
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Standardized response wrapping custom data
        echo json_encode(['status' => 'success', 'stats' => $stats, 'data' => $leaves]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch leave data.', 'details' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 2: FETCH EMPLOYEE DROPDOWN
// =================================================================================
if ($action === 'fetch_employees') {
    try {
        // Fetch only active employees
        $stmt = $pdo->query("SELECT employee_id, firstname, lastname FROM tbl_employees WHERE employment_status < 5 ORDER BY lastname ASC");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($employees); // Returns raw array for simple JS population
    } catch (Exception $e) {
        echo json_encode([]); // Return empty array on failure
    }
    exit;
}

// =================================================================================
// ACTION 3: CREATE NEW LEAVE
// =================================================================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = trim($_POST['employee_id'] ?? '');
    $type = trim($_POST['leave_type'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    
    // Simple Validation
    if (empty($emp_id) || empty($type) || empty($start_date) || empty($end_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }
    
    try {
        // A. Calculate Days Count
        // NOTE: This simple calculation does not account for weekends or holidays.
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $diff = $start->diff($end);
        $days = $diff->days + 1;

        $pdo->beginTransaction();

        // B. Insert Record
        $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                VALUES (:employee_id, :leave_type, :start_date, :end_date, :days_count, :reason, 0, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':employee_id' => $emp_id,
            ':leave_type'  => $type,
            ':start_date'  => $start_date,
            ':end_date'    => $end_date,
            ':days_count'  => $days,
            ':reason'      => $reason
        ]);
        
        $last_id = $pdo->lastInsertId();

        // C. Fetch Employee Name (for notifications)
        $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
        $stmt_name->execute([$emp_id]);
        $emp_data = $stmt_name->fetch(PDO::FETCH_ASSOC);
        $emp_name = $emp_data ? ($emp_data['firstname'] . ' ' . $emp_data['lastname']) : $emp_id;

        if($result) {
            // --- NOTIFICATION 1: ALERT ADMINS ---
            $admin_msg = "New {$type} request filed for {$emp_name} ({$days} days).";
            // Pass the leave ID as reference ID
            send_notification($pdo, null, 'Admin', 'leave', $admin_msg, 'leave_management.php', $last_id);

            // --- NOTIFICATION 2: ALERT THE EMPLOYEE ---
            $emp_msg = "A {$type} request ({$days} days) has been successfully filed.";
            send_notification($pdo, $emp_id, 'Employee', 'leave', $emp_msg, 'my_leaves.php', $last_id);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Leave filed successfully!']);
        } else {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Failed to save record.']);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 4: UPDATE STATUS (Approve/Reject)
// =================================================================================
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status_str = trim($_POST['status_action'] ?? ''); // 'approve' or 'reject'
    $status_int = ($status_str === 'approve') ? 1 : 2;

    if ($id <= 0 || !in_array($status_str, ['approve', 'reject'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID or status action.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Fetch data before update (Needed for notification)
        $stmt_get = $pdo->prepare("SELECT employee_id, leave_type, start_date FROM tbl_leave WHERE id = :id FOR UPDATE");
        $stmt_get->execute([':id' => $id]);
        $leave_data = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if (!$leave_data) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Leave record not found.']);
            exit;
        }

        // 2. Update Status
        $stmt = $pdo->prepare("UPDATE tbl_leave SET status = :status, updated_on = NOW() WHERE id = :id");
        $stmt->execute([':status' => $status_int, ':id' => $id]);
        
        $pdo->commit();
        
        // --- NOTIFICATION: ALERT EMPLOYEE ---
        $action_past = ($status_str === 'approve') ? 'APPROVED' : 'REJECTED';
        $type = $leave_data['leave_type'];
        $date = date('M d, Y', strtotime($leave_data['start_date']));
        
        $notif_msg = "Your {$type} request starting {$date} has been {$action_past}.";
        
        send_notification($pdo, $leave_data['employee_id'], 'Employee', 'leave', $notif_msg, 'my_leaves.php', $id);

        echo json_encode(['status' => 'success', 'message' => 'Leave request updated!']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 5: GET SINGLE DETAILS (For Modal)
// =================================================================================
if ($action === 'get_details' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['leave_id'] ?? 0);
    try {
        $sql = "SELECT l.*, e.firstname, e.lastname, e.photo, e.department 
                FROM tbl_leave l 
                LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id 
                WHERE l.id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if($data) {
            // Standardized response keys
            echo json_encode(['status' => 'success', 'details' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Details not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error retrieving details.']);
    }
    exit;
}