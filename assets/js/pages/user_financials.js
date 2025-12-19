/**
 * Employee Financial Ledger Controller
 * Handles real-time syncing of Loan Balances, Amortization Rates, and General Ledger history.
 */

$(document).ready(function() {

    // ==========================================
    // 1. SYNC / SPINNER VISUALS
    // ==========================================
    let spinnerStartTime = 0;

    function stopSpinnerSafely() {
        const minDisplayTime = 800; // Refined duration for smooth UX
        const timeElapsed = new Date().getTime() - spinnerStartTime;

        const updateUI = () => {
            const timeString = new Date().toLocaleTimeString('en-US', { 
                hour: 'numeric', minute: '2-digit'
            });
            $('#last-updated-time').text(timeString);
            $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-gray-400');
        };

        if (timeElapsed < minDisplayTime) {
            setTimeout(updateUI, minDisplayTime - timeElapsed);
        } else {
            updateUI();
        }
    }
    
    // ==========================================
    // 2. FORMATTING HELPERS
    // ==========================================
    function formatMoney(amount) {
        return '₱' + parseFloat(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getTransactionBadge(type) {
        const badges = {
            'Loan_Grant': 'bg-soft-primary text-primary',
            'Deposit': 'bg-soft-success text-success',
            'Withdrawal': 'bg-soft-danger text-danger',
            'Loan_Payment': 'bg-soft-info text-info',
            'Adjustment': 'bg-soft-warning text-warning'
        };
        const cls = badges[type] || 'bg-soft-secondary text-secondary';
        return `<span class="badge ${cls} border px-2 rounded-pill">${type.replace('_', ' ')}</span>`;
    }

    // ==========================================
    // 3. MAIN DATA FETCH FUNCTION
    // ==========================================
    
    function loadFinancialData() {
        spinnerStartTime = new Date().getTime();
        $('#refresh-spinner').removeClass('text-gray-400').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');

        $.ajax({
            url: '../api/employee/get_financial_ledger_data.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data; 

                    // --- A. Update Loan & Assistance Cards ---
                    const categories = {
                        'sss': data.sss,
                        'pagibig': data.pagibig,
                        'company': data.company_loan,
                        'ca': data.cash_assistance
                    };

                    for (let key in categories) {
                        $(`#${key}-balance`).text(formatMoney(categories[key].balance));
                        $(`#${key}-orig`).text(formatMoney(categories[key].total_loan || categories[key].total_amount));
                        $(`#${key}-amort`).text(formatMoney(categories[key].amortization));
                    }

                    // --- B. Update Summary & Savings ---
                    let totalLiability = 
                        parseFloat(data.sss.balance) + 
                        parseFloat(data.pagibig.balance) + 
                        parseFloat(data.company_loan.balance) + 
                        parseFloat(data.cash_assistance.balance);

                    $('#total-liability').text(formatMoney(totalLiability));
                    $('#savings-total').text(formatMoney(data.savings.current_balance));
                    $('#savings-monthly').text(formatMoney(data.savings.monthly_deduction));
                    $('#last-update').text(data.meta.last_update);

                    // --- C. Populate General Ledger Table ---
                    let rows = '';
                    if(data.general_ledger && data.general_ledger.length > 0) {
                        data.general_ledger.forEach(item => {
                            const isCredit = ['Deposit', 'Loan_Grant'].includes(item.transaction_type);
                            const amountColor = isCredit ? 'text-success' : 'text-danger';
                            const sign = isCredit ? '+' : '-';

                            rows += `
                                <tr class="align-middle">
                                    <td class="font-monospace small">${item.transaction_date}</td>
                                    <td class="fw-bold">${item.category.replace('_', ' ')}</td>
                                    <td class="small text-muted">${item.ref_no || '-'}</td>
                                    <td>${getTransactionBadge(item.transaction_type)}</td>
                                    <td class="small text-muted italic">${item.remarks || ''}</td>
                                    <td class="text-end fw-bold ${amountColor}">
                                        ${sign}${formatMoney(item.amount).replace('₱','')}
                                    </td>
                                    <td class="text-end fw-bolder text-dark">
                                        ${formatMoney(item.running_balance)}
                                    </td>
                                </tr>`;
                        });
                    } else {
                        rows = '<tr><td colspan="7" class="text-center text-muted py-4">No ledger history available.</td></tr>';
                    }
                    $('#ledger-body').html(rows); 
                }
                stopSpinnerSafely();
            },
            error: function() {
                $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-danger');
                $('#last-updated-time').text('Error');
            }
        });
    }

    // ==========================================
    // 4. INITIALIZATION AND REFRESH HOOK
    // ==========================================
    
    window.refreshPageContent = function() {
        loadFinancialData();
    };

    loadFinancialData();
});