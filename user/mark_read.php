<?php
// admin/functions/mark_read.php
require_once 'models/global_model.php'; 
session_start();

// 1. Get Notification ID and Destination Link
$id = $_GET['id'] ?? null;
$link = $_GET['link'] ?? 'dashboard.php'; 

// 2. Security Check (Basic: Ensure user is logged in)
if (!isset($_SESSION['logged_in']) || !is_numeric($id)) {
    header("Location: ../../index.php"); // Redirect to login if unauthorized or ID is missing
    exit;
}

// 3. Mark the Notification as Read
if (function_exists('mark_notification_read')) {
    mark_notification_read($pdo, $id);
}

// 4. Redirect the user to the intended destination
header("Location: " . $link);
exit;
?>