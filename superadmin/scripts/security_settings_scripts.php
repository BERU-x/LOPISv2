
<script>
$(document).ready(function() {

    // 1. LOAD SETTINGS
    function loadSettings() {
        $.post('api/security_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                
                // Password Policies
                $('#min_password_length').val(d.min_password_length);
                $('#password_expiry_days').val(d.password_expiry_days);
                
                // Checkboxes (Boolean logic)
                $('#require_uppercase').prop('checked', d.require_uppercase == 1);
                $('#require_numbers').prop('checked', d.require_numbers == 1);
                $('#require_special_chars').prop('checked', d.require_special_chars == 1);

                // Access Control
                $('#max_login_attempts').val(d.max_login_attempts);
                $('#lockout_duration_mins').val(d.lockout_duration_mins);

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Updated: ' + new Date(d.updated_at).toLocaleString());
                }
            }
        }, 'json');
    }
    loadSettings();

    // 2. SAVE SETTINGS
    $('#securityForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        // Serialize data (checkboxes handled in PHP via intval/isset)
        let formData = $(this).serialize();

        $.ajax({
            url: 'api/security_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Security Updated!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadSettings();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Security Rules');
            }
        });
    });

});
</script>