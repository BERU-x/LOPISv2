/**
 * Security Settings Controller
 * Handles password complexity rules and account lockout policies.
 * Integrated with Global AppUtility for Topbar syncing.
 */

$(document).ready(function() {

    // ==============================================================================
    // 1. DATA FETCHER
    // ==============================================================================
    function loadSettings() {
        // Trigger visual loading via Global Utility
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.post(API_ROOT + '/superadmin/security_settings_action.php', { action: 'get_details' }, function(res) {
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

    // ==============================================================================
    // 2. MASTER REFRESHER HOOK
    // ==============================================================================
    window.refreshPageContent = function(isManual = false) {
        // AppUtility handles the loading state; loadSettings handles success/error
        loadSettings();
    };

    // ==============================================================================
    // 3. SAVE SECURITY SETTINGS
    // ==============================================================================
    $('#securityForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        // Notify Topbar of activity
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        // Explicitly handle checkboxes so unchecked ones are sent as 0
        let formData = $(this).serializeArray();
        
        $(this).find('input[type="checkbox"]').each(function() {
            let exists = formData.some(item => item.name === this.name);
            if (!exists) {
                formData.push({ name: this.name, value: 0 });
            }
        });

        formData.push({ name: 'action', value: 'update' });

        $.ajax({
            url: API_ROOT + '/superadmin/security_settings_action.php',
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
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Security Rules');
            }
        });
    });

});