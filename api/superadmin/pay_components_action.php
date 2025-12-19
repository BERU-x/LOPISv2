<?php
// api/superadmin/pay_components_action.php
// Handles CRUD for Earnings & Deductions (Super Admin Only)
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

$action = $_REQUEST['action'] ?? '';

try {
    // =========================================================================
    // ACTION 1: FETCH LIST (Earnings or Deductions)
    // =========================================================================
    if ($action === 'fetch') {
        $type = $_POST['type'] ?? 'earning';
        
        // Fetch components filtered by type
        $stmt = $pdo->prepare("SELECT * FROM tbl_pay_components WHERE type = ? ORDER BY name ASC");
        $stmt->execute([$type]);
        
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // =========================================================================
    // ACTION 2: GET SINGLE DETAILS
    // =========================================================================
    if ($action === 'get_details') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM tbl_pay_components WHERE id = ?");
        $stmt->execute([$id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($details) {
            echo json_encode(['status' => 'success', 'details' => $details]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Component not found.']);
        }
        exit;
    }

    // =========================================================================
    // ACTION 3: CREATE COMPONENT
    // =========================================================================
    if ($action === 'create') {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $is_taxable = $_POST['is_taxable'];
        $is_recurring = $_POST['is_recurring'];

        $stmt = $pdo->prepare("INSERT INTO tbl_pay_components (name, type, is_taxable, is_recurring, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt->execute([$name, $type, $is_taxable, $is_recurring])) {
            
            // Log Audit
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'CREATE_PAY_COMP', "Created $type component: $name");

            echo json_encode(['status' => 'success', 'message' => 'Component added successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add component.']);
        }
        exit;
    }

    // =========================================================================
    // ACTION 4: UPDATE COMPONENT
    // =========================================================================
    if ($action === 'update') {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $is_taxable = $_POST['is_taxable'];
        $is_recurring = $_POST['is_recurring'];

        $stmt = $pdo->prepare("UPDATE tbl_pay_components SET name=?, is_taxable=?, is_recurring=? WHERE id=?");
        if ($stmt->execute([$name, $is_taxable, $is_recurring, $id])) {
            
            // Log Audit
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_PAY_COMP', "Updated pay component ID: $id ($name)");

            echo json_encode(['status' => 'success', 'message' => 'Component updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update component.']);
        }
        exit;
    }

    // =========================================================================
    // ACTION 5: DELETE COMPONENT
    // =========================================================================
    if ($action === 'delete') {
        $id = $_POST['id'];

        // Get Name for Log
        $stmtName = $pdo->prepare("SELECT name FROM tbl_pay_components WHERE id = ?");
        $stmtName->execute([$id]);
        $compName = $stmtName->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM tbl_pay_components WHERE id = ?");
        if ($stmt->execute([$id])) {
            
            // Log Audit
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'DELETE_PAY_COMP', "Deleted pay component: $compName");

            echo json_encode(['status' => 'success', 'message' => 'Component deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete component.']);
        }
        exit;
    }

    // Fallback
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>