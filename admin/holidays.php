<?php
// admin/holidays.php
$page_title = 'Manage Holidays';
$current_page = 'holidays'; 

require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Holiday Management</h1>
            <p class="mb-0 text-muted">Configure holidays and payroll rates</p>
        </div>
        <button class="btn btn-teal shadow-sm fw-bold" onclick="openModal()">
             <i class="fas fa-plus-circle me-2"></i> Add New Holiday
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-gray-600">Holiday List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle" id="holidayTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th>Date</th>
                            <th>Holiday Name</th>
                            <th>Type</th>
                            <th class="text-center" width="100">Action</th> 
                            </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-secondary" id="modalTitle">Add New Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="holidayForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="holiday_id">

                    <div class="mb-3">
                        <label class="small text-gray-600 font-weight-bold">Holiday Date</label>
                        <input type="date" name="holiday_date" id="holiday_date" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-gray-600 font-weight-bold">Holiday Name</label>
                        <input type="text" name="holiday_name" id="holiday_name" class="form-control" placeholder="e.g. Independence Day" required>
                    </div>

                    <div class="mb-3">
                        <label class="small text-gray-600 font-weight-bold">Holiday Type</label>
                        <select name="holiday_type" class="form-select" id="holiday_type" onchange="updateMultiplier()" required>
                            <option value="Regular">Regular Holiday</option>
                            <option value="Special Non-Working">Special Non-Working</option>
                            <option value="Special Working">Special Working</option>
                            <option value="National Local">National Local</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small text-gray-600 font-weight-bold">Payroll Multiplier</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="payroll_multiplier" id="payroll_multiplier" class="form-control" value="1.00" required>
                            <span class="input-group-text bg-light">x Rate</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-teal fw-bold">Save Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/holiday_scripts.php'; ?>