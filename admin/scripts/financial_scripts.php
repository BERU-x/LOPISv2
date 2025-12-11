<?php
// scripts/financial_management_scripts.php
if (!isset($employment_statuses)) {
    $employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];
}
?>

<script>
// IMPORTANT: Ensure Bootstrap's JS (jQuery and bootstrap.bundle.min.js) are loaded before this script runs.

// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS
// ==============================================================================
var financialTable;
var currentViewedEmployeeId = null; 
let spinnerStartTime = 0; 

// 1.1 HELPER: Updates the final timestamp text (Refresher Logic)
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.2 HELPER: Stops the spinner safely (Refresher Logic)
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

// 1.3 HELPER: Format Currency (PHP)
function formatCurrency(amount) {
    amount = parseFloat(amount) || 0; 
    return new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP',
        minimumFractionDigits: 2 
    }).format(amount);
}

// 1.4 MASTER REFRESHER TRIGGER (Hook for Topbar/Buttons)
window.refreshPageContent = function() {
    spinnerStartTime = new Date().getTime(); 
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    if(financialTable) {
        financialTable.ajax.reload(null, false);
    }
};

// 1.5 NEW MAIN FUNCTION: Manages both View and Update in one modal
window.manageFinancialRecord = function(id, encodedName) {
    currentViewedEmployeeId = id; 
    const name = decodeURIComponent(encodedName); 

    // 1. Show Loading Indicator
    Swal.fire({ 
        title: `Fetching Records for ${name}...`, 
        allowOutsideClick: false, 
        didOpen: () => { Swal.showLoading(); } 
    });

    // 2. Define AJAX calls
    const recordFetch = $.ajax({
        url: 'api/financial_action.php?action=get_financial_record',
        type: 'POST',
        data: { employee_id: id },
        dataType: 'json'
    });
    
    const ledgerFetch = $.ajax({
        url: 'api/financial_action.php?action=fetch_ledger',
        type: 'GET',
        data: { employee_id: id, category: 'All' },
        dataType: 'json'
    });

    // 3. Use $.when to handle both
    $.when(recordFetch, ledgerFetch)
    .done(function(recordResponse, ledgerResponse) {
        Swal.close();

        const recordResult = recordResponse[0];
        const ledgerResult = ledgerResponse[0];

        // Check the custom status returned by PHP for both
        if (recordResult.status === 'success' && ledgerResult.status === 'success') {
            
            // A. Populate Adjustment Form (recordResult.data)
            populateAdjustmentForm(recordResult.data, name); 

            // B. Populate Ledger History Tab (ledgerResult.data)
            populateLedgerTable(ledgerResult.data);
            
            // C. Show the combined modal
            $('#combinedFinancialModal').modal('show');

        } else {
            // One call succeeded but returned a PHP-side error status
            let message = "Failed to load all financial data. ";
            if (recordResult.status !== 'success') {
                message += `Record Error: ${recordResult.message}. `;
            }
            if (ledgerResult.status !== 'success') {
                message += `Ledger Error: ${ledgerResult.message}.`;
            }
            Swal.fire('Error', message, 'error');
        }
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        Swal.close();
        let errorMessage = `Network/Parsing Error during fetch. Status: ${textStatus}.`;
        
        if (jqXHR.responseText) {
             console.error("AJAX Failed Response Text:", jqXHR.responseText);
             errorMessage += " Check console for server error details.";
        }
        
        Swal.fire('Error', errorMessage, 'error');
    });
};

// 1.6 Helper to populate the Ledger Table (History Tab)
function populateLedgerTable(data) {
    const tableBody = $('#ledgerTableBody');
    tableBody.empty();
    
    if (data && data.length > 0) {
        data.forEach(function(row) {
            let amortDisplay = parseFloat(row.amortization) > 0 
                ? `<div class="small text-muted fst-italic mt-1">Amort: ${formatCurrency(row.amortization)}/mo</div>` 
                : '';

            const html = `
                <tr>
                    <td class="align-middle">${row.transaction_date}</td>
                    <td class="align-middle fw-bold">${row.category.replace('_', ' ')}</td>
                    <td class="align-middle">
                        ${row.transaction_type.replace('_', ' ')}
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
        tableBody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No records found.</td></tr>');
    }
}

// 1.7 Helper to populate the Adjustment/Update Form
function populateAdjustmentForm(data, name) {
    // Update header/hidden ID
    $('#combined_employee_name').text(name);
    // CRITICAL: Set the ID which is now inside the form
    $('#adjust_employee_id').val(data.employee_id); 
    
    // --- Populate Balances ---
    $('#adjust_savings_bal').val(parseFloat(data.savings_bal).toFixed(2));
    $('#adjust_sss_bal').val(parseFloat(data.sss_bal).toFixed(2));
    $('#adjust_pagibig_bal').val(parseFloat(data.pagibig_bal).toFixed(2));
    $('#adjust_company_bal').val(parseFloat(data.company_bal).toFixed(2));
    $('#adjust_cash_bal').val(parseFloat(data.cash_bal).toFixed(2));

    // --- Populate Contribution/Amortization fields ---
    $('#adjust_savings_contrib').val(parseFloat(data.savings_contrib).toFixed(2));
    $('#adjust_sss_amort').val(parseFloat(data.sss_amort).toFixed(2));
    $('#adjust_pagibig_amort').val(parseFloat(data.pagibig_amort).toFixed(2));
    $('#adjust_company_amort').val(parseFloat(data.company_amort).toFixed(2));
    $('#adjust_cash_amort').val(parseFloat(data.cash_amort).toFixed(2));

    // Optional: Reset remarks on load
    $('#adjustment_remarks').val(''); 
}

// 1.8 Helper to filter Ledger data (used on history tab filter change)
function loadLedgerData(empId, category) {
    const tableBody = $('#ledgerTableBody');
    tableBody.html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading history...</td></tr>');

    $.ajax({
        url: 'api/financial_action.php?action=fetch_ledger',
        type: 'GET',
        data: { employee_id: empId, category: category },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                populateLedgerTable(response.data);
            } else {
                tableBody.html('<tr><td colspan="7" class="text-center py-4 text-danger">Error fetching data.</td></tr>');
            }
        },
        error: function() {
            tableBody.html('<tr><td colspan="7" class="text-center py-4 text-danger">Error fetching data.</td></tr>');
        }
    });
}

// --- MAIN DOCUMENT READY BLOCK ---
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
            
            drawCallback: function(settings) {
                const icon = $('#refresh-spinner');
                if (icon.hasClass('fa-spin')) { 
                    stopSpinnerSafely();
                } else {
                    updateLastSyncTime(); 
                }
            },

            columns: [
                // Col 1: Name & ID
                { 
                    data: 'lastname', 
                    className: 'align-middle',
                    render: function(data, type, row) {
                        return `
                            <div>
                                <div class="fw-bold text-gray-800">${row.lastname}, ${row.firstname}</div>
                                <div class="small text-muted">ID: ${row.employee_id}</div>
                            </div>
                        `;
                    }
                },
                // Col 2-6: Balances (Unchanged)
                { 
                    data: 'savings_bal', 
                    className: 'text-end align-middle',
                    render: function(data) { return `<span class="fw-bold">${formatCurrency(data)}</span>`; }
                },
                { 
                    data: 'sss_bal', 
                    className: 'text-end align-middle',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold text-danger">${formatCurrency(data)}</span>` : '-'; }
                },
                { 
                    data: 'pagibig_bal', 
                    className: 'text-end align-middle',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold text-danger">${formatCurrency(data)}</span>` : '-'; }
                },
                { 
                    data: 'company_bal', 
                    className: 'text-end align-middle',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold text-danger">${formatCurrency(data)}</span>` : '-'; }
                },
                { 
                    data: 'cash_bal', 
                    className: 'text-end align-middle',
                    render: function(data) { return parseFloat(data) > 0 ? `<span class="fw-bold text-danger">${formatCurrency(data)}</span>` : '-'; }
                },
                // Col 7: Actions (Combined Button)
                {
                    data: 'employee_id',
                    orderable: false,
                    className: 'text-center align-middle',
                    render: function(data, type, row) {
                        const fullName = `${row.lastname}, ${row.firstname}`;
                        const encodedFullName = encodeURIComponent(fullName);
                        
                        return `
                            <button class="btn btn-sm btn-teal shadow-sm" 
                                    title="Manage Record and History" 
                                    onclick="manageFinancialRecord('${data}', '${encodedFullName}')">
                                <i class="fa-solid fa-folder-tree me-1"></i> Manage
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
        if (financialTable) {
            financialTable.search(this.value).draw();
        }
    });

    // --- 3. Handle History Filter Change ---
    // Now applies to the combined modal's history tab
    $('#filter_category').on('change', function() {
        if(currentViewedEmployeeId) {
            // Note: The select is inside the history pane of the combined modal
            loadLedgerData(currentViewedEmployeeId, this.value);
        }
    });
    
    // --- 4. Handle Adjustment Form Submission ---
    $('#adjustmentForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Updating Financial Record...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        var formData = new FormData(this);

        $.ajax({
            url: 'api/financial_action.php?action=update_financial_record',
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    $('#combinedFinancialModal').modal('hide');
                    window.refreshPageContent(); 
                } else {
                    Swal.fire('Error', res.message || 'Update failed.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed. Please check network and server logs.', 'error');
            }
        });
    });
    
    // --- 5. Reset Form on Modal Close ---
    $('#combinedFinancialModal').on('hidden.bs.modal', function() {
        // Reset the form values
        $('#adjustmentForm')[0].reset();
        // Reset the filter for the next time the modal opens
        $('#filter_category').val('All');
        // Reset the history table display
        $('#ledgerTableBody').html('<tr><td colspan="7" class="text-center py-4 text-muted">Select an employee to view history.</td></tr>');
    });
});
</script>