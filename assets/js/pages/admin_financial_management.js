/**
 * Financial Management Controller
 * Updated: Fixed FormData persistence and Sync Engine integration.
 */

var financialTable;
var currentViewedEmployeeId = null;
window.isProcessing = false; 
const API_URL = '../api/admin/financial_action.php'; 

// ==============================================================================
// 1. MASTER REFRESHER HOOK
// ==============================================================================
window.refreshPageContent = function(isManual = false) {
    if (window.isProcessing) return; 

    if (financialTable && $.fn.DataTable.isDataTable('#financialTable')) {
        window.isProcessing = true; 
        
        if (isManual && window.AppUtility) {
            window.AppUtility.updateSyncStatus('loading');
            $('#refreshIcon').addClass('fa-spin');
        }
        
        // Reload without resetting pagination
        financialTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. HELPER: CURRENCY FORMATTER
// ==============================================================================
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', { 
        style: 'currency', 
        currency: 'PHP',
        minimumFractionDigits: 2 
    }).format(parseFloat(amount) || 0);
}

// ==============================================================================
// 3. BUSINESS LOGIC (View/Edit)
// ==============================================================================
window.manageFinancialRecord = function(id, encodedName) {
    currentViewedEmployeeId = id;
    const name = decodeURIComponent(encodedName); 

    Swal.fire({ 
        title: `Fetching Records...`, 
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
            Swal.fire('Error', 'Failed to retrieve profile.', 'error');
        }
    })
    .fail(() => {
        Swal.close();
        Swal.fire('Error', 'Network error.', 'error');
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
        $(`#adjust_${c}_bal`).val(parseFloat(data[`${c}_bal`] || 0).toFixed(2));
        // Amortizations/Contributions
        let amortKey = (c === 'savings') ? 'savings_contrib' : `${c}_amort`;
        $(`#adjust_${amortKey}`).val(parseFloat(data[amortKey] || 0).toFixed(2));
    });

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

            tableBody.append(`
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
                </tr>`);
        });
    } else {
        tableBody.html('<tr><td colspan="6" class="text-center py-4 text-muted">No transactions found.</td></tr>');
    }
}

// ==============================================================================
// 4. INITIALIZATION & EVENTS
// ==============================================================================
$(document).ready(function() {

    // 1. INITIALIZATION
    window.isProcessing = true; 

    financialTable = $('#financialTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, 
        dom: 'rtip', 
        ajax: {
            url: API_URL + "?action=fetch_overview", 
            type: "GET"
        },
        drawCallback: function() {
            if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
            setTimeout(() => {
                $('#refreshIcon').removeClass('fa-spin');
                window.isProcessing = false; 
            }, 500);
        },
        columns: [
            { 
                data: 'lastname', 
                className: 'align-middle',
                render: (d, t, r) => `<div><div class="fw-bold text-dark">${r.lastname}, ${r.firstname}</div>
                                      <div class="small text-muted">ID: ${r.employee_id}</div></div>`
            },
            { data: 'savings_bal', className: 'text-end align-middle font-monospace', render: d => formatCurrency(d) },
            { data: 'sss_bal', className: 'text-end align-middle font-monospace', render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '-' },
            { data: 'pagibig_bal', className: 'text-end align-middle font-monospace', render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '-' },
            { data: 'company_bal', className: 'text-end align-middle font-monospace', render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '-' },
            { data: 'cash_bal', className: 'text-end align-middle font-monospace', render: d => parseFloat(d) > 0 ? `<span class="text-danger">${formatCurrency(d)}</span>` : '-' },
            {
                data: 'employee_id',
                orderable: false,
                className: 'text-center align-middle',
                render: (d, t, r) => {
                    const name = encodeURIComponent(`${r.lastname}, ${r.firstname}`);
                    return `<button class="btn btn-sm btn-outline-secondary shadow-sm fw-bold" onclick="manageFinancialRecord('${d}', '${name}')">
                            <i class="fa-solid fa-folder-tree me-1"></i> Manage</button>`;
                }
            }
        ]
    });

    // 2. EVENT BINDINGS
    $('#financialSearch').on('keyup', function() { 
        financialTable.search(this.value).draw(); 
    });

    $('#adjustmentForm').on('submit', function(e) {
        e.preventDefault();

        // ⭐ FIXED: Create object once and ensure ID is included
        let formData = new FormData(this);
        if (currentViewedEmployeeId) {
            formData.set('employee_id', currentViewedEmployeeId);
        }

        Swal.fire({ title: 'Applying Adjustments...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: API_URL + '?action=update_financial_record',
            type: 'POST',
            data: formData, // ⭐ FIXED: Use the prepared variable
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
            },
            error: (xhr) => {
                console.error(xhr.responseText);
                Swal.fire('Server Error', 'Check console for details.', 'error');
            }
        });
    });

    $('#filter_category').on('change', function() {
        if(currentViewedEmployeeId) {
            $('#ledgerTableBody').html('<tr><td colspan="6" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Filtering...</td></tr>');
            $.getJSON(API_URL, { action: 'fetch_ledger', employee_id: currentViewedEmployeeId, category: this.value }, (res) => {
                populateLedgerTable(res.data);
            });
        }
    });
});