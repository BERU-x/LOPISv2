<?php
error_log(print_r($_POST, true));

// api/superadmin/admin_management_action.php
// Handles CRUD operations for Managing System Administrators (usertype = 1)
header('Content-Type: application/json');
session_start();

// --- 1. AUTHENTICATION CHECK ---
// Only Super Admin (0) can access this file
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DATABASE CONNECTION (Up 2 Levels) ---
require_once __DIR__ . '/../../db_connection.php';

// --- 3. AUDIT LOGGING HELPER ---
require_once __DIR__ . '/../../helpers/audit_helper.php';

// --- 4. INCLUDE EMAIL HANDLER ---
require_once __DIR__ . '/../../helpers/email_handler.php';  // Add this line

// Get the Action
$action = $_REQUEST['action'] ?? '';

try {

    // =================================================================================
    // ACTION 1: FETCH ALL ADMINS (Server-Side DataTables)
    // =================================================================================
    if ($action === 'fetch') {

        // Base Query: Fetch only Admins (usertype = 1)
        // We exclude Deleted status (if you use soft deletes) or just fetch all
        $sql = "SELECT id, employee_id, email, status, created_at FROM tbl_users WHERE usertype = 1";
        $params = [];

        // Search Logic
        if (!empty($_POST['search']['value'])) {
            $searchValue = $_POST['search']['value'];
            $sql .= " AND (employee_id LIKE ? OR email LIKE ?)";
            $params[] = "%$searchValue%";
            $params[] = "%$searchValue%";
        }

        // Count Filtered Records
        $stmtCount = $pdo->prepare($sql);
        $stmtCount->execute($params);
        $recordsFiltered = $stmtCount->rowCount();

        // Ordering
        $columns = ['employee_id', 'email', 'status', 'created_at'];
        if (isset($_POST['order'][0]['column'])) {
            $colIndex = $_POST['order'][0]['column'];
            $colDir = $_POST['order'][0]['dir'] === 'desc' ? 'DESC' : 'ASC';
            $orderBy = $columns[$colIndex] ?? 'created_at';
            $sql .= " ORDER BY $orderBy $colDir";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }

        // Pagination
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $sql .= " LIMIT $start, $length";

        // Execute Final Query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Total Records (Unfiltered)
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_users WHERE usertype = 1");
        $recordsTotal = $totalStmt->fetchColumn();

        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data
        ]);
        exit;
    }

    // =================================================================================
    // ACTION 2: GET SINGLE DETAILS
    // =================================================================================
    if ($action === 'get_details') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, employee_id, email, status FROM tbl_users WHERE id = ? AND usertype = 1");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'details' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Admin not found.']);
        }
        exit;
    }
    // =================================================================================
    // ACTION 6: GET AVAILABLE EMPLOYEES
    // =================================================================================
    if ($action === 'get_available_employees') {
        $sql = "SELECT employee_id, CONCAT(firstname, ' ', lastname) AS name FROM tbl_employees WHERE employee_id NOT IN (SELECT employee_id FROM tbl_users)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($employees) {
            echo json_encode(['status' => 'success', 'employees' => $employees]);
        } else {
            echo json_encode(['status' => 'success', 'employees' => []]); // Return empty array if no employees found
        }
        exit;
    }

    // =================================================================================
    // ACTION 3: CREATE NEW ADMIN
    // =================================================================================
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $employee_id = trim($_POST['employee_id']);
        $email = trim($_POST['email']);
        $status = $_POST['status'] ?? 1;
        $raw_password = !empty($_POST['password']) ? $_POST['password'] : 'losi@123'; // Default strong password

        // Validation: Check Duplicates in tbl_users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE employee_id = ? OR email = ?");
        $stmt->execute([$employee_id, $email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee ID or Email already exists in users table!']);
            exit;
        }

        // Validation: Check if employee exists in employees table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_employees WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee ID does not exist in employees table!']);
            exit;
        }

        // Validation: Ensure the employee doesn't already HAVE an account (even if not admin)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This employee already has a user account.']);
            exit;
        }

        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
        $usertype = 1; // Admin Role

        $sql = "INSERT INTO tbl_users (employee_id, email, password, usertype, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$employee_id, $email, $hashed_password, $usertype, $status])) {

            $user_id = $pdo->lastInsertId(); // Get the newly inserted user's ID
            $token = bin2hex(random_bytes(32)); // Generate a random token
            $reason = "new_admin_account"; // Reason for the email

            // --- ADD PENDING EMAIL (BEFORE SENDING) ---
            if (!addPendingEmail($pdo, $user_id, $token, $reason)) {
                error_log("Failed to add pending email to tbl_pending_emails for user ID: $user_id");
                $message = 'New Admin added successfully! Failed to add pending email.'; // Still a success, but with a warning
                echo json_encode(['status' => 'success', 'message' => $message]);
                exit; // Exit early, but still indicate success
            }

            // --- EMAIL NOTIFICATION ---
            $subject = 'New Admin Account Created';
            $body = "Dear Admin,\n\nA new admin account has been created for you with the following details:\n\nEmail: $email\nPassword: $raw_password (default)\n\nPlease log in and change your password as soon as possible.\n\nSincerely,\nThe System Administrator";
            $html_body = "<p>Dear Admin,</p><p>A new admin account has been created for you with the following details:</p><p>Email: $email</p><p>Password: $raw_password (default)</p><p>Please log in and change your password as soon as possible.</p><p>Sincerely,<br>The System Administrator</p>";

            // Call the send_email function
            $email_status = send_email($pdo, $email, $subject, $body, $html_body);

            if ($email_status === 'sent') {
                $message = 'New Admin added successfully! Email sent.';
                // Mark email as sent (if you want to remove it from pending after successful sending)
                // You'd need the pending email ID here, which isn't readily available.  Consider returning the ID from addPendingEmail
            } elseif ($email_status === 'disabled') {
                $message = 'New Admin added successfully! Email sending is disabled.';
            } else {
                $message = 'New Admin added successfully!  Failed to send email.';
            }

            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'CREATE_ADMIN', "Created new admin: $email");

            echo json_encode(['status' => 'success', 'message' => $message]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add admin.']);
        }
        exit;
    }
    // =================================================================================
    // ACTION 4: UPDATE ADMIN
    // =================================================================================
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $id = $_POST['id'];
        $employee_id = trim($_POST['employee_id']);
        $email = trim($_POST['email']);
        $status = $_POST['status'];

        // Check duplicates (excluding self)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE (employee_id = ? OR email = ?) AND id != ?");
        $stmt->execute([$employee_id, $email, $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Employee ID or Email already exists for another user.']);
            exit;
        }

        // Prepare Update
        $params = [$employee_id, $email, $status];
        $sql = "UPDATE tbl_users SET employee_id = ?, email = ?, status = ?";

        // Only update password if provided
        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {

            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'UPDATE_ADMIN', "Updated admin details for ID: $id ($email)");

            echo json_encode(['status' => 'success', 'message' => 'Admin updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update admin.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION 5: DELETE ADMIN
    // =================================================================================
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];

        // Prevent deleting self
        if ($_SESSION['user_id'] == $id) {
            echo json_encode(['status' => 'error', 'message' => 'You cannot delete your own account!']);
            exit;
        }

        // Fetch email for logging before deletion
        $stmt = $pdo->prepare("SELECT email FROM tbl_users WHERE id = ?");
        $stmt->execute([$id]);
        $target_email = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = ?");
        if ($stmt->execute([$id])) {

            // Audit Log
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'DELETE_ADMIN', "Deleted admin: $target_email (ID: $id)");

            echo json_encode(['status' => 'success', 'message' => 'Admin deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete admin.']);
        }
        exit;
    }

    // Fallback
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>