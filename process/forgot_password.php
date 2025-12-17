<?php
// forgot_password.php

// TEMPORARY: Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- DATABASE CONNECTION & EMAIL HANDLER ---
// Path is relative to process/forgot_password.php
require __DIR__ .'/../db_connection.php'; 
// Include the centralized email file (assuming it's one level up)
require __DIR__ .'/../email_handler.php'; 

// --- DYNAMIC TIMEZONE FETCH ---
try {
    global $pdo; // Ensure $pdo is visible if accessed outside the main block
    $stmt_tz = $pdo->query("SELECT system_timezone FROM tbl_general_settings WHERE id = 1");
    $timezone = $stmt_tz->fetchColumn() ?? 'Asia/Manila';
    date_default_timezone_set($timezone); 
} catch (PDOException $e) {
    date_default_timezone_set('Asia/Manila');
}
// -----------------------------

// If the user is already logged in, redirect them away
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: ../index.php"); 
    exit;
}


// =================================================================================
// AJAX SUBMISSION HANDLER
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
        // 1. DETERMINE LOGIN FIELD
        $login_field_db = (preg_match('/^[0-9]{3}$/', $login_id)) ? 'employee_id' : 'email';

        // 2. FETCH USER DATA & EMAIL
        $sql = "SELECT id, usertype, email, status FROM tbl_users WHERE {$login_field_db} = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$login_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- Handle user not found/inactive (Log the attempt, but return generic message) ---
        if (!$user || $user['status'] != 1) {
            
            // Log the failed request attempt (Anonymous User)
            $log_details = "Failed reset request attempt for ID/Email: " . $login_id;
            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, usertype, action, details, ip_address, user_agent) VALUES (?, ?, 'PASSWORD_RESET', ?, ?, ?)")
                ->execute([
                    null, 
                    null, // user_id and usertype are NULL since the user is not authenticated/valid
                    $log_details, 
                    $_SERVER['REMOTE_ADDR'], 
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);
            
            // SECURITY NOTE: Return a generic success message to prevent user enumeration.
            http_response_code(200); 
            echo json_encode(['status' => 'success', 'message' => 'If an account matching the provided information was found, a password reset link has been sent.']);
            exit;
        }

        $user_id = $user['id'];
        $user_usertype = $user['usertype'];
        $user_email = $user['email'];

        // 3. GENERATE & STORE RESET TOKEN (Valid for 1 hour)
        $token = bin2hex(random_bytes(32)); 
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $pdo->prepare("DELETE FROM tbl_password_resets WHERE user_id = ?")->execute([$user_id]);

        $stmt_insert = $pdo->prepare("INSERT INTO tbl_password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt_insert->execute([$user_id, $token, $expires_at]);

        // 4. CONSTRUCT EMAIL CONTENT
        $reset_link = "http://localhost/LOPISv2/process/reset_password.php?token={$token}";
        $subject = "Password Reset Request";
        $body = "You have requested a password reset for your account. Click the link below to reset your password. This link will expire in 1 hour: \n\n" . $reset_link;
        
        // 5. SEND EMAIL and get status
        $email_status = send_email($pdo, $user_email, $subject, $body);

        // 6. Set message based on email status AND LOG TO PENDING TABLE IF NECESSARY
        if ($email_status === 'sent') {
            $message = "A password reset link has been successfully sent to your email.";
            $log_message = "Password reset token generated and email SENT to " . $user_email;
            $pending_action = false;
        } elseif ($email_status === 'disabled') {
            $message = "The password reset process has been queued, but email notifications are currently disabled by the system administrator. Please contact IT support for assistance.";
            $log_message = "Password reset token generated. Email SKIPPED (notifications disabled) for " . $user_email;
            $pending_action = true;
        } else { // 'failed'
            $message = "The password reset process has been queued, but the system encountered an error while sending the email. Please contact IT support.";
            $log_message = "Password reset token generated. Email FAILED to send to " . $user_email;
            $pending_action = true;
        }

        // --- LOGGING TO PENDING TABLE (for manual retry) ---
        if ($pending_action) {
            // Insert into the new pending table
            $pdo->prepare("INSERT INTO tbl_pending_emails (user_id, token, reason) VALUES (?, ?, ?)")
                ->execute([$user_id, $token, strtoupper($email_status)]);
        }
        // --- END LOGGING TO PENDING TABLE ---
        
        // 7. LOG THE SUCCESSFUL TOKEN GENERATION AND DELIVERY STATUS (Valid User)
        $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, usertype, action, details, ip_address, user_agent) VALUES (?, ?, 'PASSWORD_RESET', ?, ?, ?)")
            ->execute([
                $user_id, 
                $user_usertype, 
                $log_message, 
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

        // Final response is always 'success' (for security) with a tailored message
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => $message]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Database error during forgot password: ' . $e->getMessage()); 
        echo json_encode(['status' => 'error', 'message' => 'A server error occurred. Please try again.']);
    }
    exit;
}
// =================================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="icon" href="../assets/images/favicon.ico" type="image/ico">
    <link href="../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet"> 
    <link rel="stylesheet" href="../assets/vendor/fa6/css/all.min.css" />  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">   
    <link rel="stylesheet" href="../assets/css/login_styles.css">
    <style>
        body { background-color: #f0f2f5; }
        .card { max-width: 450px; }
    </style>
</head>
<body>

    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="card shadow-lg border-0">
            <div class="card-body p-5">
                
                <div class="text-center mb-4">
                    <img src="../assets/images/LOPISv2.png" alt="LOPISv2 Logo" class="img-fluid mb-3" style="max-height: 150px;">
                    <h5 class="text-dark fw-bold">Reset Your Password</h5>
                    <p class="text-muted small">Enter your Employee ID or Email address below to receive a password reset link.</p>
                </div>

                <form id="forgot-password-form">
                    
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                            <input type="text" class="form-control" id="login_id" name="login_id" placeholder="Employee ID or Email" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" id="btn-submit">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="fa fa-envelope me-2"></i>
                            Send Reset Link
                        </button>
                        <a href="../index.php" class="btn btn-link text-secondary">Back to Login</a>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // --- DEFINE TOAST MIXIN ---
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000, 
                timerProgressBar: true
            });

            // --- FORM SUBMISSION ---
            $('#forgot-password-form').submit(function(e) {
                e.preventDefault(); 

                var formData = $(this).serialize();
                var submitButton = $('#btn-submit');
                var spinner = submitButton.find('.spinner-border');
                var icon = submitButton.find('.fa-envelope');

                $.ajax({
                    type: 'POST',
                    url: 'forgot_password.php', // Submit to the same file
                    data: formData,
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
                                title: 'Request Processed',
                                text: response.message,
                                confirmButtonText: 'OK'
                            });
                        } else {
                            // Only trigger toast for specific internal errors
                            Toast.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        let errorMessage = 'A network error occurred. Please try again later.';
                        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message;
                        } else if (jqXHR.status == 500) {
                            errorMessage = 'Internal Server Error. Check server logs.';
                        }

                        Toast.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: errorMessage
                        });
                    },
                    complete: function() {
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