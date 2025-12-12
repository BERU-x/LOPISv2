<script>
$(document).ready(function() {

    // 1. FETCH CURRENT DATA ON LOAD
    function loadDetails() {
        $.post('api/company_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                $('#company_name').val(d.company_name);
                $('#contact_number').val(d.contact_number);
                $('#email_address').val(d.email_address);
                $('#website').val(d.website);
                $('#company_address').val(d.company_address);
                
                if(d.logo_path) {
                    $('#logoPreview').attr('src', '../assets/images/' + d.logo_path + '?t=' + new Date().getTime()); // Force refresh cache
                }

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Updated: ' + new Date(d.updated_at).toLocaleDateString());
                }
            }
        }, 'json');
    }
    loadDetails();

    // 2. IMAGE PREVIEW HANDLER
    $('#logoInput').change(function() {
        const file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(event) {
                $('#logoPreview').attr('src', event.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    // 3. FORM SUBMISSION (Using FormData for File Upload)
    $('#companyForm').on('submit', function(e) {
        e.preventDefault();
        
        // Append the file from the hidden input to the form data
        let formData = new FormData(this);
        let fileInput = $('#logoInput')[0].files[0];
        if(fileInput) {
            formData.append('company_logo', fileInput);
        }

        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'api/company_action.php',
            type: 'POST',
            data: formData,
            contentType: false, // Required for file upload
            processData: false, // Required for file upload
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadDetails(); // Refresh to update timestamp/logo path
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
            }
        });
    });

});
</script>