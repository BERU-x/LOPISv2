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
// --- HELPER: Calculate Working Days (Excludes Sat/Sun) ---
    function countWorkingDays(start, end) {
        let count = 0;
        let curDate = new Date(start);
        let endDate = new Date(end);

        // Loop through every day
        while (curDate <= endDate) {
            let dayOfWeek = curDate.getDay();
            // 0 = Sunday, 6 = Saturday
            if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                count++;
            }
            // Move to next day
            curDate.setDate(curDate.getDate() + 1);
        }
        return count;
    }

    // --- FUNCTION: Validate Logic ---
    function validateLeaveRequest() {
        // 1. Get Inputs
        let type = $('#leave_type_select').val();
        let startVal = $('#start_date').val();
        let endVal = $('#end_date').val();
        let submitBtn = $('#submit_btn');
        let warningBox = $('#credit_warning');

        // 2. Initial Checks
        if (!type || !startVal || !endVal) {
            warningBox.slideUp();
            submitBtn.prop('disabled', false);
            return;
        }

        // 3. Get Remaining Balance
        let remaining = 0;
        if ($.isEmptyObject(leaveBalances)) return; // Wait for AJAX

        if (type === 'Vacation Leave') remaining = parseFloat(leaveBalances.vl.remaining);
        else if (type === 'Sick Leave') remaining = parseFloat(leaveBalances.sl.remaining);
        else if (type === 'Emergency Leave') remaining = parseFloat(leaveBalances.el.remaining);

        // 4. Calculate Days (Excluding Weekends)
        let requestedDays = countWorkingDays(startVal, endVal);

        // 5. Compare & Update UI
        if (requestedDays > remaining) {
            // BLOCK: Request exceeds balance
            submitBtn.prop('disabled', true);
            
            warningBox.removeClass('alert-warning').addClass('alert-danger');
            warningBox.html(`
                <i class="fas fa-ban me-2"></i> 
                <strong>Insufficient Credits.</strong><br>
                This request is for <b>${requestedDays} working days</b> (excluding weekends), 
                but you only have <b>${remaining}</b> remaining.
            `);
            warningBox.slideDown();
        } else {
            // ALLOW
            submitBtn.prop('disabled', false);
            
            // Optional: Show a neutral info message about the count
            // warningBox.removeClass('alert-danger').addClass('alert-info');
            // warningBox.html(`<i class="fas fa-info-circle me-2"></i> Requesting <b>${requestedDays}</b> working days.`);
            // warningBox.slideDown();
            
            // Or just hide it if valid
            warningBox.slideUp();
        }
    }

    // --- 4. EVENT LISTENERS ---

    // Unified Listener: Runs when Type, Start Date, or End Date changes
    $('#leave_type_select, #start_date, #end_date').on('change', function() {
        let startElem = $('#start_date');
        let endElem = $('#end_date');

        // Auto-fix End Date if it's before Start Date
        if (this.id === 'start_date') {
            if (endElem.val() === '' || endElem.val() < startElem.val()) {
                endElem.val(startElem.val());
            }
            endElem.attr('min', startElem.val());
        }

        validateLeaveRequest();
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