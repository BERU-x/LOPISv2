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
?>