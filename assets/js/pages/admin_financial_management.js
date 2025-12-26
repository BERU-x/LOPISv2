/**
 * Financial Management Controller
 * Updated: Matches Footer Auto-Sync Architecture
 */

var financialTable;
var currentViewedEmployeeId = null;
window.isProcessing = false; // ⭐ The "Lock"
const API_URL = '../api/admin/financial_action.php'; 

// ==============================================================================
// 1. MASTER REFRESHER HOOK (Called by Footer)
// ==============================================================================
window.refreshPageContent = function(isManual = false) {
    // 1. Block if already loading
    if (window.isProcessing) return; 

    if (financialTable && $.fn.DataTable.isDataTable('#financialTable')) {
        window.isProcessing = true; 
        
        // 2. UI Feedback (Only if manual click)
        if (isManual) {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');
            $('#refreshIcon').addClass('fa-spin');
        }
        
        // 3. Reload Data (null = no callback, false = keep paging)
        // We let 'drawCallback' below handle the unlocking/success state
        financialTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. HELPER: CURRENCY FORMATTER
// ==============================================================================
function formatCurrency(amount) {
    amount = parseFloat(amount) || 0; 
    return new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP',
        minimumFractionDigits: 2 
    }).format(amount);
}

// ==============================================================================
// 3. BUSINESS LOGIC (View/Edit) - Kept exactly as provided
// ==============================================================================
window.manageFinancialRecord = function(id, encodedName) {
    currentViewedEmployeeId = id;
    const name = decodeURIComponent(encodedName); 

    Swal.fire({ 
        title: `Fetching Records for ${name}...`, 
        allowOutsideClick: false, 
        didOpen: () => { Swal.showLoading(); } 
    });

    const recordFetch = $.ajax({
        url: API_URL + '?action=get_financial_record',
        type: 'POST',
        data: { employee_id: id },
        dataType: 'json'
    });
    
    const ledgerFetch = $.ajax({
        url: API_URL + '?action=fetch_ledger',
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
            populateAdjustmentForm(recordResult.data, name); 
            populateLedgerTable(ledgerResult.data);
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

function populateAdjustmentForm(data, name) {
    $('#combined_employee_name').text(name);
    $('#adjust_employee_id').val(data.employee_id); 
    
    // Compensation
    $('#adjust_daily_rate').val(data.daily_rate);
    $('#adjust_monthly_rate').val(data.monthly_rate);
    $('#adjust_food_allowance').val(data.food_allowance);
    $('#adjust_transpo_allowance').val(data.transpo_allowance);

    // Balances & Amortizations
    const cats = ['savings', 'sss', 'pagibig', 'company', 'cash'];
    cats.forEach(c => {
        $(`#adjust_${c}_bal`).val(parseFloat(data[`${c}_bal`]).toFixed(2));
    });
    $('#adjust_savings_contrib').val(parseFloat(data.savings_contrib).toFixed(2));
    $('#adjust_sss_amort').val(parseFloat(data.sss_amort).toFixed(2));
    $('#adjust_pagibig_amort').val(parseFloat(data.pagibig_amort).toFixed(2));
    $('#adjust_company_amort').val(parseFloat(data.company_amort).toFixed(2));
    $('#adjust_cash_amort').val(parseFloat(data.cash_amort).toFixed(2));

    $('#adjustment_remarks').val(''); 
}

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
                </tr>`;
            tableBody.append(html);
        });
    } else {
        tableBody.html('<tr><td colspan="6" class="text-center py-4 text-muted">No historical transactions found.</td></tr>');
    }
}

// ==============================================================================
// 4. INITIALIZATION & EVENTS
// ==============================================================================
$(document).ready(function() {

    // 1. CLEANUP (Standardized)
    if ($.fn.DataTable.isDataTable('#financialTable')) {
        $('#financialTable').DataTable().destroy();
        $('#financialTable tbody').empty();
    }

    // 2. INITIALIZATION
    window.isProcessing = true; // Lock immediately on load

    financialTable = $('#financialTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: API_URL + "?action=fetch_overview", 
            type: "GET"
        },
        // ⭐ CENTRALIZED UNLOCKING
        // Runs after Initial Load, Search, Sort, or Refresh
        drawCallback: function(settings) {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            
            setTimeout(() => {
                $('#refreshIcon').removeClass('fa-spin');
                window.isProcessing = false; // 🔓 Release lock
            }, 500);
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
                    return `<button class="btn btn-sm btn-outline-secondary shadow-sm fw-bold" onclick="manageFinancialRecord('${data}', '${name}')">
                            <i class="fa-solid fa-folder-tree me-1"></i> Manage</button>`;
                }
            }
        ]
    });

    // 3. EVENT BINDINGS
    $('#financialSearch').on('keyup', function() { 
        financialTable.search(this.value).draw(); 
    });

    // Manual Refresh Button
    $('#refreshIcon').parent().on('click', function(e) {
        e.preventDefault();
        window.refreshPageContent(true);
    });

    // Form Submission
    $('#adjustmentForm').on('submit', function(e) {
        e.preventDefault();

        let formData = new FormData(this);

        if (currentViewedEmployeeId) {
            formData.set('employee_id', currentViewedEmployeeId);
        } else {
            Swal.fire('Error', 'System lost track of the Employee ID. Please close and reopen the modal.', 'error');
            return;
        }

        Swal.fire({ title: 'Applying Adjustments...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: API_URL + '?action=update_financial_record',
            type: 'POST',
            data: new FormData(this), // Re-grab form data to ensure updates
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('Updated', res.message, 'success');
                    $('#combinedFinancialModal').modal('hide');
                    window.refreshPageContent(true); // Force refresh
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                Swal.fire('Server Error', 'Check console for details.', 'error');
            }
        });
    });

    // Ledger Filter
    $('#filter_category').on('change', function() {
        if(currentViewedEmployeeId) {
            $('#ledgerTableBody').html('<tr><td colspan="6" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Filtering...</td></tr>');
            $.getJSON(API_URL, { action: 'fetch_ledger', employee_id: currentViewedEmployeeId, category: this.value }, function(res) {
                populateLedgerTable(res.data);
            });
        }
    });
});