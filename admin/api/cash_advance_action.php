<?php
// api/cash_advance_action.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../models/global_model.php'; 

if (!isset($pdo)) {
    // Standardized fatal error response for the SSP endpoint
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? 'fetch';
$draw = (int)($_GET['draw'] ?? 1);

// =================================================================================
// ACTION 1: FETCH DATA (For DataTables SSP)
// =================================================================================
if ($action === 'fetch') {

    // --- A. COLUMNS & SORTING MAPPING ---
    $columns = [
        0 => 'e.lastname', // Sort by name
        1 => 'a.date_requested', 
        2 => 'a.amount', 
        3 => 'a.status'
    ];

    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    
    // Custom Filters
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    // --- B. BUILD QUERY BASE ---
    $sql_base = " FROM tbl_cash_advances a LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id ";
    
    $where_params = [];
    $where_bindings = [];

    // Global Search
    if (!empty($search)) {
        $term = '%' . $search . '%';
        $where_params[] = "(
            a.employee_id LIKE :search OR 
            e.firstname LIKE :search OR 
            e.lastname LIKE :search OR 
            a.status LIKE :search
        )";
        $where_bindings[':search'] = $term;
    }

    // Date Range
    if (!empty($start_date) && !empty($end_date)) {
        $where_params[] = "a.date_requested BETWEEN :start_date AND :end_date";
        $where_bindings[':start_date'] = $start_date;
        $where_bindings[':end_date']  = $end_date;
    }

    $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";

    // Ordering
    $order_sql = " ORDER BY a.date_requested DESC, a.created_at DESC"; // Default
    if (isset($_GET['order'])) {
        $col_idx = (int)$_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
        
        if (isset($columns[$col_idx])) {
            $colName = $columns[$col_idx];
            
            if ($colName === 'e.lastname') {
                $order_sql = " ORDER BY e.lastname $dir, e.firstname $dir";
            } else {
                $order_sql = " ORDER BY $colName $dir";
            }
        }
    }

    // --- C. EXECUTE COUNT QUERIES ---
    try {
        // Total Records (Unfiltered)
        $stmt = $pdo->query("SELECT COUNT(a.id) $sql_base");
        $recordsTotal = (int)$stmt->fetchColumn();

        // Filtered Records
        $stmt = $pdo->prepare("SELECT COUNT(a.id) $sql_base $where_sql");
        $stmt->execute($where_bindings);
        $recordsFiltered = (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        // Standardized SSP Error Response for count queries
        echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Counting query failed."]);
        exit;
    }

    // --- D. FETCH DATA ---
    $sql_data = "SELECT 
                    a.id, 
                    a.employee_id, 
                    a.date_requested, 
                    a.amount, 
                    a.status, 
                    a.remarks,
                    e.firstname, e.lastname, e.photo, e.department 
                  $sql_base $where_sql $order_sql LIMIT :start_limit, :length_limit";

    try {
        $stmt = $pdo->prepare($sql_data);
        
        // Bind search/filter parameters
        foreach ($where_bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        // Bind LIMIT parameters
        $stmt->bindValue(':start_limit', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length_limit', $length, PDO::PARAM_INT);

        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Final SSP Success Response
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ]);

    } catch (PDOException $e) {
        // Standardized SSP Error Response for data query
        echo json_encode(["draw" => $draw, "recordsTotal" => $recordsTotal, "recordsFiltered" => $recordsFiltered, "data" => [], "error" => "Data fetching query failed."]);
    }
    exit;
}

// =================================================================================
// ACTION 2: GET DETAILS (For View Modal)
// =================================================================================
if ($action === 'get_details') {
    $id = (int)($_POST['id'] ?? 0);

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   e.firstname, e.lastname, e.photo, e.department, e.position
            FROM tbl_cash_advances a
            LEFT JOIN tbl_employees e ON a.employee_id = e.employee_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
             echo json_encode(['status' => 'success', 'details' => $data]);
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Cash Advance record not found.']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error fetching details: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 3: PROCESS (Approve/Reject)
// =================================================================================
if ($action === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $type   = trim($_POST['type'] ?? ''); 
    $amount = (float)($_POST['amount'] ?? 0);

    // Validation
    if ($id <= 0 || !in_array($type, ['approve', 'reject'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID or processing type.']);
        exit;
    }
    if ($type === 'approve' && $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Approved amount must be greater than zero.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Fetch Request Info (Needed for Notification)
        $stmt_info = $pdo->prepare("SELECT employee_id, date_requested FROM tbl_cash_advances WHERE id = ? FOR UPDATE");
        $stmt_info->execute([$id]);
        $ca_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ca_info) {
             $pdo->rollBack();
             echo json_encode(['status' => 'error', 'message' => 'Cash Advance record not found.']);
             exit;
        }

        // 2. Perform Update and Notification Setup
        if ($type === 'approve') {
            $sql = "UPDATE tbl_cash_advances SET status = 'Deducted', amount = ?, date_updated = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$amount, $id]);
            $msg = "Cash Advance approved (₱" . number_format($amount, 2) . ").";
            $notif_msg = "Your Cash Advance request has been APPROVED.";
        } else {
            $sql = "UPDATE tbl_cash_advances SET status = 'Cancelled', date_updated = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $msg = "Cash Advance rejected.";
            $notif_msg = "Your Cash Advance request has been REJECTED.";
        }
        
        $pdo->commit();

        // 3. Send Notification to Employee
        send_notification($pdo, $ca_info['employee_id'], 'Employee', 'cash_advance', $notif_msg, 'my_cash_advance.php', $id);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => 'Error processing request: ' . $e->getMessage()]);
    }
    exit;
}