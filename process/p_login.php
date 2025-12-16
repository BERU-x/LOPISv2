<?php
session_start();

// --- DATABASE CONNECTION & USER MODEL ---
require __DIR__ . '/../db_connection.php'; // Your DB connection file

// Set response header to JSON
header('Content-Type: application/json');

// Get POST data (renamed from 'email' to 'login_id' in index.php)
$login_id = $_POST['login_id'] ?? ''; // Expects email or employee_id
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

    // --- 0b. FETCH SECURITY SETTINGS (PASSWORD EXPIRY) ---
    $stmt_sec = $pdo->query("SELECT password_expiry_days FROM tbl_security_settings WHERE id = 1");
    $sec_settings = $stmt_sec->fetch(PDO::FETCH_ASSOC);
    $password_expiry_days = $sec_settings['password_expiry_days'] ?? 0; // Default 0 means no expiry check

    // 1. DETERMINE LOGIN FIELD
    if (preg_match('/^[0-9]{3}$/', $login_id)) { 
        $login_field = 'u.employee_id';
    } else {
        $login_field = 'u.email';
    }

    // 2. FETCH USER DATA (Flexible Query)
    // IMPORTANT: Assuming a column 'u.password_updated_at' exists in tbl_users for expiry check
    $sql = "SELECT 
                u.*, 
                u.updated_at AS password_updated_at, -- Assuming 'updated_at' can track password change
                CONCAT(e.firstname, ' ', e.lastname) AS fullname 
            FROM tbl_users u
            LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
            WHERE {$login_field} = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_password_correct = $user && password_verify($password, $user['password']);

    // 3. Verify user exists and password is correct
    if ($is_password_correct) {
        
        // --- CRITICAL MAINTENANCE MODE CHECK ---
        if ($maintenance_mode == 1 && $user['usertype'] != 0) {
            http_response_code(503); // Service Unavailable
            echo json_encode(['status' => 'error', 'message' => 'The system is currently undergoing maintenance. Please try again later.']);
            exit;
        }
        
        // Check if account is active
        if ($user['status'] != 1) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Your account is inactive. Please contact the administrator.']);
            exit;
        }

        // --- NEW: PASSWORD EXPIRY CHECK (Forced Reset) ---
        $force_password_reset = false;
        if ($password_expiry_days > 0) {
            // Use password_updated_at if available, otherwise fallback to the row's general updated_at
            $last_update_date = $user['password_updated_at'] ?? $user['updated_at'];
            $expiry_timestamp = strtotime($last_update_date . " +{$password_expiry_days} days");

            if ($expiry_timestamp < time()) {
                $force_password_reset = true;
            }
        }
        // --- END PASSWORD EXPIRY CHECK ---

        // --- Login Successful ---
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'] ?? $user['employee_id'];
        $_SESSION['usertype'] = $user['usertype'];
        $_SESSION['show_loader'] = true;
        
        // Store expiry status in session
        $_SESSION['force_password_reset'] = $force_password_reset;
        
        // --- "Keep me Signed In" Logic (Unchanged) ---
        if ($remember_me) {
            // ... (Cookie generation and DB insertion logic remains here) ...
            // Generate secure tokens
            $selector = bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); 
            $hashed_token = hash('sha256', $token);
            
            try {
                $stmt_token = $pdo->prepare(
                    "INSERT INTO tbl_auth_tokens (selector, hashed_token, employee_id, expires_at) 
                    VALUES (?, ?, ?, ?)"
                );
                $stmt_token->execute([$selector, $hashed_token, $user['employee_id'], $expires_at]);
                
                $cookie_options = [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 
                    'httponly' => true,
                    'samesite' => 'Lax'
                ];
                
                setcookie('remember_selector', $selector, $cookie_options);
                setcookie('remember_token', $token, $cookie_options);
            } catch (PDOException $e) {
                error_log('Remember Me cookie token insert failed: ' . $e->getMessage());
            }
        }
        // --- End of "Keep me Signed In" Logic ---

        // Determine redirect URL
        // If password expired, redirect to forced reset page instead of dashboard
        if ($force_password_reset) {
            $redirect_url = 'process/force_password_reset.php'; 
            $message = 'Your password has expired. You must update it now.';
        } else {
            $redirect_url = 'user/dashboard.php'; // Default for user (2)
            if ($user['usertype'] == 0) {
                $redirect_url = 'superadmin/dashboard.php';
            } elseif ($user['usertype'] == 1) {
                $redirect_url = 'admin/dashboard.php';
            }
            $message = 'Login successful! Redirecting...';
        }


        // Send success response
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'redirect' => $redirect_url
        ]);

    } else {
        // Invalid credentials
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid Login ID or password.']);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log('Database error: ' . $e->getMessage()); 
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}
?>