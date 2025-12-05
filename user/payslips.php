<?php
// payslip.php - Employee Portal

// --- 1. PAGE CONFIGURATION ---
$page_title = 'My Payslips';
$current_page = 'payslips'; 

// --- 2. INCLUDES ---
// Assuming these templates are adapted for the employee portal structure
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';

// --- 3. SECURITY CHECK & EMPLOYEE ID ---
// *** IMPORTANT: Ensure the session variable exists and is secure. ***
if (!isset($_SESSION['employee_id'])) {
    // Redirect or show error if employee is not logged in
    header('Location: login.php');
    exit();
}
$employee_id = $_SESSION['employee_id'];

// --- FETCH STATS (Optional: Last Payslip Net Pay) ---
$last_net_pay = 0;
$payslip_count = 0;
try {
    // Fetch net pay from the most recent 'Paid' payslip for the current employee
    // NOTE: The stats fetch still needs 'AND status = 1' to align with the data displayed in the table.
    $sql_last_pay = "SELECT net_pay FROM tbl_payroll WHERE employee_id = ? AND status = 1 ORDER BY cut_off_end DESC LIMIT 1";
    $stmt_last_pay = $pdo->prepare($sql_last_pay);
    $stmt_last_pay->execute([$employee_id]);
    $last_net_pay = $stmt_last_pay->fetchColumn() ?: 0;

    // Fetch total number of *Approved* payslips for the employee
    $sql_count = "SELECT COUNT(id) FROM tbl_payroll WHERE employee_id = ? AND status = 1"; // <--- Updated count to match filter
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute([$employee_id]);
    $payslip_count = $stmt_count->fetchColumn() ?: 0;
} catch (PDOException $e) { error_log($e->getMessage()); }
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">My Payslips</h1>
            <p class="text-muted mb-0">View and download your payroll history.</p>
        </div>
        
        <div class="d-flex gap-2">
            </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-teal shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Latest Net Pay</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ₱ <?php echo number_format($last_net_pay, 2); ?>
                            </div> 
                        </div>
                        <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Total Payslips Found</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($payslip_count); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-history me-2"></i>Payslip History</h6>
            </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="payslipTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Cut-Off Period</th>
                            <th class="border-0 text-end">Net Pay</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require 'template/footer.php'; ?>

<?php if(isset($_SESSION['status'])) { ?>
    <script>
        Swal.fire({
            toast: true, position: 'top-end', icon: "<?php echo $_SESSION['status_code']; ?>",
            title: "<?php echo $_SESSION['status_title']; ?>", text: "<?php echo $_SESSION['status']; ?>",
            showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
    </script>
<?php unset($_SESSION['status'], $_SESSION['status_title'], $_SESSION['status_code']); } ?>

<script>
$(document).ready(function() {

    // --- Initialize DataTable (Server-Side) ---
    var payslipTable = $('#payslipTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true, // Allow ordering for employees
        order: [[0, 'desc']], // Default sort by cut-off date descending
        searching: false, // Disable search since it's only one employee's data
        dom: 'rtip', 
        ajax: {
            // NEW ENDPOINT: Pointing to the custom payroll SSP script
            url: "fetch/payslip_ssp.php", 
            type: "GET",
            data: function(d) {
                // Pass the employee ID to the server-side script
                d.employee_id = '<?php echo $employee_id; ?>';
            }
        },
        columns: [
            // Col 0: Cut-Off
            { 
                data: 'cut_off_start',
                render: function(data, type, row) {
                    // Server-side returns cut_off_start and cut_off_end as formatted strings
                    return `<span class="fw-bold text-gray-700 small">${row.cut_off_start}</span> <i class="fas fa-arrow-right mx-1 text-xs text-muted"></i> <span class="fw-bold text-gray-700 small">${row.cut_off_end}</span>`;
                }
            },
            // Col 1: Net Pay
            { data: 'net_pay', className: 'text-end fw-bolder text-gray-800', render: $.fn.dataTable.render.number(',', '.', 2, '₱ ') },
            
            // Col 2: Status - MODIFIED TO USE RAW DATA FROM SERVER
            { 
                data: 'status',
                className: 'text-center',
                // The server now sends the complete HTML badge string, 
                // so we just return the data directly.
                render: function(data) {
                    return data; 
                }
            },
            // Col 3: Action (View Payslip)
            {
                data: 'id', // Use the actual record ID
                orderable: false, 
                className: 'text-center',
                render: function(data) {
                    // Link to the detailed payslip view
                    return `<a href="functions/print_payslip.php?id=${data}" class="btn btn-sm btn-secondary text-white shadow-sm" title="View/Download Payslip">
                                <i class="fas fa-download"></i>
                            </a>`;
                }
            }
        ]
    });
});
</script>