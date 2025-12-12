<?php
session_start();

// --- 1. INCLUDE DB CONNECTION ---
// We need this to delete the token from the database
// Adjust path if logout.php is in a subfolder (e.g., use '../db_connection.php')
require 'db_connection.php'; 

// --- 2. CLEAR REMEMBER ME TOKEN FROM DATABASE ---
// This prevents the token from being used again (Security Best Practice)
if (isset($_COOKIE['remember_selector']) && isset($pdo)) {
    $selector = $_COOKIE['remember_selector'];
    try {
        // Delete only the token associated with this specific browser/session
        $stmt = $pdo->prepare("DELETE FROM tbl_auth_tokens WHERE selector = ?");
        $stmt->execute([$selector]);
    } catch (PDOException $e) {
        // If DB fails, we continue logging out locally anyway
        error_log("Logout DB Error: " . $e->getMessage());
    }
}

// --- 3. CLEAR REMEMBER ME COOKIES ---
// Set expiration time to the past to delete them from the browser
if (isset($_COOKIE['remember_selector'])) {
    setcookie('remember_selector', '', time() - 3600, '/', '', false, true);
    unset($_COOKIE['remember_selector']);
}
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    unset($_COOKIE['remember_token']);
}

// --- 4. DESTROY SESSION (Standard Logic) ---
// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// --- 5. REDIRECT TO LOGIN ---
header("Location: index.php");
exit;
?>