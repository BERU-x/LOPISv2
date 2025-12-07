<script>
$(document).ready(function() {
    let leaveBalances = {}; 
    let table; // Global table variable
    
    // --- 1. SPINNER / SYNC LOGIC ---
    let spinnerStartTime = 0; 

    function stopSpinnerSafely() {
        const minDisplayTime = 1000; // Minimum time to show spinner (1 second)
        const timeElapsed = new Date().getTime() - spinnerStartTime;

        const updateTime = () => {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                second: '2-digit'
            });
            
            // Update Topbar Elements
            $('#last-updated-time').text(timeString);
            $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-gray-400');
        };

        // If data loaded too fast, wait remaining time to prevent flicker
        if (timeElapsed < minDisplayTime) {
            setTimeout(updateTime, minDisplayTime - timeElapsed);
        } else {
            updateTime();
        }
    }

    // --- 2. FUNCTION: Load Leave Credits ---
    function loadLeaveCredits() {
        // Start the spinner visual
        spinnerStartTime = new Date().getTime();
        $('#refresh-spinner').removeClass('text-gray-400').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');

        // Optional: Local loading state for the card itself
        $('#credits-container').addClass('d-none');
        $('#credits-loading').removeClass('d-none');

        $.ajax({
            url: 'api/leave_action.php?action=get_credits',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    leaveBalances = response;
                    
                    // Update UI Text
                    $('#vl_remaining').text(response.vl.remaining);
                    $('#vl_total').text(response.vl.total);
                    
                    $('#sl_remaining').text(response.sl.remaining);
                    $('#sl_total').text(response.sl.total);

                    $('#el_remaining').text(response.el.remaining);
                    $('#el_total').text(response.el.total);

                    // Show Container
                    $('#credits-loading').addClass('d-none');
                    $('#credits-container').removeClass('d-none');
                }
                // Stop the Topbar Spinner
                stopSpinnerSafely();
            },
            error: function(err) {
                console.error('Error loading leave credits:', err);
                // Show error state in Topbar
                $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-danger');
                $('#last-updated-time').text('Error');
                
                $('#credits-loading').html('<span class="text-danger small">Failed to load leave credits.</span>');
            }
        });
    }

    // --- 3. INITIALIZATION ---
    loadLeaveCredits();

    // --- 4. EVENT LISTENERS ---

    // Client-Side Validation: Check Credits
    $('#leave_type_select').on('change', function() {
        let type = $(this).val();
        let remaining = 0;
        let checkCredit = false;

        if ($.isEmptyObject(leaveBalances)) return;

        if (type === 'Vacation Leave') { remaining = leaveBalances.vl.remaining; checkCredit = true; }
        else if (type === 'Sick Leave') { remaining = leaveBalances.sl.remaining; checkCredit = true; }
        else if (type === 'Emergency Leave') { remaining = leaveBalances.el.remaining; checkCredit = true; }

        if (checkCredit && remaining <= 0) {
            $('#credit_warning').slideDown();
        } else {
            $('#credit_warning').slideUp();
        }
    });

    // Auto-update End Date
    $('#start_date').on('change', function() {
        let startVal = $(this).val();
        let endVal = $('#end_date').val();
        if (!endVal || endVal < startVal) {
            $('#end_date').val(startVal);
        }
        $('#end_date').attr('min', startVal);
    });

    // Initialize DataTables (History)
    table = $('#leaveHistoryTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "api/leave_action.php?action=fetch_history",
        "order": [[ 1, "desc" ]],
        "columns": [
            { "data": "leave_type", "className": "fw-bold" },
            { "data": "dates", "className": "small" },
            { "data": "days_count", "className": "text-center" },
            { "data": "status", "className": "text-center" }
        ],
        "language": {
            "emptyTable": "No leave requests found.",
            "processing": "<div class='spinner-border text-teal' role='status'></div>"
        }
    });

    // AJAX Form Submission
    $('#leaveRequestForm').on('submit', function(e) {
        e.preventDefault();

        // UI: Loading State
        let btn = $('#submit_btn');
        let originalText = $('#btn_text').html();
        btn.prop('disabled', true);
        $('#btn_text').text('Submitting...');
        $('#btn_spinner').removeClass('d-none');

        $.ajax({
            url: 'api/leave_action.php?action=submit_leave',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                let alertClass = (response.status === 'success') ? 'alert-success' : 'alert-danger';
                let icon = (response.status === 'success') ? '<i class="fas fa-check-circle me-2"></i>' : '<i class="fas fa-exclamation-triangle me-2"></i>';
                
                let alertHtml = `
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                        ${icon} ${response.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                
                $('#alert-container').html(alertHtml);

                if (response.status === 'success') {
                    $('#leaveRequestForm')[0].reset(); // Clear form
                    $('#credit_warning').hide();
                    table.ajax.reload(null, false); // Reload Table
                    loadLeaveCredits(); // Refresh balances (triggering spinner again)
                }
            },
            error: function(xhr, status, error) {
                let alertHtml = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> System Error: ${error}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                $('#alert-container').html(alertHtml);
            },
            complete: function() {
                // UI: Reset Button
                btn.prop('disabled', false);
                $('#btn_text').html(originalText);
                $('#btn_spinner').addClass('d-none');
            }
        });
    });
});
</script>