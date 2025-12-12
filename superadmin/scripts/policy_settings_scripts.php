<script>
$(document).ready(function() {

    // 1. LOAD SETTINGS
    function loadPolicies() {
        $.post('api/policy_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                // Attendance
                $('#standard_work_hours').val(d.standard_work_hours);
                $('#attendance_grace_period_mins').val(d.attendance_grace_period_mins);
                $('#overtime_min_minutes').val(d.overtime_min_minutes);
                
                // Leave
                $('#annual_vacation_leave').val(d.annual_vacation_leave);
                $('#annual_sick_leave').val(d.annual_sick_leave);
                $('#max_leave_carry_over').val(d.max_leave_carry_over);

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Configuration Update: ' + new Date(d.updated_at).toLocaleString());
                }
            }
        }, 'json');
    }
    loadPolicies();

    // 2. SAVE SETTINGS
    $('#policyForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        $.ajax({
            url: 'api/policy_action.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Policies Updated!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadPolicies();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server connection failed.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Policies');
            }
        });
    });

});
</script>