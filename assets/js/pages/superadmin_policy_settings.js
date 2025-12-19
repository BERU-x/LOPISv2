// assets/js/pages/policy_settings.js

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
    // 2. LOAD POLICY SETTINGS
    // ==============================================================================
    function loadPolicies() {
        updateSyncStatus('loading');

        $.post('../api/superadmin/policy_settings_action.php', { action: 'get_details' }, function(res) {
            if(res.status === 'success') {
                let d = res.data;
                
                // Attendance & Work Hours
                $('#standard_work_hours').val(d.standard_work_hours);
                $('#attendance_grace_period_mins').val(d.attendance_grace_period_mins);
                $('#overtime_min_minutes').val(d.overtime_min_minutes);
                
                // Leave Benefits
                $('#annual_vacation_leave').val(d.annual_vacation_leave);
                $('#annual_sick_leave').val(d.annual_sick_leave);
                $('#max_leave_carry_over').val(d.max_leave_carry_over);

                if(d.updated_at) {
                    $('#last-updated-text').text('Last Configuration Update: ' + new Date(d.updated_at).toLocaleString());
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
    loadPolicies();

    // Hook for Master Refresher (Topbar Sync Icon)
    window.refreshPageContent = function(isManual = false) {
        if(isManual) $('#refreshIcon').addClass('fa-spin');
        loadPolicies();
        if(isManual) setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
    };

    // ==============================================================================
    // 3. SAVE POLICY SETTINGS
    // ==============================================================================
    $('#policyForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        let formData = $(this).serialize() + '&action=update';

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        $.ajax({
            // ⭐ UPDATED API PATH
            url: '../api/superadmin/policy_settings_action.php',
            type: 'POST',
            data: formData,
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
                    loadPolicies(); // Refresh to update timestamps
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

    // 4. MANUAL REFRESH BUTTON LISTENER (if applicable)
    $('#refreshIcon').closest('a, div').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });

});