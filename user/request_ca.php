<?php
// user/request_ca.php

// --- 1. CONFIGURATION & SESSION ---
require_once '../db_connection.php'; 
// Ensure session is started in your header, if not, start it here:
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Redirect if not logged in (Adjust checks based on your system)
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_employee_id = $_SESSION['employee_id']; // Get ID from session

$page_title = 'Request Cash Advance';
$current_page = 'request_ca'; 

// --- 2. TEMPLATE INCLUDES ---
require 'template/header.php'; 
require 'template/sidebar.php'; // Ensure this sidebar is for USERS/EMPLOYEES
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Cash Advance</h1>
            <p class="mb-0 text-muted">Submit requests and track your cash advance history.</p>
        </div>
        <button type="button" class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#newRequestModal">
             <i class="fas fa-plus-circle me-2"></i> New Request
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label  text-uppercase mb-1">Pending Request</div>
                            <?php
                                $stmt = $pdo->prepare("SELECT SUM(amount) FROM tbl_cash_advances WHERE employee_id = ? AND status = 'Pending'");
                                $stmt->execute([$current_employee_id]);
                                $pending_total = $stmt->fetchColumn() ?: 0;
                            ?>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?= number_format($pending_total, 2) ?></div>
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
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-label"><i class="fas fa-history me-2"></i>My Request History</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="myRequestsTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Date Needed</th>
                            <th class="border-0 text-center">Amount</th>
                            <th class="border-0">Purpose</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch history for THIS employee only
                        $stmt = $pdo->prepare("SELECT * FROM tbl_cash_advances WHERE employee_id = ? ORDER BY date_requested DESC");
                        $stmt->execute([$current_employee_id]);
                        
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                            // Status Badge Logic
                            $status_badge = 'secondary';
                            if($row['status'] == 'Approved') $status_badge = 'success'; // 'Approved' implies Approved/Completed
                            if($row['status'] == 'Cancelled') $status_badge = 'danger';
                            if($row['status'] == 'Pending') $status_badge = 'warning';
                        ?>
                        <tr>
                            <td class="fw-bold text-gray-700"><?= date('M d, Y', strtotime($row['date_requested'])) ?></td>
                            
                            <td class="text-center fw-bold">₱<?= number_format($row['amount'], 2) ?></td>
                            
                            <td class="small text-muted"><?= htmlspecialchars($row['remarks']) ?></td>
                            
                            <td class="text-center">
                                <span class="badge bg-soft-<?= $status_badge ?> text-<?= $status_badge ?> border border-<?= $status_badge ?> px-2 rounded-pill">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            
                            <td class="text-center small text-gray-500">
                                <?= date('M d, Y', strtotime($row['date_requested'])) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="newRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-label"><i class="fas fa-hand-holding-usd me-2"></i>Request Cash Advance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="caRequestForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase text-gray-600">Amount Required (PHP)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 fw-bold text-muted">₱</span>
                            <input type="number" name="amount" class="form-control border-start-0 ps-1" step="0.01" min="1" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase text-gray-600">Date Needed</label>
                        <input type="date" name="date_needed" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase text-gray-600">Purpose / Reason</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Please state the reason for this request..." required></textarea>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-teal fw-bold shadow-sm py-2">
                            <i class="fas fa-paper-plane me-2"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    console.log("1. Script loaded. jQuery is running.");

    // Initialize DataTable
    if ($('#myRequestsTable').length) {
        $('#myRequestsTable').DataTable({
            "order": [],
            "pageLength": 5,
            "lengthChange": false,
            "language": { "emptyTable": "No request history found." }
        });
    }

    // DEBUG: Check if form exists
    if ($('#caRequestForm').length === 0) {
        console.error("ERROR: Form with ID #caRequestForm not found!");
        alert("Error: Form ID mismatch. Please check the code.");
        return;
    }

    // Handle Form Submission
    $('#caRequestForm').off('submit').on('submit', function(e) {
        e.preventDefault(); // Stop page reload
        console.log("2. Submit button clicked. Form submission intercepted.");

        // Check if SweetAlert is loaded
        if (typeof Swal === 'undefined') {
            console.error("ERROR: SweetAlert2 (Swal) is not loaded!");
            alert("Error: SweetAlert library is missing. Check header/footer includes.");
            return;
        }

        var formData = $(this).serialize();
        console.log("3. Form Data gathered:", formData);

        // SweetAlert Confirmation
        Swal.fire({
            title: 'Submit Request?',
            text: "Are you sure you want to submit this cash advance request?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#20c997',
            confirmButtonText: 'Yes, Submit'
        }).then((result) => {
            if (result.isConfirmed) {
                console.log("4. User confirmed. Sending AJAX request...");
                
                $.ajax({
                    // IMPORTANT: Ensure this path is correct relative to request_ca.php
                    url: 'functions/submit_ca_request.php', 
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    beforeSend: function() {
                        console.log("5. AJAX starting...");
                        Swal.showLoading();
                    },
                    success: function(response) {
                        console.log("6. AJAX Success Response:", response);
                        if (response.status === 'success') {
                            Swal.fire('Submitted!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("6. AJAX Failed:", status, error);
                        console.log("Response Text:", xhr.responseText); // See what the PHP file actually printed
                        Swal.fire('Error', 'Server connection failed. Check Console (F12) for details.', 'error');
                    }
                });
            } else {
                console.log("User cancelled submission.");
            }
        });
    });
});
</script>