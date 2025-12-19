<?php
// api/superadmin/admin_management_action.php
// Handles CRUD operations for Managing System Administrators (usertype = 1)
header('Content-Type: application/json');
session_start();

// --- 1. AUTHENTICATION CHECK ---
// Only Super Admin (0) can access this file
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DATABASE CONNECTION (Up 2 Levels) ---
require_once __DIR__ . '/../../db_connection.php'; 

// --- 3. AUDIT LOGGING HELPER ---
require_once __DIR__ . '/../../helpers/audit_helper.php';

// Get the Action
$action = $_REQUEST['action'] ?? '';

try {

    // =================================================================================
    // ACTION 1: FETCH ALL ADMINS (Server-Side DataTables)
    // =================================================================================
    if ($action === 'fetch') {
        
        // Base Query: Fetch only Admins (usertype = 1)
        // We exclude Deleted status (if you use soft deletes) or just fetch all
        $sql = "SELECT id, employee_id, email, status, created_at FROM tbl_users WHERE usertype = 1";
        $params = [];

        // Search Logic
        if (!empty($_POST['search']['value'])) {
            $searchValue = $_POST['search']['value'];
            $sql .= " AND (employee_id LIKE ? OR email LIKE ?)";
            $params[] = "%$searchValue%";
            $params[] = "%$searchValue%";
        }

        // Count Filtered Records
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

        // Execute Final Query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Total Records (Unfiltered)
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_users WHERE usertype = 1");
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
    // ACTION 2: GET SINGLE DETAILS
    // =================================================================================
    if ($action === 'get_details') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, employee_id, email, status FROM tbl_users WHERE id = ? AND usertype = 1");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'details' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Admin not found.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION 3: CREATE NEW ADMIN
    // =================================================================================
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $employee_id = trim($_POST['employee_id']);
        $email = trim($_POST['email']);
        $status = $_POST['status'] ?? 1;
        $raw_password = !empty($_POST['password']) ? $_POST['password'] : 'Admin@123'; // Default strong password

        // Validation: Check Duplicates
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE employee_id = ? OR email = ?");
        $stmt->execute([$employee_id, $email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee ID or Email already exists!']);
            exit;
        }

        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
        $usertype = 1; // Admin Role

        $sql = "INSERT INTO tbl_users (employee_id, email, password, usertype, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $email, $hashed_password, $usertype, $status])) {
            
            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'CREATE_ADMIN', "Created new admin: $email");
            
            echo json_encode(['status' => 'success', 'message' => 'New Admin added successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add admin.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION 4: UPDATE ADMIN
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

        // Prepare Update
        $params = [$employee_id, $email, $status];
        $sql = "UPDATE tbl_users SET employee_id = ?, email = ?, status = ?";

        // Only update password if provided
        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            
            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_ADMIN', "Updated admin details for ID: $id ($email)");

            echo json_encode(['status' => 'success', 'message' => 'Admin updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update admin.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION 5: DELETE ADMIN
    // =================================================================================
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];

        // Prevent deleting self
        if ($_SESSION['user_id'] == $id) {
            echo json_encode(['status' => 'error', 'message' => 'You cannot delete your own account!']);
            exit;
        }

        // Fetch email for logging before deletion
        $stmt = $pdo->prepare("SELECT email FROM tbl_users WHERE id = ?");
        $stmt->execute([$id]);
        $target_email = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = ?");
        if ($stmt->execute([$id])) {
            
            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'DELETE_ADMIN', "Deleted admin: $target_email (ID: $id)");

            echo json_encode(['status' => 'success', 'message' => 'Admin deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete admin.']);
        }
        exit;
    }

    // Fallback
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>