<?php
// user/balances.php
require 'template/header.php'; 

if (!isset($_SESSION['employee_id'])) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Financial Balances';
$current_page = 'balances'; 

require 'template/sidebar.php';
require 'template/topbar.php'; 
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Financial Balances Detail</h1>
    
    <div id="status-message" class="alert alert-info py-2 small">
        <i class="fas fa-spinner fa-spin me-2"></i> Loading financial data...
    </div>

    <div class="card shadow mb-4 bg-white border-bottom-primary">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col">
                    <div class="text-xs font-weight-bold text-uppercase mb-1 text-gray-800">Total Outstanding Liability</div>
                    <div class="h2 mb-0 font-weight-bold text-danger" id="total-outstanding">₱0.00</div>
                </div>
                <div class="col-auto"><i class="fas fa-coins fa-2x text-gray-300"></i></div>
            </div>
            <p class="text-xs mt-3 mb-0 text-muted">Last Loan Update: <span id="last-update" class="fw-bold">...</span></p>
        </div>
    </div>
    
    <div class="row" id="loans-breakdown-container">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 py-2 border-left-danger">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <h6 class="font-weight-bold mb-3 text-primary">SSS Loan</h6>
                            <p class="mb-1 text-gray-800 fw-bold">Remaining: <span id="sss-balance">₱0.00</span></p>
                            <p class="text-xs text-muted mb-0">Original: <span id="sss-orig">₱0.00</span></p>
                        </div>
                        <div class="col-auto"><i class="fas fa-landmark fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 py-2 border-left-info">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <h6 class="font-weight-bold mb-3 text-info">Pag-IBIG Loan</h6>
                            <p class="mb-1 text-gray-800 fw-bold">Remaining: <span id="pagibig-balance">₱0.00</span></p>
                            <p class="text-xs text-muted mb-0">Original: <span id="pagibig-orig">₱0.00</span></p>
                        </div>
                        <div class="col-auto"><i class="fas fa-home fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100 py-2 border-left-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <h6 class="font-weight-bold mb-3 text-warning">Company Loan</h6>
                            <p class="mb-1 text-gray-800 fw-bold">Remaining: <span id="company-balance">₱0.00</span></p>
                            <p class="text-xs text-muted mb-0">Original: <span id="company-orig">₱0.00</span></p>
                        </div>
                        <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Other Financial Items</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Cash Advance Total
                            <span class="font-weight-bold text-gray-800" id="cash-total">₱0.00</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Cash Advance Deduction
                            <span class="font-weight-bold text-danger" id="cash-deduction">₱0.00</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Savings Deduction (Per Cutoff)
                            <span class="font-weight-bold text-success" id="savings-deduction">₱0.00</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-teal text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-wallet me-2"></i>Savings Ledger History</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-striped table-hover mb-0 small">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th>Ref No</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="ledger-body">
                                <tr><td colspan="5" class="text-center text-muted py-3">Loading transactions...</td></tr>
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