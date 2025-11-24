<?php
// admin/fetch/fetch_single_employee.php

session_start();
require '../../db_connection.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// [CHANGE 1]: Do NOT validate as INT. Sanitize as String instead.
// We expect "EMP001", "A-123", etc.
$emp_id_param = htmlspecialchars(strip_tags($_POST['id']));

if (!$emp_id_param) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Employee ID provided.']);
    exit;
}

// [CHANGE 2]: Update WHERE clause to use 'employee_id' instead of 'id'
$sql = "SELECT 
            e.*, 
            c.daily_rate, 
            c.monthly_rate, 
            c.food_allowance, 
            c.transpo_allowance
        FROM tbl_employees e
        LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
        WHERE e.employee_id = :eid"; // <--- Changed from e.id to e.employee_id

try {
    $stmt = $pdo->prepare($sql);
    // [CHANGE 3]: Bind as STRING, not INT
    $stmt->bindParam(':eid', $emp_id_param, PDO::PARAM_STR);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        // Handle null values
        $employee['daily_rate'] = $employee['daily_rate'] ?? 0;
        $employee['food_allowance'] = $employee['food_allowance'] ?? 0;
        $employee['transpo_allowance'] = $employee['transpo_allowance'] ?? 0;

        echo json_encode(['success' => true, 'data' => $employee]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("DB Error fetching employee: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>