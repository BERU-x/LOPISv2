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
                const d = response.data;
                const ledger = response.ledger;

                // A. Update Summary Cards
                $('#total-outstanding').text(formatMoney(d.outstanding_balance));
                $('#last-update').text(d.last_loan_update);
                
                $('#sss-balance').text(formatMoney(d.sss_loan_balance));
                $('#sss-orig').text(formatMoney(d.sss_loan_orig));
                
                $('#pagibig-balance').text(formatMoney(d.pagibig_loan_balance));
                $('#pagibig-orig').text(formatMoney(d.pagibig_loan_orig));
                
                $('#company-balance').text(formatMoney(d.company_loan_balance));
                $('#company-orig').text(formatMoney(d.company_loan_orig));
                
                $('#cash-total').text(formatMoney(d.cash_assist_total));
                $('#cash-deduction').text(formatMoney(d.cash_assist_deduction));
                $('#savings-deduction').text(formatMoney(d.savings_deduction));

                // B. Populate Ledger Table
                let rows = '';
                if(ledger && ledger.length > 0) {
                    ledger.forEach(item => {
                        let typeBadge = (item.transaction_type === 'Deposit') 
                            ? '<span class="badge bg-success">Deposit</span>' 
                            : '<span class="badge bg-danger">Withdrawal</span>';
                        
                        let amountColor = (item.transaction_type === 'Deposit') ? 'text-success' : 'text-danger';

                        rows += `
                            <tr>
                                <td>${item.transaction_date}</td>
                                <td>${item.ref_no || '-'}</td>
                                <td>${typeBadge}</td>
                                <td class="text-end ${amountColor} fw-bold">${formatMoney(item.amount)}</td>
                                <td class="text-end fw-bold">${formatMoney(item.running_balance)}</td>
                            </tr>
                        `;
                    });
                } else {
                    rows = '<tr><td colspan="5" class="text-center text-muted py-3">No transactions found.</td></tr>';
                }
                $('#ledger-body').html(rows);

                // Hide page loader message
                $('#status-message').slideUp();
            } else {
                $('#status-message')
                    .removeClass('alert-info')
                    .addClass('alert-warning')
                    .html('<i class="fas fa-exclamation-triangle me-2"></i> ' + response.message);
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
                .html('<i class="fas fa-times-circle me-2"></i> System Error: Could not fetch balances.');
            
            // Topbar Error
            $('#refresh-spinner').removeClass('fa-spin text-teal').addClass('text-danger');
            $('#last-updated-time').text('Error');
        }
    });
});
</script>