<?php
// process/verify_code.php

// TEMPORARY DEBUGGING
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// --- DEPENDENCIES ---
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/../helpers/audit_helper.php';

// --- TIMEZONE ---
try {
    $stmt_tz = $pdo->query("SELECT system_timezone FROM tbl_general_settings WHERE id = 1");
    $timezone = $stmt_tz->fetchColumn() ?? 'Asia/Manila';
    date_default_timezone_set($timezone); 
} catch (PDOException $e) {
    date_default_timezone_set('Asia/Manila');
}

// =================================================================================
// AJAX HANDLER: VERIFY CODE
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $login_id = $_POST['login_id'] ?? '';
    $code     = $_POST['otp_code'] ?? '';

    if (empty($login_id) || empty($code)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing Email/ID or Verification Code.']);
        exit;
    }

    try {
        // 1. GET USER ID
        $login_field = (preg_match('/^[0-9]{3}$/', $login_id)) ? 'employee_id' : 'email';
        $stmt_user = $pdo->prepare("SELECT id, usertype, email FROM tbl_users WHERE {$login_field} = ?");
        $stmt_user->execute([$login_id]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Log generic failure to avoid enumeration info leak (though unlikely at this stage)
            logAudit($pdo, null, null, 'OTP_VERIFY_FAILED', "Attempt for non-existent user: $login_id");
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid Request.']);
            exit;
        }

        // 2. CHECK TOKEN IN DATABASE
        $stmt_check = $pdo->prepare("
            SELECT * FROM tbl_password_resets 
            WHERE user_id = ? AND token = ?
        ");
        $stmt_check->execute([$user['id'], $code]);
        $reset_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

        // 3. VALIDATE MATCH AND EXPIRY
        if ($reset_record) {
            $expiry = strtotime($reset_record['expires_at']);
            
            if (time() > $expiry) {
                // EXPIRED
                logAudit($pdo, $user['id'], $user['usertype'], 'OTP_EXPIRED', "Expired code attempted.");
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'This code has expired. Please request a new one.']);
                exit;
            } else {
                // VALID
                
                // Set Session flags to allow access to the reset_password page
                $_SESSION['allow_password_reset'] = true;
                $_SESSION['reset_user_id'] = $user['id'];
                
                // Log Success
                logAudit($pdo, $user['id'], $user['usertype'], 'OTP_VERIFIED', "Identity verified via OTP.");

                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Code verified! Redirecting...',
                    'redirect' => 'reset_password.php' // Next Page
                ]);
                exit;
            }
        } else {
            // INVALID CODE
            logAudit($pdo, $user['id'], $user['usertype'], 'OTP_INVALID', "Wrong code entered.");
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid verification code. Please try again.']);
            exit;
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("OTP Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Server error occurred.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code | LOPISv2</title>
    <link rel="icon" href="../assets/images/favicon.ico" type="image/ico">
    <link href="../assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet"> 
    <link rel="stylesheet" href="../assets/vendor/fa6/css/all.min.css" />   
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">   
    <link rel="stylesheet" href="../assets/css/login_styles.css">
    <style>
        /* Custom OTP Input Styling */
        .otp-input {
            letter-spacing: 0.5em;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
            font-family: monospace;
        }
    </style>
</head>
<body class="bg-light">

    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="card shadow-lg border-0" style="max-width: 450px; width: 100%;">
            <div class="card-body p-5">
                
                <div class="text-center mb-4">
                    <img src="../assets/images/LOPISv2.png" alt="Logo" class="img-fluid mb-3" style="max-height: 80px;">
                    <h5 class="fw-bold text-dark">Enter Verification Code</h5>
                    <p class="text-muted small">We sent a 6-digit code to your email associated with <span id="display-email" class="fw-bold text-primary">your account</span>.</p>
                </div>

                <form id="verify-code-form">
                    
                    <input type="hidden" id="login_id" name="login_id">

                    <div class="mb-4">
                        <label for="otp_code" class="form-label small text-secondary">6-Digit Code</label>
                        <input type="text" class="form-control form-control-lg otp-input" id="otp_code" name="otp_code" maxlength="6" placeholder="000000" required autofocus autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" id="btn-verify">
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            Verify Code
                        </button>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">Didn't receive code?</p>
                    <a href="forgot_password.php" class="text-decoration-none small fw-bold">Try Again / Resend</a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // 1. Retrieve Email/ID from SessionStorage
            const storedEmail = sessionStorage.getItem('reset_email');
            
            if (storedEmail) {
                $('#login_id').val(storedEmail);
                $('#display-email').text(storedEmail);
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Session Expired',
                    text: 'Please request a new code.',
                    confirmButtonText: 'Go Back'
                }).then(() => {
                    window.location.href = 'forgot_password.php';
                });
            }

            // 2. Handle Form Submission
            $('#verify-code-form').on('submit', function(e) {
                e.preventDefault(); 

                const btn = $('#btn-verify');
                const spinner = btn.find('.spinner-border');

                $.ajax({
                    type: 'POST',
                    url: 'verify_code.php',
                    data: $(this).serialize(),
                    dataType: 'json',
                    beforeSend: function() {
                        btn.prop('disabled', true);
                        spinner.removeClass('d-none');
                    },
                    success: function(response) {
                        // This block runs only if HTTP Status is 200
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Verified',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = response.redirect;
                            });
                        }
                    },
                    error: function(xhr) {
                        // This block runs if HTTP Status is 400, 401, 403, 500, etc.
                        let msg = 'An unexpected error occurred.';
                        
                        // Check if the server sent a specific JSON message (like "Code expired")
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Verification Failed',
                            text: msg
                        });

                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html>