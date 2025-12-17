<?php
// reset_password.php

// TEMPORARY: Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- DATABASE CONNECTION ---
// Path is relative to process/reset_password.php
require __DIR__ .'/../db_connection.php'; 

// --- DYNAMIC TIMEZONE FETCH ---
try {
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

// --- FETCH SECURITY SETTINGS (PASSWORD POLICY) ---
$security_settings = [];
$min_password_length = 8; // Default fallback
try {
    $stmt_sec = $pdo->query("
        SELECT min_password_length, require_uppercase, require_numbers, require_special_chars 
        FROM tbl_security_settings WHERE id = 1
    ");
    $security_settings = $stmt_sec->fetch(PDO::FETCH_ASSOC);
    if ($security_settings) {
        $min_password_length = $security_settings['min_password_length'];
    }
} catch (PDOException $e) {
    error_log("Failed to fetch security settings: " . $e->getMessage());
}
// ----------------------------------------------------


$token = $_GET['token'] ?? null;
$error_message = null;
$user_id = null;


// =================================================================================
// PASSWORD POLICY VALIDATION FUNCTION
// =================================================================================
/**
 * Checks a password against the rules defined in tbl_security_settings.
 * @return array|null Error message array if validation fails, null on success.
 */
function validate_password_policy($password, $settings, $min_length) {
    if (strlen($password) < $min_length) {
        return ['status' => 'error', 'message' => "Password must be at least {$min_length} characters long."];
    }
    if ($settings['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        return ['status' => 'error', 'message' => 'Password must contain at least one uppercase letter.'];
    }
    if ($settings['require_numbers'] && !preg_match('/[0-9]/', $password)) {
        return ['status' => 'error', 'message' => 'Password must contain at least one number.'];
    }
    if ($settings['require_special_chars'] && !preg_match('/[^a-zA-Z0-9\s]/', $password)) {
        return ['status' => 'error', 'message' => 'Password must contain at least one special character.'];
    }
    return null; // Validation successful
}


// =================================================================================
// TOKEN VALIDATION (GET Request Handler)
// =================================================================================
if ($token && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // ... (Token validation logic remains the same) ...
    try {
        $sql = "SELECT user_id, expires_at FROM tbl_password_resets WHERE token = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$token]);
        $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset_record) {
            $expires_at = strtotime($reset_record['expires_at']);
            
            if ($expires_at > time()) {
                $user_id = $reset_record['user_id'];
            } else {
                $error_message = "This password reset link has expired. Please request a new one.";
                $pdo->prepare("DELETE FROM tbl_password_resets WHERE token = ?")->execute([$token]);
            }
        } else {
            $error_message = "Invalid or previously used reset token.";
        }
    } catch (PDOException $e) {
        $error_message = "A database error occurred during token validation.";
        error_log("DB Error in reset_password.php (GET): " . $e->getMessage());
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !$token) {
    $error_message = "No reset token provided. Please use the link sent to your email.";
}

// =================================================================================
// PASSWORD RESET SUBMISSION (AJAX/POST Request Handler)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $token_post = $_POST['token'] ?? '';
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 1. Initial validation
    if (empty($token_post) || empty($new_password) || empty($confirm_password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Token, Password, and Confirmation are required.']);
        exit;
    }
    if ($new_password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
        exit;
    }
    
    // 2. APPLY PASSWORD POLICY CHECK
    $policy_error = validate_password_policy($new_password, $security_settings, $min_password_length);
    if ($policy_error) {
        http_response_code(400);
        echo json_encode($policy_error);
        exit;
    }

    try {
        // 3. RE-VALIDATE TOKEN (Double-check token and get user_id)
        $sql = "SELECT user_id, expires_at FROM tbl_password_resets WHERE token = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$token_post]);
        $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset_record || strtotime($reset_record['expires_at']) <= time()) {
            http_response_code(403);
            $pdo->prepare("DELETE FROM tbl_password_resets WHERE token = ?")->execute([$token_post]);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired reset token. Please request a new link.']);
            exit;
        }

        $user_id_to_reset = $reset_record['user_id'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 4. UPDATE USER'S PASSWORD AND updated_at timestamp
        $sql_update = "UPDATE tbl_users SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$hashed_password, $user_id_to_reset]);

        if ($stmt_update->rowCount() > 0) {
            // 5. DELETE THE USED TOKEN
            $pdo->prepare("DELETE FROM tbl_password_resets WHERE user_id = ?")->execute([$user_id_to_reset]);
            
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Your password has been successfully reset.', 'redirect' => '../index.php']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Account not found or database error.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Database error during password reset submission: ' . $e->getMessage()); 
        echo json_encode(['status' => 'error', 'message' => 'A critical server error occurred.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
                    <h5 class="text-dark fw-bold">Password Reset</h5>
                </div>

                <?php if ($error_message): // Display error message if token validation failed ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <div class="mt-2"><a href="forgot_password.php" class="btn btn-sm btn-danger">Request New Link</a></div>
                    </div>
                    <div class="text-center mt-3"><a href="../index.php" class="btn btn-link text-secondary">Back to Login</a></div>

                <?php elseif ($user_id): // Display password form if token is valid ?>
                    <p class="text-muted small text-center mb-4">
                        Enter and confirm your new password below.
                        <br>
                        **Minimum Length:** <?php echo $min_password_length; ?> characters.
                    </p>
                    
                    <form id="reset-password-form">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label visually-hidden">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="New Password (min <?php echo $min_password_length; ?> chars)" required minlength="<?php echo $min_password_length; ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label visually-hidden">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required minlength="<?php echo $min_password_length; ?>">
                            </div>
                            <div id="password-match-warning" class="text-danger mt-1 d-none small fw-bold">
                                Passwords do not match.
                            </div>
                        </div>
                        
                        <div class="small text-muted mb-4 p-2 bg-light rounded" id="password-policy-hints">
                            <i class="fas fa-shield-alt me-1"></i> Policy Requirements:
                            <ul>
                                <li>Minimum Length: <strong class="text-dark"><?php echo $min_password_length; ?></strong> characters</li>
                                <?php if ($security_settings['require_uppercase']): ?>
                                <li>Requires at least one <strong>Uppercase</strong> letter (A-Z)</li>
                                <?php endif; ?>
                                <?php if ($security_settings['require_numbers']): ?>
                                <li>Requires at least one <strong>Number</strong> (0-9)</li>
                                <?php endif; ?>
                                <?php if ($security_settings['require_special_chars']): ?>
                                <li>Requires at least one <strong>Special Character</strong></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="btn-reset">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <i class="fa fa-sync-alt me-2"></i>
                                Reset Password
                            </button>
                        </div>
                    </form>

                <?php else: // Display error if no token was even provided in the URL ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>To reset your password, please start the process from the login page.
                    </div>
                    <div class="text-center mt-3"><a href="../index.php" class="btn btn-link text-secondary">Back to Login</a></div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            
            // --- INPUT VALIDATION HANDLER ---
            $('#confirm_password').on('keyup', function () {
                if ($('#password').val() !== $('#confirm_password').val()) {
                    $('#password-match-warning').removeClass('d-none');
                } else {
                    $('#password-match-warning').addClass('d-none');
                }
            });

            // --- FORM SUBMISSION ---
            $('#reset-password-form').submit(function(e) {
                e.preventDefault(); 

                if ($('#password').val() !== $('#confirm_password').val()) {
                    $('#password-match-warning').removeClass('d-none');
                    return;
                }
                
                var formData = $(this).serialize();
                var resetButton = $('#btn-reset');
                var spinner = resetButton.find('.spinner-border');
                var icon = resetButton.find('.fa-sync-alt');

                $.ajax({
                    type: 'POST',
                    url: 'reset_password.php', // Submit to the same file
                    data: formData,
                    dataType: 'json',
                    beforeSend: function() {
                        resetButton.prop('disabled', true);
                        spinner.removeClass('d-none');
                        icon.addClass('d-none');
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Password Updated!',
                                text: response.message,
                                confirmButtonText: 'Proceed to Login'
                            }).then(() => {
                                window.location.href = response.redirect;
                            });
                        } else {
                            // Display the specific policy error message from the PHP backend
                            Swal.fire({
                                icon: 'error',
                                title: 'Reset Failed',
                                text: response.message,
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // If the token was invalid/expired, reload to show the error state
                                if (jqXHR.status == 403) {
                                     window.location.reload(); 
                                }
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        let errorMessage = 'A network error occurred.';
                        
                        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message;
                        } else if (jqXHR.status == 500) {
                             errorMessage = 'Internal Server Error. Check server logs.';
                        } else if (jqXHR.status == 403) {
                            // Token invalid/expired check
                            errorMessage = jqXHR.responseJSON.message || 'The reset link is invalid or has expired.';
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: errorMessage,
                            confirmButtonText: 'OK'
                        }).then(() => {
                             if (jqXHR.status == 403) {
                                 // Force reload to move to the token invalid state
                                 window.location.reload(); 
                             }
                        });
                    },
                    complete: function() {
                        resetButton.prop('disabled', false);
                        spinner.addClass('d-none');
                        icon.removeClass('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html>