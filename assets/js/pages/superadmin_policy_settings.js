/**
 * Policy Settings Controller
 * Handles configuration for attendance grace periods, work hours, and leave benefits.
 * Integrated with Global AppUtility for Topbar syncing.
 */

$(document).ready(function() {

    // ==============================================================================
    // 1. DATA FETCHER
    // ==============================================================================
    function loadPolicies() {
        // Trigger visual loading via Global Utility
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.post(API_ROOT + '/superadmin/policy_settings_action.php', { action: 'get_details' }, function(res) {
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
    loadPolicies();

    // ==============================================================================
    // 2. MASTER REFRESHER HOOK
    // ==============================================================================
    window.refreshPageContent = function(isManual = false) {
        // AppUtility handles the loading state; loadPolicies handles the success/error
        loadPolicies();
    };

    // ==============================================================================
    // 3. SAVE POLICY SETTINGS
    // ==============================================================================
    $('#policyForm').on('submit', function(e) {
        e.preventDefault();
        
        let btn = $('#saveBtn');
        let formData = $(this).serialize() + '&action=update';

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        // Notify Topbar of activity
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.ajax({
            url: API_ROOT + '/superadmin/policy_settings_action.php',
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
                    loadPolicies(); // Refresh to update timestamps and topbar
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
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Policies');
            }
        });
    });

});