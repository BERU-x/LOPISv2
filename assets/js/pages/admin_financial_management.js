/**
 * Financial Management Controller
 * Handles Financial Overviews, Ledger Histories, and Manual Adjustments.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var financialTable;
var currentViewedEmployeeId = null; 

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

/**
 * 1.2 HELPER: Format Currency (PHP)
 */
function formatCurrency(amount) {
    amount = parseFloat(amount) || 0; 
    return new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP',
        minimumFractionDigits: 2 
    }).format(amount);
}

// 1.3 MASTER REFRESHER TRIGGER
window.refreshPageContent = function(isManual = false) {
    if (financialTable) {
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        financialTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. BUSINESS LOGIC (View/Edit/Ledger)
// ==============================================================================

window.manageFinancialRecord = function(id, encodedName) {
    currentViewedEmployeeId = id; 
    const name = decodeURIComponent(encodedName); 

    Swal.fire({ 
        title: `Fetching Records for ${name}...`, 
        allowOutsideClick: false, 
        didOpen: () => { Swal.showLoading(); } 
    });

    // Parallel fetch for current balances and ledger history
    const recordFetch = $.ajax({
        url: '../api/admin/financial_action.php?action=get_financial_record',
        type: 'POST',
        data: { employee_id: id },
        dataType: 'json'
    });
    
    const ledgerFetch = $.ajax({
        url: '../api/admin/financial_action.php?action=fetch_ledger',
        type: 'GET',
        data: { employee_id: id, category: 'All' },
        dataType: 'json'
    });

    $.when(recordFetch, ledgerFetch)
    .done(function(recordResponse, ledgerResponse) {
        Swal.close();

        const recordResult = recordResponse[0];
        const ledgerResult = ledgerResponse[0];

        if (recordResult.status === 'success' && ledgerResult.status === 'success') {
            
            // A. Populate Adjustment Form
            populateAdjustmentForm(recordResult.data, name); 

            // B. Populate Ledger History Tab
            populateLedgerTable(ledgerResult.data);
            
            // C. Show combined modal
            $('#combinedFinancialModal').modal('show');

        } else {
            Swal.fire('Error', 'Failed to retrieve complete financial profile.', 'error');
        }
    })
    .fail(function() {
        Swal.close();
        Swal.fire('Error', 'Network error while accessing financial data.', 'error');
    });
};

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
                    <td class="align-middle small">${row.transaction_date}</td>
                    <td class="align-middle fw-bold">${row.category.replace('_', ' ')}</td>
                    <td class="align-middle">
                        <span class="badge bg-light text-dark border">${row.transaction_type}</span>
                        ${amortDisplay} 
                    </td>
                    <td class="align-middle text-end font-monospace">${formatCurrency(row.amount)}</td>
                    <td class="align-middle text-end fw-bold font-monospace text-primary">${formatCurrency(row.running_balance)}</td>
                    <td class="align-middle small text-muted">${row.remarks || ''}</td>
                </tr>
            `;
            tableBody.append(html);
        });
    } else {
        tableBody.html('<tr><td colspan="6" class="text-center py-4 text-muted">No historical transactions found.</td></tr>');
    }
}

function populateAdjustmentForm(data, name) {
    $('#combined_employee_name').text(name);
    $('#adjust_employee_id').val(data.employee_id); 
    
    // Set Balances
    const cats = ['savings', 'sss', 'pagibig', 'company', 'cash'];
    cats.forEach(c => {
        $(`#adjust_${c}_bal`).val(parseFloat(data[`${c}_bal`]).toFixed(2));
    });

    // Set Amortizations/Contributions
    $('#adjust_savings_contrib').val(parseFloat(data.savings_contrib).toFixed(2));
    $('#adjust_sss_amort').val(parseFloat(data.sss_amort).toFixed(2));
    $('#adjust_pagibig_amort').val(parseFloat(data.pagibig_amort).toFixed(2));
    $('#adjust_company_amort').val(parseFloat(data.company_amort).toFixed(2));
    $('#adjust_cash_amort').val(parseFloat(data.cash_amort).toFixed(2));

    $('#adjustment_remarks').val(''); 
}

// ==============================================================================
// 3. INITIALIZATION & EVENTS
// ==============================================================================
$(document).ready(function() {

    // 3.1 Initialize Overview Table
    if ($('#financialTable').length) {
        financialTable = $('#financialTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip', 
            ajax: {
                url: "../api/admin/financial_action.php?action=fetch_overview", 
                type: "GET"
            },
            drawCallback: function() {
                updateSyncStatus('success');
                setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
            },
            columns: [
                { 
                    data: 'lastname', 
                    className: 'align-middle',
                    render: function(data, type, row) {
                        return `<div><div class="fw-bold text-dark">${row.lastname}, ${row.firstname}</div>
                                <div class="small text-muted">ID: ${row.employee_id}</div></div>`;
                    }
                },
                { data: 'savings_bal', className: 'text-end align-middle font-monospace', render: d => formatCurrency(d) },
                { 
                    data: 'sss_bal', 
                    className: 'text-end align-middle font-monospace', 
                    render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '<span class="text-muted">-</span>' 
                },
                { 
                    data: 'pagibig_bal', 
                    className: 'text-end align-middle font-monospace', 
                    render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '<span class="text-muted">-</span>' 
                },
                { 
                    data: 'company_bal', 
                    className: 'text-end align-middle font-monospace', 
                    render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '<span class="text-muted">-</span>' 
                },
                { 
                    data: 'cash_bal', 
                    className: 'text-end align-middle font-monospace', 
                    render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '<span class="text-muted">-</span>' 
                },
                {
                    data: 'employee_id',
                    orderable: false,
                    className: 'text-center align-middle',
                    render: function(data, type, row) {
                        const name = encodeURIComponent(`${row.lastname}, ${row.firstname}`);
                        return `<button class="btn btn-sm btn-teal shadow-sm fw-bold" onclick="manageFinancialRecord('${data}', '${name}')">
                                <i class="fa-solid fa-folder-tree me-1"></i> Manage</button>`;
                    }
                }
            ]
        });
    }

    // 3.2 Form Submission
    $('#adjustmentForm').on('submit', function(e) {
        e.preventDefault();
        Swal.fire({ title: 'Applying Adjustments...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: '../api/admin/financial_action.php?action=update_financial_record',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Updated', res.message, 'success');
                    $('#combinedFinancialModal').modal('hide');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // 3.3 Event Listeners
    $('#financialSearch').on('keyup', function() { financialTable.search(this.value).draw(); });
    $('#filter_category').on('change', function() {
        if(currentViewedEmployeeId) {
            $('#ledgerTableBody').html('<tr><td colspan="6" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Filtering...</td></tr>');
            $.getJSON('../api/admin/financial_action.php', { action: 'fetch_ledger', employee_id: currentViewedEmployeeId, category: this.value }, function(res) {
                populateLedgerTable(res.data);
            });
        }
    });
});