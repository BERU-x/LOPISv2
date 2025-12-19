<?php
// api/superadmin/audit_logs_action.php
// Handles System Activity Monitoring (Super Admin Only)
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

$action = $_REQUEST['action'] ?? '';

try {

    // =========================================================================
    // ACTION 1: FETCH LOGS (Server-Side DataTables Logic)
    // =========================================================================
    if ($action === 'fetch') {
        
        // Base Query with COALESCE to prioritize Full Name, then Email, then ID
        $sql = "SELECT l.*, 
                       COALESCE(CONCAT(e.firstname, ' ', e.lastname), u.email, CONCAT('User #', l.user_id)) as full_name,
                       u.employee_id
                FROM tbl_audit_logs l
                LEFT JOIN tbl_users u ON l.user_id = u.id
                LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
                WHERE 1=1";

        $params = [];

        // Search Logic (Action, IP, or Email)
        if (!empty($_POST['search']['value'])) {
            $search = $_POST['search']['value'];
            $sql .= " AND (l.action LIKE ? OR l.ip_address LIKE ? OR u.email LIKE ? OR l.details LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Count Filtered
        $stmtCount = $pdo->prepare($sql);
        $stmtCount->execute($params);
        $recordsFiltered = $stmtCount->rowCount();

        // Order Logic
        if (isset($_POST['order'][0]['column'])) {
            $columns_map = ['l.created_at', 'full_name', 'l.usertype', 'l.action', 'l.ip_address'];
            $colIndex = $_POST['order'][0]['column'];
            $colDir = $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            $sortCol = $columns_map[$colIndex] ?? 'l.created_at'; 
            $sql .= " ORDER BY $sortCol $colDir";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        // Pagination
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 25;
        $sql .= " LIMIT $start, $length";

        // Execute Final Query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Total Records (Unfiltered)
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

    // =========================================================================
    // ACTION 2: GET LOG DETAILS (For Modal View)
    // =========================================================================
    if ($action === 'get_details') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT l.*, u.email FROM tbl_audit_logs l LEFT JOIN tbl_users u ON l.user_id = u.id WHERE l.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Log entry not found.']);
        }
        exit;
    }

    // =========================================================================
    // ACTION 3: MAINTENANCE - CLEAR OLD LOGS
    // =========================================================================
    if ($action === 'clear_logs') {
        // Deletes logs older than 30 days to save DB space
        $stmt = $pdo->prepare("DELETE FROM tbl_audit_logs WHERE created_at < NOW() - INTERVAL 30 DAY");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        echo json_encode(['status' => 'success', 'message' => "Successfully purged $deleted old log entries."]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>