<?php
// index.php

// TEMPORARY: Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require 'db_connection.php'; 

/**
 * AUTHENTICATE VIA COOKIES (Super Admin Bypass check)
 * Updated to support Single Session Policy
 */
function checkRememberMe($pdo) {
    if (isset($_COOKIE['remember_selector']) && isset($_COOKIE['remember_token'])) {
        $selector = $_COOKIE['remember_selector'];
        $token = $_COOKIE['remember_token'];
        $token_hash = hash('sha256', $token);
        
        $sql = "SELECT t.*, u.id AS user_id, u.usertype, u.status, u.current_session_id,
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
            $new_session_id = session_id();

            // ⭐ SINGLE SESSION POLICY: Update database with new session ID for cookie login
            $pdo->prepare("UPDATE tbl_users SET current_session_id = ? WHERE id = ?")
                ->execute([$new_session_id, $auth_token['user_id']]);

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $auth_token['user_id'];
            $_SESSION['current_session_id'] = $new_session_id; 
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

// --- MAINTENANCE BLOCKING LOGIC ---
$is_super_admin = ($usertype === 0);
if ($maintenance_mode == 1 && $session_active && !$is_super_admin) {
    session_unset();
    session_destroy();
    setcookie('remember_selector', '', time() - 3600, '/');
    setcookie('remember_token', '', time() - 3600, '/');
    http_response_code(503);
    // (Maintenance HTML omitted for brevity, keep your original block here)
    exit; 
}

// --- FINAL REDIRECTION ---
// At the bottom of the PHP block in index.php
if ($session_active) {
    $target = $_GET['redirect'] ?? null;
    
    if ($target) {
        header("Location: $target");
    } else {
        $redirect_url = match((int)$_SESSION['usertype']) {
            0 => 'superadmin/dashboard.php',
            1 => 'admin/dashboard.php',
            default => 'user/dashboard.php'
        };
        header("Location: $redirect_url");
    }
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
</head>
<body>
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="col-lg-4 col-md-6 col-sm-10">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="assets/images/LOPISv2.png" alt="LOPISv2 Logo" class="img-fluid" style="max-height: 120px;">
                        
                        <?php if (isset($_GET['error']) && $_GET['error'] === 'session_conflict'): ?>
                            <div class="alert alert-danger small mt-3 fw-bold animate__animated animate__shakeX">
                                <i class="fas fa-user-slash me-1"></i> Account accessed from another device. You have been logged out.
                            </div>
                        <?php endif; ?>

                        <?php if ($maintenance_mode == 1): ?>
                            <div class="alert alert-warning small mt-3 fw-bold">
                                <i class="fas fa-exclamation-triangle me-1"></i> System is under maintenance.
                            </div>
                        <?php endif; ?>
                    </div>

                    <form id="login-form">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_GET['redirect'] ?? ''); ?>">
                        
                        <div class="mb-4">
                            <div class="input-group-modern">
                                <input type="text" class="form-input" id="login_id" name="login_id" placeholder=" " required>
                                <label class="floating-label" for="login_id">Email or Employee ID</label>
                                <span class="input-icon"><i class="fas fa-user-circle"></i></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group-modern">
                                <input type="password" class="form-input" id="password" name="password" placeholder=" " required>
                                <label class="floating-label" for="password">Password</label>
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <button class="btn-outline-secondary" type="button" id="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div id="caps-warning" class="caps-warning d-none">Caps Lock is ON!</div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label small" for="remember">Keep me Signed In</label>
                            </div>
                            <?php if ($allow_forgot_password == 1): ?>
                                <a href="process/forgot_password.php" class="small text-decoration-none">Forgot Password?</a>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="btn-login">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <i class="fa fa-sign-in-alt me-2"></i>Sign In
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
    <script src="assets/js/pages/login_scripts.js"></script>
</body>
</html>