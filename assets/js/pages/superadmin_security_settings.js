// assets/js/pages/security_settings.js

$(document).ready(function() {

    // ==============================================================================
    // 1. UI HELPERS & SYNC STATUS
    // ==============================================================================
    function updateSyncStatus(state) {
        const $dot = $('.live-dot');
        const $text = $('#last-updated-time');
        const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

        $dot.removeClass('text-success text-warning text-danger');

        if (state === 'loading') {
            $text.text('Syncing...');
            $dot.addClass('text-warning'); 
        } 
        else if (state === 'success') {
            $text.text(`Synced: ${time}`);
            $dot.addClass('text-success'); 
        } 
        else {
            $text.text(`Failed: ${time}`);
            $dot.addClass('text-danger');  
        }
    }

    // ==============================================================================
    // 2. LOAD SECURITY SETTINGS
    // ==============================================================================
    function loadSettings() {
        updateSyncStatus('loading');

        $.post('../api/superadmin/security_settings_action.php', { action: 'get_details' }, function(res) {
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
                updateSyncStatus('success');
            } else {
                updateSyncStatus('error');
            }
        }, 'json').fail(function() {
            updateSyncStatus('error');
        });
    }

    // Initial Load
    loadSettings();

    // Hook for Master Refresher (Topbar Sync Icon)
    window.refreshPageContent = function(isManual = false) {
        if(isManual) $('#refreshIcon').addClass('fa-spin');
        loadSettings();
        if(isManual) setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
    };

    // ==============================================================================
    // 3. SAVE SECURITY SETTINGS
    // ==============================================================================
    $('#securityForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        // Explicitly handle checkboxes so unchecked ones are sent as 0
        let formData = $(this).serializeArray();
        
        // Find all checkboxes in the form
        $(this).find('input[type="checkbox"]').each(function() {
            // Check if this checkbox is already in the serialized array
            let exists = formData.some(item => item.name === this.name);
            if (!exists) {
                formData.push({ name: this.name, value: 0 });
            }
        });

        // Add action
        formData.push({ name: 'action', value: 'update' });

        $.ajax({
            url: '../api/superadmin/security_settings_action.php',
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