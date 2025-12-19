<?php
// api/superadmin/roles_management_action.php
// Handles Permission Matrix Logic (Super Admin Only)
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
require_once __DIR__ . '/../../helpers/audit_helper.php'; // Audit Logger

$action = $_POST['action'] ?? '';

// =============================================================================
// ACTION 1: FETCH MATRIX DATA
// =============================================================================
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

// =============================================================================
// ACTION 2: TOGGLE PERMISSION
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_permission') {
    
    $usertype = intval($_POST['usertype']);
    $feature_id = intval($_POST['feature_id']);
    $status = intval($_POST['status']); // 1 = Add, 0 = Remove

    try {
        // Fetch Feature Name for Audit Log (Optional but better for readability)
        $stmtF = $pdo->prepare("SELECT feature_code FROM tbl_features WHERE id = ?");
        $stmtF->execute([$feature_id]);
        $featName = $stmtF->fetchColumn() ?: "Feature #$feature_id";

        // Map Role ID to Name for Log
        $roleName = ($usertype == 1) ? 'Admin' : (($usertype == 2) ? 'Employee' : 'Unknown');

        if ($status === 1) {
            // GRANT Permission
            $stmt = $pdo->prepare("INSERT IGNORE INTO tbl_role_permissions (usertype, feature_id) VALUES (?, ?)");
            $stmt->execute([$usertype, $feature_id]);

            // Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'GRANT_PERM', "Granted '$featName' access to $roleName role.");

        } else {
            // REVOKE Permission
            $stmt = $pdo->prepare("DELETE FROM tbl_role_permissions WHERE usertype = ? AND feature_id = ?");
            $stmt->execute([$usertype, $feature_id]);

            // Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'REVOKE_PERM', "Revoked '$featName' access from $roleName role.");
        }

        echo json_encode(['status' => 'success', 'message' => 'Permission updated.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>