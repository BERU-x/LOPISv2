<?php
/**
 * CHECKING.PHP
 * 1. Handles Session Start.
 * 2. Processes "Keep Me Signed In" (Auto-Login) using Split Tokens.
 * 3. Redirects logged-in users away from the login page.
 */

// Use __DIR__ to ensure we find the connection file relative to this script
require_once __DIR__ . '/db_connection.php'; 

if (empty(session_id())) {
    session_start();
}

// --- PART A: AUTO-LOGIN (KEEP ME SIGNED IN) ---
// We only run this if the user is NOT logged in, but HAS the cookies.
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_token'])) {
    
    $selector = $_COOKIE['remember_selector'];
    $raw_token = $_COOKIE['remember_token'];

    try {
        // 1. Look up the Selector (Fast Index Search)
        // We also check expires_at to ensure the token isn't stale
        $stmt = $pdo->prepare("SELECT * FROM tbl_auth_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$selector]);
        $auth_token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth_token) {
            // 2. Verify the Token (Slow Hash Comparison)
            // We hash the cookie's raw token and compare it to the database's hashed token
            // hash_equals prevents timing attacks
            if (hash_equals($auth_token['hashed_token'], hash('sha256', $raw_token))) {
                
                // 3. Token is Valid! Fetch the User
                // We use the 'employee_id' stored in the token table to find the user
                $stmt_user = $pdo->prepare(
                    "SELECT u.id, u.employee_id, u.email, u.usertype, u.status, u.password,
                            CONCAT(e.firstname, ' ', e.lastname) AS fullname,
                            e.photo 
                     FROM tbl_users u
                     LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
                     WHERE u.employee_id = ? AND u.status = 1"
                );

                $stmt_user->execute([$auth_token['employee_id']]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // 4. Log the User In (Set Session Variables)
                    session_regenerate_id(true); // Prevent Session Fixation
                    
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['fullname'] = !empty($user['fullname']) ? $user['fullname'] : $user['email'];
                    $_SESSION['usertype'] = $user['usertype']; // 0=Superadmin, 1=Admin, 2=User
                    $_SESSION['profile_picture'] = $user['photo'] ?? 'default.png'; 
                    $_SESSION['show_loader'] = true; // Optional: Show welcome loader

                    // 5. SECURITY: Rotate the Token
                    // Generate a NEW validator token for the next visit. 
                    // This means if a hacker stole the cookie, it works ONCE, but the real user's 
                    // next visit invalidates the hacker's stolen cookie.
                    $new_raw_token = bin2hex(random_bytes(32));
                    $new_hashed_token = hash('sha256', $new_raw_token);
                    // Extend expiration
                    $new_expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); 

                    // Update DB with new hash
                    $stmt_update = $pdo->prepare("UPDATE tbl_auth_tokens SET hashed_token = ?, expires_at = ? WHERE selector = ?");
                    $stmt_update->execute([$new_hashed_token, $new_expires_at, $selector]);

                    // Update Browser Cookie with new raw token
                    // Determine Secure Flag based on HTTPS
                    $is_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

                    setcookie('remember_token', $new_raw_token, [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'domain' => '', // Set to your domain if needed
                        'secure' => $is_secure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
            }
        }
    } catch (PDOException $e) {
        // Silently fail on DB errors for auto-login (don't break the page load)
        error_log('Remember Me Auto-Login Error: ' . $e->getMessage());
    }
}

// --- PART B: TRAFFIC CONTROLLER (Redirection) ---
// This redirects logged-in users away from "index.php" or "login.php"
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    
    // Get the current file name (e.g., "index.php")
    $current_script_name = basename($_SERVER['PHP_SELF']);

    // Only redirect if they are currently ON the login page
    if ($current_script_name == 'index.php' || $current_script_name == 'login.php') {
        
        $role = $_SESSION['usertype'];
        
        switch ($role) {
            case 0:
                header("Location: superadmin/dashboard.php");
                exit;
            case 1:
                header("Location: admin/dashboard.php"); 
                exit;
            case 2:
                header("Location: user/dashboard.php");
                exit;
            default:
                // Unknown role? Log them out to be safe
                header("Location: logout.php");
                exit;
        }
    }
}
?>