<?php
/**
 * superadmin/attendance_management.php
 * Master Attendance Logs with Bulk Selection, Pauseable Refresh, and Multi-Status support.
 */

// --- 1. SET PAGE CONFIGURATIONS ---
$page_title = 'Master Attendance Logs';
$current_page = 'attendance_logs'; 

// --- TEMPLATE INCLUDES ---
require '../template/header.php'; 
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Master Attendance Logs</h1>
            <p class="mb-0 text-muted">Superadmin View: Full historical records with manual correction tools.</p>
        </div>
        <div class="no-print d-flex gap-2 align-items-center">
            <div id="bulkActions" class="d-none animate__animated animate__fadeIn">
                <button id="clearSelectionBtn" class="btn btn-sm btn-outline-secondary shadow-sm fw-bold me-1">
                    <i class="fas fa-times me-1"></i> Deselect
                </button>
                <button id="bulkTimeoutBtn" class="btn btn-sm btn-danger shadow-sm fw-bold">
                    <i class="fas fa-clock me-1"></i> Bulk Time Out (<span id="selectedCount">0</span>)
                </button>
            </div>

            <div class="vr mx-2 d-none d-lg-block" id="bulkSeparator" style="height: 30px; display:none;"></div>

            <button class="btn btn-sm btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
                <i class="fas fa-plus me-1"></i> Manual Input
            </button>
            <button class="btn btn-sm btn-outline-primary shadow-sm fw-bold" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print Report
            </button>
        </div>
    </div>

    <div class="card shadow mb-4 border-left-teal">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-teal">
                <i class="fas fa-filter me-2"></i>Advanced Filtering
            </h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Start Date</label>
                    <input type="date" class="form-control border-0 shadow-sm" id="filter_start_date">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">End Date</label>
                    <input type="date" class="form-control border-0 shadow-sm" id="filter_end_date">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Employee ID</label>
                    <input type="text" class="form-control border-0 shadow-sm" id="filter_employee_id" placeholder="Enter ID (e.g. 001)">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button id="applyFilterBtn" class="btn btn-teal w-100 fw-bold shadow-sm">Apply</button>
                    <button id="clearFilterBtn" class="btn btn-secondary shadow-sm" title="Reset Filters"><i class="fas fa-undo"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800">
                <i class="fas fa-database me-2"></i>System-wide Logs 
                <span id="refreshStatus" class="ms-3 badge bg-soft-success text-success fw-normal d-none animate__animated animate__pulse animate__infinite">
                    <i class="fas fa-sync-alt fa-spin me-1"></i> Live
                </span>
            </h6>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Quick search...">
            </div>
        </div>
        <div class="card-body p-0"> <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="superAttendanceTable" width="100%">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="text-center" style="width: 40px;">
                                <input type="checkbox" id="selectAll" class="cursor-pointer">
                            </th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Work Type</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Out Date</th>
                            <th>Status</th>
                            <th class="text-center">Hrs</th>
                            <th class="text-center">OT</th>
                            <th class="text-center text-danger">Deduct</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="addAttendanceForm" class="modal-content">
            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>New Attendance Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" placeholder="Enter ID (e.g. EMP-001)" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Shift Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Time In</label>
                        <input type="time" name="time_in" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Time Out</label>
                        <input type="time" name="time_out" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Work Type</label>
                        <select name="status_based" class="form-control">
                            <option value="OFB">OFB (Office Based)</option>
                            <option value="WFH">WFH (Work From Home)</option>
                            <option value="FIELD">FIELD</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-teal px-4 fw-bold shadow-sm">Create Log</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="editAttendanceForm" class="modal-content">
            <div class="modal-header bg-soft-teal text-white border-bottom-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2 text-teal"></i>Manual Correction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="log_id" id="edit_log_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Clock In Time</label>
                        <input type="time" name="time_in" id="edit_time_in" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Clock Out Time</label>
                        <input type="time" name="time_out" id="edit_time_out" class="form-control">
                        <small class="text-muted italic">Note: Leaving this blank keeps the session 'Active'.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Attendance Status (Multi-select)</label>
                        <select name="attendance_status[]" id="edit_status" class="form-control" multiple style="height: 100px;">
                            <option value="Ontime">Ontime</option>
                            <option value="Late">Late</option>
                            <option value="Undertime">Undertime</option>
                            <option value="Overtime">Overtime</option>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Audit Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="e.g., User forgot to time out..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary shadow-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-teal px-4 fw-bold shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require '../template/footer.php'; ?>

<script src="../assets/js/pages/superadmin_attendance.js"></script>