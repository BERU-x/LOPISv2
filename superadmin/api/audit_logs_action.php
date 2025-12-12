<?php
// api/audit_logs_action.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../db_connection.php'; 

$action = $_REQUEST['action'] ?? '';

try {

    // 1. FETCH LOGS (Server-Side Logic)
    if ($action === 'fetch') {
        
        // Base Columns
        $columns = ['l.created_at', 'username', 'l.usertype', 'l.action', 'l.ip_address'];
        
        // Complex Join to get Name based on usertype
        // If usertype 0/1/2, check tbl_users -> tbl_employees (if linked) or fallback to email
        $sql = "SELECT l.*, 
                       COALESCE(CONCAT(e.firstname, ' ', e.lastname), u.email, 'Unknown') as username
                FROM tbl_audit_logs l
                LEFT JOIN tbl_users u ON l.user_id = u.id
                LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
                WHERE 1=1";

        $params = [];

        // Search
        if (!empty($_POST['search']['value'])) {
            $search = $_POST['search']['value'];
            $sql .= " AND (l.action LIKE ? OR l.ip_address LIKE ? OR u.email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Count Filtered
        $stmtCount = $pdo->prepare($sql);
        $stmtCount->execute($params);
        $recordsFiltered = $stmtCount->rowCount();

        // Order
        if (isset($_POST['order'][0]['column'])) {
            $colIndex = $_POST['order'][0]['column'];
            $colDir = $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            // Map index 0 to created_at
            $sortCol = ($colIndex == 0) ? 'l.created_at' : 'l.id'; 
            $sql .= " ORDER BY $sortCol $colDir";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        // Limit
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 25;
        $sql .= " LIMIT $start, $length";

        // Execute
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total Records
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM tbl_audit_logs");
        $recordsTotal = $totalStmt->fetchColumn();

        echo json_encode([
            "draw" => intval($_POST['draw'] ?? 1),
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsFiltered),
            "data" => $data
        ]);
        exit;
    }

    // 2. GET DETAILS
    if ($action === 'get_details') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_audit_logs WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // 3. CLEAR LOGS (Older than 30 days)
    if ($action === 'clear_logs') {
        // Only Super Admin can do this (Security check)
        if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 0) {
             echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
             exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tbl_audit_logs WHERE created_at < NOW() - INTERVAL 30 DAY");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        echo json_encode(['status' => 'success', 'message' => "Cleaned up $deleted old log entries."]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>