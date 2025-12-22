<?php
// api/superadmin/employee_account_action.php
// Handles CRUD operations for Employee Accounts (usertype = 2)
header('Content-Type: application/json');
session_start();

// --- 1. AUTHENTICATION (Super Admin Only) ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DEPENDENCIES ---
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

// ⭐ IMPORT EMAIL HANDLER
require_once __DIR__ . '/../../helpers/email_handler.php'; 

// CONSTANT: Target Role = 2 (Employee)
$TARGET_USERTYPE = 2; 
$action = $_REQUEST['action'] ?? '';

try {

    // =================================================================================
    // ACTION 1: FETCH (Server-Side DataTables)
    // =================================================================================
    if ($action === 'fetch') {
        
        // Base Query
        $sql = "SELECT id, employee_id, email, status, created_at FROM tbl_users WHERE usertype = $TARGET_USERTYPE";
        $params = [];

        // Search Logic
        if (!empty($_POST['search']['value'])) {
            $searchValue = $_POST['search']['value'];
            $sql .= " AND (employee_id LIKE ? OR email LIKE ?)";
            $params[] = "%$searchValue%";
            $params[] = "%$searchValue%";
        }

        // Count Filtered
        $stmtCount = $pdo->prepare($sql);
        $stmtCount->execute($params);
        $recordsFiltered = $stmtCount->rowCount();

        // Ordering
        $columns = ['employee_id', 'email', 'status', 'created_at']; 
        if (isset($_POST['order'][0]['column'])) {
            $colIndex = $_POST['order'][0]['column'];
            $colDir = $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
            $orderBy = $columns[$colIndex] ?? 'created_at';
            $sql .= " ORDER BY $orderBy $colDir";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }

        // Pagination
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $sql .= " LIMIT $start, $length";

        // Execute Final
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total Records
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_users WHERE usertype = $TARGET_USERTYPE");
        $recordsTotal = $totalStmt->fetchColumn();

        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data
        ]);
        exit;
    }

    // =================================================================================
    // ACTION 2: GET DETAILS
    // =================================================================================
    if ($action === 'get_details') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, employee_id, email, status FROM tbl_users WHERE id = ? AND usertype = $TARGET_USERTYPE");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'details' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION 6: GET AVAILABLE EMPLOYEES (FOR DROPDOWN)
    // =================================================================================
    if ($action === 'get_available_employees') {
        // Fetch employees who are NOT already users in tbl_users
        // This subquery ensures we don't double-create accounts for the same person
        $sql = "SELECT employee_id, CONCAT(firstname, ' ', lastname) AS name 
                FROM tbl_employees 
                WHERE employee_id NOT IN (SELECT employee_id FROM tbl_users)
                ORDER BY lastname ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'employees' => $employees ?: []]);
        exit;
    }

    // =================================================================================
    // ACTION 3: CREATE ACCOUNT
    // =================================================================================
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $employee_id = trim($_POST['employee_id']);
        $email = trim($_POST['email']);
        $status = $_POST['status'] ?? 1;
        $raw_password = !empty($_POST['password']) ? $_POST['password'] : 'losi123'; 

        // Validate Uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE employee_id = ? OR email = ?");
        $stmt->execute([$employee_id, $email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee ID or Email already exists!']);
            exit;
        }

        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO tbl_users (employee_id, email, password, usertype, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $email, $hashed_password, $TARGET_USERTYPE, $status])) {
            
            $new_user_id = $pdo->lastInsertId();

            // --- 1. QUEUE WELCOME EMAIL ---
            $subject = "Welcome to LOPISv2 - Employee Access";
            $body = "
                <h3>Welcome!</h3>
                <p>Your employee account has been created.</p>
                <ul>
                    <li><strong>Email:</strong> $email</li>
                    <li><strong>Temporary Password:</strong> $raw_password</li>
                </ul>
                <p>Please log in and change your password immediately.</p>
                <br>
                <p>Regards,<br>HR Department</p>
            ";

            // Add to Global Queue (Type: WELCOME_EMPLOYEE)
            queueEmail($pdo, $new_user_id, $email, $subject, $body, 'WELCOME_EMPLOYEE');
            
            // --- 2. AUDIT LOG ---
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'CREATE_EMP_ACC', "Created employee account: $email ($employee_id)");

            echo json_encode(['status' => 'success', 'message' => 'Employee Account created! Welcome email queued.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create account.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION 4: UPDATE ACCOUNT
    // =================================================================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $id = $_POST['id'];
        $employee_id = trim($_POST['employee_id']);
        $email = trim($_POST['email']);
        $status = $_POST['status'];

        // Check duplicates (excluding self)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE (employee_id = ? OR email = ?) AND id != ?");
        $stmt->execute([$employee_id, $email, $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee ID or Email already exists for another user.']);
            exit;
        }

        $params = [$employee_id, $email, $status];
        $sql = "UPDATE tbl_users SET employee_id = ?, email = ?, status = ?";

        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ? AND usertype = $TARGET_USERTYPE";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            
            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_EMP_ACC', "Updated employee account: $email");

            echo json_encode(['status' => 'success', 'message' => 'Account updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update account.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION 5: DELETE ACCOUNT
    // =================================================================================
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];

        // Get email for logging
        $stmt = $pdo->prepare("SELECT email FROM tbl_users WHERE id = ?");
        $stmt->execute([$id]);
        $target_email = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = ? AND usertype = $TARGET_USERTYPE");
        if ($stmt->execute([$id])) {
            
            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'DELETE_EMP_ACC', "Deleted employee account: $target_email (ID: $id)");

            echo json_encode(['status' => 'success', 'message' => 'Account removed successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete account.']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>