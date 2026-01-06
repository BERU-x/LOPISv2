// scripts/login_scripts.js

$(document).ready(function() {
            
    // --- DEFINE TOAST MIXIN (SweetAlert Toastr Style) ---
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

    // --- LOGIN FORM SUBMISSION (AJAX) ---
    $('#login-form').submit(function(e) {
        e.preventDefault(); 

        var formData = $(this).serialize();
        var loginButton = $('#btn-login');
        var spinner = loginButton.find('.spinner-border');
        var icon = loginButton.find('.fa-sign-in-alt');

        $.ajax({
            type: 'POST',
            // CRUCIAL: Pointing to the dedicated backend script
            url: 'api/login_action.php', 
            data: formData,
            dataType: 'json',
            beforeSend: function() {
                // Disable button and show spinner before sending request
                loginButton.prop('disabled', true);
                spinner.removeClass('d-none');
                icon.addClass('d-none');
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Success notification and redirection
                    Toast.fire({
                        icon: 'success',
                        title: response.message
                    }).then(() => {
                        window.location.href = response.redirect;
                    });
                } else {
                    // Error notification (e.g., Invalid credentials, Lockout message)
                    Toast.fire({
                        icon: 'error',
                        title: response.message
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let errorMessage = 'Network Error: Could not connect to the server.';
                
                // Handle HTTP Status Codes
                if (jqXHR.status == 503) { 
                    // Server has explicitly stated maintenance mode is active
                    errorMessage = 'The system is currently undergoing maintenance. Access is restricted.';
                    // Force reload to trigger the static maintenance page in index.php
                    setTimeout(() => { window.location.reload(true); }, 1500); 
                } else if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    // Handle JSON error message from the PHP catch block (e.g., DB Error)
                    errorMessage = jqXHR.responseJSON.message;
                } else if (jqXHR.status == 404) {
                    errorMessage = 'Error: Target script not found (404).';
                } else if (jqXHR.status == 500) {
                    errorMessage = 'Internal Server Error. Please contact admin.';
                } else if (jqXHR.status == 400) {
                     // This often happens if the form sends empty data unexpectedly
                    errorMessage = 'Login ID and password are required.';
                }

                Toast.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    text: errorMessage
                });
            },
            complete: function() {
                // Re-enable button and hide spinner regardless of success/error
                loginButton.prop('disabled', false);
                spinner.addClass('d-none');
                icon.removeClass('d-none');
            }
        });
    });
    
    // --- Caps Lock Detection ---
    $('#password').on('keyup keydown click focus', function(e) {
        
        // Reliable check for Caps Lock state using the native event object
        if (e.originalEvent && typeof e.originalEvent.getModifierState === 'function') {
            
            if (e.originalEvent.getModifierState('CapsLock')) {
                $('#caps-warning').removeClass('d-none');
            } else {
                $('#caps-warning').addClass('d-none');
            }
        }
    });

    // Hide warning when user clicks away
    $('#password').on('blur', function() {
        $('#caps-warning').addClass('d-none');
    });
});