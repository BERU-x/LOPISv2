<script>
$(document).ready(function() {
    
    // Toast Configuration
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });

    // LISTEN FOR TOGGLE CHANGES
    $('.perm-toggle').on('change', function() {
        
        let checkbox = $(this);
        let usertype = checkbox.data('usertype');
        let featureId = checkbox.data('feature');
        let isChecked = checkbox.is(':checked') ? 1 : 0;

        // Visual Feedback (Disable temporarily to prevent spam clicks)
        checkbox.prop('disabled', true);

        $.ajax({
            url: 'api/roles_management_action.php',
            type: 'POST',
            data: { 
                action: 'toggle_permission', 
                usertype: usertype, 
                feature_id: featureId, 
                status: isChecked 
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Toast.fire({ icon: 'success', title: 'Saved' });
                } else {
                    // Revert check if failed
                    checkbox.prop('checked', !isChecked); 
                    Toast.fire({ icon: 'error', title: 'Save Failed' });
                }
            },
            error: function() {
                // Revert check on network error
                checkbox.prop('checked', !isChecked);
                Toast.fire({ icon: 'error', title: 'Connection Error' });
            },
            complete: function() {
                // Re-enable checkbox
                checkbox.prop('disabled', false);
            }
        });
    });
});
</script>