<?php
// api/admin_action.php
// Handles CRUD operations for System Administrators (usertype = 1)
header('Content-Type: application/json');
session_start();

// Adjust path to your actual file structure
require_once __DIR__ . '/../../db_connection.php'; 

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$response = ['status' => 'error', 'message' => 'Invalid request'];

// Check Request Method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Retrieve POST data
        $action = $_POST['action'] ?? '';
        $employee_id = trim($_POST['employee_id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = $_POST['status'] ?? 1;

        // =================================================================================
        // ACTION: FETCH ALL ADMINS (For DataTables)
        // =================================================================================
        if ($action === 'fetch') {

            // Unlike the Employee API, the Admin API doesn't need complex server-side processing 
            // (ordering, searching, limiting) because the table is small and the DataTables JS
            // handles client-side filtering well for this small dataset.
            // If the admin list grows huge, you would need to implement the full DataTables logic here.
            
            $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE usertype = 1 ORDER BY created_at DESC");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Note: Returning 'data' key for DataTables compatibility
            echo json_encode(['data' => $result]);
            exit;

        // =================================================================================
        // ACTION: ADD NEW ADMIN (No complex photo/compensation transaction needed)
        // =================================================================================
        } elseif ($action === 'add') {
            
            // Validate Uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE employee_id = ? OR email = ?");
            $stmt->execute([$employee_id, $email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Employee ID or Email already exists!");
            }

            // Default Password & User Type
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $usertype = 1; // Admin

            $sql = "INSERT INTO tbl_users (employee_id, email, password, usertype, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$employee_id, $email, $password, $usertype, $status]);

            $response = ['status' => 'success', 'message' => 'New Admin added successfully!'];

        // =================================================================================
        // ACTION: EDIT ADMIN
        // =================================================================================
        } elseif ($action === 'edit') {
            $user_id = $_POST['user_id'];

            // Prepare dynamic update query
            $sql = "UPDATE tbl_users SET employee_id = ?, email = ?, status = ?";
            $params = [$employee_id, $email, $status];

            // Only update password if user typed one in
            if (!empty($_POST['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $response = ['status' => 'success', 'message' => 'Admin details updated successfully!'];

        // =================================================================================
        // ACTION: DELETE ADMIN
        // =================================================================================
        } elseif ($action === 'delete') {
            $user_id = $_POST['user_id'];

            // Note: In a real system, you might want to soft-delete (status=2) instead of DELETE
            $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = ?");
            $stmt->execute([$user_id]);

            $response = ['status' => 'success', 'message' => 'Admin account removed.'];
        }

    } catch (Exception $e) {
        // Handle common errors like Duplicate Entry gracefully
        $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) 
             ? "Error: Employee ID or Email already exists for another user." 
             : "Database error: " . $e->getMessage();
             
        $response = ['status' => 'error', 'message' => $msg];
    }
}

echo json_encode($response);
exit;
?>