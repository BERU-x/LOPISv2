<?php
// fetch/holidays_data.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Adjust path as necessary
require_once __DIR__ . '/../../db_connection.php'; 

$response = ['status' => 'error', 'message' => 'Query failed.', 'data' => []];

if (!isset($pdo)) {
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

try {
    // Select holidays that are today or in the future, ordered by date
    // Limit to 5 upcoming holidays for the dashboard card
    $stmt = $pdo->prepare("
        SELECT 
            holiday_date, 
            holiday_name, 
            holiday_type 
        FROM tbl_holidays
        WHERE holiday_date >= CURDATE()
        ORDER BY holiday_date ASC
        LIMIT 5
    ");
    
    $stmt->execute();
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['status'] = 'success';
    $response['message'] = 'Holidays loaded.';
    $response['data'] = $holidays;
    
} catch (PDOException $e) {
    error_log("Holidays Fetch Error: " . $e->getMessage());
    $response['message'] = 'Database error fetching holidays.';
}

echo json_encode($response);
?>