<?php
// superadmin/tax_settings.php
$page_title = 'Tax Settings - LOPISv2';
$current_page = 'tax_settings';

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Income Tax Configuration</h1>
        <button class="btn btn-primary shadow-sm" onclick="openModal()">
            <i class="fa-solid fa-plus fa-sm text-white-50 me-2"></i>Add Tax Slab
        </button>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Withholding Tax Table (Semi-Monthly)</h6>
                    <button class="btn btn-link btn-sm text-decoration-none" onclick="window.refreshPageContent()">
                        <i id="refresh-spinner" class="fas fa-sync-alt fa-sm fa-fw text-gray-400"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="alert alert-info border-left-info shadow-sm py-2">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Logic:</strong> Tax = <code>Base Tax</code> + ((Taxable Income - <code>Min Income</code>) × <code>Excess Rate</code>)
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="taxTable" width="100%">
                            <thead class="bg-light text-uppercase text-gray-700 text-xs font-weight-bold">
                                <tr>
                                    <th>Tier Name</th>
                                    <th>Min Income (Over)</th>
                                    <th>Max Income (Not Over)</th>
                                    <th>Base Tax</th>
                                    <th>Excess Rate (%)</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="taxModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="taxForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">Add Tax Slab</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tax_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Tier Name</label>
                        <input type="text" class="form-control" id="tier_name" name="tier_name" required placeholder="e.g. Compensation Level 2">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Minimum Income</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="min_income" name="min_income" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Maximum Income</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="max_income" name="max_income" placeholder="Leave blank if infinite">
                            </div>
                            <small class="text-muted" style="font-size:10px;">Leave empty for highest tier</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Base Tax Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="base_tax" name="base_tax" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold small">Rate on Excess (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" id="excess_rate" name="excess_rate" value="0.00" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/tax_settings_scripts.php'; ?>