<?php
// helpers/audit_helper.php

function logAudit($pdo, $user_id, $usertype, $action, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        $stmt = $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, usertype, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $usertype, $action, $details, $ip, $agent]);
    } catch (Exception $e) {
        // Silently fail so we don't break the main app flow
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
?>