<?php
// payroll.php
$page_title = 'Payroll Processing';
$current_page = 'payroll'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Payroll Management</h1>
            <p class="text-muted mb-0">Manage salaries, deductions, and generate payouts.</p>
        </div>
        
        <div class="d-flex gap-2">
            <button class="btn btn-white text-gray-800 shadow-sm fw-bold border" onclick="printBatchPayslips()">
                <i class="fas fa-print me-2"></i> Print Batch
            </button>
            <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
                <i class="fas fa-plus text-white me-2"></i> Run Payroll
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-teal shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Total Payout (This Month)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-payout">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div> 
                        </div>
                        <div class="col-auto"><i class="fas fa-coins fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Pending Approvals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-pending">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-filter me-2 text-secondary"></i>Filter Records</h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Cut-Off Start</label>
                    <input type="date" id="filter_start_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Cut-Off End</label>
                    <input type="date" id="filter_end_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="button" id="applyFilterBtn" class="btn btn-teal w-100 fw-bold shadow-sm">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="button" id="clearFilterBtn" class="btn btn-secondary w-100 fw-bold shadow-sm">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-column flex-md-row align-items-center justify-content-between bg-white">
            <div class="d-flex align-items-center mb-2 mb-md-0">
                <h6 class="m-0 font-weight-bold text-gray-800 me-3"><i class="fas fa-list-alt me-2"></i>Payroll History</h6>
                <div class="dropdown no-arrow">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle fw-bold" type="button" id="batchDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-check-square me-1"></i> Batch Actions
                    </button>
                    <div class="dropdown-menu shadow animated--fade-in" aria-labelledby="batchDropdown">
                        <a class="dropdown-item" href="javascript:void(0);" id="btnBatchApprove">
                            <i class="fas fa-check text-success me-2"></i> Approve Selected
                        </a>
                        <a class="dropdown-item" href="javascript:void(0);" id="btnBatchEmail">
                            <i class="fas fa-envelope text-primary me-2"></i> Send Payslips
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search payroll..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="payrollTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="text-center" style="width: 20px;">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th class="border-0">Employee</th>
                            <th class="border-0">Cut-Off Period</th>
                            <th class="border-0 text-end">Net Pay</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-label"><i class="fas fa-calculator me-2"></i>Run Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="generatePayrollForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Target Employee</label>
                        <select name="employee_id" id="gen_emp_id" class="form-select">
                            <option value="all" selected>All Active Employees</option>
                            <option disabled>----------------</option>
                             <?php
                            $stmt_dropdown = $pdo->query("SELECT employee_id, firstname, lastname FROM tbl_employees WHERE employment_status = 1 ORDER BY lastname ASC");
                            while($row = $stmt_dropdown->fetch()){
                                echo "<option value='{$row['employee_id']}'>{$row['lastname']}, {$row['firstname']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Payroll Type</label>
                        <select name="payroll_type" class="form-select">
                            <option value="semi-monthly" selected>Semi-Monthly (15th/30th)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Date Range</label>
                        <div class="input-group">
                            <input type="date" name="start_date" class="form-control" required>
                            <span class="input-group-text bg-light">to</span>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-teal fw-bold shadow-sm">
                            <i class="fas fa-check-circle me-2"></i> Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/payroll_scripts.php'; ?>