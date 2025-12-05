<?php
// api/overtime_action.php

// --- 1. CONFIGURATION & INCLUDES ---
header('Content-Type: application/json');

// Adjust paths as necessary based on your folder structure
require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../models/global_model.php'; // Required for notifications

// Check DB Connection
if (!isset($pdo)) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? 'fetch'; // Default to fetch if not set

// =================================================================================
// ACTION: FETCH DATA (For DataTables)
// =================================================================================
if ($action === 'fetch') {

    // --- A. COLUMNS & SORTING ---
    // Mapping DataTables column index to Database columns
    // Frontend Columns: 0:Employee, 1:Date, 2:ReqHrs, 3:Status, 4:Actions
    $columns = [
        0 => 'employee_name', 
        1 => 'ot.ot_date', 
        2 => 'ot.hours_requested',
        3 => 'ot.status'
    ];

    $draw   = $_GET['draw'] ?? 1;
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = $_GET['search']['value'] ?? '';
    
    // Custom Filters
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    // --- B. BUILD QUERY ---
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

    // Date Range
    if (!empty($start_date) && !empty($end_date)) {
        $where_params[] = "ot.ot_date BETWEEN :start AND :end";
        $where_bindings[':start'] = $start_date;
        $where_bindings[':end']   = $end_date;
    }

    $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";

    // Ordering
    $order_sql = " ORDER BY ot.ot_date DESC, ot.created_at DESC"; // Default
    if (isset($_GET['order'])) {
        $col_idx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        
        if (isset($columns[$col_idx])) {
            $colName = $columns[$col_idx];
            // Handle custom sort for Name column
            if ($colName === 'employee_name') {
                $order_sql = " ORDER BY e.lastname $dir, e.firstname $dir";
            } else {
                $order_sql = " ORDER BY $colName $dir";
            }
        }
    }

    $limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

    // --- C. EXECUTE COUNT QUERIES ---
    // Total Records
    $stmt = $pdo->query("SELECT COUNT(ot.id) $sql_base");
    $recordsTotal = $stmt->fetchColumn();

    // Filtered Records
    $stmt = $pdo->prepare("SELECT COUNT(ot.id) $sql_base $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = $stmt->fetchColumn();

    // --- D. FETCH DATA ---
    $sql_data = "SELECT 
                    ot.id, 
                    ot.employee_id, 
                    ot.ot_date, 
                    ot.hours_requested, 
                    ot.status, 
                    e.firstname, e.lastname, e.photo, e.department 
                 $sql_base $where_sql $order_sql $limit_sql";

    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($where_bindings);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "draw" => (int)$draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $data
    ]);
    exit;
}

// =================================================================================
// ACTION: GET DETAILS (For View Modal)
// =================================================================================
if ($action === 'get_details') {
    $id = $_POST['id'] ?? 0;

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
        
        echo json_encode($data ?: []);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: UPDATE STATUS (Approve/Reject)
// =================================================================================
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $status_action = $_POST['status_action'] ?? ''; // 'approve' or 'reject'
    $approved_hrs = $_POST['approved_hours'] ?? 0;

    try {
        // 1. Fetch Request Info (Needed for Notification)
        $stmt_info = $pdo->prepare("SELECT employee_id, ot_date FROM tbl_overtime WHERE id = ?");
        $stmt_info->execute([$id]);
        $ot_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$ot_info) {
            echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
            exit;
        }

        // 2. Perform Update
        if ($status_action === 'approve') {
            $sql = "UPDATE tbl_overtime SET status = 'Approved', hours_approved = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$approved_hrs, $id]);
            
            $msg = "Overtime approved successfully.";
            $notif_type = 'APPROVED';
        } else {
            $sql = "UPDATE tbl_overtime SET status = 'Rejected', updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $msg = "Overtime rejected.";
            $notif_type = 'REJECTED';
        }

        // 3. Send Notification to Employee
        $date_formatted = date('M d', strtotime($ot_info['ot_date']));
        $notif_msg = "Your Overtime request for {$date_formatted} has been {$notif_type}.";
        
        send_notification($pdo, $ot_info['employee_id'], 'Employee', 'overtime', $notif_msg, 'my_overtime.php', null);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>