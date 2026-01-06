/**
 * Payroll Management Controller
 * Handles SSP DataTables, Batch Approvals, and AppUtility Syncing.
 */

// ==============================================================================
// 1. GLOBAL STATE & MASTER REFRESHER
// ==============================================================================
var payrollTable;

window.refreshPageContent = function(isManual = false) {
    if (payrollTable) {
        if(isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
        }
        payrollTable.ajax.reload(null, false);
    }
    loadStats(); 
};

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

window.performBatchAction = function(subAction) {
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
        confirmButtonColor: subAction === 'approve' ? '#0cc0df' : '#4e73df',
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
};

window.printBatchPayslips = function() {
    let start = $('#filter_start_date').val();
    let end = $('#filter_end_date').val();
    if(!start || !end) {
        Swal.fire('Incomplete Dates', 'Please set the cut-off range in the filter first.', 'warning');
        return;
    }
    window.open(`functions/print_batch_payslips.php?start=${start}&end=${end}`, '_blank');
};

// ==============================================================================
// 3. PAYROLL GENERATION (Modal Handler)
// ==============================================================================

function initPayrollGeneration() {
    $('#generatePayrollForm').on('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Generating Payroll',
            text: 'Calculating attendance, holidays, and taxes. Please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'functions/create_payroll.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('Generated!', res.message, 'success');
                    $('#generatePayrollModal').modal('hide');
                    window.refreshPageContent(true);
                } else {
                    // Show detailed error (useful for Debug Mode)
                    Swal.fire({
                        icon: 'error',
                        title: 'Generation Failed',
                        text: res.message,
                        footer: 'Check attendance logs or employee compensation settings.'
                    });
                }
            },
            error: function(xhr) {
                Swal.fire('System Error', 'Could not contact the payroll engine.', 'error');
                console.error(xhr.responseText);
            }
        });
    });
}

// ==============================================================================
// 4. INITIALIZATION
// ==============================================================================
$(document).ready(function() {
    
    loadStats();
    initPayrollGeneration();

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
            },
            error: function() {
                if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
            }
        },
        drawCallback: function() {
            if(window.AppUtility) window.AppUtility.updateSyncStatus('success');
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

    // Search Debouncing
    $('#customSearch').on('keyup', function() { 
        clearTimeout(window.searchTimer);
        window.searchTimer = setTimeout(() => { payrollTable.search(this.value).draw(); }, 400); 
    });

    // Filters
    $('#applyFilterBtn').on('click', () => window.refreshPageContent(true)); 
    $('#clearFilterBtn').on('click', () => {
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        payrollTable.search('').draw();
        window.refreshPageContent(true); 
    });

    // Select All
    $('#selectAll').on('click', function(){
        $('.payroll-checkbox').prop('checked', this.checked);
    });

    // Refresh
    $('#btn-refresh').on('click', (e) => { 
        e.preventDefault(); 
        window.refreshPageContent(true); 
    });
});