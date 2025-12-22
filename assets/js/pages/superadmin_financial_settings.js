/**
 * Financial Settings Controller
 * Handles currency configurations and fiscal year settings.
 * Integrated with Global AppUtility for Topbar syncing.
 */

$(document).ready(function() {

    // ==============================================================================
    // 1. DATA FETCHER
    // ==============================================================================
    function loadDetails() {
        // Trigger visual loading via Global Utility
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.post(API_ROOT + '/superadmin/financial_settings_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                $('#currency_code').val(d.currency_code);
                $('#currency_symbol').val(d.currency_symbol);
                $('#fiscal_year_start_month').val(d.fiscal_year_start_month);
                $('#fiscal_year_start_day').val(d.fiscal_year_start_day);

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Updated: ' + new Date(d.updated_at).toLocaleDateString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric'
                    }));
                }
                
                // Sync Success
                if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            } else {
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        }, 'json').fail(function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
        });
    }

    // Initial Load
    loadDetails();

    // ==============================================================================
    // 2. MASTER REFRESHER HOOK
    // ==============================================================================
    window.refreshPageContent = function(isManual = false) {
        // AppUtility handles the loading state; loadDetails handles the success/error
        loadDetails();
    };

    // ==============================================================================
    // 3. FORM SUBMISSION
    // ==============================================================================
    $('#financialForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        let formData = $(this).serialize() + '&action=update';

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');
        
        // Notify Topbar of activity
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.ajax({
            url: API_ROOT + '/superadmin/financial_settings_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Settings Saved',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    loadDetails(); // Refresh to update timestamp and topbar
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
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Configuration');
            }
        });
    });

    // ==============================================================================
    // 4. INPUT VALIDATION
    // ==============================================================================
    
    // Force Uppercase for Currency Code (e.g., PHP, USD)
    $('#currency_code').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });

});