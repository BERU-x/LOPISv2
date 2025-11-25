<?php
// manage_financials.php
$page_title = 'Manage Financials';
$current_page = 'employee_management';

// --- CONFIGURATION & INCLUDES ---
require 'template/header.php'; 
require 'models/employee_model.php'; 

if (!isset($_GET['id'])) {
    header("Location: employee_management.php");
    exit;
}

$id = $_GET['id'];

try {
    // --- 1. Fetch Employee Details and Recurring Financials ---
    // REMOVED f.cash_advance from the SELECT list
    $sql = "SELECT 
                e.id as emp_db_id, e.employee_id as emp_string_id, e.firstname, e.lastname,
                c.daily_rate, c.monthly_rate, c.food_allowance, c.transpo_allowance,
                f.sss_loan, f.pagibig_loan, f.company_loan, f.savings_deduction,
                f.cash_assist_total, f.cash_assist_deduction,
                f.sss_loan_balance, f.pagibig_loan_balance, f.company_loan_balance
            FROM tbl_employees e
            LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
            LEFT JOIN tbl_employee_financials f ON e.employee_id = f.employee_id
            WHERE e.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) { die("Employee not found."); }

    // --- 2. Fetch Total Pending Cash Advance (from new table) ---
    $sql_ca = "SELECT SUM(amount) FROM tbl_cash_advances WHERE employee_id = ? AND status = 'Pending'";
    $stmt_ca = $pdo->prepare($sql_ca);
    $stmt_ca->execute([$emp['emp_string_id']]);
    $pending_ca_total = floatval($stmt_ca->fetchColumn() ?: 0);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --- 3. Set Defaults for Display ---
$emp['daily_rate'] = $emp['daily_rate'] ?? 0;
$emp['monthly_rate'] = $emp['monthly_rate'] ?? 0;
$emp['food_allowance'] = $emp['food_allowance'] ?? 0;
$emp['transpo_allowance'] = $emp['transpo_allowance'] ?? 0;

$emp['sss_loan'] = $emp['sss_loan'] ?? 0;
$emp['pagibig_loan'] = $emp['pagibig_loan'] ?? 0;
$emp['company_loan'] = $emp['company_loan'] ?? 0;
$emp['savings_deduction'] = $emp['savings_deduction'] ?? 0;
$emp['cash_assist_total'] = $emp['cash_assist_total'] ?? 0;
$emp['cash_assist_deduction'] = $emp['cash_assist_deduction'] ?? 0;

$emp['sss_loan_balance'] = $emp['sss_loan_balance'] ?? 0;
$emp['pagibig_loan_balance'] = $emp['pagibig_loan_balance'] ?? 0;
$emp['company_loan_balance'] = $emp['company_loan_balance'] ?? 0;

// The pending CA amount for display
$display_ca_total = $pending_ca_total; 

require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1">
                    <li class="breadcrumb-item"><a href="employee_management.php">Employees</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Financials</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                Financial Profile: <span class="text-teal"><?php echo htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']); ?></span>
            </h1>
        </div>
        <button class="btn btn-sm btn-danger shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addCAModal">
            <i class="fas fa-plus me-2"></i> Record New Cash Advance
        </button>
    </div>

    <form action="functions/update_financials.php" method="POST">
        <input type="hidden" name="db_id" value="<?php echo $emp['emp_db_id']; ?>">
        <input type="hidden" name="string_id" value="<?php echo $emp['emp_string_id']; ?>">
        
        <div class="row">
            
            <div class="col-lg-6 mb-4">
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-success text-white">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-money-bill-wave me-2"></i>Compensation (Income)</h6>
                    </div>
                    <div class="card-body">
                        <h6 class="text-uppercase text-gray-600 text-xs fw-bold mb-3">Basic Salary</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Daily Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" name="daily_rate" class="form-control fw-bold" value="<?php echo $emp['daily_rate']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Monthly Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" name="monthly_rate" class="form-control fw-bold" value="<?php echo $emp['monthly_rate']; ?>" required>
                                </div>
                            </div>
                        </div>
                        <hr class="sidebar-divider">
                        <h6 class="text-uppercase text-gray-600 text-xs fw-bold mb-3">Fixed Allowances</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Food Allowance</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" name="food_allowance" class="form-control" value="<?php echo $emp['food_allowance']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Transpo Allowance</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" name="transpo_allowance" class="form-control" value="<?php echo $emp['transpo_allowance']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 border-left-primary">
                    <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-hand-holding-heart me-2"></i>Cash Assistance (Loan)</h6>
                        <span class="badge bg-primary">Long Term</span>
                    </div>
                    <div class="card-body bg-soft-primary">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-gray-800 small">Total Balance Remaining</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">₱</span>
                                    <input type="number" step="0.01" name="cash_assist_total" class="form-control fw-bold" 
                                            value="<?php echo $emp['cash_assist_total']; ?>" placeholder="e.g. 10000">
                                </div>
                                <div class="form-text text-xs">Total amount they owe the company.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-gray-800 small">Deduction per Payroll</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-primary border-primary">₱</span>
                                    <input type="number" step="0.01" name="cash_assist_deduction" class="form-control border-primary fw-bold" 
                                            value="<?php echo $emp['cash_assist_deduction']; ?>" placeholder="e.g. 500">
                                </div>
                                <div class="form-text text-xs">Amount to subtract every cutoff.</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-lg-6 mb-4">
                
                <div class="card shadow mb-4 border-left-warning">
                    <div class="card-header py-3 bg-warning text-dark">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-university me-2"></i>Loan Balances & Amortizations</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            
                            <div class="col-md-6 mb-3">
                                <h6 class="text-uppercase text-gray-600 text-xs fw-bold mb-1">SSS Loan</h6>
                                <label class="form-label small">Total Balance</label>
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text bg-light">₱</span>
                                    <input type="number" step="0.01" name="sss_loan_balance" class="form-control" value="<?php echo $emp['sss_loan_balance']; ?>">
                                </div>
                                <label class="form-label small">Deduction Amount (Per Payroll)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-warning text-dark border-warning">₱</span>
                                    <input type="number" step="0.01" name="sss_loan" class="form-control border-warning fw-bold" value="<?php echo $emp['sss_loan']; ?>">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <h6 class="text-uppercase text-gray-600 text-xs fw-bold mb-1">Pag-IBIG Loan</h6>
                                <label class="form-label small">Total Balance</label>
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text bg-light">₱</span>
                                    <input type="number" step="0.01" name="pagibig_loan_balance" class="form-control" value="<?php echo $emp['pagibig_loan_balance']; ?>">
                                </div>
                                <label class="form-label small">Deduction Amount (Per Payroll)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-warning text-dark border-warning">₱</span>
                                    <input type="number" step="0.01" name="pagibig_loan" class="form-control border-warning fw-bold" value="<?php echo $emp['pagibig_loan']; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-uppercase text-gray-600 text-xs fw-bold mb-1">Company Loan</h6>
                                <label class="form-label small">Total Balance</label>
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text bg-light">₱</span>
                                    <input type="number" step="0.01" name="company_loan_balance" class="form-control" value="<?php echo $emp['company_loan_balance']; ?>">
                                </div>
                                <label class="form-label small">Deduction Amount (Per Payroll)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-warning text-dark border-warning">₱</span>
                                    <input type="number" step="0.01" name="company_loan" class="form-control border-warning fw-bold" value="<?php echo $emp['company_loan']; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-uppercase text-gray-600 text-xs fw-bold mb-1 text-danger">Pending Cash Advance</h6>
                                <label class="form-label small">Total Amount Pending Deduction</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-danger text-white border-danger">₱</span>
                                    <input type="text" readonly class="form-control border-danger fw-bold bg-light" 
                                            value="<?php echo number_format($display_ca_total, 2); ?>">
                                </div>
                            </div>
                            </div>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-header py-3 bg-info text-white">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-piggy-bank me-2"></i>Employee Savings</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <label class="form-label fw-bold text-gray-800">Forced Savings Deduction</label>
                                <div class="small text-muted">Amount deducted per payroll.</div>
                            </div>
                            <div class="input-group" style="width: 150px;">
                                <span class="input-group-text bg-info text-white border-info">₱</span>
                                <input type="number" step="0.01" name="savings_deduction" class="form-control fw-bold text-end" value="<?php echo $emp['savings_deduction']; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow fixed-bottom position-sticky mt-3">
            <div class="card-body d-flex justify-content-between align-items-center py-3 bg-light">
                <a href="employee_management.php" class="btn btn-secondary fw-bold text-gray-900">
                    <i class="fas fa-arrow-left me-2"></i> Back to List
                </a>
                <button type="submit" name="update_financials" class="btn btn-teal btn-lg fw-bold shadow">
                    <i class="fas fa-save me-2"></i> Save Recurring Financial Profile
                </button>
            </div>
        </div>
    </form>
</div>
<?php require 'template/footer.php'; ?>

<div class="modal fade" id="addCAModal" tabindex="-1" role="dialog" aria-labelledby="addCAModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="addCAModalLabel"><i class="fas fa-hand-holding-usd me-2"></i>Record New Cash Advance</h5>
                <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form id="record-ca-form">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" value="<?php echo $emp['emp_string_id']; ?>">
                    <p class="text-muted small">Adding a new record here will increase the total deduction on the next payroll run.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Amount to be deducted (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" name="amount" class="form-control" required min="100" placeholder="e.g., 2500.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date Requested</label>
                        <input type="date" name="date_requested" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks / Reason</label>
                        <textarea name="reason" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger" type="submit" id="save-ca-btn"><i class="fas fa-save me-2"></i>Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // --- Existing Calculation function remains here ---

    // --- NEW: Handle Cash Advance Form Submission ---
    $('#record-ca-form').submit(function(e) {
        e.preventDefault();
        let form = $(this);
        let btn = $('#save-ca-btn');
        let originalText = btn.html();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

        $.ajax({
            url: 'functions/record_cash_advance.php', 
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    Swal.fire({
                        icon: 'success', 
                        title: 'CA Recorded!',
                        text: res.message, 
                        timer: 2000, 
                        showConfirmButton: false
                    }).then(() => {
                        // Reload the page to show the updated pending CA total
                        window.location.reload(); 
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to communicate with server.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>