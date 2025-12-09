<?php
// api/leave_action.php
header('Content-Type: application/json');

// 1. INCLUDES
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../models/global_model.php'; // REQUIRED for notifications

$action = $_GET['action'] ?? '';

// --- 1. FETCH ALL DATA (Table + Stats) ---
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
            $stats['pending'] = $row['pending'] ?? 0;
            $stats['approved'] = $row['approved'] ?? 0;
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

        echo json_encode(['stats' => $stats, 'data' => $leaves]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// --- 2. FETCH EMPLOYEE DROPDOWN ---
if ($action === 'fetch_employees') {
    try {
        // Changed WHERE clause from != 5 to < 5
        $stmt = $pdo->query("SELECT employee_id, firstname, lastname FROM tbl_employees WHERE employment_status < 5 ORDER BY lastname ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// --- 3. CREATE NEW LEAVE ---
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $emp_id = $_POST['employee_id'];
        $type = $_POST['leave_type'];
        
        // A. Fetch Employee Name FIRST (for notifications)
        $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
        $stmt_name->execute([$emp_id]);
        $emp_data = $stmt_name->fetch(PDO::FETCH_ASSOC);
        
        // Fallback to ID if name not found, otherwise construct Full Name
        $emp_name = $emp_data ? ($emp_data['firstname'] . ' ' . $emp_data['lastname']) : $emp_id;

        // B. Calculate Dates
        $start = new DateTime($_POST['start_date']);
        $end = new DateTime($_POST['end_date']);
        $diff = $start->diff($end);
        $days = $diff->days + 1;

        // C. Insert Record
        $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                VALUES (:employee_id, :leave_type, :start_date, :end_date, :days_count, :reason, 0, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':employee_id' => $emp_id,
            ':leave_type'  => $type,
            ':start_date'  => $_POST['start_date'],
            ':end_date'    => $_POST['end_date'],
            ':days_count'  => $days,
            ':reason'      => $_POST['reason']
        ]);

        if($result) {
            // --- NOTIFICATION 1: ALERT ADMINS ---
            // Updated to use Name instead of ID
            $admin_msg = "New {$type} request filed for {$emp_name}.";
            send_notification($pdo, null, 'Admin', 'leave', $admin_msg, 'leave_management.php', null);

            // --- NOTIFICATION 2: ALERT THE EMPLOYEE ---
            // Notify the employee that an admin filed this for them
            $emp_msg = "An administrator has filed a {$type} request on your behalf.";
            send_notification($pdo, $emp_id, 'Employee', 'leave', $emp_msg, 'my_leaves.php', null);

            echo json_encode(['status' => 'success', 'message' => 'Leave filed successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save record.']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 4. UPDATE STATUS (Approve/Reject) ---
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $status_str = $_POST['status_action']; // 'approve' or 'reject'
    $status_int = ($status_str === 'approve') ? 1 : 2;

    try {
        // 1. Fetch Employee ID First (Need this to send notification)
        $stmt_get = $pdo->prepare("SELECT employee_id, leave_type, start_date FROM tbl_leave WHERE id = :id");
        $stmt_get->execute([':id' => $id]);
        $leave_data = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if (!$leave_data) {
            echo json_encode(['status' => 'error', 'message' => 'Leave record not found.']);
            exit;
        }

        // 2. Update Status
        $stmt = $pdo->prepare("UPDATE tbl_leave SET status = :status, updated_on = NOW() WHERE id = :id");
        $stmt->execute([':status' => $status_int, ':id' => $id]);
        
        // --- NOTIFICATION: ALERT EMPLOYEE ---
        $action_past = ($status_str === 'approve') ? 'APPROVED' : 'REJECTED';
        $type = $leave_data['leave_type'];
        $date = date('M d', strtotime($leave_data['start_date']));
        
        $notif_msg = "Your {$type} request for {$date} has been {$action_past}.";
        
        // Target: Employee ID from the fetched record
        send_notification($pdo, $leave_data['employee_id'], 'Employee', 'leave', $notif_msg, 'my_leaves.php', null);

        echo json_encode(['status' => 'success', 'message' => 'Leave request updated!']);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 5. GET SINGLE DETAILS (For Modal) ---
if ($action === 'get_details' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['leave_id'];
    try {
        $sql = "SELECT l.*, e.firstname, e.lastname, e.photo, e.department 
                FROM tbl_leave l 
                LEFT JOIN tbl_employees e ON l.employee_id = e.employee_id 
                WHERE l.id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if($data) {
            echo json_encode(['success' => true, 'details' => $data]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>