<?php
// api/superadmin/tax_settings_action.php
// Handles CRUD for Tax Brackets/Slabs (Super Admin Only)
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

$action = $_REQUEST['action'] ?? '';

try {
    // =========================================================================
    // ACTION 1: FETCH ALL TAX SLABS
    // =========================================================================
    if ($action === 'fetch') {
        $stmt = $pdo->query("SELECT * FROM tbl_tax_table ORDER BY min_income ASC");
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // =========================================================================
    // ACTION 2: GET DETAILS
    // =========================================================================
    if ($action === 'get_details') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM tbl_tax_table WHERE id = ?");
        $stmt->execute([$id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($details) {
            echo json_encode(['status' => 'success', 'details' => $details]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Tax slab not found.']);
        }
        exit;
    }

    // =========================================================================
    // ACTION 3: CREATE TAX SLAB
    // =========================================================================
    if ($action === 'create') {
        $tier_name   = trim($_POST['tier_name']);
        $min_income  = $_POST['min_income'];
        $max_income  = !empty($_POST['max_income']) ? $_POST['max_income'] : NULL;
        $base_tax    = $_POST['base_tax'];
        $excess_rate = $_POST['excess_rate'];

        $stmt = $pdo->prepare("INSERT INTO tbl_tax_table (tier_name, min_income, max_income, base_tax, excess_rate) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$tier_name, $min_income, $max_income, $base_tax, $excess_rate])) {
            
            // Log Audit
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'CREATE_TAX_SLAB', "Created tax tier: $tier_name (Min: $min_income)");

            echo json_encode(['status' => 'success', 'message' => 'Tax slab added successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add tax slab.']);
        }
        exit;
    }

    // =========================================================================
    // ACTION 4: UPDATE TAX SLAB
    // =========================================================================
    if ($action === 'update') {
        $id          = $_POST['id'];
        $tier_name   = trim($_POST['tier_name']);
        $min_income  = $_POST['min_income'];
        $max_income  = !empty($_POST['max_income']) ? $_POST['max_income'] : NULL;
        $base_tax    = $_POST['base_tax'];
        $excess_rate = $_POST['excess_rate'];

        $stmt = $pdo->prepare("UPDATE tbl_tax_table SET tier_name=?, min_income=?, max_income=?, base_tax=?, excess_rate=? WHERE id=?");
        
        if ($stmt->execute([$tier_name, $min_income, $max_income, $base_tax, $excess_rate, $id])) {
            
            // Log Audit
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_TAX_SLAB', "Updated tax tier ID: $id ($tier_name)");

            echo json_encode(['status' => 'success', 'message' => 'Tax slab updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update tax slab.']);
        }
        exit;
    }

    // =========================================================================
    // ACTION 5: DELETE TAX SLAB
    // =========================================================================
    if ($action === 'delete') {
        $id = $_POST['id'];

        // Get Name for Log
        $stmtName = $pdo->prepare("SELECT tier_name FROM tbl_tax_table WHERE id = ?");
        $stmtName->execute([$id]);
        $tierName = $stmtName->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM tbl_tax_table WHERE id = ?");
        if ($stmt->execute([$id])) {
            
            // Log Audit
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'DELETE_TAX_SLAB', "Deleted tax tier: $tierName");

            echo json_encode(['status' => 'success', 'message' => 'Tax slab removed successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete tax slab.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>