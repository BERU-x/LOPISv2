<?php
// process/reset_password.php

// DEBUGGING
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
// ACCESS CHECK (Security Gate)
// =================================================================================
// We only allow access if the user successfully passed verify_code.php
if (!isset($_SESSION['allow_password_reset']) || $_SESSION['allow_password_reset'] !== true || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

$user_id = $_SESSION['reset_user_id'];

// --- FETCH SECURITY SETTINGS (PASSWORD POLICY) ---
$min_password_length = 8;
$security_settings = [];
try {
    $stmt_sec = $pdo->query("SELECT min_password_length, require_uppercase, require_numbers, require_special_chars FROM tbl_security_settings WHERE id = 1");
    $security_settings = $stmt_sec->fetch(PDO::FETCH_ASSOC);
    if ($security_settings) {
        $min_password_length = $security_settings['min_password_length'];
    }
} catch (PDOException $e) {
    error_log("Failed to fetch security settings: " . $e->getMessage());
}

// =================================================================================
// PASSWORD VALIDATION HELPER
// =================================================================================
function validate_password_policy($password, $settings, $min_length) {
    if (strlen($password) < $min_length) {
        return "Password must be at least {$min_length} characters long.";
    }
    if ($settings['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if ($settings['require_numbers'] && !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }
    if ($settings['require_special_chars'] && !preg_match('/[^a-zA-Z0-9\s]/', $password)) {
        return 'Password must contain at least one special character.';
    }
    return null; 
}

// =================================================================================
// AJAX HANDLER: RESET PASSWORD
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $new_password     = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 1. Basic Match Check
    if (empty($new_password) || empty($confirm_password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }
    if ($new_password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
        exit;
    }

    // 2. Policy Check
    $policy_error = validate_password_policy($new_password, $security_settings, $min_password_length);
    if ($policy_error) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $policy_error]);
        exit;
    }

    try {
        // 3. Update Database
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt_update = $pdo->prepare("UPDATE tbl_users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt_update->execute([$hashed_password, $user_id]);

        if ($stmt_update->rowCount() > 0) {
            
            // 4. Cleanup & Audit
            // Remove the reset session flags so they can't use back button to reset again
            unset($_SESSION['allow_password_reset']);
            unset($_SESSION['reset_user_id']);
            
            // Clear the used token from DB
            $pdo->prepare("DELETE FROM tbl_password_resets WHERE user_id = ?")->execute([$user_id]);

            // Get user role for audit
            $stmt_role = $pdo->prepare("SELECT usertype FROM tbl_users WHERE id = ?");
            $stmt_role->execute([$user_id]);
            $role = $stmt_role->fetchColumn();

            // ⭐ AUDIT LOG: PASSWORD CHANGED
            logAudit($pdo, $user_id, $role, 'PASSWORD_RESET_SUCCESS', "User successfully reset their password via OTP flow.");

            echo json_encode(['status' => 'success', 'message' => 'Your password has been reset successfully.', 'redirect' => '../index.php']);
        } else {
            // This might happen if they submitted the same password as before, but generally safer to say success or generic error
            echo json_encode(['status' => 'success', 'message' => 'Password updated.', 'redirect' => '../index.php']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Reset Password Error: " . $e->getMessage());
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
    <title>Set New Password | LOPISv2</title>
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
                    <img src="../assets/images/LOPISv2.png" alt="Logo" class="img-fluid mb-3" style="max-height: 80px;">
                    <h5 class="fw-bold text-dark">Set New Password</h5>
                    <p class="text-muted small">Please create a strong password for your account.</p>
                </div>

                <form id="reset-password-form">
                    
                    <div class="mb-3">
                        <label for="password" class="form-label small fw-bold text-secondary">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-lock text-secondary"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required minlength="<?php echo $min_password_length; ?>">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label small fw-bold text-secondary">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-lock text-secondary"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="<?php echo $min_password_length; ?>">
                        </div>
                        <div id="password-match-warning" class="text-danger mt-1 d-none small fw-bold">
                            <i class="fas fa-times-circle me-1"></i> Passwords do not match.
                        </div>
                    </div>
                    
                    <div class="small text-muted mb-4 p-3 bg-white border rounded">
                        <h6 class="text-dark small fw-bold mb-2"><i class="fas fa-shield-alt text-primary me-1"></i> Requirements:</h6>
                        <ul class="mb-0 ps-3">
                            <li>At least <strong><?php echo $min_password_length; ?></strong> characters</li>
                            <?php if ($security_settings['require_uppercase']): ?>
                                <li>One <strong>Uppercase</strong> letter (A-Z)</li>
                            <?php endif; ?>
                            <?php if ($security_settings['require_numbers']): ?>
                                <li>One <strong>Number</strong> (0-9)</li>
                            <?php endif; ?>
                            <?php if ($security_settings['require_special_chars']): ?>
                                <li>One <strong>Special Character</strong></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" id="btn-reset">
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            Update Password
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            
            // Toggle Password Visibility
            $('#togglePassword').on('click', function() {
                const passInput = $('#password');
                const type = passInput.attr('type') === 'password' ? 'text' : 'password';
                passInput.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            // Live Match Check
            $('#confirm_password').on('keyup', function () {
                if ($('#password').val() !== $('#confirm_password').val()) {
                    $('#password-match-warning').removeClass('d-none');
                } else {
                    $('#password-match-warning').addClass('d-none');
                }
            });

            // Form Submit
            $('#reset-password-form').on('submit', function(e) {
                e.preventDefault(); 

                if ($('#password').val() !== $('#confirm_password').val()) {
                    $('#password-match-warning').removeClass('d-none');
                    return;
                }

                const btn = $('#btn-reset');
                const spinner = btn.find('.spinner-border');

                $.ajax({
                    type: 'POST',
                    url: 'reset_password.php',
                    data: $(this).serialize(),
                    dataType: 'json',
                    beforeSend: function() {
                        btn.prop('disabled', true);
                        spinner.removeClass('d-none');
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message,
                                confirmButtonText: 'Login Now',
                                confirmButtonColor: '#0d6efd',
                                allowOutsideClick: false
                            }).then(() => {
                                window.location.href = response.redirect;
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: response.message
                            });
                            btn.prop('disabled', false);
                            spinner.addClass('d-none');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'An unexpected error occurred.';
                        if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                        
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html>