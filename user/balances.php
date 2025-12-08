<?php
// user/balances.php
$page_title = 'Financial Balances';
$current_page = 'balances'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php'; 
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Financial Overview</h1>
    
    <div id="status-message" class="alert alert-info py-2 small" style="display:none;">
        <i class="fas fa-spinner fa-spin me-2"></i> Loading financial data...
    </div>

    <div class="row mb-4">
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Total Outstanding Liability</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800" id="total-liability">₱0.00</div>
                            <small class="text-muted">Combined Loans & Cash Assistance</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Total Savings Available</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800" id="savings-total">₱0.00</div>
                            <small class="text-muted">Monthly Deduction: <span id="savings-monthly">₱0.00</span></small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h5 mb-0 text-gray-800">Loan Breakdowns</h1>
        <span class="text-xs text-muted">Last Updated: <span id="last-update" class="font-weight-bold">...</span></span>
    </div>

    <div class="row" id="loans-container">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <h6 class="font-weight-bold text-label mb-2">SSS Loan</h6>
                            <div class="mb-0 text-gray-800 small">Balance:</div>
                            <div class="h5 font-weight-bold text-gray-900" id="sss-balance">₱0.00</div>
                            <hr class="my-2">
                            <div class="text-xs text-muted">Original: <span id="sss-orig">₱0.00</span></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-landmark fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <h6 class="font-weight-bold text-label mb-2">Pag-IBIG Loan</h6>
                            <div class="mb-0 text-gray-800 small">Balance:</div>
                            <div class="h5 font-weight-bold text-gray-900" id="pagibig-balance">₱0.00</div>
                            <hr class="my-2">
                            <div class="text-xs text-muted">Original: <span id="pagibig-orig">₱0.00</span></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-home fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <h6 class="font-weight-bold text-label mb-2">Company Loan</h6>
                            <div class="mb-0 text-gray-800 small">Balance:</div>
                            <div class="h5 font-weight-bold text-gray-900" id="company-balance">₱0.00</div>
                            <hr class="my-2">
                            <div class="text-xs text-muted">Original: <span id="company-orig">₱0.00</span></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <h6 class="font-weight-bold text-label mb-2">Cash Assistance</h6>
                            <div class="mb-0 text-gray-800 small">Balance:</div>
                            <div class="h5 font-weight-bold text-gray-900" id="ca-balance">₱0.00</div>
                            <hr class="my-2">
                            <div class="text-xs text-muted">Total Taken: <span id="ca-total">₱0.00</span></div>
                            <div class="text-xs text-danger mt-1">Amortization: <span id="ca-amort">₱0.00</span></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 text-label d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-wallet me-2"></i>Savings Ledger History</h6>
                    <span class="badge badge-light text-success">Last 50 Transactions</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-striped table-hover mb-0 small">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th>Ref No</th>
                                    <th>Type</th>
                                    <th>Remarks</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Running Balance</th>
                                </tr>
                            </thead>
                            <tbody id="ledger-body">
                                <tr><td colspan="6" class="text-center text-muted py-3">Loading transactions...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/balance_scripts.php'; ?>