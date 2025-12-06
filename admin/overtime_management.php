<?php
// overtime_management.php
date_default_timezone_set('Asia/Manila');
$page_title = 'Overtime Management';
$current_page = 'overtime_management'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Overtime Requests</h1>
            <p class="mb-0 text-muted">Review, filter, and approve submitted employee overtime requests.</p>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-label"><i class="fas fa-filter me-2 text-label"></i>Filter Requests</h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Start Date</label>
                    <input type="date" class="form-control" id="filter_start_date">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">End Date</label>
                    <input type="date" class="form-control" id="filter_end_date">
                </div>
                <div class="col-md-2">
                    <button id="applyFilterBtn" class="btn btn-teal w-100 fw-bold shadow-sm"><i class="fas fa-filter me-1"></i> Apply</button>
                </div>
                <div class="col-md-2">
                    <button id="clearFilterBtn" class="btn btn-secondary w-100 fw-bold shadow-sm"><i class="fas fa-undo me-1"></i> Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-label"><i class="fas fa-list-alt me-2"></i>Overtime Requests</h6>
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search employee..." aria-label="Search">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="overtimeTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee</th>
                            <th class="border-0 text-center">Date</th>
                            <th class="border-0 text-center">Requested Hours</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewOTModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2"></i>Overtime Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <img id="modal_emp_photo" src="" class="rounded-circle border shadow-sm mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                    <h5 id="modal_emp_name" class="fw-bold text-gray-800 mb-0"></h5>
                    <small id="modal_emp_dept" class="text-muted"></small>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="text-xs fw-bold text-uppercase text-gray-500">OT Date</label>
                        <div id="modal_ot_date" class="fw-bold text-dark"></div>
                    </div>
                    <div class="col-6">
                        <label class="text-xs fw-bold text-uppercase text-gray-500">Status</label>
                        <div id="modal_status"></div>
                    </div>
                </div>

                <div class="bg-light p-3 rounded mb-3">
                    <label class="text-xs fw-bold text-uppercase text-gray-500">Reason</label>
                    <p id="modal_reason" class="mb-0 text-dark small fst-italic"></p>
                </div>

                <div class="row g-3 align-items-center border-top pt-3">
                    <div class="col-4 text-center border-end">
                        <label class="text-xs fw-bold text-uppercase text-gray-500 d-block">Raw Bio</label>
                        <span id="modal_raw_ot" class="fw-bold text-secondary">0.00</span> hrs
                    </div>
                    <div class="col-4 text-center border-end">
                        <label class="text-xs fw-bold text-uppercase text-gray-500 d-block">Requested</label>
                        <span id="modal_req_hrs" class="fw-bold text-teal h5">0.00</span> hrs
                    </div>
                    <div class="col-4">
                         <label class="text-xs fw-bold text-uppercase text-gray-500 d-block">Approved</label>
                        <input type="number" id="modal_approved_input" class="form-control form-control-sm text-center fw-bold" step="0.5">
                        <span id="modal_approved_display" class="fw-bold text-success h5 d-none"></span> 
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light" id="modal_actions">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="processOT('reject')">Reject</button>
                <button type="button" class="btn btn-success fw-bold" onclick="processOT('approve')">Approve</button>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/overtime_management_scripts.php'; ?>