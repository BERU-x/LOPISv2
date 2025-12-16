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

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
 * @param {string} state - 'loading', 'success', or 'error'
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

// 1.2 HELPER: Format Currency (PHP)
function formatCurrency(amount) {
    amount = parseFloat(amount) || 0; 
    return new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP',
        minimumFractionDigits: 2 
    }).format(amount);
}

// 1.3 MASTER REFRESHER TRIGGER
// isManual = true (Spin Icon) | isManual = false (Silent)
window.refreshPageContent = function(isManual = false) {
    if (financialTable) {
        // 1. Visual Feedback for Manual Actions
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        
        // 2. Reload DataTable (false = keep paging)
        financialTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. BUSINESS LOGIC (View/Edit/Ledger)
// ==============================================================================

// 2.1 NEW MAIN FUNCTION: Manages both View and Update in one modal
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

// 2.2 Helper to populate the Ledger Table (History Tab)
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

// 2.3 Helper to populate the Adjustment/Update Form
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

// 2.4 Helper to filter Ledger data (used on history tab filter change)
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

// ==============================================================================
// 3. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    // --- 3.1 Initialize Financial Overview DataTable ---
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
            // DRAW CALLBACK: Standardized UI updates
            drawCallback: function(settings) {
                updateSyncStatus('success');
                setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
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
                // Col 2-6: Balances
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

    // --- 3.2 DETECT LOADING STATE ---
    $('#financialTable').on('processing.dt', function (e, settings, processing) {
        if (processing && !$('#refreshIcon').hasClass('fa-spin')) {
            updateSyncStatus('loading');
        }
    });

    // --- 3.3 Custom Search Link ---
    $('#financialSearch').on('keyup', function() {
        if (financialTable) {
            financialTable.search(this.value).draw();
        }
    });

    // --- 3.4 Handle History Filter Change ---
    $('#filter_category').on('change', function() {
        if(currentViewedEmployeeId) {
            loadLedgerData(currentViewedEmployeeId, this.value);
        }
    });
    
    // --- 3.5 Handle Adjustment Form Submission ---
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
                    window.refreshPageContent(true); // Visual Refresh on Success
                } else {
                    Swal.fire('Error', res.message || 'Update failed.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server request failed. Please check network and server logs.', 'error');
            }
        });
    });
    
    // --- 3.6 Reset Form on Modal Close ---
    $('#combinedFinancialModal').on('hidden.bs.modal', function() {
        // Reset the form values
        $('#adjustmentForm')[0].reset();
        // Reset the filter for the next time the modal opens
        $('#filter_category').val('All');
        // Reset the history table display
        $('#ledgerTableBody').html('<tr><td colspan="7" class="text-center py-4 text-muted">Select an employee to view history.</td></tr>');
    });

    // --- 3.7 Manual Refresh Button Listener ---
    $('#btn-refresh').on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });
});
</script>