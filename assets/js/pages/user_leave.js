/**
 * Employee Leave Controller
 * Handles real-time credit validation, working-day calculation, and history tracking.
 */

$(document).ready(function() {
    let leaveBalances = {}; 
    let table; 
    let spinnerStartTime = 0; 

    // ============================================================
    //  1. SYNC & STATUS HELPERS
    // ============================================================

    function updateSyncStatus(state) {
        const $dot = $('.live-dot');
        const $text = $('#last-updated-time');
        const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

        $dot.removeClass('text-success text-warning text-danger');

        if (state === 'loading') {
            $text.text('Syncing...');
            $dot.addClass('text-warning'); 
        } else if (state === 'success') {
            $text.text(`Synced: ${time}`);
            $dot.addClass('text-success'); 
        } else {
            $text.text(`Failed: ${time}`);
            $dot.addClass('text-danger');  
        }
    }

    function stopSpinnerSafely() {
        const minDisplayTime = 800; 
        const timeElapsed = new Date().getTime() - spinnerStartTime;

        const finalizeUI = () => {
            $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-gray-400');
            $('#refreshIcon').removeClass('fa-spin');
        };

        if (timeElapsed < minDisplayTime) {
            setTimeout(finalizeUI, minDisplayTime - timeElapsed);
        } else {
            finalizeUI();
        }
    }

    // ============================================================
    //  2. DATA FETCHING
    // ============================================================

    function loadLeaveCredits() {
        spinnerStartTime = new Date().getTime();
        updateSyncStatus('loading');
        $('#refresh-spinner').addClass('fa-spin text-teal').removeClass('text-gray-400');

        // Card specific loading state
        $('.credit-value').addClass('placeholder-glow').text('...');

        $.getJSON('../api/employee/leave_action.php?action=get_credits', function(res) {
            if (res.status === 'success') {
                leaveBalances = res;
                
                // Update Numeric Cards
                $('#vl_remaining').text(res.vl.remaining).removeClass('placeholder-glow');
                $('#vl_total').text(res.vl.total);
                
                $('#sl_remaining').text(res.sl.remaining).removeClass('placeholder-glow');
                $('#sl_total').text(res.sl.total);

                $('#el_remaining').text(res.el.remaining).removeClass('placeholder-glow');
                $('#el_total').text(res.el.total);

                updateSyncStatus('success');
            } else {
                updateSyncStatus('error');
            }
            stopSpinnerSafely();
        });
    }

    // ============================================================
    //  3. LEAVE VALIDATION LOGIC
    // ============================================================

    /**
     * Calculates the number of working days between two dates.
     * 
     */
    function countWorkingDays(start, end) {
        let count = 0;
        let curDate = new Date(start);
        let endDate = new Date(end);

        while (curDate <= endDate) {
            let dayOfWeek = curDate.getDay();
            // Exclude Sunday (0) and Saturday (6)
            if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                count++;
            }
            curDate.setDate(curDate.getDate() + 1);
        }
        return count;
    }

    function validateLeaveRequest() {
        const type = $('#leave_type_select').val();
        const startVal = $('#start_date').val();
        const endVal = $('#end_date').val();
        const $submitBtn = $('#submit_btn');
        const $warningBox = $('#credit_warning');

        if (!type || !startVal || !endVal) {
            $warningBox.slideUp();
            return;
        }

        // Map type to balance
        let remaining = 0;
        if (type === 'Vacation Leave') remaining = parseFloat(leaveBalances.vl.remaining);
        else if (type === 'Sick Leave') remaining = parseFloat(leaveBalances.sl.remaining);
        else if (type === 'Emergency Leave') remaining = parseFloat(leaveBalances.el.remaining);

        const requestedDays = countWorkingDays(startVal, endVal);

        if (requestedDays > remaining) {
            $submitBtn.prop('disabled', true);
            $warningBox.removeClass('alert-info').addClass('alert-danger')
                .html(`<i class="fas fa-ban me-2"></i> <strong>Insufficient Credits.</strong><br>Requesting <b>${requestedDays} days</b>, but only <b>${remaining}</b> available.`)
                .slideDown();
        } else {
            $submitBtn.prop('disabled', false);
            $warningBox.removeClass('alert-danger').addClass('alert-info')
                .html(`<i class="fas fa-info-circle me-2"></i> Valid request for <b>${requestedDays}</b> working day(s).`)
                .slideDown();
        }
    }

    // ============================================================
    //  4. INITIALIZATION & EVENTS
    // ============================================================

    loadLeaveCredits();

    // DataTable Initialization
    table = $('#leaveHistoryTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "../api/employee/leave_action.php?action=fetch_history",
        order: [[ 1, "desc" ]],
        drawCallback: () => stopSpinnerSafely(),
        columns: [
            { data: "leave_type", className: "fw-bold align-middle" },
            { data: "dates", className: "align-middle small" },
            { data: "days_count", className: "text-center align-middle" },
            { data: "status", className: "text-center align-middle" }
        ],
        language: {
            processing: "<div class='spinner-border text-teal spinner-border-sm' role='status'></div>"
        }
    });

    // Date/Type Event Handlers
    $('#leave_type_select, #start_date, #end_date').on('change', function() {
        const startVal = $('#start_date').val();
        const endVal = $('#end_date').val();

        // Enforce End Date >= Start Date
        if (this.id === 'start_date' && (!endVal || endVal < startVal)) {
            $('#end_date').val(startVal).attr('min', startVal);
        }
        
        validateLeaveRequest();
    });

    // Form Submission
    $('#leaveRequestForm').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#submit_btn');
        const originalText = $('#btn_text').html();

        $btn.prop('disabled', true);
        $('#btn_text').text('Processing...');
        $('#btn_spinner').removeClass('d-none');

        $.ajax({
            url: '../api/employee/leave_action.php?action=submit_leave',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#leaveRequestForm')[0].reset();
                    $('#credit_warning').hide();
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);
                $('#btn_text').html(originalText);
                $('#btn_spinner').addClass('d-none');
            }
        });
    });

    // Master Refresher
    window.refreshPageContent = function(isManual = false) {
        if (isManual) $('#refreshIcon').addClass('fa-spin');
        loadLeaveCredits();
        table.ajax.reload(null, false);
    };

    $('#btn-refresh').on('click', (e) => { e.preventDefault(); window.refreshPageContent(true); });
});