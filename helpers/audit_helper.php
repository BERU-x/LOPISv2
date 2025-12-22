<?php
// helpers/audit_helper.php

function logAudit($pdo, $user_id, $usertype, $action, $details = '') {
    try {
        // 1. Check if $pdo exists
        if (!$pdo) {
            error_log("Audit Error: PDO connection object is null.");
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        // 2. Execute with error catching
        $stmt = $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, usertype, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        
        if (!$stmt->execute([$user_id, $usertype, $action, $details, $ip, $agent])) {
             // This captures SQL-specific errors
             $err = $stmt->errorInfo();
             error_log("SQL Audit Error: " . $err[2]);
        }

    } catch (Exception $e) {
        // This captures PHP-specific errors
        error_log("Audit Exception: " . $e->getMessage());
    }
}

/**
 * AUTO PURGE: Deletes logs older than X days and records the event.
 */
function autoPurgeAuditLogs(PDO $pdo, int $days = 90): int {
    try {
        // 1. Count how many logs are about to be deleted (for the report)
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $countStmt->execute([(int)$days]);
        $deletedCount = (int)$countStmt->fetchColumn();

        if ($deletedCount > 0) {
            // 2. Perform the deletion
            $deleteStmt = $pdo->prepare("DELETE FROM tbl_audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $deleteStmt->execute([(int)$days]);

            // 3. Record the maintenance event
            // We use user_id = 0 to signify the system itself performed the action
            logAudit($pdo, 0, 0, 'SYSTEM_PURGE', "Automated maintenance: Purged $deletedCount logs older than $days days.");
        }
        
        return $deletedCount;
    } catch (PDOException $e) {
        error_log("LOG PURGE ERROR: " . $e->getMessage());
        return 0;
    }
}
?>