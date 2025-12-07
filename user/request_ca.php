<?php
// user/request_ca.php
$page_title = 'Request Cash Advance';
$current_page = 'request_ca'; 
require 'template/header.php'; 
require 'template/sidebar.php'; 
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
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Pending Request</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-pending-total">₱0.00</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
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
                            <th class="border-0">Date</th>
                            <th class="border-0 text-center">Amount</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center" style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
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
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Please state the reason..." required></textarea>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-teal fw-bold shadow-sm py-2"><i class="fas fa-paper-plane me-2"></i> Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/cash_advance_scripts.php'; ?>