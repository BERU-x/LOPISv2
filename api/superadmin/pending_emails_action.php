<?php
/**
 * api/superadmin/pending_emails_action.php
 * * RESPONSIBILITIES:
 * 1. Global Auto-Sender (Action: 'process_queue') - Accessible by ANY logged-in user.
 * 2. Queue Management (Action: 'fetch_pending', 'resend_email') - Accessible ONLY by Super Admin.
 */

header('Content-Type: application/json');
session_start();

$action = $_REQUEST['action'] ?? '';

// =================================================================================
// 1. SECURITY & PERMISSIONS
// =================================================================================

// A. GLOBAL AUTHENTICATION CHECK
// Verify the user is actually logged in to the system.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please login.']);
    exit;
}

// B. ROLE-BASED PERMISSION GATE
// If the action is NOT 'process_queue', strictly enforce Super Admin (usertype 0) access.
// 'process_queue' is the exception because we want regular users to help clear the queue.
if ($action !== 'process_queue') {
    if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access Denied. Only Super Admins can manage the email queue.']);
        exit;
    }
}

// =================================================================================
// 2. DEPENDENCIES
// =================================================================================
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/email_handler.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

try {
    
    // =================================================================================
    // ACTION: BATCH PROCESS (THE "PIGGYBACK" SYSTEM)
    // Access: Public (Any Logged-in User)
    // Description: Tries to send 5 pending emails. Uses a file lock to prevent spamming.
    // =================================================================================
    if ($action === 'process_queue') {
        
        $lock_file = __DIR__ . '/../../email_lock.timestamp';
        $last_run = file_exists($lock_file) ? file_get_contents($lock_file) : 0;
        
        if (time() - $last_run < 20) {
            echo json_encode(['status' => 'success', 'message' => 'Skipped (Cooling down).']);
            exit;
        }

        file_put_contents($lock_file, time());

        // --- NEW: AUTO PURGE LOGS (Runs once per day roughly) ---
        // We only run the purge if it hasn't been done in the last 24 hours
        $purge_lock = __DIR__ . '/../../purge_lock.timestamp';
        $last_purge = file_exists($purge_lock) ? file_get_contents($purge_lock) : 0;

        if (time() - $last_purge > 86400) { // 86400 seconds = 24 hours
            autoPurgeAuditLogs($pdo, 90); // Purge logs older than 90 days
            file_put_contents($purge_lock, time());
        }

        // Proceed with Email Queue
        $result = processEmailQueue($pdo, 5);
        
        // 3. RETURN STATUS
        if ($result === 'processed') {
            echo json_encode(['status' => 'success', 'message' => 'Batch processed successfully.']);
        } elseif ($result === 'empty') {
            echo json_encode(['status' => 'success', 'message' => 'Queue is empty.']);
        } elseif ($result === 'disabled') {
            echo json_encode(['status' => 'error', 'message' => 'Email system is globally disabled in settings.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Unknown processing error.']);
        }
        exit;
    }

    // =================================================================================
    // ACTION: FETCH PENDING LIST
    // =================================================================================
    if ($action === 'fetch_pending') {
        
        $sql = "SELECT 
                    p.id, 
                    p.recipient_email, 
                    p.subject, 
                    p.email_type, 
                    p.attempted_at, 
                    p.error_message, 
                    p.is_sent,
                    u.employee_id
                FROM tbl_pending_emails p
                LEFT JOIN tbl_users u ON p.user_id = u.id
                WHERE p.is_sent IN (0, 2)
                ORDER BY p.attempted_at ASC"; // Removed p.created_at from SELECT and ORDER BY
        
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    // =================================================================================
    // ACTION: FORCE RESEND
    // Access: Super Admin Only
    // Description: Manually attempts to send a specific failed email immediately.
    // =================================================================================
    if ($action === 'resend_email') {
        $queue_id = $_POST['id'];

        if (empty($queue_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit;
        }

        // Attempt to send immediately using helper function
        // Note: sendImmediateEmail should return 'sent', 'error', or 'disabled'
        $status = sendImmediateEmail($pdo, (int)$queue_id);
        
        if ($status === 'sent') {
            // Log this manual intervention
            logAudit($pdo, $_SESSION['user_id'], 0, 'EMAIL_FORCE_SEND', "Manually forced email ID: $queue_id");
            echo json_encode(['status' => 'success', 'message' => 'Email sent successfully.']);
        } elseif ($status === 'disabled') {
            echo json_encode(['status' => 'info', 'message' => 'Cannot send: Email system is disabled.']);
        } else {
            // Fetch the error message to show the admin
            $stmt = $pdo->prepare("SELECT error_message FROM tbl_pending_emails WHERE id = ?");
            $stmt->execute([$queue_id]);
            $error = $stmt->fetchColumn();
            
            echo json_encode(['status' => 'error', 'message' => 'SMTP Error: ' . ($error ?: 'Unknown failure')]);
        }
        exit;
    }

    // Default catch-all
    echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>