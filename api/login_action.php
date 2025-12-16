<?php
// api/login_action.php

// TEMPORARY: Display all PHP errors for debugging (Keep this until resolved)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- DATABASE CONNECTION ---
require __DIR__ .'/../db_connection.php'; 

// --- DYNAMIC TIMEZONE FETCH ---
try {
    // Fetch timezone from DB. Assumes system_timezone column exists in tbl_general_settings.
    $stmt_tz = $pdo->query("SELECT system_timezone FROM tbl_general_settings WHERE id = 1");
    $timezone = $stmt_tz->fetchColumn() ?? 'Asia/Manila'; // Default to a safe zone if fetch fails
    date_default_timezone_set($timezone); 
} catch (PDOException $e) {
    date_default_timezone_set('Asia/Manila'); // Default fallback
    error_log("Failed to fetch system_timezone: " . $e->getMessage());
}
// -----------------------------

// Set response header to JSON
header('Content-Type: application/json');

// --- START: AUTO-CREATE MISSING TABLES (Ensures tbl_login_attempts exists for security) ---
try {
    $pdo->query("SELECT 1 FROM tbl_login_attempts LIMIT 1");
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        $create_table_sql = "
            CREATE TABLE `tbl_login_attempts` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `login_id` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Stores email or employee_id',
                `attempts` INT(11) NOT NULL DEFAULT 0,
                `last_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `lockout_until` DATETIME NULL DEFAULT NULL,
                INDEX `idx_login_id` (`login_id`),
                INDEX `idx_lockout` (`lockout_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks failed login attempts for security lockout.';
        ";
        try {
            $pdo->exec($create_table_sql);
        } catch (PDOException $e) {
            error_log("FATAL: Could not auto-create tbl_login_attempts. Error: " . $e->getMessage());
        }
    }
}
// --- END: AUTO-CREATE MISSING TABLES ---

// --- INPUT HANDLING ---
$login_id = $_POST['login_id'] ?? ''; 
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember']);

// Basic validation
if (empty($login_id) || empty($password)) {
    http_response_code(400); 
    echo json_encode(['status' => 'error', 'message' => 'Login ID and password are required.']);
    exit;
}

try {
    // --- 0a. FETCH GENERAL SETTINGS (MAINTENANCE MODE) ---
    $stmt_settings = $pdo->query("SELECT maintenance_mode FROM tbl_general_settings WHERE id = 1");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
    $maintenance_mode = $settings['maintenance_mode'] ?? 0;

    // --- 0b. FETCH SECURITY SETTINGS (EXPIRY & LOCKOUT) ---
    $stmt_sec = $pdo->query("SELECT password_expiry_days, max_login_attempts, lockout_duration_mins FROM tbl_security_settings WHERE id = 1");
    $sec_settings = $stmt_sec->fetch(PDO::FETCH_ASSOC);
    $password_expiry_days = $sec_settings['password_expiry_days'] ?? 0;
    $max_login_attempts = $sec_settings['max_login_attempts'] ?? 5;
    $lockout_duration_mins = $sec_settings['lockout_duration_mins'] ?? 15;

    // 1. DETERMINE LOGIN FIELD
    $login_field_db = (preg_match('/^[0-9]{3}$/', $login_id)) ? 'u.employee_id' : 'u.email';

    // --- CHECK FOR ACCOUNT LOCKOUT ---
    $stmt_lockout = $pdo->prepare("SELECT attempts, lockout_until FROM tbl_login_attempts WHERE login_id = ?");
    $stmt_lockout->execute([$login_id]);
    $lockout_record = $stmt_lockout->fetch(PDO::FETCH_ASSOC);
    
    // Check if lockout_until is not null AND if the time is in the future
    $lockout_time = $lockout_record['lockout_until'] ?? null;
    
    if ($lockout_time && strtotime($lockout_time) > time()) {
        $remaining_time = strtotime($lockout_time) - time();
        $minutes = floor($remaining_time / 60);
        $seconds = $remaining_time % 60;
        
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => "Account locked out. Try again in {$minutes}m {$seconds}s."]);
        exit;
    }
    // --- END LOCKOUT CHECK ---

    // 2. FETCH USER DATA
    $sql = "SELECT 
                u.*, 
                u.updated_at AS password_updated_at, 
                CONCAT(e.firstname, ' ', e.lastname) AS fullname 
            FROM tbl_users u
            LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
            WHERE {$login_field_db} = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_password_correct = $user && password_verify($password, $user['password']);

    // 3. Process Login Result
    if ($is_password_correct) {
        
        // a. MAINTENANCE MODE CHECK (Bypass for usertype 0)
        if ($maintenance_mode == 1 && $user['usertype'] != 0) {
            http_response_code(503); // Service Unavailable
            echo json_encode(['status' => 'error', 'message' => 'The system is currently undergoing maintenance. Please try again later.']);
            exit;
        }
        
        // b. ACCOUNT STATUS CHECK
        if ($user['status'] != 1) {
            http_response_code(403); 
            echo json_encode(['status' => 'error', 'message' => 'Your account is inactive. Please contact the administrator.']);
            exit;
        }

        // c. CLEAR LOGIN ATTEMPTS
        $pdo->prepare("DELETE FROM tbl_login_attempts WHERE login_id = ?")->execute([$login_id]);

        // d. PASSWORD EXPIRY CHECK
        $force_password_reset = false;
        if ($password_expiry_days > 0) {
            $last_update_date = $user['password_updated_at'] ?? $user['updated_at'];
            if (!empty($last_update_date)) {
                 $expiry_timestamp = strtotime($last_update_date . " +{$password_expiry_days} days");
            
                if ($expiry_timestamp < time()) {
                    $force_password_reset = true;
                }
            }
        }

        // e. SESSION CREATION
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'] ?? $user['employee_id'];
        $_SESSION['usertype'] = $user['usertype'];
        $_SESSION['show_loader'] = true;
        $_SESSION['force_password_reset'] = $force_password_reset;
        
        // f. REMEMBER ME LOGIC (Omitted)

        // g. REDIRECTION PATHS (Relative to the index.php file)
        if ($force_password_reset) {
            $redirect_url = 'process/force_password_reset.php'; 
            $message = 'Your password has expired. You must update it now.';
        } else {
            $redirect_url = 'user/dashboard.php'; 
            if ($user['usertype'] == 0) {
                $redirect_url = 'superadmin/dashboard.php'; 
            } elseif ($user['usertype'] == 1) {
                $redirect_url = 'admin/dashboard.php';
            }
            $message = 'Login successful! Redirecting...';
        }
        
        echo json_encode(['status' => 'success', 'message' => $message, 'redirect' => $redirect_url]);

    } else {
        // --- FAILED LOGIN LOGIC ---
        
        $error_message = 'Invalid Login ID or password.';
        
        // INCREMENT LOGIN ATTEMPTS (Only track if a user record was found)
        if ($user) { 
            // Using NAMED PLACEHOLDERS (Fixes duration bug)
            $attempts_sql = "
                INSERT INTO tbl_login_attempts (login_id, attempts, last_attempt_at, lockout_until)
                VALUES (:login_id, 1, NOW(), NULL)
                ON DUPLICATE KEY UPDATE 
                    attempts = attempts + 1,
                    last_attempt_at = NOW(),
                    lockout_until = IF(
                        attempts + 1 >= :max_attempts, 
                        DATE_ADD(NOW(), INTERVAL :duration MINUTE), 
                        NULL
                    )
            ";
            $stmt_update_attempts = $pdo->prepare($attempts_sql);
            
            // EXECUTE using named array
            $stmt_update_attempts->execute([
                ':login_id' => $login_id,
                ':max_attempts' => $max_login_attempts,
                ':duration' => $lockout_duration_mins
            ]);

            $current_attempts = $lockout_record['attempts'] ?? 0;
            if ($current_attempts + 1 >= $max_login_attempts) {
                $error_message = "Maximum login attempts reached. Account locked for {$lockout_duration_mins} minutes.";
            }
        }

        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => $error_message]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database error: ' . $e->getMessage()); 
    
    // TEMPORARILY Output the specific PDO error
    $debug_message = 'DB Error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')';
    
    echo json_encode(['status' => 'error', 'message' => $debug_message]);
}
?>