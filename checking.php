<?php
/**
 * CHECKING.PHP
 * 1. Handles Session Start.
 * 2. Processes "Remember Me" Auto-Login.
 * 3. Redirects logged-in users away from the login page to their specific dashboard.
 */

require_once __DIR__ . '/db_connection.php'; 

if (empty(session_id())) {
    session_start();
}

// --- PART A: AUTO-LOGIN (REMEMBER ME) ---
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_token'])) {
    
    $selector = $_COOKIE['remember_selector'];
    $token = $_COOKIE['remember_token'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_auth_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$selector]);
        $auth_token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth_token) {
            if (hash_equals($auth_token['hashed_token'], hash('sha256', $token))) {
                
                // Fetch User Details including USERTYPE
                $stmt_user = $pdo->prepare(
                    "SELECT u.id, u.employee_id, u.email, u.usertype, u.status,
                            CONCAT(e.firstname, ' ', e.lastname) AS fullname,
                            e.photo 
                     FROM tbl_users u
                     LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
                     WHERE u.employee_id = ? AND u.status = 1"
                );

                $stmt_user->execute([$auth_token['employee_id']]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['fullname'] = !empty($user['fullname']) ? $user['fullname'] : $user['email'];
                    $_SESSION['usertype'] = $user['usertype']; // 0, 1, or 2
                    $_SESSION['profile_picture'] = $user['photo'] ?? 'default.png'; 
                        
                    // Rotate Token
                    $new_token = bin2hex(random_bytes(32));
                    $new_hashed_token = hash('sha256', $new_token);
                    $new_expires_at = date('Y-m-d H:i:s', time() + (86400 * 30));

                    $stmt_update = $pdo->prepare("UPDATE tbl_auth_tokens SET hashed_token = ?, expires_at = ? WHERE selector = ?");
                    $stmt_update->execute([$new_hashed_token, $new_expires_at, $selector]);

                    // Update Cookie
                    setcookie('remember_token', $new_token, [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Remember Me error: ' . $e->getMessage());
    }
}

// --- PART B: ROLE-BASED REDIRECTION (Traffic Controller) ---
// This logic checks if a Logged-In user is trying to access the Login Page (index.php)
// and redirects them to the correct dashboard.

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    
    // [FIX] Renamed variable to avoid overwriting sidebar logic
    $current_script_name = basename($_SERVER['PHP_SELF']);

    // If user is at the login page, kick them to their dashboard
    if ($current_script_name == 'index.php' || $current_script_name == 'login.php') {
        
        $role = $_SESSION['usertype'];
        
        // 0 = Superadmin | 1 = Admin | 2 = Employee
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
                header("Location: index.php");
                break;
        }
    }
}
?>