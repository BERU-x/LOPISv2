<?php
// api/pay_components_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {
    // 1. FETCH LIST (Earnings or Deductions)
    if ($action === 'fetch') {
        $type = $_POST['type'] ?? 'earning';
        $stmt = $pdo->prepare("SELECT * FROM tbl_pay_components WHERE type = ? ORDER BY name ASC");
        $stmt->execute([$type]);
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. GET SINGLE DETAILS
    if ($action === 'get_details') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_pay_components WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'details' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 3. CREATE
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO tbl_pay_components (name, type, is_taxable, is_recurring) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'], 
            $_POST['type'], 
            $_POST['is_taxable'], 
            $_POST['is_recurring']
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Component added successfully!']);
        exit;
    }

    // 4. UPDATE
    if ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE tbl_pay_components SET name=?, is_taxable=?, is_recurring=? WHERE id=?");
        $stmt->execute([
            $_POST['name'], 
            $_POST['is_taxable'], 
            $_POST['is_recurring'], 
            $_POST['id']
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Component updated successfully!']);
        exit;
    }

    // 5. DELETE
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM tbl_pay_components WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'message' => 'Component deleted.']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>