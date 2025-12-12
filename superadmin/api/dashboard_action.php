<?php
// api/dashboard_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_POST['action'] ?? '';

if ($action === 'fetch_metrics') {
    
    // Helper to safely fetch counts
    function getCount($pdo, $sql) {
        try {
            $stmt = $pdo->query($sql);
            return $stmt->fetchColumn();
        } catch (Exception $e) { return 0; }
    }

    $response = [];

    // 1. Basic Metrics
    $response['total_admins']    = getCount($pdo, "SELECT COUNT(*) FROM tbl_users WHERE usertype = 1 AND status = 1");
    $response['total_employees'] = getCount($pdo, "SELECT COUNT(*) FROM tbl_users WHERE usertype = 2 AND status = 1");
    $response['pending_users']   = getCount($pdo, "SELECT COUNT(*) FROM tbl_users WHERE status = 0");

    // 2. Active Today
    try {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM tbl_audit_logs WHERE action LIKE '%LOGIN%' AND DATE(created_at) = CURDATE()");
        $response['active_today'] = $stmt->fetchColumn();
    } catch (Exception $e) { $response['active_today'] = 0; }

    // 3. Lists
    try {
        $stmt = $pdo->query("SELECT email, usertype, created_at FROM tbl_users ORDER BY created_at DESC LIMIT 5");
        $response['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $response['recent_users'] = []; }

    try {
        $stmt = $pdo->query("SELECT l.action, l.details, l.created_at, COALESCE(u.email, 'System') as user 
                             FROM tbl_audit_logs l 
                             LEFT JOIN tbl_users u ON l.user_id = u.id 
                             ORDER BY l.created_at DESC LIMIT 5");
        $response['recent_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $response['recent_logs'] = []; }

    // 4. Chart Data: User Growth (Last 6 Months)
    $months = [];
    $counts = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M', strtotime("-$i months"));
        $monthNum = date('m', strtotime("-$i months"));
        $year = date('Y', strtotime("-$i months"));
        
        $months[] = $month;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
            $stmt->execute([$monthNum, $year]);
            $counts[] = $stmt->fetchColumn();
        } catch(Exception $e) { $counts[] = 0; }
    }
    $response['chart_growth_labels'] = $months;
    $response['chart_growth_data'] = $counts;

    // 5. Chart Data: Role Distribution
    $response['chart_role_data'] = [
        getCount($pdo, "SELECT COUNT(*) FROM tbl_users WHERE usertype = 1"),
        getCount($pdo, "SELECT COUNT(*) FROM tbl_users WHERE usertype = 2"),
        getCount($pdo, "SELECT COUNT(*) FROM tbl_users WHERE usertype = 0")
    ];

    echo json_encode(['status' => 'success', 'data' => $response]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
?>