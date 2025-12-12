<?php
// api/user_action.php
// Handles CRUD operations for Employee Accounts (usertype = 2)
header('Content-Type: application/json');
session_start();

// Adjust path based on your directory structure
require_once __DIR__ . '/../../db_connection.php'; 

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// CONSTANT FOR THIS FILE:
$TARGET_USERTYPE = 2; // Employees

try {

    // =================================================================================
    // ACTION 1: FETCH (Server-Side DataTables)
    // =================================================================================
    if ($action === 'fetch') {
        
        // 1. Base Query
        $sql = "SELECT * FROM tbl_users WHERE usertype = $TARGET_USERTYPE";
        $params = [];

        // 2. Search Logic
        if (!empty($_POST['search']['value'])) {
            $searchValue = $_POST['search']['value'];
            $sql .= " AND (employee_id LIKE ? OR email LIKE ?)";
            $params[] = "%$searchValue%";
            $params[] = "%$searchValue%";
        }

        // 3. Count Filtered Records
        $stmtCount = $pdo->prepare($sql);
        $stmtCount->execute($params);
        $recordsFiltered = $stmtCount->rowCount();

        // 4. Ordering
        $columns = ['employee_id', 'email', 'status', 'created_at']; 
        if (isset($_POST['order'][0]['column'])) {
            $colIndex = $_POST['order'][0]['column'];
            $colDir = $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
            $orderBy = $columns[$colIndex] ?? 'created_at';
            $sql .= " ORDER BY $orderBy $colDir";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }

        // 5. Pagination
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $sql .= " LIMIT $start, $length";

        // 6. Execute Final Query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 7. Get Total Records
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
        $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE id = ? AND usertype = $TARGET_USERTYPE");
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
    // ACTION 3: CREATE ACCOUNT
    // =================================================================================
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $employee_id = trim($_POST['employee_id']);
        $email = trim($_POST['email']);
        $status = $_POST['status'] ?? 1;
        $raw_password = !empty($_POST['password']) ? $_POST['password'] : 'employee123'; 

        // Validate Uniqueness (Global check across all users to ensure no duplicate EmpID anywhere)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE employee_id = ? OR email = ?");
        $stmt->execute([$employee_id, $email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee ID or Email already exists!']);
            exit;
        }

        // Validate existence in tbl_employees (Optional but recommended)
        // $stmtEmp = $pdo->prepare("SELECT COUNT(*) FROM tbl_employees WHERE employee_id = ?");
        // $stmtEmp->execute([$employee_id]);
        // if ($stmtEmp->fetchColumn() == 0) {
        //     echo json_encode(['status' => 'error', 'message' => 'Employee Profile not found in HR Database. Create profile first.']);
        //     exit;
        // }

        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO tbl_users (employee_id, email, password, usertype, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $email, $hashed_password, $TARGET_USERTYPE, $status])) {
            echo json_encode(['status' => 'success', 'message' => 'Employee Account created successfully!']);
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

        $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = ? AND usertype = $TARGET_USERTYPE");
        if ($stmt->execute([$id])) {
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