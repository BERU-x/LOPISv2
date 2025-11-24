<?php
// payroll.php

// --- 1. PAGE CONFIGURATION ---
$page_title = 'Payroll Processing';
$current_page = 'payroll'; 

// --- 2. INCLUDES ---
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';

// --- FETCH CURRENT DEDUCTION SETTINGS ---
// We fetch these to pre-fill the Settings Modal
$stmt_settings = $pdo->query("SELECT * FROM tbl_deduction_settings");
$settings = [];
while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['name']] = $row;
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Payroll Management</h1>
            <p class="text-muted mb-0">Manage employee salaries, deductions, and payouts.</p>
        </div>
        
        <div class="d-flex gap-2">
            <button class="btn btn-warning shadow-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <i class="fas fa-cog fa-sm me-2"></i> Settings
            </button>

            <button class="btn btn-light text-teal shadow-sm fw-bold" onclick="window.print()">
                <i class="fas fa-print fa-sm text-teal me-2"></i> Print Report
            </button>
            <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
                <i class="fas fa-plus fa-sm text-white me-2"></i> Run Payroll
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-teal shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-teal text-uppercase mb-1">Total Payout (This Month)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱ 0.00</div> 
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Approvals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-transparent border-bottom-0">
            <h6 class="m-0 font-weight-bold text-teal"><i class="fas fa-filter me-1"></i> Filter Payroll Records</h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <form class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Cut-Off End Date (From)</label>
                    <input type="date" id="filter_start_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Cut-Off End Date (To)</label>
                    <input type="date" id="filter_end_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="button" id="applyFilterBtn" class="btn btn-teal w-100 fw-bold"><i class="fas fa-search me-1"></i> Filter</button>
                </div>
                <div class="col-md-2">
                    <button type="button" id="clearFilterBtn" class="btn btn-secondary w-100 fw-bold"><i class="fas fa-undo me-1"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-gray-800">Payroll List</h6>
            
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle btn btn-sm btn-light text-gray-600" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                    Batch Actions <i class="fas fa-chevron-down ms-1"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow animated--fade-in">
                    <a class="dropdown-item" href="#"><i class="fas fa-check text-success me-2"></i> Approve Selected</a>
                    <a class="dropdown-item" href="#"><i class="fas fa-envelope text-primary me-2"></i> Send Payslips</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="payrollTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Cut-Off Period</th>
                            <th class="text-end">Gross Pay</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Net Pay</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-teal"><i class="fas fa-calculator me-2"></i>Run Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-4">Select the cut-off period to generate payroll for eligible employees.</p>
                
                <form action="functions/create_payroll.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Payroll Type</label>
                        <select name="payroll_type" class="form-select">
                            <option value="semi-monthly">Semi-Monthly (15th/30th)</option>
                            <option value="monthly">Monthly</option>
                            <option value="special">13th Month / Special</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Date Range</label>
                        <div class="input-group">
                            <input type="date" name="start_date" class="form-control" required>
                            <span class="input-group-text">to</span>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" name="generate_btn" class="btn btn-teal font-weight-bold shadow-sm py-2">Generate Payroll</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-cog me-2"></i>Deduction Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-4">Update the calculation rates used for payroll generation.</p>
                
                <form action="functions/update_settings.php" method="POST">
                    
                    <div class="row mb-3 align-items-center">
                        <div class="col-8">
                            <label class="fw-bold text-gray-700">SSS Rate</label>
                            <div class="small text-muted">Percentage of Gross Pay</div>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <input type="number" step="0.01" name="SSS" class="form-control text-end fw-bold" 
                                       value="<?php echo $settings['SSS']['amount']; ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3 align-items-center">
                        <div class="col-8">
                            <label class="fw-bold text-gray-700">PhilHealth Rate</label>
                            <div class="small text-muted">Percentage of Gross Pay</div>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <input type="number" step="0.01" name="PhilHealth" class="form-control text-end fw-bold" 
                                       value="<?php echo $settings['PhilHealth']['amount']; ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3 align-items-center">
                        <div class="col-8">
                            <label class="fw-bold text-gray-700">Withholding Tax</label>
                            <div class="small text-muted">Flat percentage estimate</div>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <input type="number" step="0.01" name="Tax" class="form-control text-end fw-bold" 
                                       value="<?php echo $settings['Tax']['amount']; ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-3 align-items-center">
                        <div class="col-8">
                            <label class="fw-bold text-gray-700">Pag-IBIG Contribution</label>
                            <div class="small text-muted">Fixed amount deduction</div>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" name="Pag-IBIG" class="form-control text-end fw-bold" 
                                       value="<?php echo $settings['Pag-IBIG']['amount']; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" name="update_settings_btn" class="btn btn-warning text-dark font-weight-bold shadow-sm py-2">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>

<?php if(isset($_SESSION['status']) && $_SESSION['status'] != '') { ?>
    <script>
        Swal.fire({
            toast: true,
            position: 'top-end',
            title: "<?php echo $_SESSION['status_title']; ?>",
            text: "<?php echo $_SESSION['status']; ?>",
            icon: "<?php echo $_SESSION['status_code']; ?>",
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    </script>
<?php 
    unset($_SESSION['status']);
    unset($_SESSION['status_title']);
    unset($_SESSION['status_code']);
} ?>

<script>
$(document).ready(function() {
    
    // --- Initialize DataTable with Server-Side Processing ---
    var payrollTable = $('#payrollTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "fetch/payroll_ssp.php", // This connects to your backend API
            type: "GET",
            data: function(d) {
                // Send filter data to the server
                d.filter_start_date = $('#filter_start_date').val();
                d.filter_end_date = $('#filter_end_date').val();
            }
        },
        columns: [
            { data: 'ref_no', className: 'fw-bold text-teal' },
            { 
                data: 'employee_name',
                render: function(data, type, row) {
                    var name = data ? data : 'Unknown';
                    // Grab initials from first char of name
                    var initial = name.charAt(0);
                    var emp_id = row.employee_id || 'N/A';
                    
                    // [UPDATED] Check 'picture' (from alias in SSP) first, fallback to 'photo'
                    var pictureFilename = row.picture || row.photo || 'default.png'; 
                    var avatarHtml = '';

                    // Check if file exists (basic check if not default or empty)
                    if (pictureFilename && pictureFilename !== "default.png" && pictureFilename !== "") {
                        avatarHtml = `
                            <img src="../assets/images/${pictureFilename}" 
                                class="rounded-circle me-2 border shadow-sm" 
                                style="width:40px; height:40px; object-fit:cover;">
                        `;
                    } else {
                        // Fallback to Initials Circle
                        avatarHtml = `
                            <div class="bg-soft-teal text-teal rounded-circle d-flex align-items-center justify-content-center me-2 border shadow-sm" 
                                style="width:40px; height:40px; font-weight:bold;">
                                ${initial}
                            </div>
                        `;
                    }
                    
                    return `
                        <div class="d-flex align-items-center">
                            ${avatarHtml}
                            <div>
                                <div class="fw-bold text-dark">${name}</div>
                                <div class="small text-muted">ID: ${emp_id}</div>
                            </div>
                        </div>
                    `;
                }
            },
            { 
                data: 'department',
                render: function(data) {
                    return `<span class="badge bg-light text-secondary border">${data}</span>`;
                }
            },
            { 
                data: 'cut_off_start',
                render: function(data, type, row) {
                    // Format: MMM DD - MMM DD
                    return `<span class="fw-bold text-gray-700">${row.cut_off_start}</span> 
                            <span class="text-muted small mx-1">to</span> 
                            <span class="fw-bold text-gray-700">${row.cut_off_end}</span>`;
                }
            },
            { 
                data: 'gross_pay', 
                className: 'text-end text-success fw-bold',
                render: function(data) { return '₱ ' + parseFloat(data).toLocaleString(undefined, {minimumFractionDigits: 2}); }
            },
            { 
                data: 'total_deductions', 
                className: 'text-end text-danger',
                render: function(data) { return '₱ ' + parseFloat(data).toLocaleString(undefined, {minimumFractionDigits: 2}); }
            },
            { 
                data: 'net_pay', 
                className: 'text-end fw-bolder text-gray-800',
                render: function(data) { return '₱ ' + parseFloat(data).toLocaleString(undefined, {minimumFractionDigits: 2}); }
            },
            { 
                data: 'status',
                className: 'text-center',
                render: function(data) {
                    if(data == 1) return '<span class="badge bg-success shadow-sm">Paid</span>';
                    if(data == 2) return '<span class="badge bg-secondary">Cancelled</span>';
                    return '<span class="badge bg-warning text-dark shadow-sm">Pending</span>';
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-light text-primary border" title="View Payslip"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-light text-teal border" title="Download PDF"><i class="fas fa-download"></i></button>
                    `;
                }
            }
        ],
        order: [[ 0, "desc" ]], // Default sort by Ref #
        language: {
            search: "",
            searchPlaceholder: "Search Ref # or Name..."
        }
    });

    // --- Filter Button Logic ---
    $('#applyFilterBtn').on('click', function() {
        payrollTable.ajax.reload();
    });

    // --- Clear Filter Logic ---
    $('#clearFilterBtn').on('click', function() {
        $('#filter_start_date').val('');
        $('#filter_end_date').val('');
        payrollTable.ajax.reload();
    });
});
</script>