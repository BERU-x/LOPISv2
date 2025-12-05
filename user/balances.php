<?php
// balances.php

$page_title = 'Financial Balances';
$current_page = 'balances'; 

// NO PHP REQUIRE HERE - Data will be fetched via AJAX

// Assuming these template files are available
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php'; 
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Financial Balances Detail</h1>
    
    <p class="text-sm text-muted mb-4" id="status-message">Loading financial data...</p>

    <div class="card shadow mb-4 bg-soft-teal">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-8">
                    <div class="text-xs font-weight-bold text-uppercase mb-1 text-label">Total Outstanding Liability</div>
                    <div class="h2 mb-0 font-weight-bold" id="total-outstanding">
                        ₱0.00
                    </div>
                </div>
            </div>
            <p class="text-xs mt-3 mb-0 text-label">Last Loan Update: <span id="last-update">N/A</span></p>
        </div>
    </div>
    
    <div class="row" id="loans-breakdown-container">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 py-2 border-left-danger">
                <div class="card-body">
                    <h6 class="font-weight-bold mb-3 text-label">SSS Loan</h6>
                    <p class="mb-0 text-gray-800">Remaining Balance: <span id="sss-balance">₱0.00</span></p>
                    <p class="text-xs text-muted mb-0">Original Principal: <span id="sss-orig">₱0.00</span></p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 py-2 border-left-info">
                <div class="card-body">
                    <h6 class="font-weight-bold mb-3 text-label">Pag-IBIG Loan</h6>
                    <p class="mb-0 text-gray-800">Remaining Balance: <span id="pagibig-balance">₱0.00</span></p>
                    <p class="text-xs text-muted mb-0">Original Principal: <span id="pagibig-orig">₱0.00</span></p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 py-2 border-left-warning">
                <div class="card-body">
                    <h6 class="font-weight-bold mb-3 text-label">Company Loan</h6>
                    <p class="mb-0 text-gray-800">Remaining Balance: <span id="company-balance">₱0.00</span></p>
                    <p class="text-xs text-muted mb-0">Original Principal: <span id="company-orig">₱0.00</span></p>
                </div>
            </div>
        </div>
        
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-label">Other Financial Items</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p class="font-weight-bold text-label">Cash Advance Total:</p>
                    <p id="cash-total">₱0.00</p>
                </div>
                <div class="col-md-4">
                    <p class="font-weight-bold text-label">Cash Advance Deduction:</p>
                    <p id="cash-deduction">₱0.00</p>
                </div>
                <div class="col-md-4">
                    <p class="font-weight-bold text-label">Savings Deduction:</p>
                    <p id="savings-deduction">₱0.00</p>
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php
require 'template/footer.php';
?>

<!-- <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> -->
<script>
    $(document).ready(function() {
        const fetchUrl = 'fetch/balances_data.php'; // Adjust this path if needed
        const messageElement = $('#status-message');

        // Helper function to format currency
        function formatCurrency(value) {
            return '₱' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // --- AJAX FETCH ---
        $.ajax({
            url: fetchUrl,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log("Financial Data Received:", response);
                messageElement.text(response.message);

                if (response.status === 'success') {
                    const data = response.data;
                    
                    // 1. Overall Balance Card
                    $('#total-outstanding').text(formatCurrency(data.outstanding_balance));
                    $('#last-update').text(data.last_loan_update);

                    // 2. Loan Balances Breakdown
                    $('#sss-balance').text(formatCurrency(data.sss_loan_balance));
                    $('#sss-orig').text(formatCurrency(data.sss_loan_orig));
                    
                    $('#pagibig-balance').text(formatCurrency(data.pagibig_loan_balance));
                    $('#pagibig-orig').text(formatCurrency(data.pagibig_loan_orig));
                    
                    $('#company-balance').text(formatCurrency(data.company_loan_balance));
                    $('#company-orig').text(formatCurrency(data.company_loan_orig));

                    // 3. Other Financial Items
                    $('#cash-total').text(formatCurrency(data.cash_assist_total));
                    $('#cash-deduction').text(formatCurrency(data.cash_assist_deduction));
                    $('#savings-deduction').text(formatCurrency(data.savings_deduction));

                } else {
                    messageElement.addClass('text-danger');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error fetching balances:", textStatus, errorThrown);
                messageElement.addClass('text-danger').text("Failed to load financial data due to network error.");
            }
        });
    });
</script>