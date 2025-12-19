<?php
// superadmin/security_settings.php
$page_title = 'Security Settings - LOPISv2';
$current_page = 'security_settings';

require '../template/header.php';
require '../template/sidebar.php';
require '../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Security Configuration</h1>
    </div>

    <form id="securityForm">
        <input type="hidden" name="action" value="update">

        <div class="row">

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-danger">
                        <h6 class="m-0 font-weight-bold text-label">
                            <i class="fas fa-key me-2"></i>Password Policies
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="alert alert-light border small text-muted mb-4">
                            These rules apply when users create or change their passwords.
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-gray-700">Minimum Password Length</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="min_password_length" name="min_password_length" min="4" max="32" required>
                                <span class="input-group-text">Characters</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="small fw-bold text-gray-700">Password Expiry</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="password_expiry_days" name="password_expiry_days" min="0" required>
                                <span class="input-group-text">Days</span>
                            </div>
                            <small class="text-muted">Set to <strong>0</strong> to disable expiration.</small>
                        </div>

                        <hr>
                        <h6 class="small fw-bold text-uppercase text-gray-500 mb-3">Complexity Requirements</h6>

                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require_uppercase" name="require_uppercase" value="1">
                                <label class="form-check-label" for="require_uppercase">Require Uppercase Letters (A-Z)</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require_numbers" name="require_numbers" value="1">
                                <label class="form-check-label" for="require_numbers">Require Numbers (0-9)</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require_special_chars" name="require_special_chars" value="1">
                                <label class="form-check-label" for="require_special_chars">Require Special Characters (!@#$%^&*)</label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-warning">
                        <h6 class="m-0 font-weight-bold text-label">
                            <i class="fas fa-shield-alt me-2"></i>Access Control
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="alert alert-light border small text-muted mb-4">
                            Brute-force protection settings for user logins.
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-gray-700">Max Login Attempts</label>
                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" min="3" max="10" required>
                            <small class="text-muted">Account locks after this many failed tries.</small>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-gray-700">Lockout Duration</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="lockout_duration_mins" name="lockout_duration_mins" min="1" max="1440" required>
                                <span class="input-group-text">Minutes</span>
                            </div>
                            <small class="text-muted">How long the user must wait before trying again.</small>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <div class="card shadow mb-5 border-top-primary">
            <div class="card-body d-flex justify-content-between align-items-center py-3">
                <span id="last-updated-text" class="small text-muted font-italic">Loading settings...</span>
                <button class="btn btn-teal px-4 shadow" type="submit" id="saveBtn">
                    <i class="fas fa-save me-2"></i> Save Security Rules
                </button>
            </div>
        </div>

    </form>

</div>

<?php require '../template/footer.php'; ?>
<script src="../assets/js/pages/superadmin_security_settings.js"></script>