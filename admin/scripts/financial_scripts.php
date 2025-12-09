<?php
// scripts/financial_management_scripts.php
?>

<script>
// --- GLOBAL STATE VARIABLES ---
var financialTable;
var currentViewedEmployeeId = null; // To track who we are viewing in the history modal
let spinnerStartTime = 0; // Global variable to track when the spin started

// 1. HELPER: Updates the final timestamp text (Refresher Logic)
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 2. HELPER: Stops the spinner safely (Refresher Logic)
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

// 3. HELPER: Format Currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP',
        minimumFractionDigits: 2 
    }).format(amount);
}

// --- Global: Set Employee for "Add Transaction" Modal ---
window.setTransactionEmployee = function(id, name) {
    $('#trans_employee_id').val(id);
    $('#trans_employee_name').val(name);
};

// --- Global: View Ledger History ---
window.viewLedger = function(id) {
    currentViewedEmployeeId = id; 
    $('#filter_category').val('All'); 
    loadLedgerData(id, 'All'); 
};

// --- Internal: Fetch and Render Ledger Data ---
function loadLedgerData(empId, category) {
    const tableBody = $('#ledgerTableBody');
    
    tableBody.html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Loading history...</td></tr>');

    $.ajax({
        url: 'api/financial_action.php?action=fetch_ledger',
        type: 'GET',
        data: { 
            employee_id: empId, 
            category: category 
        },
        dataType: 'json',
        success: function(response) {
            tableBody.empty(); 

            if (response.data && response.data.length > 0) {
                response.data.forEach(function(row) {
                    
                    let typeBadge = '';
                    if (row.transaction_type === 'Deposit' || row.transaction_type === 'Loan_Payment') {
                        typeBadge = '<span class="badge bg-soft-success text-success"> + Credit</span>';
                    } else {
                        typeBadge = '<span class="badge bg-soft-danger text-danger"> - Debit</span>';
                    }
                    
                    let label = row.transaction_type.replace('_', ' ');

                    // Check if amortization exists to display it
                    let amortDisplay = parseFloat(row.amortization) > 0 
                        ? `<div class="small text-muted fst-italic mt-1">Amort: ${formatCurrency(row.amortization)}/mo</div>` 
                        : '';

                    const html = `
                        <tr>
                            <td class="align-middle">${row.transaction_date}</td>
                            <td class="align-middle fw-bold">${row.category.replace('_', ' ')}</td>
                            <td class="align-middle">
                                ${label}
                                ${amortDisplay} 
                            </td>
                            <td class="align-middle small text-muted">${row.ref_no || '-'}</td>
                            <td class="align-middle text-end font-monospace">${formatCurrency(row.amount)}</td>
                            <td class="align-middle text-end fw-bold font-monospace">${formatCurrency(row.running_balance)}</td>
                            <td class="align-middle small">${row.remarks || ''}</td>
                        </tr>
                    `;
                    tableBody.append(html);
                });
            } else {
                tableBody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No records found for this category.</td></tr>');
            }
        },
        error: function() {
            tableBody.html('<tr><td colspan="7" class="text-center py-4 text-danger">Error fetching data.</td></tr>');
        }
    });
}

$(document).ready(function() {

    // --- 1. Initialize Financial Overview DataTable ---
    if ($('#financialTable').length) {
        
        financialTable = $('#financialTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip', 
            ajax: {
                url: "api/financial_action.php?action=fetch_overview", 
                type: "GET"
            },
            
            // ⭐ CRITICAL: Triggers the safe stop function after data is received
            drawCallback: function(settings) {
                const icon = $('#refresh-spinner');
                if (icon.hasClass('fa-spin')) { 
                    stopSpinnerSafely();
                } else {
                    updateLastSyncTime(); 
                }
            },

            columns: [
                { 
                    data: 'lastname', 
                    render: function(data, type, row) {
                        return `
                            <div>
                                <div class="fw-bold text-gray-800">${row.lastname}, ${row.firstname}</div>
                                <div class="small text-muted">ID: ${row.employee_id}</div>
                            </div>
                        `;
                    }
                },
                { 
                    data: 'savings_bal', 
                    className: 'text-end',
                    render: function(data) { return `<span class="fw-bold">${formatCurrency(data)}</span>`; }
                },
                { 
                    data: 'sss_bal', 
                    className: 'text-end',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold">${formatCurrency(data)}</span>` : '-'; }
                },
                { 
                    data: 'pagibig_bal', 
                    className: 'text-end',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold">${formatCurrency(data)}</span>` : '-'; }
                },
                { 
                    data: 'company_bal', 
                    className: 'text-end',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold">${formatCurrency(data)}</span>` : '-'; }
                },
                { 
                    data: 'cash_bal', 
                    className: 'text-end',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold">${formatCurrency(data)}</span>` : '-'; }
                },
                {
                    data: 'employee_id',
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        const fullName = `${row.lastname}, ${row.firstname}`;
                        return `
                            <button class="btn btn-sm btn-info shadow-sm text-white me-1" 
                                    title="View History" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#ledgerHistoryModal" 
                                    onclick="viewLedger('${data}')">
                                <i class="fas fa-history"></i>
                            </button>
                            <button class="btn btn-sm btn-teal shadow-sm" 
                                    title="Add Transaction" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#transactionModal" 
                                    onclick="setTransactionEmployee('${data}', '${fullName}')">
                                <i class="fas fa-plus"></i>
                            </button>
                        `;
                    }
                }
            ],
            order: [[ 0, "asc" ]]
        });
    }

    // --- 2. Custom Search Link ---
    $('#financialSearch').on('keyup', function() {
        financialTable.search(this.value).draw();
    });

    // --- 3. Handle Transaction Form Submission ---
    $('#transactionForm').on('submit', function(e) {
        e.preventDefault();
        
        const empId = $('#trans_employee_id').val();
        if(!empId) {
            Swal.fire('Error', 'Please select an employee first.', 'warning');
            return;
        }

        var formData = new FormData(this);

        Swal.fire({
            title: 'Processing Transaction...',
            text: 'Updating ledger and balances...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'api/financial_action.php?action=save_transaction',
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#transactionModal').modal('hide');
                    $('#transactionForm')[0].reset(); 
                    // Reset Amortization Field logic
                    $('#amortization').prop('disabled', true).val('');
                    
                    // Reload table via the new Refresher Hook
                    window.refreshPageContent(); 
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed.', 'error');
            }
        });
    });

    // --- 4. Handle History Filter Change ---
    $('#filter_category').on('change', function() {
        if(currentViewedEmployeeId) {
            loadLedgerData(currentViewedEmployeeId, this.value);
        }
    });

    // --- 5. Reset Transaction Modal on Close ---
    $('#transactionModal').on('hidden.bs.modal', function() {
        $('#transactionForm')[0].reset();
        $('#trans_employee_id').val(''); 
        $('#amortization').prop('disabled', true).val(''); // Reset Amortization
    });

    // --- 6. UX: Toggle Amortization/Contribution Field Logic ---
    function toggleAmortizationField() {
        const type = $('#transaction_type').val();
        const category = $('#category').val();
        const amortInput = $('#amortization');
        const amortLabel = $('#amortization_label');
        const amortHelp = $('#amortization_help');

        // CASE A: SAVINGS (Setting the Pledge)
        if (category === 'Savings') {
            amortLabel.text('Monthly Contribution'); // Change Label
            amortHelp.text('Set the amount to deduct per payroll');
            
            // Allow setting contribution on Deposit or Adjustment
            // (e.g. Initial Deposit sets the rate, or Adjustment changes the rate)
            if (type === 'Deposit' || type === 'Adjustment') {
                amortInput.prop('disabled', false);
            } else {
                amortInput.prop('disabled', true);
                amortInput.val('');
            }
        } 
        // CASE B: LOANS (Setting the Amortization)
        else {
            amortLabel.text('Monthly Amortization'); // Reset Label
            amortHelp.text('For Loan Grants only');

            // Only enable for Loan Grants
            if (type === 'Loan_Grant') {
                amortInput.prop('disabled', false);
                amortInput.prop('required', true); 
            } else {
                amortInput.prop('disabled', true);
                amortInput.val('');
                amortInput.prop('required', false);
            }
        }
    }

    // Bind logic to change events
    $('#transaction_type, #category').on('change', toggleAmortizationField);
    
    // Run once on init
    toggleAmortizationField();


    // --- 7. MASTER REFRESHER HOOK (Linked to Topbar) ---
    window.refreshPageContent = function() {
        // 1. Record Start Time
        spinnerStartTime = new Date().getTime(); 
        
        // 2. Start Visual feedback & Text
        $('#refresh-spinner').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');
        
        // 3. Reload table
        if(financialTable) {
            financialTable.ajax.reload(null, false);
        }
    };

});
</script>