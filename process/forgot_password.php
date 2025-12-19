<?php
// forgot_password.php

// TEMPORARY: Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- 1. DATABASE, EMAIL, & AUDIT SETUP ---
require_once __DIR__ . '/../db_connection.php'; 
require_once __DIR__ . '/../helpers/email_handler.php'; 
require_once __DIR__ . '/../helpers/audit_helper.php'; 

// --- 2. TIMEZONE ---
try {
    $stmt_tz = $pdo->query("SELECT system_timezone FROM tbl_general_settings WHERE id = 1");
    $timezone = $stmt_tz->fetchColumn() ?? 'Asia/Manila';
    date_default_timezone_set($timezone); 
} catch (PDOException $e) {
    date_default_timezone_set('Asia/Manila');
}

// Redirect if logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: ../index.php"); 
    exit;
}

// =================================================================================
// AJAX HANDLER
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $login_id = $_POST['login_id'] ?? '';

    if (empty($login_id)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Employee ID or Email is required.']);
        exit;
    }

    try {
        // 1. DETERMINE ID TYPE
        $login_field_db = (preg_match('/^[0-9]{3}$/', $login_id)) ? 'employee_id' : 'email';

        // 2. FETCH USER
        $sql = "SELECT id, usertype, email, status FROM tbl_users WHERE {$login_field_db} = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$login_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- EXPLICIT CHECK: USER DOES NOT EXIST ---
        if (!$user) {
            // Audit Log for security monitoring
            logAudit($pdo, null, null, 'RESET_FAILED_UNKNOWN', "Reset attempted for non-existent ID: " . $login_id);
            
            http_response_code(404); // Not Found
            echo json_encode([
                'status' => 'error', 
                'message' => 'We could not find an account with that Email or Employee ID.'
            ]);
            exit;
        }

        // --- EXPLICIT CHECK: ACCOUNT INACTIVE ---
        if ($user['status'] != 1) {
            logAudit($pdo, $user['id'], $user['usertype'], 'RESET_FAILED_INACTIVE', "Reset attempted on inactive account.");
            
            http_response_code(403); // Forbidden
            echo json_encode([
                'status' => 'error', 
                'message' => 'This account is currently inactive. Please contact HR or IT Support.'
            ]);
            exit;
        }

        $user_id = $user['id'];
        $user_usertype = $user['usertype'];
        $user_email = $user['email'];

        // 3. GENERATE 6-DIGIT CODE (OTP)
        $reset_code = sprintf("%06d", random_int(0, 999999)); 
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes')); 

        // Clean old codes
        $pdo->prepare("DELETE FROM tbl_password_resets WHERE user_id = ?")->execute([$user_id]);

        // Store Code
        $stmt_insert = $pdo->prepare("INSERT INTO tbl_password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt_insert->execute([$user_id, $reset_code, $expires_at]);

        // 4. CONSTRUCT EMAIL
        $subject = "Password Reset Code - LOPISv2";
        $body = "
            <h3>Password Reset Request</h3>
            <p>You requested to reset your password. Use the code below to proceed:</p>
            <h1 style='background-color: #f3f3f3; padding: 10px; display: inline-block; letter-spacing: 5px; font-family: monospace;'>{$reset_code}</h1>
            <p>This code will expire in 15 minutes.</p>
            <p>If you did not request this, please ignore this email.</p>
        ";
        
        // 5. SEND EMAIL
        $email_status = send_email($pdo, $user_email, $subject, $body, $body);

        // 6. PROCESS RESULT
        $pending_action = false;
        if ($email_status === 'sent') {
            $message = "A verification code has been sent to " . $user_email; // Helpful to show part of email
            $action_label = 'RESET_CODE_SENT';
            $log_detail = "OTP sent to " . $user_email;
        } elseif ($email_status === 'disabled') {
            $message = "User found, but email notifications are disabled. Contact Admin.";
            $action_label = 'RESET_CODE_QUEUED';
            $log_detail = "OTP Skipped (Disabled) for " . $user_email;
            $pending_action = true;
        } else {
            $message = "User found, but the system failed to send the email. Contact IT.";
            $action_label = 'RESET_CODE_ERROR';
            $log_detail = "OTP SMTP Failure for " . $user_email;
            $pending_action = true;
        }

        // Pending Log
        if ($pending_action) {
            $pdo->prepare("INSERT INTO tbl_pending_emails (user_id, token, reason) VALUES (?, ?, ?)")
                ->execute([$user_id, $reset_code, strtoupper($email_status)]);
        }
        
        // Audit Log
        logAudit($pdo, $user_id, $user_usertype, $action_label, $log_detail);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => $message, 'redirect' => 'process/verify_code.php']);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Forgot Password Error: ' . $e->getMessage()); 
        echo json_encode(['status' => 'error', 'message' => 'Server error occurred.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | LOPISv2</title>
    <link rel="icon" href="../assets/images/favicon.ico" type="image/ico">
    <link href="../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet"> 
    <link rel="stylesheet" href="../assets/vendor/fa6/css/all.min.css" />   
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">   
    <link rel="stylesheet" href="../assets/css/login_styles.css">
</head>
<body class="bg-light">

    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="card shadow-lg border-0" style="max-width: 450px; width: 100%;">
            <div class="card-body p-5">
                
                <div class="text-center mb-4">
                    <img src="../assets/images/LOPISv2.png" alt="Logo" class="img-fluid mb-3" style="max-height: 100px;">
                    <h5 class="fw-bold text-dark">Password Recovery</h5>
                    <p class="text-muted small">Enter your Employee ID or Email to receive a verification code.</p>
                </div>

                <form id="forgot-password-form">
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-user-circle text-secondary"></i></span>
                            <input type="text" class="form-control" id="login_id" name="login_id" placeholder="Employee ID or Email" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" id="btn-submit">
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            <i class="fa fa-paper-plane me-2"></i>Send Code
                        </button>
                        <a href="../index.php" class="btn btn-link text-decoration-none text-secondary small">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            $('#forgot-password-form').on('submit', function(e) {
                e.preventDefault(); 

                const submitButton = $('#btn-submit');
                const spinner = submitButton.find('.spinner-border');
                const icon = submitButton.find('.fa-paper-plane');
                const loginId = $('#login_id').val(); 

                $.ajax({
                    type: 'POST',
                    url: 'forgot_password.php',
                    data: $(this).serialize(),
                    dataType: 'json',
                    beforeSend: function() {
                        submitButton.prop('disabled', true);
                        spinner.removeClass('d-none');
                        icon.addClass('d-none');
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Code Sent',
                                text: response.message,
                                confirmButtonColor: '#0d6efd'
                            }).then(() => {
                                if(response.redirect) {
                                    sessionStorage.setItem('reset_email', loginId);
                                    window.location.href = response.redirect;
                                }
                            });
                        }
                    },
                    error: function(xhr) {
                        let msg = 'An unexpected error occurred.';
                        
                        // Capture specific error messages (404 Not Found, 403 Forbidden)
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }

                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Failed', 
                            text: msg 
                        });
                        
                        submitButton.prop('disabled', false);
                        spinner.addClass('d-none');
                        icon.removeClass('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html>