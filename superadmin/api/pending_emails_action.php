<?php
// superadmin/api/pending_emails_action.php - UPDATED for token expiry check
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../email_handler.php'; // Required for resend action

// Check Superadmin Authentication (Assuming usertype 0 is Super Admin)
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {

    // --- 1. FETCH LIST OF PENDING EMAILS (No change needed here) ---
    if ($action === 'fetch_pending') {
        // ... (fetch logic remains the same) ...
        $sql = "
            SELECT 
                p.id, 
                p.token, 
                p.reason, 
                p.attempted_at, 
                u.employee_id, 
                u.email
            FROM tbl_pending_emails p
            JOIN tbl_users u ON p.user_id = u.id
            WHERE p.is_sent = 0 
            ORDER BY p.attempted_at DESC";
        
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // --- 2. RESEND A SPECIFIC EMAIL (NEW LOGIC) ---
    if ($action === 'resend_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pending_id = $_POST['id'] ?? null;

        if (!$pending_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing pending ID.']);
            exit;
        }

        // a) Fetch the pending record details and its corresponding token from tbl_password_resets
        $stmt = $pdo->prepare("
            SELECT 
                p.user_id, p.token, u.email, 
                r.expires_at AS token_expiry 
            FROM tbl_pending_emails p
            JOIN tbl_users u ON p.user_id = u.id
            LEFT JOIN tbl_password_resets r ON p.token = r.token 
            WHERE p.id = ? AND p.is_sent = 0
        ");
        $stmt->execute([$pending_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            echo json_encode(['status' => 'error', 'message' => 'Record not found or already sent.']);
            exit;
        }
        
        // b) CRITICAL CHECK: Verify token expiration
        if (empty($record['token_expiry'])) {
            // This token might have been used or deleted from tbl_password_resets
            echo json_encode(['status' => 'warning', 'message' => 'Cannot resend: The reset token is missing from the active list (already used or corrupted).']);
            // Optionally delete from pending_emails here
            $pdo->prepare("DELETE FROM tbl_pending_emails WHERE id = ?")->execute([$pending_id]);
            exit;
        }

        $token_expiry = strtotime($record['token_expiry']);
        if ($token_expiry <= time()) {
            // Token is expired!
            echo json_encode(['status' => 'warning', 'message' => 'Cannot resend: The reset link has expired. The user must initiate a new reset request.']);
            // Delete the expired token from the pending table and the reset table
            $pdo->prepare("DELETE FROM tbl_pending_emails WHERE id = ?")->execute([$pending_id]);
            $pdo->prepare("DELETE FROM tbl_password_resets WHERE token = ?")->execute([$record['token']]);
            exit;
        }


        // c) Construct reset link and email content
        $reset_link = "http://localhost/LOPISv2/process/reset_password.php?token={$record['token']}";
        $subject = "RE-SENT: Password Reset Request";
        $body = "This is a manual re-send of your password reset link. Click here: \n\n" . $reset_link;

        // d) Attempt to send email again
        $email_status = send_email($pdo, $record['email'], $subject, $body);

        if ($email_status === 'sent') {
            // e) Mark the record as sent and delete the pending entry
            $pdo->prepare("DELETE FROM tbl_pending_emails WHERE id = ?")->execute([$pending_id]);

            // Optional: Log success to audit logs
            // ...

            echo json_encode(['status' => 'success', 'message' => 'Email successfully re-sent to ' . $record['email'] . '. Pending entry removed.']);
        } elseif ($email_status === 'disabled') {
            // f) If still disabled, update the attempted_at time to reflect the retry failure
            $pdo->prepare("UPDATE tbl_pending_emails SET attempted_at = NOW(), reason = 'DISABLED' WHERE id = ?")->execute([$pending_id]);
            echo json_encode(['status' => 'info', 'message' => 'Email notifications are still disabled. Retry skipped and attempt time updated.']);
        } else { // 'failed'
            // g) SMTP error again
            $pdo->prepare("UPDATE tbl_pending_emails SET attempted_at = NOW(), reason = 'FAILED' WHERE id = ?")->execute([$pending_id]);
            echo json_encode(['status' => 'error', 'message' => 'Resend attempt failed again due to an SMTP error. Attempt time updated.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Pending Email Admin Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>