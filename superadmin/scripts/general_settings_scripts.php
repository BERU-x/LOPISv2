<script>
// Toggle Password Visibility
function togglePass() {
    let input = document.getElementById('smtp_password');
    input.type = input.type === 'password' ? 'text' : 'password';
}

$(document).ready(function() {

    // 1. LOAD DATA
    function loadSettings() {
        $.post('api/general_settings_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                
                // System Params
                $('#system_timezone').val(d.system_timezone);
                $('#session_timeout_minutes').val(d.session_timeout_minutes);
                
                // Switches (Check if value is 1)
                $('#maintenance_mode').prop('checked', d.maintenance_mode == 1);
                $('#allow_forgot_password').prop('checked', d.allow_forgot_password == 1);
                $('#enable_email_notifications').prop('checked', d.enable_email_notifications == 1);

                // SMTP (Password is not returned for security)
                $('#smtp_host').val(d.smtp_host);
                $('#smtp_port').val(d.smtp_port);
                $('#smtp_username').val(d.smtp_username);
                $('#email_sender_name').val(d.email_sender_name);

                // UI Logic: Disable inputs if notifications disabled
                toggleSmtpInputs(d.enable_email_notifications == 1);

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Updated: ' + new Date(d.updated_at).toLocaleString());
                }
            }
        }, 'json');
    }
    loadSettings();

    // Helper: Enable/Disable SMTP fields based on master toggle
    function toggleSmtpInputs(isEnabled) {
        $('#smtp_settings_wrapper input').prop('disabled', !isEnabled);
        if(!isEnabled) {
            $('#smtp_settings_wrapper').addClass('opacity-50');
        } else {
            $('#smtp_settings_wrapper').removeClass('opacity-50');
        }
    }

    // Listener for Toggle
    $('#enable_email_notifications').change(function() {
        toggleSmtpInputs($(this).is(':checked'));
    });

    // 2. SAVE SETTINGS
    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        
        // Handle unchecked checkboxes (they don't send data naturally)
        let formData = $(this).serializeArray();
        
        // Manual check for switches (if not in array, add as 0)
        if(!$('#maintenance_mode').is(':checked')) formData.push({name: 'maintenance_mode', value: 0});
        if(!$('#allow_forgot_password').is(':checked')) formData.push({name: 'allow_forgot_password', value: 0});
        if(!$('#enable_email_notifications').is(':checked')) formData.push({name: 'enable_email_notifications', value: 0});

        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        $.ajax({
            url: 'api/general_settings_action.php',
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
                    loadSettings(); // Refresh to ensure UI matches DB state
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Configuration');
            }
        });
    });

});
</script>