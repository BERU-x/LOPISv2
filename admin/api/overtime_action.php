<?php
// api/overtime_action.php

// --- 1. CONFIGURATION & INCLUDES ---
header('Content-Type: application/json');

// Adjust paths as necessary based on your folder structure
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../models/global_model.php'; // Required for notifications

// Check DB Connection and provide initial error response for DataTables if required
if (!isset($pdo)) {
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
        1 => 'ot.ot_date', 
        2 => 'ot.hours_requested',
        3 => 'ot.status'
    ];

    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    
    // Custom Filters
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    // --- B. BUILD QUERY BASE ---
    $sql_base = " FROM tbl_overtime ot 
                  LEFT JOIN tbl_employees e ON ot.employee_id = e.employee_id ";
    
    $where_params = [];
    $where_bindings = [];

    // Global Search
    if (!empty($search)) {
        $term = '%' . $search . '%';
        $where_params[] = "(
            ot.employee_id LIKE :search OR 
            e.firstname LIKE :search OR 
            e.lastname LIKE :search OR 
            ot.status LIKE :search
        )";
        $where_bindings[':search'] = $term;
    }

    // Date Range Filter
    if (!empty($start_date) && !empty($end_date)) {
        $where_params[] = "ot.ot_date BETWEEN :start_date AND :end_date";
        $where_bindings[':start_date'] = $start_date;
        $where_bindings[':end_date']  = $end_date;
    }

    $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";

    // Ordering
    $order_sql = " ORDER BY ot.ot_date DESC, ot.created_at DESC"; // Default
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
        $stmt = $pdo->query("SELECT COUNT(ot.id) $sql_base");
        $recordsTotal = (int)$stmt->fetchColumn();

        // Filtered Records
        $stmt = $pdo->prepare("SELECT COUNT(ot.id) $sql_base $where_sql");
        $stmt->execute($where_bindings);
        $recordsFiltered = (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        // Standardized SSP Error Response for count queries
        echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Counting query failed."]);
        exit;
    }

    // --- D. FETCH DATA ---
    $sql_data = "SELECT 
                    ot.id, 
                    ot.employee_id, 
                    ot.ot_date, 
                    ot.hours_requested, 
                    ot.status, 
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
        // Fetch detailed info including raw biometric data
        $stmt = $pdo->prepare("
            SELECT ot.*, 
                   e.firstname, e.lastname, e.photo, e.department, e.position,
                   ta.overtime_hr as raw_biometric_ot
            FROM tbl_overtime ot
            LEFT JOIN tbl_employees e ON ot.employee_id = e.employee_id
            LEFT JOIN tbl_attendance ta ON (ot.employee_id = ta.employee_id AND ot.ot_date = ta.date)
            WHERE ot.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            echo json_encode(['status' => 'success', 'details' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Overtime record not found.']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error fetching details: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 3: UPDATE STATUS (Approve/Reject)
// =================================================================================
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status_action = trim($_POST['status_action'] ?? ''); // 'approve' or 'reject'
    $approved_hrs = (float)($_POST['approved_hours'] ?? 0);

    if ($id <= 0 || !in_array($status_action, ['approve', 'reject'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID or status action.']);
        exit;
    }
    
    // Validation for approval: hours must be positive
    if ($status_action === 'approve' && $approved_hrs <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Approved hours must be greater than zero.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Fetch Request Info (Needed for Notification)
        $stmt_info = $pdo->prepare("SELECT employee_id, ot_date FROM tbl_overtime WHERE id = ? FOR UPDATE");
        $stmt_info->execute([$id]);
        $ot_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ot_info) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
            exit;
        }

        // 2. Perform Update
        if ($status_action === 'approve') {
            $sql = "UPDATE tbl_overtime SET status = 'Approved', hours_approved = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$approved_hrs, $id]);
            
            $msg = "Overtime approved successfully ({$approved_hrs} hrs).";
            $notif_type = 'APPROVED';
        } else {
            $sql = "UPDATE tbl_overtime SET status = 'Rejected', hours_approved = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $msg = "Overtime rejected.";
            $notif_type = 'REJECTED';
        }

        $pdo->commit();

        // 3. Send Notification to Employee
        $date_formatted = date('M d', strtotime($ot_info['ot_date']));
        $notif_msg = "Your Overtime request for {$date_formatted} has been {$notif_type}.";
        
        send_notification($pdo, $ot_info['employee_id'], 'Employee', 'overtime', $notif_msg, 'my_overtime.php', $id);

        // Final Success Response
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => 'Error updating status: ' . $e->getMessage()]);
    }
    exit;
}