<?php
// index.php

// TEMPORARY: Display all PHP errors for debugging (Should be removed in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- DATABASE CONNECTION & USER MODEL ---
// Path is relative to index.php
require 'db_connection.php'; 

// --- FUNCTION TO AUTHENTICATE VIA COOKIES (Super Admin Bypass check) ---
function checkRememberMe($pdo) {
    if (isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_token'])) {
        $selector = $_COOKIE['remember_selector'];
        $token = $_COOKIE['remember_token'];
        $token_hash = hash('sha256', $token);
        
        $sql = "SELECT t.*, u.id AS user_id, u.usertype, u.status, 
                       CONCAT(e.firstname, ' ', e.lastname) AS fullname 
                FROM tbl_auth_tokens t
                JOIN tbl_users u ON t.employee_id = u.employee_id
                LEFT JOIN tbl_employees e ON t.employee_id = e.employee_id
                WHERE t.selector = ? AND t.expires_at > NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selector]);
        $auth_token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth_token && hash_equals($auth_token['hashed_token'], $token_hash)) {
            if ($auth_token['status'] != 1) return false;

            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $auth_token['user_id'];
            $_SESSION['employee_id'] = $auth_token['employee_id'];
            $_SESSION['fullname'] = $auth_token['fullname'] ?? $auth_token['employee_id'];
            $_SESSION['usertype'] = $auth_token['usertype'];
            $_SESSION['show_loader'] = true;
            
            return true;
        } else {
            setcookie('remember_selector', '', time() - 3600, '/');
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    return false;
}

// --- FETCH SETTINGS ---
$maintenance_mode = 0;
$allow_forgot_password = 1;
try {
    $stmt_settings = $pdo->query("SELECT maintenance_mode, allow_forgot_password FROM tbl_general_settings WHERE id = 1");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    $maintenance_mode = $settings['maintenance_mode'] ?? 0;
    $allow_forgot_password = $settings['allow_forgot_password'] ?? 1;
} catch (PDOException $e) {
    error_log("Failed to fetch system settings: " . $e->getMessage());
}

// --- SESSION/COOKIE CHECK ---
$session_active = false;
$usertype = null;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $session_active = true;
    $usertype = $_SESSION['usertype'];
} else {
    try {
        if (checkRememberMe($pdo)) {
            $session_active = true;
            $usertype = $_SESSION['usertype'];
        }
    } catch (PDOException $e) {
        error_log("Cookie auth failed: " . $e->getMessage());
    }
}

// --- MAINTENANCE BLOCKING LOGIC (Non-Super Admins) ---
$is_super_admin = ($usertype === 0);

if ($maintenance_mode == 1 && $session_active && !$is_super_admin) {
    // If logged in via session/cookie, but not Super Admin, block them
    session_unset();
    session_destroy();
    setcookie('remember_selector', '', time() - 3600, '/');
    setcookie('remember_token', '', time() - 3600, '/');
    
    http_response_code(503);
    
    // Display Maintenance Page HTML (for external access)
    echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance</title>
    <link href="assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fa6/css/all.min.css" />
    <style>
        body { background-color: #f0f2f5; color: #495057; }
        .maintenance-container { height: 100vh; display: flex; justify-content: center; align-items: center; text-align: center; }
        .maintenance-box { padding: 40px; border-radius: 10px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .maintenance-icon { font-size: 5rem; color: #ffc107; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-box">
            <i class="fas fa-tools maintenance-icon"></i>
            <h1 class="mt-3 fw-bold">System Maintenance</h1>
            <p class="lead">We are currently performing scheduled maintenance. The system will be back online shortly.</p>
            <p class="text-muted small">Thank you for your patience.</p>
        </div>
    </div>
</body>
</html>';
    exit; 
}

// --- FINAL REDIRECTION ---
if ($session_active) {
    $redirect_url = 'user/dashboard.php'; 
    if ($_SESSION['usertype'] == 0) {
        $redirect_url = 'superadmin/dashboard.php';
    } elseif ($_SESSION['usertype'] == 1) {
        $redirect_url = 'admin/dashboard.php';
    }
    header("Location: $redirect_url");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOPISv2 - Login</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/ico">
    <link href="assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet"> 
    <link rel="stylesheet" href="assets/vendor/fa6/css/all.min.css" />  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">   
    <link rel="stylesheet" href="assets/css/login_styles.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
    </style>
</head>
<body>
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="col-lg-4 col-md-6 col-sm-10">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="assets/images/LOPISv2.png" alt="LOPISv2 Logo" class="img-fluid" style="max-height: 300px;">
                        <p class="text-muted mt-3">Sign in to your account</p>
                        <?php if ($maintenance_mode == 1): ?>
                            <div class="alert alert-warning small mt-3 fw-bold">
                                <i class="fas fa-exclamation-triangle me-1"></i> System is under maintenance.
                            </div>
                        <?php endif; ?>
                    </div>

                    <form id="login-form">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                                <input type="text" class="form-control" id="login_id" name="login_id" placeholder="Email or Employee ID" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggle-password"><i class="fas fa-eye"></i></button>
                            </div>
                            <div id="caps-warning" class="text-danger mt-1 d-none small fw-bold">
                                <i class="fas fa-exclamation-triangle me-1"></i> Caps Lock is ON
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Keep me Signed In</label>
                            </div>
                            
                            <?php if ($allow_forgot_password == 1): ?>
                                <div class="text-end">
                                    <a href="process/forgot_password.php" class="small text-muted">Forgot Password?</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="btn-login">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <i class="fa fa-sign-in-alt me-2"></i>
                                Sign In
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="scripts/login_scripts.js"></script>

</body>
</html>