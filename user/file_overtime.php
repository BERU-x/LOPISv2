<?php
// file_overtime.php
$page_title = 'File Overtime';
$current_page = 'file_overtime'; 
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">File Overtime Request</h1>
            <p class="mb-0 text-muted">Submit manual overtime hours for review.</p>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-label"><i class="fas fa-edit me-2 text-label"></i>New Request Form</h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <form id="otRequestForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Date *</label>
                        <input type="date" class="form-control" id="ot_date" name="ot_date" required max="<?php echo date('Y-m-d'); ?>">
                        
                        <div id="rawOtDisplay" class="mt-2 small text-muted">
                            Please select an overtime date.
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Hours *</label>
                        <input type="number" step="0.5" class="form-control" 
                               id="hours_requested" name="hours_requested" min="0.5" max="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Reason *</label>
                        <input type="text" class="form-control" name="reason" placeholder="e.g., Critical testing" required>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-teal fw-bold shadow-sm"><i class="fas fa-paper-plane me-2"></i> Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-label"><i class="fas fa-calendar-alt me-2"></i>Request History</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="otHistoryTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-label text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Date</th>
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

<?php require 'template/footer.php'; ?>
<?php require 'scripts/overtime_scripts.php'; ?>