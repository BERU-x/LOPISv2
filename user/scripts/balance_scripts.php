<script>
$(document).ready(function() {

    // ==========================================
    // 1. SYNC / SPINNER VISUALS
    // ==========================================
    let spinnerStartTime = 0;

    function stopSpinnerSafely() {
        const minDisplayTime = 1000; // Min duration 1s to prevent flicker
        const timeElapsed = new Date().getTime() - spinnerStartTime;

        const updateTime = () => {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                second: '2-digit'
            });
            
            // Update Topbar
            $('#last-updated-time').text(timeString);
            $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-gray-400');
        };

        if (timeElapsed < minDisplayTime) {
            setTimeout(updateTime, minDisplayTime - timeElapsed);
        } else {
            updateTime();
        }
    }
    
    // ==========================================
    // 2. HELPER FUNCTIONS
    // ==========================================
    function formatMoney(amount) {
        return '₱' + parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // Function to determine badge class based on transaction type
    function getTransactionBadge(type) {
        if (type === 'Deposit' || type === 'Loan_Grant') return '<span class="badge bg-success">Deposit/Grant</span>';
        if (type === 'Withdrawal' || type === 'Loan_Payment' || type === 'Adjustment') return '<span class="badge bg-danger">Withdrawal/Payment</span>';
        return '<span class="badge bg-secondary">Other</span>';
    }

    // ==========================================
    // 3. MAIN DATA FETCH FUNCTION
    // ==========================================
    
    function loadFinancialData() {
        // Start Visuals
        spinnerStartTime = new Date().getTime();
        $('#refresh-spinner').removeClass('text-gray-400').addClass('fa-spin text-teal');
        $('#last-updated-time').text('Syncing...');
        $('#status-message').slideDown().html('<i class="fas fa-spinner fa-spin me-2"></i> Loading financial data...');

        $.ajax({
            url: 'api/get_balances.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                
                if (response.status === 'success') {
                    const data = response.data; 
                    const generalLedger = data.general_ledger; 

                    // -----------------------------------
                    // A. Update Loan Cards
                    // -----------------------------------
                    $('#sss-balance').text(formatMoney(data.sss.balance));
                    $('#sss-orig').text(formatMoney(data.sss.total_loan));
                    $('#sss-amort').text(formatMoney(data.sss.amortization));
                    
                    $('#pagibig-balance').text(formatMoney(data.pagibig.balance));
                    $('#pagibig-orig').text(formatMoney(data.pagibig.total_loan));
                    $('#pagibig-amort').text(formatMoney(data.pagibig.amortization));
                    
                    $('#company-balance').text(formatMoney(data.company_loan.balance));
                    $('#company-orig').text(formatMoney(data.company_loan.total_loan));
                    $('#company-amort').text(formatMoney(data.company_loan.amortization));

                    $('#ca-balance').text(formatMoney(data.cash_assistance.balance));
                    $('#ca-total').text(formatMoney(data.cash_assistance.total_amount));
                    $('#ca-amort').text(formatMoney(data.cash_assistance.amortization));

                    // -----------------------------------
                    // B. Update Summary Cards
                    // -----------------------------------
                    let totalLiability = 
                        parseFloat(data.sss.balance) + 
                        parseFloat(data.pagibig.balance) + 
                        parseFloat(data.company_loan.balance) + 
                        parseFloat(data.cash_assistance.balance);

                    $('#total-liability').text(formatMoney(totalLiability));
                    
                    $('#savings-total').text(formatMoney(data.savings.current_balance));
                    $('#savings-monthly').text(formatMoney(data.savings.monthly_deduction));
                    
                    $('#last-update').text(data.meta.last_update);

                    // -----------------------------------
                    // C. Populate Ledger Table
                    // -----------------------------------
                    let rows = '';
                    if(generalLedger && generalLedger.length > 0) {
                        generalLedger.forEach(item => {
                            let typeBadge = getTransactionBadge(item.transaction_type);
                            
                            // Determine amount sign and color based on transaction type
                            let isCredit = (item.transaction_type === 'Deposit' || item.transaction_type === 'Loan_Grant');
                            let amountColor = isCredit ? 'text-success' : 'text-danger';
                            let sign = isCredit ? '+' : '-';

                            rows += `
                                <tr>
                                    <td>${item.transaction_date}</td>
                                    <td>${item.category}</td>
                                    <td>${item.ref_no || '-'}</td>
                                    <td>${typeBadge}</td>
                                    <td>${item.remarks || ''}</td>
                                    <td class="text-right ${amountColor}">
                                        ${sign} ${formatMoney(item.amount)}
                                    </td>
                                    <td class="text-right font-weight-bold">
                                        ${formatMoney(item.running_balance)}
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        // Colspan set to 7 to match table headers
                        rows = '<tr><td colspan="7" class="text-center text-muted py-3">No financial transactions found in the ledger.</td></tr>';
                    }
                    $('#ledger-body').html(rows); 

                    // Hide page loader message
                    $('#status-message').slideUp();
                } else {
                    $('#status-message')
                        .removeClass('alert-info')
                        .addClass('alert-warning')
                        .html('<i class="fas fa-exclamation-triangle me-2"></i> ' + response.message).show();
                }

                // Stop Topbar Spinner
                stopSpinnerSafely();
            },
            error: function(xhr, status, error) {
                console.error(error);
                // Page Error
                $('#status-message')
                    .removeClass('alert-info')
                    .addClass('alert-danger')
                    .html('<i class="fas fa-times-circle me-2"></i> System Error: Could not fetch balances.').show();
                
                // Topbar Error
                $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-danger');
                $('#last-updated-time').text('Error');
            }
        });
    }

    // ==========================================
    // 4. INITIALIZATION AND REFRESH HOOK
    // ==========================================
    
    // Global hook for refreshing the content (Called by topbar button)
    window.refreshPageContent = function() {
        loadFinancialData();
    };

    // Initial load when the page is ready
    loadFinancialData();
});
</script>