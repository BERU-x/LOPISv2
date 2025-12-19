<?php
/**
 * api/admin/holiday_action.php
 * Handles Holiday Server-Side Processing (SSP), CRUD operations, and Payroll Multipliers.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php';

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['usertype']) || !in_array($_SESSION['usertype'], [0, 1])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if (!isset($pdo)) {
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';
$draw = (int)($_GET['draw'] ?? 1);

// =================================================================================
// ACTION 1: FETCH ALL (DataTables SSP)
// =================================================================================
if ($action === 'fetch') {
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    
    try {
        // Count Total
        $recordsTotal = (int)$pdo->query("SELECT COUNT(id) FROM tbl_holidays")->fetchColumn();

        // Build Filters
        $where_params = [];
        $where_bindings = [];
        if (!empty($search)) {
            $where_params[] = "(holiday_name LIKE :search OR holiday_type LIKE :search)";
            $where_bindings[':search'] = "%$search%";
        }
        
        $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";
        
        // Count Filtered
        $filteredStmt = $pdo->prepare("SELECT COUNT(id) FROM tbl_holidays" . $where_sql);
        $filteredStmt->execute($where_bindings);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        // Fetch Data
        $sql = "SELECT * FROM tbl_holidays $where_sql ORDER BY holiday_date DESC LIMIT :start, :length";
        $stmt = $pdo->prepare($sql);
        
        foreach ($where_bindings as $key => $val) { 
            $stmt->bindValue($key, $val); 
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ]);
    } catch (Exception $e) {
        echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 2: FETCH SINGLE (For Edit Modal)
// =================================================================================
if ($action === 'get_details') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_holidays WHERE id = ?");
        $stmt->execute([$id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($details) {
            echo json_encode(['status' => 'success', 'details' => $details]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Holiday not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 3: CREATE / UPDATE HOLIDAY
// =================================================================================
if (in_array($action, ['create', 'update'])) {
    $id    = (int)($_POST['id'] ?? 0);
    $date  = trim($_POST['holiday_date'] ?? '');
    $name  = trim($_POST['holiday_name'] ?? '');
    $type  = trim($_POST['holiday_type'] ?? 'Regular'); 
    $rate  = (float)($_POST['payroll_multiplier'] ?? 1.00);

    if (empty($date) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Date and Name are required.']);
        exit;
    }

    try {
        // Check for duplicates
        $dupeSql = "SELECT id FROM tbl_holidays WHERE holiday_date = ? " . ($action === 'update' ? "AND id != ?" : "");
        $dupeStmt = $pdo->prepare($dupeSql);
        $dupeParams = ($action === 'update') ? [$date, $id] : [$date];
        $dupeStmt->execute($dupeParams);

        if($dupeStmt->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'A holiday already exists on this date.']);
            exit;
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO tbl_holidays (holiday_date, holiday_name, holiday_type, payroll_multiplier) VALUES (?, ?, ?, ?)");
            $stmt->execute([$date, $name, $type, $rate]);
            $audit_type = 'CREATE_HOLIDAY';
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_holidays SET holiday_date=?, holiday_name=?, holiday_type=?, payroll_multiplier=? WHERE id=?");
            $stmt->execute([$date, $name, $type, $rate, $id]);
            $audit_type = 'UPDATE_HOLIDAY';
        }
        
        // Log to Audit Trail
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], $audit_type, "Holiday: $name on $date (Rate: $rate)");

        echo json_encode(['status' => 'success', 'message' => "Holiday successfully " . ($action === 'create' ? "added." : "updated.")]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 4: DELETE
// =================================================================================
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        // Fetch details for logging before deleting
        $st = $pdo->prepare("SELECT holiday_name, holiday_date FROM tbl_holidays WHERE id = ?");
        $st->execute([$id]);
        $h = $st->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM tbl_holidays WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], 'DELETE_HOLIDAY', "Deleted holiday: {$h['holiday_name']} ({$h['holiday_date']})");
            echo json_encode(['status' => 'success', 'message' => 'Holiday deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Holiday not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}