<?php
// api/superadmin/pending_emails_action.php
header('Content-Type: application/json');
session_start();

// --- 1. AUTHENTICATION (Super Admin Only) ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// --- 2. DEPENDENCIES ---
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/email_handler.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php'; // <--- Added Audit Helper

$action = $_REQUEST['action'] ?? '';

try {

    // =========================================================================
    // ACTION 1: FETCH PENDING EMAILS
    // =========================================================================
    if ($action === 'fetch_pending') {
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

    // =========================================================================
    // ACTION 2: RESEND EMAIL (With Audit Log)
    // =========================================================================
    if ($action === 'resend_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pending_id = $_POST['id'] ?? null;

        if (!$pending_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing pending ID.']);
            exit;
        }

        // A. Fetch Record
        $stmt = $pdo->prepare("
            SELECT 
                p.user_id, p.token AS otp_code, u.email, 
                r.expires_at AS token_expiry 
            FROM tbl_pending_emails p
            JOIN tbl_users u ON p.user_id = u.id
            LEFT JOIN tbl_password_resets r ON p.token = r.token 
            WHERE p.id = ? AND p.is_sent = 0
        ");
        $stmt->execute([$pending_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            echo json_encode(['status' => 'error', 'message' => 'Record not found or already processed.']);
            exit;
        }
        
        // B. Verify Expiry
        if (empty($record['token_expiry'])) {
            $pdo->prepare("DELETE FROM tbl_pending_emails WHERE id = ?")->execute([$pending_id]);
            
            // Log the cleanup
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'RESEND_CLEANUP', "Removed invalid pending entry for {$record['email']}");
            
            echo json_encode(['status' => 'warning', 'message' => 'Invalid code. Entry removed.']);
            exit;
        }

        $token_expiry = strtotime($record['token_expiry']);
        if ($token_expiry <= time()) {
            $pdo->prepare("DELETE FROM tbl_pending_emails WHERE id = ?")->execute([$pending_id]);
            $pdo->prepare("DELETE FROM tbl_password_resets WHERE token = ?")->execute([$record['otp_code']]);
            
            // Log the cleanup
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'RESEND_EXPIRED', "Removed expired pending entry for {$record['email']}");

            echo json_encode(['status' => 'warning', 'message' => 'Code expired. Entry removed.']);
            exit;
        }

        // C. Construct OTP Email
        $otp_code = $record['otp_code']; 
        $subject = "RE-SENT: Password Reset Code - LOPISv2";
        $body_html = "
            <h3>Password Reset Request (Resent)</h3>
            <p>An administrator has manually re-sent your verification code. Please use this code to proceed:</p>
            <h1 style='background-color: #f3f3f3; padding: 10px; display: inline-block; letter-spacing: 5px; font-family: monospace;'>{$otp_code}</h1>
            <p>This code will expire shortly.</p>
        ";
        
        // D. Send Email
        $email_status = send_email($pdo, $record['email'], $subject, strip_tags($body_html), $body_html);

        if ($email_status === 'sent') {
            // Success: Remove from pending list
            $pdo->prepare("DELETE FROM tbl_pending_emails WHERE id = ?")->execute([$pending_id]);

            // ⭐ LOG AUDIT SUCCESS ⭐
            logAudit(
                $pdo, 
                $_SESSION['user_id'], 
                $_SESSION['usertype'], 
                'RESEND_OTP_SUCCESS', 
                "Manually resent OTP code to " . $record['email']
            );

            echo json_encode(['status' => 'success', 'message' => 'Code successfully re-sent to ' . $record['email']]);
        } elseif ($email_status === 'disabled') {
            // Failed: Disabled
            $pdo->prepare("UPDATE tbl_pending_emails SET attempted_at = NOW(), reason = 'DISABLED' WHERE id = ?")->execute([$pending_id]);
            
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'RESEND_OTP_SKIPPED', "Resend skipped (Email Disabled) for " . $record['email']);
            
            echo json_encode(['status' => 'info', 'message' => 'Email system is disabled. Retry updated but skipped.']);
        } else { 
            // Failed: SMTP Error
            $pdo->prepare("UPDATE tbl_pending_emails SET attempted_at = NOW(), reason = 'FAILED' WHERE id = ?")->execute([$pending_id]);
            
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'RESEND_OTP_FAILED', "SMTP Error resending to " . $record['email']);
            
            echo json_encode(['status' => 'error', 'message' => 'SMTP Error: Failed to resend.']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Pending Email API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>