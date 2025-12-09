<?php
// financial_management.php

// --- 1. CONFIGURATION & DATA ---
$page_title = 'Financial Management';
$current_page = 'financial_management';

// Categories matching your Database ENUM
$financial_categories = [
    'Savings' => 'Savings Fund',
    'SSS_Loan' => 'SSS Loan',
    'Pagibig_Loan' => 'Pag-IBIG Loan',
    'Company_Loan' => 'Company Loan',
    'Cash_Assist' => 'Cash Assistance'
];

// Transaction Types matching your Database ENUM
$transaction_types = [
    'Deposit' => 'Deposit (Add to Savings)',
    'Withdrawal' => 'Withdrawal (Deduct from Savings)',
    'Loan_Grant' => 'Loan Grant (Add to Debt)',
    'Loan_Payment' => 'Loan Payment (Deduct from Debt)',
    'Adjustment' => 'Adjustment'
];

// --- TEMPLATE INCLUDES ---
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Financial Management</h1>
            <p class="mb-0 text-muted">Manage savings, loans, and ledger history.</p>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-wallet me-2"></i>Financial Overview</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="financialSearch" class="form-control bg-light border-0 small" placeholder="Search employee..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="financialTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-label text-xs font-weight-bold text-center">
                        <tr>
                            <th class="border-0 align-middle">Employee</th>
                            <th class="border-0 align-middle">Savings</th>
                            <th class="border-0 align-middle">SSS Loan</th>
                            <th class="border-0 align-middle">Pag-IBIG</th>
                            <th class="border-0 align-middle">Company</th>
                            <th class="border-0 align-middle">Cash Assist</th>
                            <th class="border-0 align-middle">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"> 
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0 p-4"> 
                <h5 class="modal-title fw-bold text-label">
                    <i class="fas fa-exchange-alt me-3"></i> New Transaction
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="transactionForm">
                <div class="modal-body pt-0 px-4">
                    
                    <div class="mb-3">
                        <label class="text-label mb-1">Employee</label>
                        <input type="text" id="trans_employee_name" class="form-control bg-light" readonly placeholder="Select from table actions...">
                        <input type="hidden" name="employee_id" id="trans_employee_id">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-label mb-1">Category</label>
                            <select name="category" id="category" class="form-select" required>
                                <?php foreach ($financial_categories as $key => $val): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="text-label mb-1">Transaction Type</label>
                            <select name="transaction_type" id="transaction_type" class="form-select" required>
                                <?php foreach ($transaction_types as $key => $val): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="text-label mb-1">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-label mb-1" id="amortization_label">Monthly Amortization</label>
                            
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" name="amortization" id="amortization" class="form-control" placeholder="0.00">
                            </div>
                            <small class="text-muted text-xs" id="amortization_help">For Loan Grants only</small>
                        </div>

                        <div class="col-md-6">
                            <label class="text-label mb-1">Date</label>
                            <input type="date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="text-label mb-1">Reference / Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. OR#12345 or Payroll Deduction"></textarea>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top-0 p-4"> 
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_transaction" class="btn btn-teal fw-bold shadow-sm">
                        <i class="fas fa-check me-2"></i> Process Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="ledgerHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"> 
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0 p-4"> 
                <div>
                    <h5 class="modal-title fw-bold text-label">
                        <i class="fas fa-history me-2"></i> Ledger History
                    </h5>
                    <p class="mb-0 text-muted small" id="history_employee_name">Viewing records for: ...</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body pt-0 px-4">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select id="filter_category" class="form-select form-select-sm">
                            <option value="All">All Categories</option>
                            <?php foreach ($financial_categories as $key => $val): ?>
                                <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="ledgerTable" width="100%">
                        <thead class="bg-light text-uppercase text-xs">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Ref No.</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Run. Bal.</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Loading data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/financial_scripts.php'; ?>