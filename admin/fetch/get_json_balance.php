<?php
// admin/fetch/get_json_balance.php
require_once '../../db_connection.php'; 
require '../models/leave_model.php';

header('Content-Type: application/json');

if (isset($_POST['employee_id'])) {
    $balances = get_leave_balance($pdo, $_POST['employee_id']);
    echo json_encode($balances);
} else {
    echo json_encode([]);
}
?>