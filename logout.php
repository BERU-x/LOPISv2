<?php
/**
 * logout.php
 * 1. Clears Single Session ID from Database.
 * 2. Revokes "Remember Me" tokens.
 * 3. Destroys PHP Session.
 */

session_start();

// --- 1. INCLUDE DB CONNECTION ---
require 'db_connection.php'; 

// --- 2. SINGLE SESSION & TOKEN CLEANUP ---
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $user_id = $_SESSION['user_id'];
    $selector = $_COOKIE['remember_selector'] ?? null;

    try {
        // A. Clear the current_session_id so the user is officially logged out in DB
        $stmt_user = $pdo->prepare("UPDATE tbl_users SET current_session_id = NULL WHERE id = ?");
        $stmt_user->execute([$user_id]);

        // B. Delete the "Remember Me" token from DB if it exists
        if ($selector) {
            $stmt_token = $pdo->prepare("DELETE FROM tbl_auth_tokens WHERE selector = ?");
            $stmt_token->execute([$selector]);
        }
    } catch (PDOException $e) {
        error_log("Logout DB Error: " . $e->getMessage());
    }
}

// --- 3. CLEAR REMEMBER ME COOKIES ---
$cookie_options = [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '', 
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
];

if (isset($_COOKIE['remember_selector'])) {
    setcookie('remember_selector', '', $cookie_options);
    unset($_COOKIE['remember_selector']);
}
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', $cookie_options);
    unset($_COOKIE['remember_token']);
}

// --- 4. DESTROY SESSION ---
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// --- 5. REDIRECT TO LOGIN ---
header("Location: index.php");
exit;
?>