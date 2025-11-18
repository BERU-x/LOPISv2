<?php
session_start();
require '../db_connection.php'; // Your DB connection file

// Set response header to JSON
header('Content-Type: application/json');

// Get POST data
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
// --- 1. ADDED: Check for 'remember me' ---
$remember_me = isset($_POST['remember']);

// Basic validation
if (empty($email) || empty($password)) {
    http_response_code(400); 
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit;
}

try {
    // Find the user
    $stmt = $pdo->prepare(
        "SELECT u.*, CONCAT(e.firstname, ' ', e.lastname) AS fullname 
         FROM tbl_users u
         LEFT JOIN tbl_employees e ON u.employee_id = e.id
         WHERE u.email = ?"
    );
    
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_password_correct = $user && password_verify($password, $user['password']);

    // Verify user exists and password is correct
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
        $_SESSION['fullname'] = $user['fullname'] ?? $user['email'];
        $_SESSION['usertype'] = $user['usertype'];
        $_SESSION['show_loader'] = true;

        // --- 2. ADDED: "Remember Me" Logic ---
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
                $stmt_token = $pdo->prepare(
                    "INSERT INTO tbl_auth_tokens (selector, hashed_token, user_id, expires_at) 
                     VALUES (?, ?, ?, ?)"
                );
                $stmt_token->execute([$selector, $hashed_token, $user['id'], $expires_at]);
                
                // Set cookies (httponly = true for security)
                // Set 'secure' to true if you are on HTTPS
                $cookie_options = [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'domain' => 'lendell.ph', // Set your domain if needed
                    'secure' => true, // !! SET TO TRUE FOR PRODUCTION (HTTPS) !!
                    'httponly' => true,
                    'samesite' => 'Lax' // Or 'Strict'
                ];
                
                setcookie('remember_selector', $selector, $cookie_options);
                setcookie('remember_token', $token, $cookie_options);
                
            } catch (PDOException $e) {
                // It's okay if this fails, the user is still logged in for the session.
                // Just log the error.
                error_log('Remember Me cookie token insert failed: ' . $e->getMessage());
            }
        }
        // --- End of "Remember Me" Logic ---

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
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log('Database error: ' . $e->getMessage()); 
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}
?>