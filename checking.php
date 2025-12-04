<?php
/**
 * CHECKING.PHP
 * Updates: Now fetches 'photo' from tbl_employees to prevent missing profile pictures.
 */

// Adjust this path if needed
require_once __DIR__ . '/db_connection.php'; 

if (empty(session_id())) {
    session_start();
}

// Check if user is NOT logged in but HAS remember me cookies
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_token'])) {
    
    $selector = $_COOKIE['remember_selector'];
    $token = $_COOKIE['remember_token'];

    try {
        // Find the token in the database
        $stmt = $pdo->prepare("SELECT * FROM tbl_auth_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$selector]);
        $auth_token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth_token) {
            // Verify token
            if (hash_equals($auth_token['hashed_token'], hash('sha256', $token))) {
                
                // --- TOKEN IS VALID - LOG THE USER IN ---
                
                // 1. Get user details AND PHOTO
                // Added e.photo to the SELECT list
                $stmt_user = $pdo->prepare(
                    "SELECT u.*, 
                            CONCAT(e.firstname, ' ', e.lastname) AS fullname,
                            e.photo 
                     FROM tbl_users u
                     LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
                     WHERE u.id = ? AND u.status = 1"
                );
                
                // NOTE ON JOIN above: I changed 'ON u.employee_id = e.id' to 'ON u.employee_id = e.employee_id'
                // based on your previous var_dump showing employee_id is '006' (string), matching tbl_employees.employee_id.
                // If your tbl_users stores the integer ID (1, 2, 3), change it back to e.id.

                $stmt_user->execute([$auth_token['user_id']]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // 2. Set session variables
                    session_regenerate_id(true);
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['fullname'] = $user['fullname'] ?? $user['email'];
                    $_SESSION['usertype'] = $user['usertype'];
                    
                    // NEW: Save the photo to session so topbar.php works correctly
                    $_SESSION['profile_picture'] = $user['photo']; 
                        
                    // 3. Rotate Token (Security)
                    $new_token = bin2hex(random_bytes(32));
                    $new_hashed_token = hash('sha256', $new_token);
                    $new_expires_at = date('Y-m-d H:i:s', time() + (86400 * 30));

                    $stmt_update = $pdo->prepare(
                        "UPDATE tbl_auth_tokens SET hashed_token = ?, expires_at = ? WHERE selector = ?"
                    );
                    $stmt_update->execute([$new_hashed_token, $new_expires_at, $selector]);

                    // 4. Update Cookie
                    $cookie_options = [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'domain' => '', 
                        'secure' => false, // Change to true if using HTTPS
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ];
                    setcookie('remember_token', $new_token, $cookie_options);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Remember Me cookie check failed: ' . $e->getMessage());
    }
}
?>