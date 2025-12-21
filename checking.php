<?php
/**
 * CHECKING.PHP
 * 1. Output Buffering & Session Start.
 * 2. SYSTEM TIMEZONE SETUP (New Feature).
 * 3. Inactivity Timeout.
 * 4. Auto-Login.
 * 5. Access Control.
 */

ob_start();

require_once __DIR__ . '/db_connection.php'; 

if (empty(session_id())) {
    session_start();
}

// =========================================================
// PART A: FETCH SYSTEM SETTINGS (Cached in Session)
// =========================================================
// We check if either timezone OR timeout is missing from session
if (!isset($_SESSION['system_timezone']) || !isset($_SESSION['session_timeout'])) {
    
    // Default Fallbacks
    $timezone = 'Asia/Manila'; 
    $timeout_minutes = 30; // Default 30 mins

    try {
        // MATCHING YOUR TABLE NAME: tbl_general_settings
        $stmt_settings = $pdo->query("SELECT system_timezone, session_timeout_minutes FROM tbl_general_settings LIMIT 1");
        $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);

        if ($settings) {
            if (!empty($settings['system_timezone'])) {
                $timezone = $settings['system_timezone'];
            }
            if (!empty($settings['session_timeout_minutes'])) {
                $timeout_minutes = $settings['session_timeout_minutes'];
            }
        }
    } catch (Exception $e) {
        error_log("Settings fetch error: " . $e->getMessage());
    }

    // Save to session cache
    $_SESSION['system_timezone'] = $timezone;
    $_SESSION['session_timeout'] = $timeout_minutes * 60; // Convert minutes to seconds for calculation
}

// Apply Timezone
date_default_timezone_set($_SESSION['system_timezone']);

// =========================================================
// PART B: INACTIVITY TIMEOUT (Dynamic)
// =========================================================
// Use the variable we just set from the DB
$timeout_duration = $_SESSION['session_timeout']; 

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: /index.php?timeout=1"); 
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// =========================================================
// PART C: AUTO-LOGIN (KEEP ME SIGNED IN)
// =========================================================
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_token'])) {
    
    $selector = $_COOKIE['remember_selector'];
    $raw_token = $_COOKIE['remember_token'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_auth_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$selector]);
        $auth_token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth_token) {
            if (hash_equals($auth_token['hashed_token'], hash('sha256', $raw_token))) {
                
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
                    session_regenerate_id(true);
                    
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['fullname'] = !empty($user['fullname']) ? $user['fullname'] : $user['email'];
                    $_SESSION['usertype'] = $user['usertype'];
                    $_SESSION['profile_picture'] = $user['photo'] ?? 'default.png'; 
                    $_SESSION['show_loader'] = true; 
                    $_SESSION['last_activity'] = time();
                    
                    // IMPORTANT: Ensure timezone is set for the newly logged-in user too
                    if(isset($_SESSION['system_timezone'])) {
                        date_default_timezone_set($_SESSION['system_timezone']);
                    }

                    // Rotate Token logic...
                    $new_raw_token = bin2hex(random_bytes(32));
                    $new_hashed_token = hash('sha256', $new_raw_token);
                    $new_expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); 

                    $stmt_update = $pdo->prepare("UPDATE tbl_auth_tokens SET hashed_token = ?, expires_at = ? WHERE selector = ?");
                    $stmt_update->execute([$new_hashed_token, $new_expires_at, $selector]);

                    $is_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                    setcookie('remember_token', $new_raw_token, [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'domain' => '', 
                        'secure' => $is_secure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Remember Me Auto-Login Error: ' . $e->getMessage());
    }
}

// =========================================================
// PART D: TRAFFIC CONTROLLER
// =========================================================
$current_uri = $_SERVER['REQUEST_URI'];
$current_script = basename($_SERVER['PHP_SELF']);

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['usertype'];
    
    if ($current_script == 'index.php' || $current_script == 'login.php') {
        switch ($role) {
            case 0: header("Location: superadmin/dashboard.php"); exit;
            case 1: header("Location: admin/dashboard.php"); exit;
            case 2: header("Location: user/dashboard.php"); exit;
            default: header("Location: logout.php"); exit;
        }
    }

    if ($role != 0 && strpos($current_uri, '/superadmin/') !== false) {
        header("Location: logout.php"); exit;
    }

    if (($role != 1 && $role != 0) && strpos($current_uri, '/admin/') !== false) {
        header("Location: logout.php"); exit;
    }

} else {
    $public_pages = ['index.php', 'login.php', 'forgot_password.php', 'reset_password.php'];
    if (!in_array($current_script, $public_pages)) {
        header("Location: /index.php"); 
        exit;
    }
}

ob_end_flush();
?>