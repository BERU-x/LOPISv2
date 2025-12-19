/**
 * Payroll Management Controller
 * Handles SSP DataTables, Batch Approvals, and Real-time Financial Stats.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var payrollTable;

/**
 * 1.1 HELPER: Updates the Topbar Status (Sync Dot & Time)
 */
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

// 1.2 MASTER REFRESHER TRIGGER
window.refreshPageContent = function(isManual = false) {
    if (payrollTable) {
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        payrollTable.ajax.reload(null, false);
    }
    loadStats(); // Silently update dashboard cards
};

// 1.3 Helper: Load Financial Stats (Payout vs Pending)
function loadStats() {
    $.getJSON('../api/admin/payroll_action.php?action=stats', function(res) {
        if(res.status === 'success') {
            $('#stat-payout').text('₱ ' + res.total_payout);
            $('#stat-pending').text(res.pending_count);
        }
    });
}

// ==============================================================================
// 2. BATCH ACTIONS & PRINTING
// ==============================================================================

function performBatchAction(subAction) {
    let selectedIds = [];
    $('.payroll-checkbox:checked').each(function() { selectedIds.push($(this).val()); });

    if (selectedIds.length === 0) {
        Swal.fire('No Selection', 'Please select at least one payroll record.', 'warning');
        return;
    }

    Swal.fire({
        title: subAction === 'approve' ? 'Finalize Payroll?' : 'Send Email Notifications?',
        text: `Applying this action to ${selectedIds.length} employees. This updates financial ledgers.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirm'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            $.post('../api/admin/payroll_action.php?action=batch_action', { 
                ids: selectedIds, 
                sub_action: subAction 
            }, function(res) {
                if (res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    window.refreshPageContent(true);
                    $('#selectAll').prop('checked', false);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}

function printBatchPayslips() {
    let start = $('#filter_start_date').val();
    let end = $('#filter_end_date').val();
    if(!start || !end) {
        Swal.fire('Incomplete Dates', 'Please set the cut-off range in the filter first.', 'warning');
        return;
    }
    window.open(`functions/print_batch_payslips.php?start=${start}&end=${end}`, '_blank');
}

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    loadStats();

    // 3.1 Initialize SSP DataTable
    payrollTable = $('#payrollTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: "../api/admin/payroll_action.php?action=fetch", 
            type: "GET",
            data: function(d) {
                d.filter_start_date = $('#filter_start_date').val();
                d.filter_end_date = $('#filter_end_date').val();
            }
        },
        drawCallback: function() {
            updateSyncStatus('success');
            setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
        },
        columns: [
            {
                data: 'id',
                className: 'text-center align-middle',
                orderable: false,
                render: d => `<input type="checkbox" class="payroll-checkbox form-check-input" value="${d}">`
            },
            { 
                data: 'employee_name',
                className: 'align-middle',
                render: function(data, type, row) {
                    let photo = row.picture || 'default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="../assets/images/users/${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 38px; height: 38px; object-fit: cover;" onerror="this.src='../assets/images/users/default.png'">
                            <div>
                                <div class="fw-bold text-dark mb-0">${data}</div>
                                <div class="small text-muted font-monospace">${row.ref_no}</div>
                            </div>
                        </div>`;
                }
            },
            { 
                data: 'cut_off_end',
                className: 'align-middle text-center',
                render: (data, type, row) => `<span class="small fw-bold">${row.cut_off_start}</span> <i class="fa-solid fa-arrow-right mx-1 text-xs text-muted"></i> <span class="small fw-bold">${row.cut_off_end}</span>`
            },
            { 
                data: 'net_pay', 
                className: 'text-end fw-bold text-dark align-middle', 
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            { 
                data: 'status',
                className: 'text-center align-middle',
                render: function(data) {
                    let color = data == 1 ? 'success' : 'warning';
                    let label = data == 1 ? 'Finalized' : 'Pending';
                    return `<span class="badge bg-soft-${color} text-${color} border border-${color} px-3 rounded-pill">${label}</span>`;
                }
            },
            {
                data: 'id',
                orderable: false, 
                className: 'text-center align-middle',
                render: d => `<a href="../app/view_payslip.php?id=${d}" class="btn btn-sm btn-outline-teal fw-bold shadow-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Details</a>`
            }
        ],
        order: [[ 1, "asc" ]]
    });

    // 3.2 Search Debouncing
    $('#customSearch').on('keyup', function() { 
        clearTimeout(window.searchTimer);
        window.searchTimer = setTimeout(() => { payrollTable.search(this.value).draw(); }, 400); 
    });

    // 3.3 Filters & Refresh
    $('#applyFilterBtn').on('click', () => window.refreshPageContent(true)); 
    $('#clearFilterBtn').on('click', () => {
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        payrollTable.search('').draw();
        window.refreshPageContent(true); 
    });

    // 3.4 Select All Logic
    $('#selectAll').on('click', function(){
        $('.payroll-checkbox').prop('checked', this.checked);
    });

    // 3.5 Manual Action Refresh
    $('#btn-refresh').on('click', (e) => { e.preventDefault(); window.refreshPageContent(true); });
});