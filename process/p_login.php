<?php
session_start();

// --- DATABASE CONNECTION & USER MODEL ---
require '../db_connection.php'; // Your DB connection file

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
    // 1. DETERMINE LOGIN FIELD
    // Assume Employee IDs are exactly 3 digits (VARCHAR(3) in tbl_employees)
    if (preg_match('/^[0-9]{3}$/', $login_id)) { 
        $login_field = 'u.employee_id';
    } else {
        $login_field = 'u.email';
    }

    // 2. FETCH USER DATA (Flexible Query)
    $sql = "SELECT 
                u.*, 
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
        
        // Check if account is active
        if ($user['status'] != 1) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Your account is inactive. Please contact the administrator.']);
            exit;
        }

        // --- Login Successful ---
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'] ?? $user['employee_id']; // Use employee_id as fallback name
        $_SESSION['usertype'] = $user['usertype'];
        $_SESSION['show_loader'] = true;

        // --- "Keep me Signed In" Logic ---
        if ($remember_me) {
            // Generate secure tokens
            $selector = bin2hex(random_bytes(16));
            $token = bin2hex(random_bytes(32));
            
            // Set expiry date (e.g., 30 days from now)
            $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); 
            
            // Hash the token for database storage
            $hashed_token = hash('sha256', $token);
            
            // Store in the database
            try {
                // FIX: Changed 'user_id' column reference to 'employee_id' to match DB schema.
                $stmt_token = $pdo->prepare(
                    "INSERT INTO tbl_auth_tokens (selector, hashed_token, employee_id, expires_at) 
                    VALUES (?, ?, ?, ?)"
                );
                // FIX: Pass $user['employee_id'] instead of $user['id'].
                $stmt_token->execute([$selector, $hashed_token, $user['employee_id'], $expires_at]);
                
                // Set cookies (httponly = true for security)
                $cookie_options = [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => false, 
                    'httponly' => true,
                    'samesite' => 'Lax'
                ];
                
                // Set 'secure' based on environment
                if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                    $cookie_options['secure'] = true;
                }
                
                setcookie('remember_selector', $selector, $cookie_options);
                setcookie('remember_token', $token, $cookie_options);
                
            } catch (PDOException $e) {
                error_log('Remember Me cookie token insert failed: ' . $e->getMessage());
            }
        }
        // --- End of "Keep me Signed In" Logic ---

        // Determine redirect URL
        $redirect_url = 'user/dashboard.php'; // Default for user (2)
        if ($user['usertype'] == 0) {
            $redirect_url = 'superadmin/dashboard.php';
        } elseif ($user['usertype'] == 1) {
            $redirect_url = 'admin/dashboard.php';
        }

        // Send success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful! Redirecting...',
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