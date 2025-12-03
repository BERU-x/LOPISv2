<?php
// file_overtime.php (Employee Portal)

// --- TEMPLATE INCLUDES & INITIAL SETUP ---
// NOTE: 'template/header.php' is assumed to include 'checking.php' 
// and handle session authorization before this script runs.
require 'template/header.php'; 

// Assuming database connection ($pdo) is available after header inclusion.
date_default_timezone_set('Asia/Manila');

// --- 1. GET AUTHENTICATED EMPLOYEE ID ---
// The security checks (logged_in, usertype check, etc.) happen inside header.php -> checking.php.
if (!isset($_SESSION['employee_id'])) {
    header('Location: ../index.php'); 
    exit();
}

// Define the local $employee_id variable from the guaranteed session value.
$employee_id = $_SESSION['employee_id']; 

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
        $submission_message = ['type' => 'danger', 'text' => 'Please fill out all required fields.'];
    } elseif (!is_numeric($ot_hours) || $ot_hours < 0.5 || $ot_hours > 8) {
        $submission_message = ['type' => 'danger', 'text' => 'Requested hours must be a valid number between 0.5 and 8.'];
    } else {
        try {
            // 🛑 VALIDATION 1: Check if the employee clocked raw OT hours (overtime_hr > 0) on this date.
            $raw_ot_check = $pdo->prepare(
                "SELECT overtime_hr FROM tbl_attendance WHERE employee_id = ? AND date = ?"
            );
            $raw_ot_check->execute([$employee_id, $ot_date]);
            $attendance_record = $raw_ot_check->fetch(PDO::FETCH_ASSOC);

            if (!$attendance_record || $attendance_record['overtime_hr'] <= 0) {
                 $submission_message = ['type' => 'danger', 'text' => 'You must have calculated raw overtime recorded on this date to file a request.'];
            } else {
                // 🛑 VALIDATION 2: Check for existing Pending OT request in tbl_overtime.
                $check_existing = $pdo->prepare(
                    "SELECT id FROM tbl_overtime WHERE employee_id = ? AND ot_date = ? AND status = 'Pending'"
                );
                $check_existing->execute([$employee_id, $ot_date]);

                if ($check_existing->fetch(PDO::FETCH_ASSOC)) {
                     $submission_message = ['type' => 'warning', 'text' => 'An unapproved overtime request already exists for this date.'];
                } else {
                     // Insert the request into tbl_overtime
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO tbl_overtime (employee_id, ot_date, hours_requested, reason, status, created_at) 
                        VALUES (?, ?, ?, ?, 'Pending', NOW())
                    ");
                    
                    $insert_stmt->execute([
                        $employee_id, 
                        $ot_date, 
                        $ot_hours, 
                        $reason
                    ]); 

                    $submission_message = ['type' => 'success', 'text' => 'Overtime request submitted successfully! It is now pending approval.'];
                }
            }
        } catch (PDOException $e) {
            error_log('OT Submission Error: ' . $e->getMessage()); 
            $submission_message = ['type' => 'danger', 'text' => 'A database error occurred. Could not submit request.'];
        }
    }
}

// --- 3. TEMPLATE INCLUDES (Structure) ---
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">📝 File Overtime Request</h1>
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
            <h6 class="m-0 font-weight-bold text-teal">
                <i class="fas fa-edit me-2 text-teal"></i>New Request Form
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
            <h6 class="m-0 font-weight-bold text-teal"><i class="fas fa-calendar-alt me-2"></i>My Overtime Request History</h6>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="otHistoryTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Date</th>
                            <th class="border-0 text-center">Raw OT (Log)</th>
                            <th class="border-0">Reason</th>
                            <th class="border-0 text-center">Requested Hrs</th>
                            <th class="border-0 text-center">Approved Hrs</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Action</th>
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
        // Destroy existing DataTables instance if it exists
        if ($.fn.DataTable.isDataTable('#otHistoryTable')) {
            $('#otHistoryTable').DataTable().destroy();
        }

        var otHistoryTable = $('#otHistoryTable').DataTable({
            processing: true,
            serverSide: true,
            ordering: true, 
            dom: 'rtip', 
            
            ajax: {
                url: "../admin/fetch/overtime_ssp.php", // Connects to your SSP script
                type: "GET",
                data: function (d) {
                    // 🛑 Pass the authenticated employee_id to filter results
                    d.employee_id = '<?php echo $employee_id; ?>'; 
                    // Set a non-existent search term for name/status/ID so it only fetches this user's data
                    d.search.value = d.employee_id; 
                }
            },
            
            columns: [
                // Col 0: ot_date
                { data: 'ot_date' }, 
                
                // Col 1: Raw OT (Time Log) - Maps to 'raw_ot_hr' from SSP
                { 
                    data: 'raw_ot_hr',
                    className: 'text-center text-danger fw-bold',
                    render: function(data) {
                        return data === '0.00 hrs' || data === '0.00' ? '—' : data;
                    }
                },
                
                // Col 2: Reason
                { 
                    data: 'reason',
                    orderable: false, 
                },
                
                // Col 3: Requested Hrs
                { 
                    data: 'hours_requested',
                    className: 'text-center fw-bold text-teal' 
                },
                
                // Col 4: Approved Hrs
                { 
                    data: 'hours_approved',
                    className: 'text-center fw-bold text-success',
                    render: function(data) {
                        return data === '—' ? '—' : data;
                    }
                },
                
                // Col 5: Status (HTML badge from SSP)
                { 
                    data: 'status',
                    className: 'text-center'
                },
                
                // Col 6: Action (File CTO Link) - Custom rendering based on approved status
                { 
                    data: 'raw_data',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        // Check if status is Approved AND hours are > 0
                        if (data.status === 'Approved' && parseInt(data.hours_approved) > 0) {
                            return `<a href="file_leave.php?date=${data.ot_date}&type=CTO" class="btn btn-sm btn-outline-teal fw-bold">
                                        File CTO
                                    </a>`;
                        } else {
                            return '—';
                        }
                    }
                }
            ],
            
            language: {
                processing: "<div class='spinner-border text-teal' role='status'><span class='visually-hidden'>Loading...</span></div>",
                emptyTable: "No overtime requests submitted yet."
            }
        });
        
        // --- 🛑 IMPORTANT: RE-AJAX SETUP FOR EMPLOYEE PORTAL 🛑 ---
        // Since you are using a single SSP file for both Admin (all data) and Employee (filtered data),
        // we must ensure the employee filter is always active. A cleaner way is to handle the filter
        // directly in the DataTables 'data' function and restrict the SSP query,
        // which the Admin view may not handle correctly.
        // For a permanent Employee Portal, a dedicated SSP file for the user is best.
    }
});
</script>