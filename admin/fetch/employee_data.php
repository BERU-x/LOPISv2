<?php
// This file is the AJAX endpoint to fetch a single employee record by ID

// 1. Ensure you start the session and include your database connection
session_start();
require '../../db_connection.php'; // Ensure this file defines $pdo

// 2. Set the content type to JSON  
header('Content-Type: application/json');

// 3. Check for valid request method (POST is common for AJAX data retrieval)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$employee_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);

if (!$employee_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Employee ID provided.']);
    exit;
}

// 4. Prepare and Execute Query
$sql = "SELECT * FROM tbl_employees WHERE id = :id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        // Success: Return the employee data
        echo json_encode(['success' => true, 'data' => $employee]);
    } else {
        // Failure: ID not found
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
    }

} catch (PDOException $e) {
    // Database error
    http_response_code(500);
    error_log("DB Error fetching employee: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>