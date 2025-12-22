/**
 * General Settings Controller
 * Handles System Parameters (Timezone, Timeout) and SMTP/Email configurations.
 * Integrated with Global AppUtility for Topbar syncing.
 */

// Toggle Password Visibility (Global scope for button onclick)
function togglePass() {
    let input = document.getElementById('smtp_password');
    let icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

$(document).ready(function() {

    // ==============================================================================
    // 1. UI HELPERS
    // ==============================================================================
    
    // Helper: Enable/Disable SMTP fields based on master toggle
    function toggleSmtpInputs(isEnabled) {
        $('#smtp_settings_wrapper input').prop('disabled', !isEnabled);
        if(!isEnabled) {
            $('#smtp_settings_wrapper').addClass('opacity-50');
        } else {
            $('#smtp_settings_wrapper').removeClass('opacity-50');
        }
    }

    // Listener for Toggle Change
    $('#enable_email_notifications').change(function() {
        toggleSmtpInputs($(this).is(':checked'));
    });

    // ==============================================================================
    // 2. LOAD SYSTEM SETTINGS
    // ==============================================================================
    function loadSettings() {
        // Trigger visual loading via Global Utility
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.post(API_ROOT + '/superadmin/general_settings_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                
                // System Parameters
                $('#system_timezone').val(d.system_timezone);
                $('#session_timeout_minutes').val(d.session_timeout_minutes);
                
                // Switches
                $('#maintenance_mode').prop('checked', d.maintenance_mode == 1);
                $('#allow_forgot_password').prop('checked', d.allow_forgot_password == 1);
                $('#enable_email_notifications').prop('checked', d.enable_email_notifications == 1);

                // SMTP Fields
                $('#smtp_host').val(d.smtp_host);
                $('#smtp_port').val(d.smtp_port);
                $('#smtp_username').val(d.smtp_username);
                $('#email_sender_name').val(d.email_sender_name);
                $('#smtp_password').val(''); // Keep empty for security

                // Apply UI Logic for SMTP
                toggleSmtpInputs(d.enable_email_notifications == 1);

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Updated: ' + new Date(d.updated_at).toLocaleString());
                }

                // Notify Global AppUtility of success
                if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            } else {
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        }, 'json').fail(function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
        });
    }

    // Initial Load
    loadSettings();

    // Hook for Master Refresher (Topbar Sync Icon)
    window.refreshPageContent = function(isManual = false) {
        // AppUtility handles the loading state; loadSettings handles success/error
        loadSettings();
    };

    // ==============================================================================
    // 3. SAVE SYSTEM SETTINGS
    // ==============================================================================
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        
        let formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'update'});
        
        // Ensure checkboxes send 0 if unchecked
        const toggles = ['maintenance_mode', 'allow_forgot_password', 'enable_email_notifications'];
        toggles.forEach(id => {
            if(!$(`#${id}`).is(':checked')) {
                formData.push({name: id, value: 0});
            } else {
                formData = formData.filter(item => item.name !== id);
                formData.push({name: id, value: 1});
            }
        });

        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        // Notify Topbar of activity
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.ajax({
            url: API_ROOT + '/superadmin/general_settings_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Configuration Saved!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadSettings(); // Refresh to update timestamps and topbar
                } else {
                    Swal.fire('Error', res.message, 'error');
                    if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Configuration');
            }
        });
    });

});