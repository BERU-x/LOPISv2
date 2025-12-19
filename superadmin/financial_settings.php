<?php
// superadmin/financial_settings.php
$page_title = 'Financial Settings - LOPISv2';
$current_page = 'financial_settings';

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Financial Configuration</h1>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-label">General Financial Settings</h6>
                    <span id="last-updated-text" class="small text-muted"></span>
                </div>
                <div class="card-body">
                    
                    <form id="financialForm">
                        <input type="hidden" name="action" value="update">
                        
                        <h6 class="fw-bold text-gray-800 mb-3"><i class="fas fa-coins me-2"></i>Currency Settings</h6>
                        <div class="row gx-3 mb-4">
                            <div class="col-md-6">
                                <label class="small mb-1 fw-bold">Currency Code (ISO)</label>
                                <input class="form-control" id="currency_code" name="currency_code" type="text" placeholder="e.g. PHP" required uppercase>
                                <small class="text-muted">Used for reports (e.g., PHP, USD).</small>
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1 fw-bold">Currency Symbol</label>
                                <input class="form-control" id="currency_symbol" name="currency_symbol" type="text" placeholder="e.g. ₱" required>
                                <small class="text-muted">Displayed in payroll slips.</small>
                            </div>
                        </div>

                        <hr>

                        <h6 class="fw-bold text-gray-800 mb-3 mt-4"><i class="fas fa-calendar-alt me-2"></i>Fiscal Year</h6>
                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1 fw-bold">Fiscal Year Start Month</label>
                                <select class="form-select" id="fiscal_year_start_month" name="fiscal_year_start_month">
                                    <?php 
                                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                    foreach($months as $m) echo "<option value='$m'>$m</option>";
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1 fw-bold">Start Day</label>
                                <input class="form-control" id="fiscal_year_start_day" name="fiscal_year_start_day" type="number" min="1" max="31" value="1" required>
                            </div>
                        </div>

                        <div class="alert alert-warning small py-2 mb-4">
                            <i class="fas fa-exclamation-triangle me-1"></i> 
                            Changing the Fiscal Year settings may affect how annual reports are generated.
                        </div>

                        <div class="d-flex justify-content-end">
                            <button class="btn btn-teal px-4" type="submit" id="saveBtn">
                                <i class="fas fa-save me-1"></i> Save Configuration
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/superadmin_financial_settings.js"></script>