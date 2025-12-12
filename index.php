<?php
session_start();

// If the user is already logged in, redirect them to their dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // --- FIX: Check if redirect URL is already in session ---
    if (isset($_SESSION['usertype'])) {
        $redirect_url = 'user/dashboard.php'; // Default for user (2)
        if ($_SESSION['usertype'] == 0) {
            $redirect_url = 'superadmin/dashboard.php';
        } elseif ($_SESSION['usertype'] == 1) {
            $redirect_url = 'admin/dashboard.php';
        }
        header("Location: $redirect_url");
    } else {
        // Fallback if usertype isn't set for some reason
        header("Location: dashboard.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOPISv2 - Login</title>

    <link rel="icon" href="assets/images/favicon.ico" type="image/ico">
    <link href="assets/vendor/bs5/css/bootstrap.min.css" rel="stylesheet">  
    <link rel="stylesheet" href="assets/vendor/fa6/css/all.min.css" />   
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">    
    <link rel="stylesheet" href="assets/css/login_styles.css">

    <style>
        body {
            background-color: #f0f2f5;
        }
    </style>
</head>
<body>

    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="col-lg-4 col-md-6 col-sm-10">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    
                    <div class="text-center mb-4">
                        <img src="assets/images/LOPISv2.png" alt="LOPISv2 Logo" class="img-fluid" style="max-height: 300px;">
                        <p class="text-muted mt-3">Sign in to your account</p>
                    </div>

                    <form id="login-form">
                        
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user-circle"></i> 
                                </span>
                                <input type="text" class="form-control" id="login_id" name="login_id" placeholder="Email or Employee ID" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                
                                <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="caps-warning" class="text-danger mt-1 d-none small fw-bold">
                                <i class="fas fa-exclamation-triangle me-1"></i> Caps Lock is ON
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Keep me Signed In</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="btn-login">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <i class="fa fa-sign-in-alt me-2"></i>
                                Sign In
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
        $(document).ready(function() {
            
            // --- DEFINE TOAST MIXIN (Toastr Style) ---
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end', // Upper right corner
                showConfirmButton: false,
                timer: 1500, // Disappears after 1.5 seconds
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                },
                customClass: {
                    popup: 'swal2-toast-popup' 
                }
            });

            // --- Password Toggle Visibility Handler ---
            $('#toggle-password').click(function() {
                var passwordField = $('#password');
                var fieldType = passwordField.attr('type');
                var icon = $(this).find('i');

                if (fieldType === 'password') {
                    passwordField.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            $('#login-form').submit(function(e) {
                e.preventDefault(); // Stop default form submission

                var formData = $(this).serialize();
                var loginButton = $('#btn-login');
                var spinner = loginButton.find('.spinner-border');
                var icon = loginButton.find('.fa-sign-in-alt');

                $.ajax({
                    type: 'POST',
                    url: 'process/p_login.php', 
                    data: formData,
                    dataType: 'json',
                    beforeSend: function() {
                        loginButton.prop('disabled', true);
                        spinner.removeClass('d-none');
                        icon.addClass('d-none');
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Toast.fire({
                                icon: 'success',
                                title: response.message
                            }).then(() => {
                                window.location.href = response.redirect;
                            });
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        let errorMessage = 'Network Error: Could not connect to the server.';
                        
                        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message;
                        } else if (jqXHR.status == 404) {
                            errorMessage = 'Error: The login script (p_login.php) was not found.';
                        } else if (jqXHR.status == 500) {
                            errorMessage = 'Internal Server Error. Please contact admin.';
                        }

                        Toast.fire({
                            icon: 'error',
                            title: 'Login Failed',
                            text: errorMessage
                        });
                    },
                    complete: function() {
                        loginButton.prop('disabled', false);
                        spinner.addClass('d-none');
                        icon.removeClass('d-none');
                    }
                });
            });
            // --- Caps Lock Detection ---
            $('#password').on('keyup keydown click focus', function(e) {
                // getModifierState is a native DOM method, so we use e.originalEvent
                if (e.originalEvent.getModifierState('CapsLock')) {
                    $('#caps-warning').removeClass('d-none');
                } else {
                    $('#caps-warning').addClass('d-none');
                }
            });

            // Optional: Hide warning when user clicks away from the password field
            $('#password').on('blur', function() {
                $('#caps-warning').addClass('d-none');
            });
        });
    </script>
</body>
</html>