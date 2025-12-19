// assets/js/pages/financial_settings.js

$(document).ready(function() {

    // ==============================================================================
    // 1. UI HELPERS
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
    // 2. FETCH CURRENT DATA ON LOAD
    // ==============================================================================
    function loadDetails() {
        updateSyncStatus('loading');

        $.post('../api/superadmin/financial_settings_action.php', { action: 'get_details' }, function(res) {
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
                updateSyncStatus('success');
            } else {
                updateSyncStatus('error');
            }
        }, 'json').fail(function() {
            updateSyncStatus('error');
        });
    }

    // Initial Load
    loadDetails();

    // Hook for Master Refresher
    window.refreshPageContent = function(isManual = false) {
        if(isManual) $('#refreshIcon').addClass('fa-spin');
        loadDetails();
        if(isManual) setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
    };

    // ==============================================================================
    // 3. FORM SUBMISSION
    // ==============================================================================
    $('#financialForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        let formData = $(this).serialize() + '&action=update';

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        $.ajax({
            url: '../api/superadmin/financial_settings_action.php',
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
                    loadDetails(); // Refresh to update timestamp
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
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