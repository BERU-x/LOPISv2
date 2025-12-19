/**
 * Employee Cash Advance Controller
 * Handles personal request filing, real-time status tracking, and stats syncing.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
let caTable;
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
    
    spinnerStartTime = new Date().getTime(); 
    
    loadStats(); // Reload numeric cards
    if (caTable) {
        caTable.ajax.reload(null, false); // Reload history table
    }
};

/**
 * 1.3 HELPER: Stops the spinner safely (prevents flickering)
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

/**
 * Fetches latest pending totals for dashboard cards
 */
function loadStats() {
    $.getJSON('../api/employee/cash_advance_action.php?action=stats', function(res) {
        if(res.status === 'success') {
            $('#stat-pending-total').text('₱' + res.pending_total);
        }
    });
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================

$(document).ready(function() {

    loadStats();

    // 3.1 Initialize History Table
    if ($('#myRequestsTable').length) {
        caTable = $('#myRequestsTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip', 
            order: [[0, 'desc']],
            ajax: {
                url: "../api/employee/cash_advance_action.php?action=fetch", 
                type: "GET",
                error: function() {
                    updateSyncStatus('error');
                    stopSpinnerSafely();
                }
            },
            drawCallback: function() {
                updateSyncStatus('success');
                stopSpinnerSafely();
            },
            columns: [
                { data: 'date_col', className: 'fw-bold align-middle' },
                { data: 'amount', className: 'text-center align-middle' },
                { data: 'status', className: 'text-center align-middle' },
                { 
                    data: null, 
                    orderable: false,
                    className: 'text-center align-middle',
                    render: function() {
                        return `<button class="btn btn-sm btn-outline-teal shadow-sm fw-bold btn-view-details">
                                    <i class="fas fa-eye me-1"></i> Details
                                </button>`;
                    }
                }
            ],
            language: {
                processing: "<div class='spinner-border text-teal spinner-border-sm' role='status'></div>",
                emptyTable: "No request history found."
            }
        });

        // 3.2 Detail Viewer (Modal)
        $('#myRequestsTable tbody').on('click', '.btn-view-details', function () {
            const data = caTable.row($(this).closest('tr')).data();

            const contentHtml = `
                <div class="text-start p-2">
                    <div class="mb-2 small text-muted font-monospace">Ref ID: #${data.id}</div>
                    <div class="mb-2"><strong>Date Needed:</strong> ${data.date_needed}</div>
                    <div class="mb-2"><strong>Amount:</strong> <span class="text-teal fw-bold">${data.amount}</span></div>
                    <div class="mb-2"><strong>Purpose:</strong> <br><span class="text-muted fst-italic">"${data.remarks || 'N/A'}"</span></div>
                    <div class="mt-3 text-center border-top pt-3"><strong>Current Status:</strong> ${data.status}</div>
                </div>`;

            Swal.fire({
                title: 'Request Details',
                html: contentHtml,
                icon: 'info',
                confirmButtonText: 'Close',
                confirmButtonColor: '#0CC0DF'
            });
        });
    }

    // 3.3 Form Submission
    $('#caRequestForm').on('submit', function(e) {
        e.preventDefault(); 
        const formData = new FormData(this);

        Swal.fire({
            title: 'Confirm Request',
            text: "Submit this cash advance for approval?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#20c997',
            confirmButtonText: 'Yes, Submit'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../api/employee/cash_advance_action.php?action=create', 
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() { Swal.showLoading(); },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('Submitted!', res.message, 'success');
                            $('#caRequestForm')[0].reset();
                            $('#newRequestModal').modal('hide');
                            window.refreshPageContent(true);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Server connection failed.', 'error'); }
                });
            }
        });
    });

    // Page Specific Refresh
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});