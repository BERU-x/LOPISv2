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
            <p class="mb-0 text-muted">Manage employee savings, loans, and ledger history.</p>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-wallet me-2"></i>Employee Financial Balances</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="financialSearch" class="form-control bg-light border-0 small" placeholder="Search employee..." aria-label="Search Employee">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="financialTable" width="100%" cellspacing="0" role="grid" aria-describedby="financialTable_info">
                    <thead class="bg-light text-uppercase text-label text-xs font-weight-bold text-center">
                        <tr>
                            <th class="border-0 align-middle" scope="col">Employee</th>
                            <th class="border-0 align-middle" scope="col">Savings (₱)</th>
                            <th class="border-0 align-middle" scope="col">SSS Loan (₱)</th>
                            <th class="border-0 align-middle" scope="col">Pag-IBIG (₱)</th>
                            <th class="border-0 align-middle" scope="col">Company (₱)</th>
                            <th class="border-0 align-middle" scope="col">Cash Assist (₱)</th>
                            <th class="border-0 align-middle" scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>
    </div> 
    
<div class="modal fade" id="combinedFinancialModal" tabindex="-1" aria-labelledby="combinedFinancialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"> 
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0 p-4"> 
                <div>
                    <h5 class="modal-title fw-bold text-label" id="combinedFinancialModalLabel">
                        <i class="fas fa-chart-line me-2"></i> Financial Record Management
                    </h5>
                    <p class="mb-0 text-muted small" id="combined_employee_name">Viewing records for: ...</p>
                                    </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body pt-0 px-4">
                
                <ul class="nav nav-tabs mb-3" id="financialTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="adjustment-tab" data-bs-toggle="tab" data-bs-target="#adjustment-pane" type="button" role="tab" aria-controls="adjustment-pane" aria-selected="true">
                            <i class="fas fa-pencil-alt me-1"></i> Adjustments
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="false">
                            <i class="fas fa-history me-1"></i> Ledger History
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="financialTabsContent">
                    
                    <div class="tab-pane fade show active" id="adjustment-pane" role="tabpanel" aria-labelledby="adjustment-tab" tabindex="0">
                        <form id="adjustmentForm">
                            
                            <input type="hidden" name="employee_id" id="adjust_employee_id"> 

                            <div class="row g-4">
                                
                                <div class="col-md-6">
                                    <div class="card card-body bg-light">
                                        <h6 class="fw-bold text-primary">Savings Fund</h6>
                                        <label for="adjust_savings_bal" class="text-label mb-1">Current Balance</label>
                                        <input type="number" step="0.01" id="adjust_savings_bal" name="savings_bal" class="form-control mb-2" required>
                                        <label for="adjust_savings_contrib" class="text-label mb-1">Monthly Contribution</label>
                                        <input type="number" step="0.01" id="adjust_savings_contrib" name="savings_contrib" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card card-body bg-light">
                                        <h6 class="fw-bold text-danger">SSS Loan</h6>
                                        <label for="adjust_sss_bal" class="text-label mb-1">Current Balance Owed</label>
                                        <input type="number" step="0.01" id="adjust_sss_bal" name="sss_bal" class="form-control mb-2" required>
                                        <label for="adjust_sss_amort" class="text-label mb-1">Monthly Amortization</label>
                                        <input type="number" step="0.01" id="adjust_sss_amort" name="sss_amort" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card card-body bg-light">
                                        <h6 class="fw-bold text-danger">Pag-IBIG Loan</h6>
                                        <label for="adjust_pagibig_bal" class="text-label mb-1">Current Balance Owed</label>
                                        <input type="number" step="0.01" id="adjust_pagibig_bal" name="pagibig_bal" class="form-control mb-2" required>
                                        <label for="adjust_pagibig_amort" class="text-label mb-1">Monthly Amortization</label>
                                        <input type="number" step="0.01" id="adjust_pagibig_amort" name="pagibig_amort" class="form-control">
                                        
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card card-body bg-light">
                                        <h6 class="fw-bold text-danger">Company Loan</h6>
                                        <label for="adjust_company_bal" class="text-label mb-1">Current Balance Owed</label>
                                        <input type="number" step="0.01" id="adjust_company_bal" name="company_bal" class="form-control mb-2" required>
                                        <label for="adjust_company_amort" class="text-label mb-1">Monthly Amortization</label>
                                        <input type="number" step="0.01" id="adjust_company_amort" name="company_amort" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card card-body bg-light">
                                        <h6 class="fw-bold text-danger">Cash Assistance</h6>
                                        <label for="adjust_cash_bal" class="text-label mb-1">Current Balance Owed</label>
                                        <input type="number" step="0.01" id="adjust_cash_bal" name="cash_bal" class="form-control mb-2" required>
                                        <label for="adjust_cash_amort" class="text-label mb-1">Monthly Amortization</label>
                                        <input type="number" step="0.01" id="adjust_cash_amort" name="cash_amort" class="form-control">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="adjustment_remarks" class="text-label mb-1">Reason for Adjustment / Remarks</label>
                                    <textarea name="adjustment_remarks" id="adjustment_remarks" class="form-control" rows="2" required placeholder="State the reason for manual balance or amortization adjustment."></textarea>
                                </div>

                            </div>
                            <div class="modal-footer border-top-0 p-4 mt-3"> 
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="save_adjustment" class="btn btn-teal fw-bold shadow-sm">
                                    <i class="fas fa-check me-2"></i> Save Adjustments
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab" tabindex="0">
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label for="filter_category" class="visually-hidden">Filter Category</label>
                                <select id="filter_category" class="form-select form-select-sm" aria-label="Filter Ledger Category">
                                    <option value="All">All Categories</option>
                                    <?php foreach ($financial_categories as $key => $val): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped" id="ledgerTable" width="100%" role="grid">
                                <thead class="bg-light text-uppercase text-xs">
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">Category</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Ref No.</th>
                                        <th scope="col" class="text-end">Amount</th>
                                        <th scope="col" class="text-end">Run. Bal.</th>
                                        <th scope="col">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="ledgerTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Select an employee to view history.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<?php require 'template/footer.php'; ?>
<?php require 'scripts/financial_scripts.php'; ?>