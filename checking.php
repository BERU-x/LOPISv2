<?php
/**
 * CHECKING.PHP
 *
 * This script manages session initialization and "Remember Me" cookie authentication.
 *
 * 1. Starts a session if one isn't active.
 * 2. Checks if a user is NOT logged in but HAS a "Remember Me" cookie.
 * 3. If so, it validates the cookie token against the database.
 * 4. If valid, it logs the user in by creating their session.
 * 5. It rotates the token for security.
 *
 * This file should be included at the VERY TOP of your other PHP pages.
 */

// Adjust this path if 'checking.php' is in a different location
require_once __DIR__ . '/db_connection.php'; 

// Start the session if it's not already started
if (empty(session_id())) {
    session_start();
}

// Check if user is NOT logged in but HAS remember me cookies
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_token'])) {
    
    $selector = $_COOKIE['remember_selector'];
    $token = $_COOKIE['remember_token'];

    try {
        // Find the token in the database
        $stmt = $pdo->prepare(
            "SELECT * FROM tbl_auth_tokens WHERE selector = ? AND expires_at > NOW()"
        );
        $stmt->execute([$selector]);
        $auth_token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth_token) {
            // Token found, verify it
            $hashed_token_from_cookie = hash('sha256', $token);
            
            // Use hash_equals for timing-attack-safe comparison
            if (hash_equals($auth_token['hashed_token'], $hashed_token_from_cookie)) {
                
                // --- TOKEN IS VALID - LOG THE USER IN ---
                
                // 1. Get user details
                $stmt_user = $pdo->prepare(
                    "SELECT u.*, CONCAT(e.firstname, ' ', e.lastname) AS fullname 
                     FROM tbl_users u
                     LEFT JOIN tbl_employees e ON u.employee_id = e.id
                     WHERE u.id = ? AND u.status = 1"
                );
                $stmt_user->execute([$auth_token['user_id']]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // 2. Set session variables
                    session_regenerate_id(true); // Regenerate ID for security
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['fullname'] = $user['fullname'] ?? $user['email'];
                    $_SESSION['usertype'] = $user['usertype'];
                        
                    // 3. Update the token so it can't be reused (Token Rotation)
                    $new_token = bin2hex(random_bytes(32));
                    $new_hashed_token = hash('sha256', $new_token);
                    $new_expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                    $stmt_update = $pdo->prepare(
                        "UPDATE tbl_auth_tokens 
                         SET hashed_token = ?, expires_at = ? 
                         WHERE selector = ?"
                    );
                    $stmt_update->execute([$new_hashed_token, $new_expires_at, $selector]);

                    // 4. Update the cookie with the new token
                    $cookie_options = [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'domain' => '', 
                        'secure' => false, // !! SET TO TRUE FOR PRODUCTION (HTTPS) !!
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ];
                    setcookie('remember_token', $new_token, $cookie_options);
                }
            }
        }
    } catch (PDOException $e) {
        // Log the error, but don't halt the script
        error_log('Remember Me cookie check failed: ' . $e->getMessage());
    }
}
// --- END OF AUTO-LOGIN CODE ---
?>