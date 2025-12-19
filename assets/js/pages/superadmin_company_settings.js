// assets/js/pages/company_settings.js

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

        $.post('../api/superadmin/company_settings_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                $('#company_name').val(d.company_name);
                $('#contact_number').val(d.contact_number);
                $('#email_address').val(d.email_address);
                $('#website').val(d.website);
                $('#company_address').val(d.company_address);
                
                // Update Logo Preview with Cache Busting
                if(d.logo_path) {
                    $('#logoPreview').attr('src', '../assets/images/' + d.logo_path + '?t=' + new Date().getTime());
                }

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
    // 3. IMAGE PREVIEW HANDLER (Local)
    // ==============================================================================
    $('#logoInput').change(function() {
        const file = this.files[0];
        if (file) {
            // Validation: Max 2MB
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire('File Too Large', 'Please select a logo smaller than 2MB.', 'warning');
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

        $.ajax({
            url: '../api/superadmin/company_settings_action.php',
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
                }
            },
            error: function() {
                Swal.fire('Error', 'An unexpected error occurred. Please try again.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
            }
        });
    });

});