<?php
// superadmin/pay_components.php
$page_title = 'Pay Components - LOPISv2';
$current_page = 'pay_components';

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Pay Components</h1>
    </div>

    <ul class="nav nav-tabs mb-4" id="componentTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="earnings-tab" data-bs-toggle="tab" data-bs-target="#earnings" type="button" role="tab">
                <i class="fas fa-plus-circle text-teal me-2"></i>Earnings
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="deductions-tab" data-bs-toggle="tab" data-bs-target="#deductions" type="button" role="tab">
                <i class="fas fa-minus-circle text-teal me-2"></i>Deductions
            </button>
        </li>
    </ul>

    <div class="tab-content" id="componentTabsContent">
        
        <div class="tab-pane fade show active" id="earnings" role="tabpanel">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-label">Earning Components</h6>
                    <button class="btn btn-teal btn-sm shadow-sm" onclick="openModal('earning')">
                        <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Add Earning
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle w-100" id="earningsTable">
                            <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-center">Taxable</th>
                                    <th class="text-center">Recurring</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="deductions" role="tabpanel">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-label  ">Deduction Components</h6>
                    <button class="btn btn-teal btn-sm shadow-sm" onclick="openModal('deduction')">
                        <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Add Deduction
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle w-100" id="deductionsTable">
                            <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-center">Taxable Impact</th> <th class="text-center">Recurring</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<div class="modal fade" id="componentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="componentForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">Add Component</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="comp_id" name="id">
                    <input type="hidden" id="comp_type" name="type"> 
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Component Name</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Transportation Allowance">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Taxable?</label>
                            <select class="form-select" id="is_taxable" name="is_taxable">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size: 10px;">Does this affect tax calculation?</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Recurring?</label>
                            <select class="form-select" id="is_recurring" name="is_recurring">
                                <option value="1">Yes (Every Payroll)</option>
                                <option value="0">No (One-time)</option>
                            </select>
                            <small class="text-muted d-block mt-1" style="font-size: 10px;">Appears automatically every cutoff?</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-teal">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/superadmin_pay_components.js"></script>