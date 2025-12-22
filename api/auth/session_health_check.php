<?php
// api/auth/session_health_check.php
session_start();
require_once __DIR__ . '/../../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT current_session_id FROM tbl_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$db_session_id = $stmt->fetchColumn();

// Compare IDs
if ($db_session_id && session_id() !== $db_session_id) {
    echo json_encode(['status' => 'conflict']);
} else {
    echo json_encode(['status' => 'active']);
}