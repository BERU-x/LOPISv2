<?php
// file_overtime.php (Employee Portal)

// --- TEMPLATE INCLUDES & INITIAL SETUP ---
require 'template/header.php'; 

// Note: global_model.php is included inside header.php, so send_notification() is available.
date_default_timezone_set('Asia/Manila');

// --- GET AUTHENTICATED EMPLOYEE ID ---
$employee_id = $_SESSION['employee_id'] ?? ($_SESSION['user_id'] ?? null); 
if (!$employee_id) { 
    header('Location: ../index.php'); 
    exit();
}

$page_title = 'File Overtime';
$current_page = 'file_overtime'; 

// --- 2. HANDLE FORM SUBMISSION (OT Request) ---
$submission_message = null;

if (isset($_POST['submit_ot'])) {
    
    $ot_date = $_POST['ot_date'] ?? null;
    $ot_hours = $_POST['ot_hours'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    
    // Input validation 
    if (empty($ot_date) || empty($ot_hours) || empty($reason)) {
        // Redirect with error
        header("Location: file_overtime.php?status=error&msg=empty");
        exit();
    } elseif (!is_numeric($ot_hours) || $ot_hours <= 0 || $ot_hours > 8) {
        // Redirect with error
        header("Location: file_overtime.php?status=error&msg=invalid_hours");
        exit();
    } else {
        try {
            // 🛑 VALIDATION 1: Check if the employee clocked raw OT hours
            $raw_ot_check = $pdo->prepare(
                "SELECT overtime_hr FROM tbl_attendance WHERE employee_id = ? AND date = ?"
            );
            $raw_ot_check->execute([$employee_id, $ot_date]);
            $attendance_record = $raw_ot_check->fetch(PDO::FETCH_ASSOC);

            if (!$attendance_record || $attendance_record['overtime_hr'] <= 0) {
                 header("Location: file_overtime.php?status=error&msg=no_raw_ot");
                 exit();
            } else {
                // 🛑 VALIDATION 2: Check for existing Pending OT request
                $check_existing = $pdo->prepare(
                    "SELECT id FROM tbl_overtime WHERE employee_id = ? AND ot_date = ? AND status = 'Pending'"
                );
                $check_existing->execute([$employee_id, $ot_date]);

                if ($check_existing->fetch(PDO::FETCH_ASSOC)) {
                     header("Location: file_overtime.php?status=warning&msg=existing_pending");
                     exit();
                } else {
                    // Insert the request into tbl_overtime
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO tbl_overtime (employee_id, ot_date, hours_requested, reason, status, created_at) 
                        VALUES (?, ?, ?, ?, 'Pending', NOW())
                    ");
                    
                    if ($insert_stmt->execute([$employee_id, $ot_date, $ot_hours, $reason])) {

                        // --- 3. SEND NOTIFICATION TO ADMIN ---
                        // Simplified Logic: Let global_model fetch the name automatically by passing null
                        $notif_msg = "An employee has filed an Overtime Request ($ot_hours hrs) for $ot_date.";
                        
                        // Note: If you want the specific name in the message text itself, we can fetch it briefly:
                         $sender_name = $_SESSION['employee_id']; // Default
                         $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
                         $stmt_name->execute([$employee_id]);
                         $u = $stmt_name->fetch(PDO::FETCH_ASSOC);
                         if($u) { $sender_name = $u['firstname'] . ' ' . $u['lastname']; }

                        $notif_msg = "$sender_name has filed an Overtime Request ($ot_hours hrs) for $ot_date.";

                        if (function_exists('send_notification')) {
                            send_notification($pdo, null, 'Admin', 'warning', $notif_msg, '../admin/overtime_approval.php');
                        }

                        // --- PRG REDIRECT SUCCESS ---
                        header("Location: file_overtime.php?status=success");
                        exit();
                    } else {
                        header("Location: file_overtime.php?status=error&msg=db_error");
                        exit();
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('OT Submission Error: ' . $e->getMessage()); 
            header("Location: file_overtime.php?status=error&msg=db_exception");
            exit();
        }
    }
}

// --- 3. HANDLE GET PARAMETERS FOR ALERTS ---
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    $msg_code = $_GET['msg'] ?? '';

    if ($status === 'success') {
        $submission_message = ['type' => 'success', 'text' => 'Overtime request submitted successfully! Admin has been notified.'];
    } elseif ($status === 'warning' && $msg_code === 'existing_pending') {
        $submission_message = ['type' => 'warning', 'text' => 'An unapproved overtime request already exists for this date.'];
    } elseif ($status === 'error') {
        if ($msg_code === 'empty') {
            $submission_message = ['type' => 'danger', 'text' => 'Please fill out all required fields.'];
        } elseif ($msg_code === 'invalid_hours') {
            $submission_message = ['type' => 'danger', 'text' => 'Requested hours must be a valid number between 0.5 and 8.'];
        } elseif ($msg_code === 'no_raw_ot') {
            $submission_message = ['type' => 'danger', 'text' => 'You must have calculated raw overtime recorded on this date to file a request.'];
        } else {
            $submission_message = ['type' => 'danger', 'text' => 'A database error occurred. Could not submit request.'];
        }
    }
}

// --- 4. TEMPLATE INCLUDES (Structure) ---
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

    <?php if ($submission_message): ?>
        <div class="alert alert-<?php echo $submission_message['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $submission_message['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-0">
            <h6 class="m-0 font-weight-bold text-label">
                <i class="fas fa-edit me-2 text-label"></i>New Request Form
            </h6>
        </div>
        <div class="card-body bg-light rounded-bottom">
            <form method="POST" action="file_overtime.php">
                <input type="hidden" name="submit_ot" value="1">
                <div class="row g-3">
                    
                    <div class="col-md-4">
                        <label for="ot_date" class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Date of Overtime *</label>
                        <input type="date" class="form-control" id="ot_date" name="ot_date" required max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="col-md-2">
                        <label for="ot_hours" class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Hours Requested *</label>
                        <input type="number" step="0.5" class="form-control" id="ot_hours" name="ot_hours" min="0.5" max="8" required>
                    </div>

                    <div class="col-md-6">
                        <label for="reason" class="form-label text-xs font-weight-bold text-uppercase text-gray-600">Reason/Justification *</label>
                        <input type="text" class="form-control" id="reason" name="reason" placeholder="e.g., Completed critical project testing." required>
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-teal fw-bold shadow-sm">
                            <i class="fas fa-paper-plane me-2"></i> Submit OT Request
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-label"><i class="fas fa-calendar-alt me-2"></i>My Overtime Request History</h6>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="otHistoryTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-label text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Date</th>
                            <th class="border-0 text-center">Raw OT (Log)</th>
                            <th class="border-0">Reason</th>
                            <th class="border-0 text-center">Requested Hrs</th>
                            <th class="border-0 text-center">Approved Hrs</th>
                            <th class="border-0 text-center">Status</th>
                            </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    
    if ($('#otHistoryTable').length) {
        if ($.fn.DataTable.isDataTable('#otHistoryTable')) {
            $('#otHistoryTable').DataTable().destroy();
        }

        var otHistoryTable = $('#otHistoryTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip', 
            
            ajax: {
                // FIXED URL: Pointing to the Employee-specific SSP file
                url: "fetch/overtime_ssp.php", 
                type: "GET",
            },
            
            columns: [
                { data: 'ot_date' }, 
                { 
                    data: 'raw_ot_hr',
                    className: 'text-center',
                    render: function(data) {
                        return data === '—' || data === '0.00 hrs' || data === '0.00' ? '—' : data;
                    }
                },
                { 
                    data: 'reason',
                    orderable: false, 
                },
                { 
                    data: 'hours_requested',
                    className: 'text-center' 
                },
                { 
                    data: 'hours_approved',
                    className: 'text-center',
                    render: function(data) {
                        return data === '—' || data === '0.00 hrs' ? '—' : data;
                    }
                },
                { 
                    data: 'status',
                    className: 'text-center'
                },
            ],
            
            language: {
                processing: "<div class='spinner-border text-teal' role='status'><span class='visually-hidden'>Loading...</span></div>",
                emptyTable: "No overtime requests submitted yet."
            }
        });
    }
});
</script>