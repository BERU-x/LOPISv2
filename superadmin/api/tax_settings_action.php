<?php
// api/tax_settings_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {
    // 1. FETCH ALL
    if ($action === 'fetch') {
        $stmt = $pdo->query("SELECT * FROM tbl_tax_table ORDER BY min_income ASC");
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 2. GET DETAILS
    if ($action === 'get_details') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_tax_table WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'details' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 3. CREATE
    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO tbl_tax_table (tier_name, min_income, max_income, base_tax, excess_rate) VALUES (?, ?, ?, ?, ?)");
        $max_income = !empty($_POST['max_income']) ? $_POST['max_income'] : NULL;
        
        $stmt->execute([
            $_POST['tier_name'], 
            $_POST['min_income'], 
            $max_income, 
            $_POST['base_tax'],
            $_POST['excess_rate']
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Tax slab added successfully!']);
        exit;
    }

    // 4. UPDATE
    if ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE tbl_tax_table SET tier_name=?, min_income=?, max_income=?, base_tax=?, excess_rate=? WHERE id=?");
        $max_income = !empty($_POST['max_income']) ? $_POST['max_income'] : NULL;

        $stmt->execute([
            $_POST['tier_name'], 
            $_POST['min_income'], 
            $max_income, 
            $_POST['base_tax'],
            $_POST['excess_rate'],
            $_POST['id']
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Tax slab updated successfully!']);
        exit;
    }

    // 5. DELETE
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM tbl_tax_table WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'message' => 'Tax slab removed.']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>