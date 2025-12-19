/**
 * Employee Payslip History Controller
 * Handles personal payslip fetching, statistics, and PDF download triggers.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
let payslipTable; 
let spinnerStartTime = 0; 

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
 */
function updateSyncStatus(state) {
    const $dot = $('.live-dot');
    const $text = $('#last-updated-time');
    const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    $dot.removeClass('text-success text-warning text-danger');

    if (state === 'loading') {
        $text.text('Syncing...');
        $dot.addClass('text-warning'); // Yellow
    } 
    else if (state === 'success') {
        $text.text(`Synced: ${time}`);
        $dot.addClass('text-success'); // Green
    } 
    else {
        $text.text(`Failed: ${time}`);
        $dot.addClass('text-danger');  // Red
    }
}

/**
 * 1.2 MASTER REFRESHER HOOK
 * Connects the global refresh button to this specific module.
 */
window.refreshPageContent = function(isManual = false) {
    if (isManual) {
        $('#refreshIcon').addClass('fa-spin');
        $('#refresh-spinner').addClass('fa-spin text-teal').removeClass('text-gray-400');
        updateSyncStatus('loading');
    }
    
    // Reset timer for safe spinner stop
    spinnerStartTime = new Date().getTime(); 
    
    // Reload components
    loadStats();
    if (payslipTable) {
        payslipTable.ajax.reload(null, false);
    }
};

/**
 * 1.3 HELPER: Stops the spinner only after a minimum time (UX)
 */
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

// ==============================================================================
// 2. DATA ACTIONS
// ==============================================================================

function loadStats() {
    $.getJSON('../api/employee/payslip_action.php?action=stats', function(data) {
        if (data.status === 'success') {
            $('#stat-last-pay').text('₱ ' + data.last_net_pay);
            $('#stat-ytd-total').text('₱ ' + data.ytd_total);
            $('#stat-pay-date').text(data.last_pay_date);
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================

$(document).ready(function() {

    loadStats();

    if ($('#payslipTable').length) {
        payslipTable = $('#payslipTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            searching: false,
            dom: 'rtip', 
            order: [[0, 'desc']], 
            ajax: {
                url: "../api/employee/payslip_action.php?action=fetch", 
                type: "GET"
            },
            drawCallback: function() {
                updateSyncStatus('success');
                stopSpinnerSafely();
            },
            columns: [
                { 
                    data: 'period',
                    className: 'align-middle fw-bold text-dark',
                    render: function(data, type, row) {
                        return `
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-file-invoice-dollar text-secondary me-3 fa-lg"></i>
                                <div>
                                    <div class="mb-0 text-sm">${data}</div>
                                    <div class="text-xs text-muted font-monospace">${row.ref_no}</div>
                                </div>
                            </div>`;
                    }
                },
                { 
                    data: 'net_pay', 
                    className: 'text-end fw-bold align-middle text-dark', 
                    render: d => '₱ ' + d
                },
                { 
                    data: 'status_label',
                    className: 'text-center align-middle',
                    orderable: false
                },
                {
                    data: 'id',
                    orderable: false, 
                    className: 'text-center align-middle',
                    render: function(data) {
                        return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold" onclick="location.href='../app/view_payslip.php?id=${data}'">
                                    <i class="fa-solid fa-eye"></i> Details
                                </button>`;
                    }
                }
            ],
            language: {
                emptyTable: "No released payslips available."
            }
        });
    }

    // Manual Refresh Event
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});