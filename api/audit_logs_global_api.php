<?php
// api/audit_logs_global_api.php
header('Content-Type: application/json');
session_start();

// --- PATH ADJUSTMENTS ---
// Adjust paths based on the location of this API file (e.g., /api/)
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../app/models/global_app_model.php'; // Include the Global Model

// --- SECURITY CHECK ---
// Only Super Admin (0) and Admin (1) should access logs.
// Check this before allowing any action.
if (!is_user_authorized([0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Access to Audit Logs is restricted.']);
    exit;
}

// Use $_REQUEST to handle both GET (for initial DataTables fetch) and POST
$action = $_REQUEST['action'] ?? '';

try {

    // 1. FETCH LOGS (Server-Side Logic for DataTables)
    if ($action === 'fetch') {
        
        // Complex Join to get Name based on usertype
        $sql = "SELECT l.*, 
                        COALESCE(CONCAT(e.firstname, ' ', e.lastname), u.email, 'Unknown') as username
                FROM tbl_audit_logs l
                LEFT JOIN tbl_users u ON l.user_id = u.id
                LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
                WHERE 1=1";

        $params = [];

        // --- Search ---
        if (!empty($_POST['search']['value'])) {
            $search = $_POST['search']['value'];
            $sql .= " AND (l.action LIKE ? OR l.ip_address LIKE ? OR u.email LIKE ? OR l.details LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%"; // Allow searching within details too
        }

        // Count Filtered Records
        $stmtCount = $pdo->prepare($sql);
        $stmtCount->execute($params);
        $recordsFiltered = $stmtCount->rowCount();

        // --- Order ---
        if (isset($_POST['order'][0]['column'])) {
            $colIndex = $_POST['order'][0]['column'];
            $colDir = $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            // Note: DataTables column indexes are based on the final columns array in the JS.
            // Assuming your JS columns map roughly: 0=created_at, 1=username, 2=usertype, etc.
            
            // For robust ordering, it's safer to just rely on l.created_at DESC if no specific column is mapped
            // We use l.created_at for the first column (index 0)
            $sortCol = ($colIndex == 0) ? 'l.created_at' : 'l.created_at'; 
            $sql .= " ORDER BY $sortCol $colDir";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        // --- Limit ---
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 25;
        // Cast to int for security against SQL injection in LIMIT clause
        $sql .= " LIMIT " . (int)$start . ", " . (int)$length; 

        // Execute Final Query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total Records (Unfiltered)
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

    // 2. GET DETAILS (Fetch single log entry for modal view)
    if ($action === 'get_details') {
        $stmt = $pdo->prepare("SELECT * FROM tbl_audit_logs WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $log_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($log_data) {
             echo json_encode(['status' => 'success', 'data' => $log_data]);
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Log entry not found.']);
        }
        exit;
    }

    // 3. CLEAR LOGS (Older than 30 days)
    if ($action === 'clear_logs') {
        // ENFORCED SECURITY CHECK: Only Super Admin (usertype 0) can clear logs.
        if ($_SESSION['usertype'] != 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permission denied. Only Super Administrators can clear logs.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tbl_audit_logs WHERE created_at < NOW() - INTERVAL 30 DAY");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        
        echo json_encode(['status' => 'success', 'message' => "Cleaned up $deleted old log entries."]);
        exit;
    }

    // Default response for invalid action
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);

} catch (Exception $e) {
    http_response_code(500);
    error_log('Audit Log Global API Error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>