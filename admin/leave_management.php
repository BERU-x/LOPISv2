<?php
// admin/leave_management.php
$page_title = 'Leave Management';
$current_page = 'leave_management';

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Leave Management</h1>
            <p class="text-muted mb-0">Manage employee leave requests and balances.</p>
        </div>
        <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
            <i class="fas fa-plus-circle me-2"></i> File New Leave
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Pending Requests</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-pending">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Approved Leaves</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-approved">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-list-alt me-2"></i>Request History</h6>
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search requests..." aria-label="Search">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="leaveTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee</th>
                            <th class="border-0">Leave Details</th>
                            <th class="border-0 text-center">Days</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-secondary"><i class="fas fa-clipboard-list me-2"></i> Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="leave-details-content">
                    <div class="text-center py-5">
                        <div class="spinner-border text-teal" role="status"></div>
                        <p class="mt-2 text-muted">Loading details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between" id="modal-footer-actions">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-secondary"><i class="fas fa-calendar-plus me-2"></i> File New Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="applyLeaveForm">
                <div class="modal-body">
                    <p class="text-muted small mb-4">Submit a leave request on behalf of an employee.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Select Employee</label>
                        <select name="employee_id" id="empDropdown" class="form-select" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Leave Type</label>
                        <select name="leave_type" class="form-select" required> 
                            <option value="">-- Select Type --</option>
                            <option value="Vacation Leave">Vacation Leave</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Maternity/Paternity">Maternity/Paternity</option>
                            <option value="Unpaid Leave">Unpaid Leave (LWOP)</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-xs text-uppercase">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-xs text-uppercase">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Reason / Notes</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason..." required></textarea>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-teal fw-bold shadow-sm py-2">Submit Request</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/leave_management_scripts.php'; ?>