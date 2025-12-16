<?php
// superadmin/policy_settings.php
$page_title = 'Policy Settings - LOPISv2';
$current_page = 'policy_settings';

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Company Policies</h1>
    </div>

    <form id="policyForm">
        <input type="hidden" name="action" value="update">

        <div class="row">

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-primary">
                        <h6 class="m-0 font-weight-bold text-label">
                            <i class="fas fa-business-time me-2"></i>Attendance & Work Hours
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="mb-4">
                            <label class="small fw-bold text-gray-700">Standard Work Hours (Per Day)</label>
                            <div class="input-group">
                                <input type="number" step="0.5" class="form-control" id="standard_work_hours" name="standard_work_hours" required>
                                <span class="input-group-text">Hours</span>
                            </div>
                            <small class="text-muted">Used to calculate hourly rates.</small>
                        </div>

                        <div class="mb-4">
                            <label class="small fw-bold text-gray-700">Late Grace Period</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="attendance_grace_period_mins" name="attendance_grace_period_mins" required>
                                <span class="input-group-text">Minutes</span>
                            </div>
                            <small class="text-muted">Time allowed after shift start before marked "Late".</small>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-gray-700">Minimum Overtime Duration</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="overtime_min_minutes" name="overtime_min_minutes" required>
                                <span class="input-group-text">Minutes</span>
                            </div>
                            <small class="text-muted">Minimum extra work required to file for OT.</small>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-success">
                        <h6 class="m-0 font-weight-bold text-label">
                            <i class="fas fa-plane-departure me-2"></i>Leave Configuration
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="small fw-bold text-gray-700">Vacation Leave (VL)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="annual_vacation_leave" name="annual_vacation_leave" required>
                                    <span class="input-group-text">Days</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-gray-700">Sick Leave (SL)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="annual_sick_leave" name="annual_sick_leave" required>
                                    <span class="input-group-text">Days</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Annual credits credited to regular employees.</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-gray-700">Max Leave Carry-Over</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="max_leave_carry_over" name="max_leave_carry_over" required>
                                <span class="input-group-text">Days</span>
                            </div>
                            <small class="text-muted">Unused leaves allowed to be carried over to the next year.</small>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <div class="card shadow mb-4 border-bottom-secondary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span id="last-updated-text" class="small text-muted font-italic">Loading...</span>
                <button class="btn btn-teal px-4 shadow-sm" type="submit" id="saveBtn">
                    <i class="fas fa-save me-2"></i> Save Policies
                </button>
            </div>
        </div>

    </form>

</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/policy_settings_scripts.php'; ?>