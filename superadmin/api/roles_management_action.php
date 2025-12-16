<?php
// api/roles_management_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_POST['action'] ?? '';

// --- 1. FETCH MATRIX DATA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'fetch_matrix') {
    try {
        // Fetch Features
        $stmt = $pdo->query("SELECT * FROM tbl_features ORDER BY category DESC, description ASC");
        $features = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Active Permissions
        // We organize this into a simple lookup array: [feature_id => [usertype1, usertype2]]
        $stmtPerm = $pdo->query("SELECT * FROM tbl_role_permissions");
        $rawPerms = $stmtPerm->fetchAll(PDO::FETCH_ASSOC);
        
        $permissions = [];
        foreach ($rawPerms as $p) {
            $permissions[$p['feature_id']][] = $p['usertype'];
        }

        echo json_encode([
            'status' => 'success', 
            'features' => $features, 
            'permissions' => $permissions
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 2. TOGGLE PERMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_permission') {
    
    $usertype = intval($_POST['usertype']);
    $feature_id = intval($_POST['feature_id']);
    $status = intval($_POST['status']); // 1 = Add, 0 = Remove

    try {
        if ($status === 1) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO tbl_role_permissions (usertype, feature_id) VALUES (?, ?)");
            $stmt->execute([$usertype, $feature_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE usertype = ? AND feature_id = ?");
            $stmt->execute([$usertype, $feature_id]);
        }
        echo json_encode(['status' => 'success', 'message' => 'Saved']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>