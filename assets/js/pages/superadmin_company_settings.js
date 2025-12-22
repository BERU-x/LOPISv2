/**
 * Company Settings Controller
 * Handles fetching and updating general company information and branding.
 * Integrated with Global AppUtility for Topbar syncing.
 */

$(document).ready(function() {

    // ==============================================================================
    // 1. DATA FETCHER
    // ==============================================================================
    function loadDetails() {
        // Trigger visual loading via Global Utility
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.post(API_ROOT + '/superadmin/company_settings_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                $('#company_name').val(d.company_name);
                $('#contact_number').val(d.contact_number);
                $('#email_address').val(d.email_address);
                $('#website').val(d.website);
                $('#company_address').val(d.company_address);
                
                // Update Logo Preview with Cache Busting
                if(d.logo_path) {
                    // Assuming logo is stored in assets/images relative to web_root
                    $('#logoPreview').attr('src', '../assets/images/' + d.logo_path + '?t=' + new Date().getTime());
                }

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Updated: ' + new Date(d.updated_at).toLocaleDateString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric'
                    }));
                }
                
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
    // 3. IMAGE PREVIEW HANDLER
    // ==============================================================================
    $('#logoInput').change(function() {
        const file = this.files[0];
        if (file) {
            // Validation: Max 2MB
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: 'Please select a logo smaller than 2MB.'
                });
                $(this).val('');
                return;
            }

            let reader = new FileReader();
            reader.onload = function(event) {
                $('#logoPreview').attr('src', event.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    // ==============================================================================
    // 4. FORM SUBMISSION (AJAX with FormData)
    // ==============================================================================
    $('#companyForm').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        formData.append('action', 'update');

        // Check if a file is actually selected
        let fileInput = $('#logoInput')[0].files[0];
        if(fileInput) {
            formData.append('company_logo', fileInput);
        }

        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.ajax({
            url: API_ROOT + '/superadmin/company_settings_action.php',
            type: 'POST',
            data: formData,
            contentType: false, 
            processData: false, 
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Settings Updated',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    loadDetails(); // Refresh to update preview and timestamp
                } else {
                    Swal.fire('Error', res.message, 'error');
                    if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
                }
            },
            error: function() {
                Swal.fire('Error', 'An unexpected error occurred. Please try again.', 'error');
                if (window.AppUtility) window.AppUtility.updateSyncStatus('error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
            }
        });
    });

});