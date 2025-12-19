/**
 * Employee Overtime Controller
 * Handles biometric validation, OT request filing, and history tracking.
 */

let otHistoryTable;
let spinnerStartTime = 0;

// ==============================================================================
// 1. GLOBAL UI HELPERS
// ==============================================================================

function updateLastSyncTime() {
    const now = new Date();
    $('#last-updated-time').text(now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' }));
}

function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minTime = 800;
    const elapsed = new Date().getTime() - spinnerStartTime;
    const finish = () => { 
        icon.removeClass('fa-spin text-teal').addClass('text-gray-400'); 
        updateLastSyncTime(); 
    };
    if(elapsed < minTime) setTimeout(finish, minTime - elapsed); else finish();
}

// ==============================================================================
// 2. BIOMETRIC VALIDATION LOGIC
// ==============================================================================

/**
 * Fetches raw OT hours from attendance logs based on the selected date.
 * Ensures the employee doesn't request "phantom" hours.
 */
function fetchRawOTHours(otDate) {
    const $display = $('#rawOtDisplay'); 
    const $input = $('#hours_requested'); 
    
    // Initial State
    $display.html('<i class="fas fa-spinner fa-spin me-2 text-teal"></i> Validating logs...');
    $input.attr('max', 0); 
    
    if (!otDate) {
        $display.html('<span class="text-muted small">Select a date to check available overtime logs.</span>');
        $input.val('');
        return;
    }

    $.ajax({
        url: '../api/employee/overtime_action.php?action=validate_ot_request',
        type: 'POST',
        data: { ot_date: otDate },
        dataType: 'json',
        success: function(res) {
            const rawHrs = parseFloat(res.raw_ot_hr || 0);

            if (res.status === 'success') {
                if (rawHrs > 0) {
                    $display.html(`
                        <span class="text-success fw-bold small">
                            <i class="fas fa-check-circle me-1"></i> Biometric Log Found: ${rawHrs.toFixed(2)} hrs available.
                        </span>
                    `);
                    $input.attr('max', rawHrs); 
                } else {
                    $display.html(`
                        <span class="text-danger fw-bold small">
                            <i class="fas fa-times-circle me-1"></i> No biometric overtime logged for ${otDate}.
                        </span>
                    `);
                    $input.val(0).attr('max', 0); 
                }
            } else {
                $display.html(`<span class="text-danger small">${res.message}</span>`);
            }
        },
        error: function() {
            $display.html('<span class="text-danger small">Biometric server unavailable.</span>');
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION & DATATABLES
// ==============================================================================

$(document).ready(function() {

    // 3.1 Initialize OT History Table
    if ($('#otHistoryTable').length) {
        otHistoryTable = $('#otHistoryTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "../api/employee/overtime_action.php?action=fetch",
            drawCallback: () => stopSpinnerSafely(),
            columns: [
                { data: 'ot_date', className: 'align-middle fw-bold' }, 
                { data: 'hours_requested', className: 'text-center align-middle font-monospace' },
                { data: 'status', className: 'text-center align-middle' },
                { 
                    data: 'id', 
                    orderable: false,
                    className: 'text-center',
                    render: (id) => `<button class="btn btn-sm btn-outline-teal shadow-sm" onclick="viewDetails(${id})"><i class="fas fa-eye"></i></button>`
                }
            ],
            order: [[0, 'desc']],
            language: { emptyTable: "No overtime requests found." }
        });
    }

    // 3.2 Date Change Trigger
    $('#ot_date').on('change', function() {
        fetchRawOTHours($(this).val());
    });

    // 3.3 Input Cap Constraint
    $('#hours_requested').on('input', function() {
        const val = parseFloat($(this).val());
        const max = parseFloat($(this).attr('max'));
        if (val > max) {
            $(this).val(max);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: `Capped at ${max} hrs based on biometric logs`,
                showConfirmButton: false,
                timer: 2000
            });
        }
    });

    // ============================================================
    //  4. SECURE FORM SUBMISSION
    // ============================================================
    $('#otRequestForm').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const requested = parseFloat($('#hours_requested').val());
        const max = parseFloat($('#hours_requested').attr('max'));

        if (requested <= 0 || isNaN(requested)) {
            Swal.fire('Invalid Input', 'Please enter a valid amount of hours.', 'error');
            return;
        }

        if (requested > max) {
            Swal.fire('Validation Error', `You cannot request ${requested} hrs when only ${max} hrs were logged.`, 'error');
            return;
        }

        Swal.fire({
            title: 'Submitting Request...',
            text: 'We are notifying HR of your OT filing.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: '../api/employee/overtime_action.php?action=create',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Submitted', res.message, 'success');
                    $form[0].reset();
                    $('#rawOtDisplay').html('<span class="text-muted small">Select a date to check logs.</span>');
                    otHistoryTable.ajax.reload();
                } else {
                    Swal.fire('Submission Failed', res.message, 'error');
                }
            }
        });
    });

    // 5. Global Refresher
    window.refreshPageContent = function() {
        spinnerStartTime = new Date().getTime(); 
        $('#refresh-spinner').addClass('fa-spin text-teal');
        if (otHistoryTable) otHistoryTable.ajax.reload(null, false);
    };
});