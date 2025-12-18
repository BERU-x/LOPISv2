<?php
// login_process.php
session_start();

// --- 1. DATABASE CONNECTION & HELPERS ---
require __DIR__ . '/../db_connection.php'; 
require __DIR__ . '/../helpers/audit_helper.php'; // --- Path to your audit helper ---

header('Content-Type: application/json');

$login_id = $_POST['login_id'] ?? ''; 
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember']);

if (empty($login_id) || empty($password)) {
    http_response_code(400); 
    echo json_encode(['status' => 'error', 'message' => 'Login ID and password are required.']);
    exit;
}

try {
    // --- 2. SYSTEM SETTINGS FETCH ---
    $stmt_settings = $pdo->query("SELECT maintenance_mode FROM tbl_general_settings WHERE id = 1");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    $maintenance_mode = $settings['maintenance_mode'] ?? 0;

    $stmt_sec = $pdo->query("SELECT password_expiry_days FROM tbl_security_settings WHERE id = 1");
    $sec_settings = $stmt_sec->fetch(PDO::FETCH_ASSOC);
    $password_expiry_days = $sec_settings['password_expiry_days'] ?? 0;

    // --- 3. DETERMINE LOGIN FIELD ---
    $login_field = (preg_match('/^[0-9]{3}$/', $login_id)) ? 'u.employee_id' : 'u.email';

    // --- 4. FETCH USER DATA ---
    $sql = "SELECT u.*, u.updated_at AS password_updated_at, CONCAT(e.firstname, ' ', e.lastname) AS fullname 
            FROM tbl_users u
            LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
            WHERE {$login_field} = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check password
    $is_password_correct = $user && password_verify($password, $user['password']);

    // =========================================================================
    // 5. AUTHENTICATION LOGIC
    // =========================================================================
    
    if ($is_password_correct) {
        
        // --- CASE: MAINTENANCE MODE ---
        if ($maintenance_mode == 1 && $user['usertype'] != 0) {
            // Log the blocked attempt
            logAudit($pdo, $user['id'], $user['usertype'], 'LOGIN_BLOCKED', 'User blocked by Maintenance Mode');
            
            http_response_code(503);
            echo json_encode(['status' => 'error', 'message' => 'System under maintenance.']);
            exit;
        }
        
        // --- CASE: INACTIVE ACCOUNT ---
        if ($user['status'] != 1) {
            logAudit($pdo, $user['id'], $user['usertype'], 'LOGIN_DISABLED', 'Attempted login to inactive account');
            
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Account is inactive.']);
            exit;
        }

        // --- PASSWORD EXPIRY CHECK ---
        $force_password_reset = false;
        if ($password_expiry_days > 0) {
            $last_update_date = $user['password_updated_at'] ?? $user['updated_at'];
            $expiry_timestamp = strtotime($last_update_date . " +{$password_expiry_days} days");
            if ($expiry_timestamp < time()) { $force_password_reset = true; }
        }

        // --- SUCCESSFUL LOGIN SETUP ---
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'] ?? $user['employee_id'];
        $_SESSION['usertype'] = $user['usertype'];
        $_SESSION['show_loader'] = true;
        $_SESSION['force_password_reset'] = $force_password_reset;
        
        // ⭐ AUDIT LOG: SUCCESSFUL LOGIN ⭐
        $log_msg = $force_password_reset ? "Login Success (Password Expired)" : "Login Success";
        logAudit($pdo, $user['id'], $user['usertype'], 'LOGIN_SUCCESS', $log_msg);

        // --- REMEMBER ME LOGIC ---
        if ($remember_me) {
            $selector = bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); 
            
            $stmt_token = $pdo->prepare("INSERT INTO tbl_auth_tokens (selector, hashed_token, employee_id, expires_at) VALUES (?, ?, ?, ?)");
            $stmt_token->execute([$selector, hash('sha256', $token), $user['employee_id'], $expires_at]);
            
            setcookie('remember_selector', $selector, ['expires' => time() + (86400 * 30), 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            setcookie('remember_token', $token, ['expires' => time() + (86400 * 30), 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }

        // Determine Redirect
        $redirect_url = $force_password_reset ? 'process/force_password_reset.php' : match((int)$user['usertype']) {
            0 => 'superadmin/dashboard.php',
            1 => 'admin/dashboard.php',
            default => 'user/dashboard.php'
        };

        echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => $redirect_url]);

    } else {
        // ⭐ AUDIT LOG: FAILED LOGIN ⭐
        // We log the attempt with the user_id if we found the account, otherwise NULL
        $target_id = $user['id'] ?? null;
        $target_type = $user['usertype'] ?? null;
        logAudit($pdo, $target_id, $target_type, 'LOGIN_FAILED', "Failed attempt for ID: $login_id");

        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid Login ID or password.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database error: ' . $e->getMessage()); 
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}