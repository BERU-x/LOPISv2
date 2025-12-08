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

    // ==========================================
    // 3. MAIN DATA FETCH
    // ==========================================
    
    // Start Visuals
    spinnerStartTime = new Date().getTime();
    $('#refresh-spinner').removeClass('text-gray-400').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');

    $.ajax({
        url: 'api/get_balances.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            
            if (response.status === 'success') {
                const data = response.data; // New structure: sss, pagibig, company_loan, etc.
                const ledger = data.savings.history; // Ledger is now inside savings

                // -----------------------------------
                // A. Update Loan Cards
                // -----------------------------------
                
                // SSS
                $('#sss-balance').text(formatMoney(data.sss.balance));
                $('#sss-orig').text(formatMoney(data.sss.total_loan));
                
                // Pag-IBIG
                $('#pagibig-balance').text(formatMoney(data.pagibig.balance));
                $('#pagibig-orig').text(formatMoney(data.pagibig.total_loan));
                
                // Company Loan
                $('#company-balance').text(formatMoney(data.company_loan.balance));
                $('#company-orig').text(formatMoney(data.company_loan.total_loan));
                
                // Cash Assistance (Updated IDs)
                $('#ca-balance').text(formatMoney(data.cash_assistance.balance));
                $('#ca-total').text(formatMoney(data.cash_assistance.total_amount));
                $('#ca-amort').text(formatMoney(data.cash_assistance.amortization));

                // -----------------------------------
                // B. Update Summary Cards (Liabilities vs Savings)
                // -----------------------------------

                // Calculate Total Liability (Sum of all balances)
                let totalLiability = 
                    parseFloat(data.sss.balance) + 
                    parseFloat(data.pagibig.balance) + 
                    parseFloat(data.company_loan.balance) + 
                    parseFloat(data.cash_assistance.balance);

                $('#total-liability').text(formatMoney(totalLiability));
                
                // Savings Summary
                $('#savings-total').text(formatMoney(data.savings.current_balance));
                $('#savings-monthly').text(formatMoney(data.savings.monthly_deduction));
                
                // Last Update Date
                $('#last-update').text(data.meta.last_update);

                // -----------------------------------
                // C. Populate Ledger Table
                // -----------------------------------
                let rows = '';
                if(ledger && ledger.length > 0) {
                    ledger.forEach(item => {
                        let typeBadge = (item.transaction_type === 'Deposit') 
                            ? '<span class="badge badge-success">Deposit</span>' 
                            : '<span class="badge badge-warning">Withdrawal</span>';
                        
                        let amountColor = (item.transaction_type === 'Deposit') ? 'text-success' : 'text-danger';
                        let sign = (item.transaction_type === 'Deposit') ? '+' : '-';

                        rows += `
                            <tr>
                                <td>${item.transaction_date}</td>
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
                    rows = '<tr><td colspan="6" class="text-center text-muted py-3">No transactions found.</td></tr>';
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
});
</script>