<?php
// user/request_leave.php
$page_title = 'Request Leave';
$current_page = 'request_leave';

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold">File Leave Request</h1>
    </div>

    <div id="alert-container"></div>

    <div id="credits-loading" class="text-center py-3">
        <div class="spinner-border text-teal" role="status"></div>
        <p class="small text-muted mt-2">Loading leave balances...</p>
    </div>

    <div class="row mb-4 d-none" id="credits-container">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Vacation Leave</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="vl_remaining">0</span> <small class="text-muted text-xs">/ <span id="vl_total">0</span></small>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-plane fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Sick Leave</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="sl_remaining">0</span> <small class="text-muted text-xs">/ <span id="sl_total">0</span></small>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-notes-medical fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-label text-uppercase mb-1">Emergency Leave</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="el_remaining">0</span> <small class="text-muted text-xs">/ <span id="el_total">0</span></small>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-ambulance fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-teal text-label">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-edit me-2"></i>Application Form</h6>
                </div>
                <div class="card-body">
                    <form id="leaveRequestForm">
                        
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-uppercase">Leave Type</label>
                            <select name="leave_type" id="leave_type_select" class="form-select" required>
                                <option value="" selected disabled>-- Select Type --</option>
                                <option value="Vacation Leave">Vacation Leave</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Emergency Leave">Emergency Leave</option>
                                <option value="Maternity/Paternity">Maternity/Paternity</option>
                                <option value="Unpaid Leave">Unpaid Leave (LWOP)</option>
                            </select>
                            <div id="credit_warning" class="text-danger small mt-2 fw-bold" style="display:none;">
                                <i class="fas fa-exclamation-circle me-1"></i> You have 0 credits remaining for this type.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold small text-uppercase">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold small text-uppercase">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-uppercase">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for leave..." required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="submit_btn" class="btn btn-teal fw-bold">
                                <span id="btn_text"><i class="fas fa-paper-plane me-2"></i> Submit Request</span>
                                <span id="btn_spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-label">Recent Requests</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle" id="leaveHistoryTable" width="100%">
                            <thead>
                                <tr class="bg-light small">
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th class="text-center">Days</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/leave_scripts.php'; ?>