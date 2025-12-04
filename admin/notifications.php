<?php
// admin/send_notification.php
$page_title = 'Send Notification - LOPISv2';
$current_page = 'notifications';

require 'template/header.php';
// Use the header's global_model include if possible, otherwise:
// require_once '../models/global_model.php'; // Adjust path as necessary

$success_msg = '';
$error_msg = '';

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_role = $_POST['target_role']; // 'Admin', 'Employee', 'All'
    $target_user_id = !empty($_POST['target_user_id']) ? $_POST['target_user_id'] : null;
    $type = $_POST['type'];
    $message = trim($_POST['message']);
    $link = !empty($_POST['link']) ? $_POST['link'] : '#';
    
    // Send the notification
    $sender_name = $_SESSION['fullname'] ?? 'Admin'; 
    
    // We ensure the global model function is available (it should be, via header.php)
    if (function_exists('send_notification')) {
        if (send_notification($pdo, $target_user_id, $target_role, $type, $message, $link, $sender_name)) {
            $success_msg = "Notification sent successfully!";
        } else {
            $error_msg = "Failed to send notification.";
        }
    } else {
        $error_msg = "System error: Notification function not available.";
    }
}

// --- FETCH DATA FOR DROPDOWN AND HISTORY ---
$employees = [];
$notification_history = [];
try {
    // Fetch Employees for the Dropdown
    // Note: tbl_employees.id is the PK, but tbl_notifications.target_user_id is VARCHAR employee_id (E001, etc.)
    // We fetch the VARCHAR employee_id (assuming the user's dropdown value must be the VARCHAR employee_id)
    $stmt = $pdo->query("SELECT employee_id, firstname, lastname FROM tbl_employees ORDER BY lastname ASC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch History for the Table (using the function from global_model.php)
    if (function_exists('get_all_notifications')) {
        // Fetch up to 50 latest messages (Admin view)
        $notification_history = get_all_notifications($pdo, 50); 
    }
} catch (PDOException $e) { /* Ignore database errors for display */ }

require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800">Send Manual Notification</h1>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Compose Message</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-weight-bold">Send To:</label>
                        <select name="target_role" id="target_role" class="form-select" required onchange="toggleEmployeeSelect()">
                            <option value="All">Everyone (Global)</option>
                            <option value="Employee">All Employees</option>
                            <option value="Admin">All Admins</option>
                            <option value="Specific">Specific Employee</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3" id="employee_select_div" style="display:none;">
                        <label class="form-label font-weight-bold">Select Employee (Target ID):</label>
                        <select name="target_user_id" class="form-select">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>">
                                    <?php echo htmlspecialchars($emp['lastname'] . ', ' . $emp['firstname']); ?> (<?php echo $emp['employee_id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label font-weight-bold">Type:</label>
                        <select name="type" class="form-select" required>
                            <option value="info">General Info</option>
                            <option value="warning">Warning / Urgent</option>
                            <option value="payroll">Payroll Related</option>
                            <option value="leave">Leave Related</option>
                            <option value="system">System Maintenance</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label font-weight-bold">Redirect Link (Optional):</label>
                        <input type="text" name="link" class="form-control" placeholder="e.g., my_leaves.php">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">Message:</label>
                    <textarea name="message" class="form-control" rows="3" required placeholder="Type your notification here..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i> Send Notification
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-history me-2"></i>Sent Notification History (Last 50)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped small" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase">
                        <tr>
                            <th>Sent</th>
                            <th>Target</th>
                            <th>Sender</th>
                            <th>Message</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Read</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($notification_history) > 0): ?>
                            <?php foreach ($notification_history as $notif): ?>
                                <?php 
                                    // Determine the display target
                                    $target = $notif['target_role'];
                                    if ($notif['target_user_id']) {
                                        // Use full name if available via JOIN, otherwise use the ID
                                        $target_name = $notif['firstname'] ? htmlspecialchars($notif['firstname'] . ' ' . $notif['lastname']) : $notif['target_user_id'];
                                        $target = "Specific: " . $target_name;
                                    }
                                    
                                    // Status/Read Badge
                                    $read_status = $notif['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-danger">Unread</span>';

                                    // Type Badge
                                    $type_badge = match ($notif['type']) {
                                        'payroll' => '<span class="badge bg-success">Payroll</span>',
                                        'leave' => '<span class="badge bg-warning text-dark">Leave</span>',
                                        'warning' => '<span class="badge bg-danger">Warning</span>',
                                        default => '<span class="badge bg-secondary">Info</span>',
                                    };
                                ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($target); ?></td>
                                    <td><?php echo htmlspecialchars($notif['sender_name']); ?></td>
                                    <td><?php echo htmlspecialchars($notif['message']); ?></td>
                                    <td class="text-center"><?php echo $type_badge; ?></td>
                                    <td class="text-center"><?php echo $read_status; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No notification history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEmployeeSelect() {
    var role = document.getElementById("target_role").value;
    var empDiv = document.getElementById("employee_select_div");
    
    if (role === "Specific") {
        empDiv.style.display = "block";
    } else {
        // Clear the value when hiding, preventing accidental specific send
        document.querySelector('select[name="target_user_id"]').value = "";
        empDiv.style.display = "none";
    }
}
</script>

<?php require 'template/footer.php'; ?>