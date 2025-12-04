<?php
// user/request_leave.php
require 'template/header.php'; 
// 1. INCLUDE NOTIFICATION MODEL
require_once 'models/global_model.php'; 

date_default_timezone_set('Asia/Manila');

// --- AUTH CHECK ---
if (!isset($_SESSION['employee_id'])) {
    header('Location: ../index.php'); 
    exit();
}

$employee_id = $_SESSION['employee_id']; 
$page_title = 'Request Leave';
$current_page = 'request_leave'; 

// --- HANDLE FORM SUBMISSION ---
$message = null;

if (isset($_POST['submit_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $reason     = trim($_POST['reason']);

    // Basic Validation
    if (empty($leave_type) || empty($start_date) || empty($end_date)) {
        $message = ['type' => 'danger', 'text' => 'Please fill in all required fields.'];
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $message = ['type' => 'danger', 'text' => 'End date cannot be earlier than start date.'];
    } else {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $diff = $start->diff($end);
        $days_count = $diff->days + 1; // Inclusive count

        try {
            // Insert Request (Status 0 = Pending)
            $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $days_count, $reason])) {
                
                // --- FETCH SENDER NAME FROM DB (Fixes ID showing issue) ---
                $sender_name = $employee_id; // Default fallback
                
                // Query tbl_employees to get the real name
                $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
                $stmt_name->execute([$employee_id]);
                $user_info = $stmt_name->fetch(PDO::FETCH_ASSOC);

                if ($user_info) {
                    $sender_name = $user_info['firstname'] . ' ' . $user_info['lastname'];
                }

                // --- SEND NOTIFICATION ---
                $notif_msg = "$sender_name has requested $days_count day(s) of $leave_type.";
                
                if(function_exists('send_notification')) {
                    send_notification($pdo, null, 'Admin', 'leave', $notif_msg, 'leave_management.php', $sender_name);
                }

                // --- DUPLICATE PREVENTION FIX (PRG Pattern) ---
                header("Location: request_leave.php?status=success");
                exit();
                
            } else {
                $message = ['type' => 'danger', 'text' => 'Database insert failed.'];
            }

        } catch (PDOException $e) {
            $message = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
        }
    }
}

// --- DISPLAY SUCCESS MESSAGE AFTER REDIRECT ---
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = ['type' => 'success', 'text' => 'Leave request submitted successfully! Waiting for approval.'];
}

require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold">File Leave Request</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
            <?php echo $message['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div id="credits-loading" class="text-center py-3">
        <div class="spinner-border text-teal" role="status"></div>
        <p class="small text-muted mt-2">Loading leave balances...</p>
    </div>

    <div class="row mb-4 d-none" id="credits-container">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Vacation Leave</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="vl_remaining">0</span> <small class="text-muted text-xs">/ <span id="vl_total">0</span></small>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-plane fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Sick Leave</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="sl_remaining">0</span> <small class="text-muted text-xs">/ <span id="sl_total">0</span></small>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-notes-medical fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Emergency Leave</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span id="el_remaining">0</span> <small class="text-muted text-xs">/ <span id="el_total">0</span></small>
                            </div>
                        </div>
                        <div class="col-auto"><i class="fas fa-ambulance fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-teal text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-edit me-2"></i>Application Form</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="submit_leave" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-uppercase">Leave Type</label>
                            <select name="leave_type" id="leave_type_select" class="form-select" required>
                                <option value="" selected disabled>-- Select Type --</option>
                                <option value="Vacation Leave">Vacation Leave</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Emergency Leave">Emergency Leave</option>
                                <option value="Maternity/Paternity">Maternity/Paternity</option>
                                <option value="Unpaid Leave">Unpaid Leave (LWOP)</option>
                            </select>
                            <div id="credit_warning" class="text-danger small mt-2 fw-bold" style="display:none;">
                                <i class="fas fa-exclamation-circle me-1"></i> You have 0 credits remaining for this type.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold small text-uppercase">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold small text-uppercase">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold small text-uppercase">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for leave..." required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="submit_btn" class="btn btn-teal fw-bold">
                                <i class="fas fa-paper-plane me-2"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Requests</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle" id="leaveHistoryTable" width="100%">
                            <thead>
                                <tr class="bg-light small">
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th class="text-center">Days</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    let leaveBalances = {}; 

    // 1. Fetch Credits on Page Load
    $.ajax({
        url: 'fetch/get_leave_credits.php', // Fetches Balance Logic
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                leaveBalances = response;
                
                // Update UI Cards
                $('#vl_remaining').text(response.vl.remaining);
                $('#vl_total').text(response.vl.total);
                
                $('#sl_remaining').text(response.sl.remaining);
                $('#sl_total').text(response.sl.total);

                $('#el_remaining').text(response.el.remaining);
                $('#el_total').text(response.el.total);

                // Show cards
                $('#credits-loading').addClass('d-none');
                $('#credits-container').removeClass('d-none');
            }
        },
        error: function() {
            $('#credits-loading').html('<span class="text-danger">Failed to load leave credits.</span>');
        }
    });

    // 2. Client-Side Validation: Check Credits
    $('#leave_type_select').on('change', function() {
        let type = $(this).val();
        let remaining = 0;
        let checkCredit = false;

        if ($.isEmptyObject(leaveBalances)) return;

        // Map selection to balance key
        if (type === 'Vacation Leave') { remaining = leaveBalances.vl.remaining; checkCredit = true; }
        else if (type === 'Sick Leave') { remaining = leaveBalances.sl.remaining; checkCredit = true; }
        else if (type === 'Emergency Leave') { remaining = leaveBalances.el.remaining; checkCredit = true; }

        if (checkCredit && remaining <= 0) {
            $('#credit_warning').slideDown();
        } else {
            $('#credit_warning').slideUp();
        }
    });

    // 3. Auto-update End Date
    $('#start_date').on('change', function() {
        let startVal = $(this).val();
        // If End Date is empty or less than Start Date, update it
        let endVal = $('#end_date').val();
        if (!endVal || endVal < startVal) {
            $('#end_date').val(startVal);
        }
        $('#end_date').attr('min', startVal);
    });

    // 4. Initialize DataTables (History)
    $('#leaveHistoryTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "fetch/leave_ssp.php", // Fetches History Logic
        "order": [[ 1, "desc" ]], // Sort by Dates column
        "columns": [
            { "data": "leave_type", "className": "fw-bold" },
            { "data": "dates", "className": "small" },
            { "data": "days_count", "className": "text-center" },
            { "data": "status", "className": "text-center" }
        ],
        "language": {
            "emptyTable": "No leave requests found.",
            "processing": "<div class='spinner-border text-teal' role='status'></div>"
        }
    });
});
</script>