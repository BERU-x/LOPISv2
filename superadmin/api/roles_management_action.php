<?php
// api/roles_management_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_permission') {
    
    $usertype = intval($_POST['usertype']);
    $feature_id = intval($_POST['feature_id']);
    $status = intval($_POST['status']); // 1 = Checked (Add), 0 = Unchecked (Remove)

    try {
        if ($status === 1) {
            // ADD PERMISSION (Ignore if already exists)
            $stmt = $pdo->prepare("INSERT IGNORE INTO tbl_role_permissions (usertype, feature_id) VALUES (?, ?)");
            $stmt->execute([$usertype, $feature_id]);
        } else {
            // REMOVE PERMISSION
            $stmt = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE usertype = ? AND feature_id = ?");
            $stmt->execute([$usertype, $feature_id]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Permission updated']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>