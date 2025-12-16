<?php
// superadmin/general_settings.php
$page_title = 'General Settings - LOPISv2';
$current_page = 'general_settings';

require 'template/header.php';
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">System Configuration</h1>
    </div>

    <form id="settingsForm">
        <input type="hidden" name="action" value="update">

        <div class="row">

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-info">
                        <h6 class="m-0 font-weight-bold text-label">
                            <i class="fas fa-cogs me-2"></i>System Parameters
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="d-flex align-items-center justify-content-between mb-4 p-3 bg-light rounded border">
                            <div>
                                <label class="fw-bold text-dark mb-0">Maintenance Mode</label>
                                <div class="small text-muted">Prevent regular users from logging in.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-gray-700">Session Timeout (Inactivity)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="session_timeout_minutes" name="session_timeout_minutes" min="5" max="1440" required>
                                <span class="input-group-text">Minutes</span>
                            </div>
                            <small class="text-muted">Users will be logged out automatically after this duration.</small>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-gray-700">System Timezone</label>
                            <select class="form-select" id="system_timezone" name="system_timezone">
                                <option value="Asia/Manila">Asia/Manila (Philippines)</option>
                                <option value="UTC">UTC</option>
                                <option value="Asia/Singapore">Asia/Singapore</option>
                                <option value="America/New_York">America/New_York</option>
                            </select>
                        </div>

                        <hr>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="allow_forgot_password" name="allow_forgot_password" value="1">
                            <label class="form-check-label fw-bold small" for="allow_forgot_password">Enable "Forgot Password" Link</label>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 border-left-warning">
                        <h6 class="m-0 font-weight-bold text-label">
                            <i class="fas fa-bell me-2"></i>Notification & SMTP
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <span class="fw-bold text-gray-700">Enable Email Notifications</span>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enable_email_notifications" name="enable_email_notifications" value="1">
                            </div>
                        </div>

                        <div id="smtp_settings_wrapper">
                            <h6 class="small text-uppercase text-gray-500 fw-bold mb-3">SMTP Server Configuration</h6>
                            
                            <div class="mb-3">
                                <label class="small fw-bold">Sender Name</label>
                                <input type="text" class="form-control" id="email_sender_name" name="email_sender_name" placeholder="e.g. HR Department">
                            </div>

                            <div class="row gx-2 mb-3">
                                <div class="col-md-8">
                                    <label class="small fw-bold">SMTP Host</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold">Port</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" placeholder="587">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold">SMTP Username / Email</label>
                                <input type="email" class="form-control" id="smtp_username" name="smtp_username" placeholder="notifications@domain.com">
                            </div>

                            <div class="mb-3">
                                <label class="small fw-bold">SMTP Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="Leave blank to keep current">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted" style="font-size:10px">
                                    <i class="fas fa-lock me-1"></i> Stored securely. Only enter a new value if changing it.
                                </small>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <div class="card shadow mb-5 border-top-primary">
            <div class="card-body d-flex justify-content-between align-items-center py-3">
                <span id="last-updated-text" class="small text-muted font-italic">Loading configuration...</span>
                <button class="btn btn-teal px-4 shadow" type="submit" id="saveBtn">
                    <i class="fas fa-save me-2"></i> Save Configuration
                </button>
            </div>
        </div>

    </form>

</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/general_settings_scripts.php'; ?>