<?php
// admin/leave_management.php

$page_title = 'Leave Management';
$current_page = 'leave_management';

require 'template/header.php'; 
require 'models/leave_model.php'; 

// ---------------------------------------------------------
// 1. HANDLE FORM SUBMISSION (CREATE NEW LEAVE)
// ---------------------------------------------------------
if (isset($_POST['apply_leave'])) {
    $emp_id = $_POST['employee_id'];
    $l_type = $_POST['leave_type'];
    $s_date = $_POST['start_date'];
    $e_date = $_POST['end_date'];

    // A. Calculate requested days
    $start = new DateTime($s_date);
    $end = new DateTime($e_date);
    
    if ($end < $start) {
        $_SESSION['error'] = "End date cannot be before start date.";
        header("Location: leave_management.php");
        exit;
    }

    $diff = $start->diff($end);
    $days_requested = $diff->days + 1;

    // B. Fetch Current Balance for Validation
    $balances = get_leave_balance($pdo, $emp_id);
    $error = null;

    // C. Check Credits
    if ($l_type != 'Unpaid Leave' && $l_type != 'Maternity/Paternity') {
        if (isset($balances[$l_type])) {
            $remaining = $balances[$l_type]['remaining'];
            if ($days_requested > $remaining) {
                $error = "Insufficient credits! Requested $days_requested days, but only $remaining days remaining for $l_type.";
            }
        }
    }

    if ($error) {
        $_SESSION['error'] = $error;
    } else {
        $data = [
            'employee_id' => $emp_id,
            'leave_type'  => $l_type,
            'start_date'  => $s_date,
            'end_date'    => $e_date,
            'reason'      => trim($_POST['reason'])
        ];

        if (create_leave_request($pdo, $data)) {
            $_SESSION['message'] = "Leave request submitted successfully!";
        } else {
            $_SESSION['error'] = "Failed to submit leave request.";
        }
    }
    
    header("Location: leave_management.php");
    exit;
}

// ---------------------------------------------------------
// 2. HANDLE APPROVE/REJECT - Now handled by MODAL action
// ---------------------------------------------------------
if (isset($_GET['action']) && isset($_GET['id'])) {
    $leave_id = (int)$_GET['id'];
    $status_code = ($_GET['action'] == 'approve') ? 1 : 2; 

    if (update_leave_status($pdo, $leave_id, $status_code)) {
        $_SESSION['message'] = "Leave status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating status. Please try again.";
    }
    header("Location: leave_management.php");
    exit;
}

// ---------------------------------------------------------
// 3. FETCH DATA
// ---------------------------------------------------------
$leaves = get_all_leaves($pdo);
$employee_list = get_employee_dropdown($pdo); 

$pending_count = count(array_filter($leaves, fn($l) => $l['status'] == 0));
$approved_count = count(array_filter($leaves, fn($l) => $l['status'] == 1));

require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Leave Management</h1>
            <p class="text-muted mb-0">Manage employee leave requests and balances.</p>
        </div>
        <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
            <i class="fas fa-plus-circle me-2"></i> File New Leave
        </button>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Requests</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_count; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved Leaves</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $approved_count; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-list-alt me-2"></i>Request History</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search requests..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="leaveTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-gray-600 text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">Employee</th>
                            <th class="border-0">Leave Details</th>
                            <th class="border-0 text-center">Days</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaves as $leave): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="../assets/images/<?php echo htmlspecialchars($leave['photo'] ?? 'default.png'); ?>" 
                                         class="rounded-circle me-3 border shadow-sm" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <?php echo htmlspecialchars($leave['firstname'] . ' ' . $leave['lastname']); ?>
                                        </div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($leave['department']); ?></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold small text-uppercase mb-1"><?php echo htmlspecialchars($leave['leave_type']); ?></span>
                                    <div class="small text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i> 
                                        <?php echo date('M d', strtotime($leave['start_date'])); ?> 
                                        <i class="fas fa-arrow-right mx-1 text-xs"></i> 
                                        <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                    </div>
                                </div>
                            </td>

                            <td class="fw-bold text-center text-gray-700"><?php echo $leave['days_count']; ?></td>

                            <td class="text-center">
                                <?php 
                                    if($leave['status'] == 0) {
                                        echo '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill"><i class="fas fa-clock me-1"></i> Pending</span>';
                                    } elseif($leave['status'] == 1) {
                                        echo '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fas fa-check me-1"></i> Approved</span>';
                                    } else {
                                        echo '<span class="badge bg-soft-danger text-danger border border-danger px-3 shadow-sm rounded-pill"><i class="fas fa-times me-1"></i> Rejected</span>';
                                    }
                                ?>
                            </td>

                            <td class="text-center">
                                <button onclick="viewLeaveDetails(<?php echo $leave['leave_id']; ?>, '<?php echo $leave['employee_id']; ?>')" 
                                        class="btn btn-sm btn-outline-teal shadow-sm fw-bold" 
                                        data-bs-toggle="modal" data-bs-target="#detailsModal" 
                                        title="View Details">
                                    <i class="fas fa-eye me-1"></i> Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-secondary"><i class="fas fa-clipboard-list me-2"></i> Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="leave-details-content">
                    <div class="text-center py-5">
                        <div class="spinner-border text-teal" role="status"></div>
                        <p class="mt-2 text-muted">Loading details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between" id="modal-footer-actions">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
        </div>
    </div>
</div>

<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-secondary"><i class="fas fa-calendar-plus me-2"></i> File New Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="leave_management.php" method="POST">
                <div class="modal-body">
                    <p class="text-muted small mb-4">Submit a leave request on behalf of an employee.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Select Employee</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach($employee_list as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>">
                                    <?php echo htmlspecialchars($emp['lastname'] . ', ' . $emp['firstname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Leave Type</label>
                        <select name="leave_type" id="leave_type" class="form-select" required> 
                            <option value="">-- Select Type --</option>
                            <option value="Vacation Leave">Vacation Leave</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Maternity/Paternity">Maternity/Paternity</option>
                            <option value="Unpaid Leave">Unpaid Leave (LWOP)</option>
                        </select>
                        <div id="credit_info" class="mt-2 small fw-bold text-teal" style="display:none;">
                            Available Credits: <span id="credit_count">0</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-xs text-uppercase">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-xs text-uppercase">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" required>
                        </div>
                        <div class="col-12 text-end mb-2">
                            <span class="badge bg-light text-dark border" id="days_display" style="display:none;">
                                Requested: <span id="days_count">0</span> days
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-xs text-uppercase">Reason / Notes</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for leave..." required></textarea>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" name="apply_leave" class="btn btn-teal fw-bold shadow-sm py-2">Submit Request</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$msg_json = isset($_SESSION['message']) ? json_encode($_SESSION['message']) : 'null';
$err_json = isset($_SESSION['error']) ? json_encode($_SESSION['error']) : 'null';
unset($_SESSION['message']);
unset($_SESSION['error']);
require 'template/footer.php'; 
?>

<script>
    // Global functions required by the modal buttons
    function confirmAction(action, id) {
        let titleText = action === 'approve' ? 'Approve this leave?' : 'Reject this leave?';
        let btnColor = action === 'approve' ? '#1cc88a' : '#e74a3b';
        
        Swal.fire({
            title: titleText,
            text: "This action cannot be undone.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: btnColor,
            cancelButtonColor: '#858796',
            confirmButtonText: 'Yes, ' + action
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirects to handle PHP logic
                window.location.href = 'leave_management.php?action=' + action + '&id=' + id;
            }
        });
    }

    // New function to load details into the modal
    function viewLeaveDetails(leave_id, employee_id) {
        const contentDiv = $('#leave-details-content');
        const actionsFooter = $('#modal-footer-actions');
        
        // Show loading state and clear previous actions
        contentDiv.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-muted">Loading details...</p></div>');
        actionsFooter.find('.btn-success, .btn-danger').remove(); 
        actionsFooter.find('.btn-secondary').text('Close'); // Reset close button text

        $.ajax({
            url: 'fetch/get_leave_details.php', // *** You need to create this SSP file ***
            method: 'POST',
            data: { leave_id: leave_id, employee_id: employee_id },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    const data = response.details;
                    
                    let statusBadge;
                    if (data.status == 0) {
                        statusBadge = '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill"><i class="fas fa-clock me-1"></i> Pending</span>';
                    } else if (data.status == 1) {
                        statusBadge = '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fas fa-check me-1"></i> Approved</span>';
                    } else {
                        statusBadge = '<span class="badge bg-soft-danger text-danger border border-danger px-3 shadow-sm rounded-pill"><i class="fas fa-times me-1"></i> Rejected</span>';
                    }

                    // Build HTML content for the modal body
                    let htmlContent = `
                        <div class="row">
                            <div class="col-md-5 text-center border-end">
                                <img src="../assets/images/${data.photo || 'default.png'}" class="rounded-circle border shadow-sm mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                                <h5 class="fw-bold">${data.firstname} ${data.lastname}</h5>
                                <p class="text-muted small">${data.department}</p>
                            </div>
                            <div class="col-md-7">
                                <h6 class="fw-bold text-gray-600 mb-3">Request Overview</h6>
                                <table class="table table-sm small">
                                    <tr><td class="fw-bold">Status:</td><td>${statusBadge}</td></tr>
                                    <tr><td class="fw-bold">Leave Type:</td><td>${data.leave_type}</td></tr>
                                    <tr><td class="fw-bold">Requested Days:</td><td><span class="text-black fw-bolder">${data.days_count}</span></td></tr>
                                    <tr><td class="fw-bold">Date Range:</td><td>${data.start_date} to ${data.end_date}</td></tr>
                                    <tr><td class="fw-bold">Filed On:</td><td>${data.date_filed}</td></tr>
                                </table>
                            </div>
                        </div>
                        <hr>
                        <h6 class="fw-bold text-gray-600 mb-3">Reason</h6>
                        <div class="alert alert-light border small">${data.reason || 'No specific reason provided.'}</div>
                        <hr>
                        <h6 class="fw-bold text-gray-600 mb-3">Leave Balance Check</h6>
                        <div id="balance-check-area">
                            ${response.balance_html}
                        </div>
                    `;

                    contentDiv.html(htmlContent);

                    // Insert Action Buttons only if status is Pending (0)
                    if (data.status == 0) {
                        actionsFooter.find('.btn-secondary').text('Cancel'); // Change close button text
                        actionsFooter.append(`
                            <button onclick="confirmAction('reject', ${data.leave_id})" class="btn btn-danger fw-bold shadow-sm">
                                <i class="fas fa-times me-1"></i> Reject
                            </button>
                            <button onclick="confirmAction('approve', ${data.leave_id})" class="btn btn-success fw-bold shadow-sm">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                        `);
                    } else {
                        actionsFooter.find('.btn-secondary').text('Close');
                    }
                } else {
                    contentDiv.html('<div class="alert alert-danger">Error fetching leave details.</div>');
                }
            },
            error: function() {
                contentDiv.html('<div class="alert alert-danger">Server connection error. Could not load data.</div>');
            }
        });
    }

    // Standard JavaScript for existing functionalities
    $(document).ready(function() {
        var table = $('#leaveTable').DataTable({
            "order": [[ 1, "desc" ]], 
            "columnDefs": [ { "orderable": false, "targets": [0, 4] } ],
            "dom": 'rtip', 
            "language": { "emptyTable": "No leave requests found." }
        });

        $('#customSearch').on('keyup', function() {
            table.search(this.value).draw();
        });

        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        });
        
        // Date Validation and Credit Checker logic remains unchanged here...
        
        // Toast Messages
        const successMsg = <?php echo $msg_json; ?>;
        const errorMsg = <?php echo $err_json; ?>;
        if(successMsg) Swal.fire({ icon: 'success', title: successMsg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        if(errorMsg) Swal.fire({ icon: 'error', title: errorMsg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    });
</script>