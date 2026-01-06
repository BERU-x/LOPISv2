<?php
// api/login_action.php

// TEMPORARY: Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- DATABASE CONNECTION & HELPERS ---
require __DIR__ .'/../db_connection.php'; 
require __DIR__ .'/../helpers/audit_helper.php'; 

// --- DYNAMIC TIMEZONE FETCH ---
try {
    $stmt_tz = $pdo->query("SELECT system_timezone FROM tbl_general_settings WHERE id = 1");
    $timezone = $stmt_tz->fetchColumn() ?? 'Asia/Manila';
    date_default_timezone_set($timezone); 
} catch (PDOException $e) {
    date_default_timezone_set('Asia/Manila');
}

header('Content-Type: application/json');

$login_id = $_POST['login_id'] ?? ''; 
$password = $_POST['password'] ?? '';

if (empty($login_id) || empty($password)) {
    http_response_code(400); 
    echo json_encode(['status' => 'error', 'message' => 'Login ID and password are required.']);
    exit;
}

try {
    // 0. FETCH SETTINGS
    $stmt_settings = $pdo->query("SELECT maintenance_mode FROM tbl_general_settings WHERE id = 1");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    $maintenance_mode = $settings['maintenance_mode'] ?? 0;

    $stmt_sec = $pdo->query("SELECT password_expiry_days, max_login_attempts, lockout_duration_mins FROM tbl_security_settings WHERE id = 1");
    $sec_settings = $stmt_sec->fetch(PDO::FETCH_ASSOC);
    $max_login_attempts = $sec_settings['max_login_attempts'] ?? 5;
    $lockout_duration_mins = $sec_settings['lockout_duration_mins'] ?? 15;
    $password_expiry_days = $sec_settings['password_expiry_days'] ?? 0;

    // 1. DETERMINE LOGIN FIELD
    $login_field_db = (preg_match('/^[0-9]{3}$/', $login_id)) ? 'u.employee_id' : 'u.email';

    // 2. CHECK FOR ACCOUNT LOCKOUT
    $stmt_lockout = $pdo->prepare("SELECT attempts, lockout_until FROM tbl_login_attempts WHERE login_id = ?");
    $stmt_lockout->execute([$login_id]);
    $lockout_record = $stmt_lockout->fetch(PDO::FETCH_ASSOC);
    
    if (isset($lockout_record['lockout_until']) && strtotime($lockout_record['lockout_until']) > time()) {
        logAudit($pdo, null, null, 'LOGIN_LOCKED', "Blocked attempt for locked account: $login_id");
        $remaining_time = strtotime($lockout_record['lockout_until']) - time();
        $minutes = floor($remaining_time / 60);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => "Account locked. Try again in {$minutes}m."]);
        exit;
    }

    // 3. FETCH USER DATA
    $sql = "SELECT u.*, u.updated_at AS password_updated_at, CONCAT(e.firstname, ' ', e.lastname) AS fullname 
            FROM tbl_users u
            LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
            WHERE {$login_field_db} = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_password_correct = $user && password_verify($password, $user['password']);

    if ($is_password_correct) {
        
        // a. MAINTENANCE MODE CHECK
        if ($maintenance_mode == 1 && $user['usertype'] != 0) {
            logAudit($pdo, $user['id'], $user['usertype'], 'LOGIN_BLOCKED', 'System in Maintenance Mode');
            http_response_code(503);
            echo json_encode(['status' => 'error', 'message' => 'System under maintenance.']);
            exit;
        }
        
        // b. ACCOUNT STATUS CHECK
        if ($user['status'] != 1) {
            logAudit($pdo, $user['id'], $user['usertype'], 'LOGIN_INACTIVE', 'Attempt to login to disabled account');
            http_response_code(403); 
            echo json_encode(['status' => 'error', 'message' => 'Your account is inactive.']);
            exit;
        }

        // c. CLEAR ATTEMPTS
        $pdo->prepare("DELETE FROM tbl_login_attempts WHERE login_id = ?")->execute([$login_id]);

        // d. PASSWORD EXPIRY CHECK
        $force_password_reset = false;
        if ($password_expiry_days > 0) {
            $last_update_date = $user['password_updated_at'] ?? $user['updated_at'];
            if ($last_update_date && strtotime($last_update_date . " +{$password_expiry_days} days") < time()) {
                $force_password_reset = true;
            }
        }

        // e. SESSION INITIALIZATION
        session_regenerate_id(true); // Prevent session fixation
        $new_session_id = session_id();

        // ⭐ SINGLE SESSION POLICY: Store the new session ID in the database
        // This effectively invalidates any other active session for this user.
        $update_sess = $pdo->prepare("UPDATE tbl_users SET current_session_id = ? WHERE id = ?");
        $update_sess->execute([$new_session_id, $user['id']]);
        
        $_SESSION['current_session_id'] = $new_session_id; // Store for comparison
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['fullname'] = $user['fullname'] ?? $user['employee_id'];
        $_SESSION['usertype'] = $user['usertype'];
        $_SESSION['show_loader'] = true;
        
        // f. AUDIT LOG: SUCCESS
        $log_msg = $force_password_reset ? "Login Successful (Forced Reset Required)" : "Login Successful";
        logAudit($pdo, $user['id'], $user['usertype'], 'LOGIN_SUCCESS', $log_msg);

        // g. REDIRECT
        $default_redirect = match((int)$user['usertype']) {
            0 => 'superadmin/dashboard.php',
            1 => 'admin/dashboard.php',
            default => 'user/dashboard.php'
        };

        // Check if a specific redirect URL was requested
        $requested_redirect = $_POST['redirect_to'] ?? '';
        
        // If force_password_reset is true, that takes priority
        if ($force_password_reset) {
            $redirect_url = 'process/force_password_reset.php';
        } 
        // If there's a requested redirect, use it, otherwise use default
        elseif (!empty($requested_redirect)) {
            $redirect_url = $requested_redirect;
        } 
        else {
            $redirect_url = $default_redirect;
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => $redirect_url]);

    } else {
        // --- FAILED LOGIN LOGIC ---
        if ($user) { 
            $attempts_sql = "INSERT INTO tbl_login_attempts (login_id, attempts, last_attempt_at, lockout_until)
                             VALUES (:login_id, 1, NOW(), NULL)
                             ON DUPLICATE KEY UPDATE 
                                attempts = attempts + 1,
                                last_attempt_at = NOW(),
                                lockout_until = IF(attempts + 1 >= :max_attempts, DATE_ADD(NOW(), INTERVAL :duration MINUTE), NULL)";
            $pdo->prepare($attempts_sql)->execute([
                ':login_id' => $login_id,
                ':max_attempts' => $max_login_attempts,
                ':duration' => $lockout_duration_mins
            ]);
            logAudit($pdo, $user['id'], $user['usertype'], 'LOGIN_FAILED', "Wrong password attempt for: $login_id");
        } else {
            logAudit($pdo, null, null, 'LOGIN_FAILED', "Login attempt for non-existent user: $login_id");
        }

        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Invalid Login ID or password.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
}