<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS (Synchronization and Logic)
// ==============================================================================
var payrollTable;

// 1.1 Synchronization Variables (New/Re-inserted)
let spinnerStartTime = 0; 
// Note: We use a simple structure since the script doesn't need to track currentCAId etc.

// 1.2 Helper function: Updates the final timestamp text (New/Re-inserted)
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.3 Helper function: Stops the spinner safely (New/Re-inserted)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    if (timeElapsed < minDisplayTime) {
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        finalizeStop();
    }
}


// 1.4 MASTER REFRESHER HOOK (Modified to include spinner start)
window.refreshPageContent = function() {
    // 1. Start Sync Visuals (NEW)
    spinnerStartTime = new Date().getTime(); 
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    // 2. Reload the table
    if (payrollTable) {
        payrollTable.ajax.reload(null, false);
    }
    // 3. Reload the stats cards
    loadStats();
};

// 1.5 Helper: Load Top Stats
function loadStats() {
    $.ajax({
        url: 'api/payroll_action.php?action=stats',
        dataType: 'json',
        success: function(data) {
            if(data.status === 'success') {
                const totalPayout = data.total_payout ? parseFloat(data.total_payout).toLocaleString('en-US', {minimumFractionDigits: 2}) : '0.00';
                $('#stat-payout').text('₱ ' + totalPayout);
                $('#stat-pending').text(data.pending_count);
            }
        }
    });
}

// 1.6 Helper: Batch Logic
function performBatchAction(subAction) {
    var selectedIds = [];
    $('.payroll-checkbox:checked').each(function() { selectedIds.push($(this).val()); });

    if (selectedIds.length === 0) {
        Swal.fire('No Selection', 'Please select records first.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Are you sure?',
        text: `Applying action to ${selectedIds.length} records.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Proceed'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing Batch...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            $.ajax({
                url: 'api/payroll_action.php?action=batch_action',
                type: 'POST',
                data: { ids: selectedIds, sub_action: subAction },
                dataType: 'json',
                success: function(res) {
                    Swal.close();
                    if (res.status === 'success') {
                        Swal.fire('Success', res.message, 'success');
                        window.refreshPageContent(); 
                        $('#selectAll').prop('checked', false);
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server communication failed.', 'error');
                }
            });
        }
    });
}

// 1.7 Helper: Print Batch Payslips
function printBatchPayslips() {
    var start = $('#filter_start_date').val();
    var end = $('#filter_end_date').val();
    if(start === '' || end === '') {
        Swal.fire('Missing Date Range', 'Please select dates in the filter first.', 'warning');
        return;
    }
    window.open(`functions/print_batch_payslips.php?start=${start}&end=${end}`, '_blank');
}


$(document).ready(function() {
    // Attach global functions to window scope for HTML onclick
    window.performBatchAction = performBatchAction;
    window.printBatchPayslips = printBatchPayslips;

    // 1. Load Stats
    loadStats();

    // 2. Initialize DataTables
    payrollTable = $('#payrollTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: "api/payroll_action.php?action=fetch", 
            type: "GET",
            data: function(d) {
                d.filter_start_date = $('#filter_start_date').val();
                d.filter_end_date = $('#filter_end_date').val();
            }
        },
        
        // ⭐ DRAW CALLBACK: Integrates spinner stop logic
        drawCallback: function(settings) {
            const icon = $('#refresh-spinner');
            if (icon.hasClass('fa-spin')) {
                stopSpinnerSafely(); // Stops spinner and updates time
            } else {
                updateLastSyncTime(); // Initial load time update
            }
        },
        
        columns: [
            // Col 0: Checkbox
            {
                data: 'id',
                className: 'text-center align-middle',
                orderable: false,
                render: function(data) {
                    return '<input type="checkbox" class="payroll-checkbox form-check-input" value="' + data + '">';
                }
            },
            // Col 1: Employee
            { 
                data: 'employee_name',
                className: 'align-middle',
                render: function(data, type, row) {
                    var ref = row.ref_no ? row.ref_no : '';
                    var photo = row.picture || 'default.png';
                    return `
                        <div class="d-flex align-items-center">
                            <img src="../assets/images/${photo}" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.src='../assets/images/default.png'">
                            <div>
                                <div class="fw-bold text-dark">${data}</div>
                                <div class="small text-muted">Ref: <span class="text-gray-600 fw-bold">${ref}</span></div>
                            </div>
                        </div>
                    `;
                }
            },
            // Col 2: Cut-Off (FA6 Update)
            { 
                data: 'cut_off_start',
                className: 'align-middle',
                render: function(data, type, row) {
                    return `<span class="fw-bold text-gray-700 small">${row.cut_off_start}</span> <i class="fa-solid fa-arrow-right mx-1 text-xs text-muted"></i> <span class="fw-bold text-gray-700 small">${row.cut_off_end}</span>`;
                }
            },
            // Col 3: Net Pay
            { 
                data: 'net_pay', 
                className: 'text-end fw-bolder text-gray-800 align-middle', 
                render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') 
            },
            // Col 4: Status
            { 
                data: 'status',
                className: 'text-center align-middle',
                render: function(data) {
                    if(data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill">Paid</span>';
                    return '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill">Pending</span>';
                }
            },
            // Col 5: Action (FA6 Update)
            {
                data: 'id',
                orderable: false, 
                className: 'text-center align-middle',
                render: function(data) {
                    return `<a href="view_payslip.php?id=${data}" class="btn btn-sm btn-outline-teal shadow-sm fw-bold"><i class="fa-solid fa-eye me-1"></i> Details </a>`;
                }
            }
        ]
    });

    // --- Search & Filter ---
    $('#customSearch').on('keyup', function() { payrollTable.search(this.value).draw(); });
    $('#applyFilterBtn').on('click', function() { window.refreshPageContent(); }); 
    
    $('#clearFilterBtn').on('click', function() {
        $('#filter_start_date, #filter_end_date, #customSearch').val('');
        payrollTable.search('').draw();
        window.refreshPageContent(); // Use hook for consistency
    });

    // --- Select All ---
    $('#selectAll').on('click', function(){
        var rows = payrollTable.rows({ 'search': 'applied' }).nodes();
        $('input[type="checkbox"]', rows).prop('checked', this.checked);
    });

    // --- GENERATE PAYROLL SUBMIT ---
    $('#generatePayrollForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Generating Payroll...',
            text: 'Calculating attendance, overtime, and deductions...',
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'api/create_payroll.php', 
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    $('#generatePayrollModal').modal('hide');
                    Swal.fire('Success', res.message, 'success');
                    window.refreshPageContent(); // Use hook for table/stats reload
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) { 
                console.log("AJAX Error:", xhr.responseText);
                Swal.fire('Error', 'Server calculation error. Check console for details.', 'error'); 
            }
        });
    });

    // --- BATCH ACTIONS ---
    // The functions are defined globally and attached to buttons with onclick in the HTML.
});
</script>