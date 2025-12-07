<?php
// user/api/overtime_action.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db_connection.php';

// 1. INCLUDE GLOBAL MODEL (Adjusted path: sibling folder 'models')
require_once __DIR__ . '/../models/global_model.php'; 

// Security Check
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$employee_id = $_SESSION['employee_id'];

// =================================================================================
// ACTION: SUBMIT OT REQUEST
// =================================================================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $ot_date = $_POST['ot_date'] ?? null;
    $ot_hours = $_POST['ot_hours'] ?? null;
    $reason = trim($_POST['reason'] ?? '');

    // Validation
    if (empty($ot_date) || empty($ot_hours) || empty($reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill out all required fields.']);
        exit;
    }
    if (!is_numeric($ot_hours) || $ot_hours <= 0 || $ot_hours > 8) {
        echo json_encode(['status' => 'error', 'message' => 'Hours must be between 0.5 and 8.']);
        exit;
    }

    try {
        // Check Attendance (Raw OT must exist)
        $stmt = $pdo->prepare("SELECT overtime_hr FROM tbl_attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee_id, $ot_date]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance || $attendance['overtime_hr'] <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'No raw overtime recorded for this date.']);
            exit;
        }

        // Check for Duplicates
        $stmt = $pdo->prepare("SELECT id FROM tbl_overtime WHERE employee_id = ? AND ot_date = ? AND status = 'Pending'");
        $stmt->execute([$employee_id, $ot_date]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'warning', 'message' => 'A pending request already exists for this date.']);
            exit;
        }

        // Insert Request
        $stmt = $pdo->prepare("INSERT INTO tbl_overtime (employee_id, ot_date, hours_requested, reason, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
        
        if ($stmt->execute([$employee_id, $ot_date, $ot_hours, $reason])) {
            
            // ---------------------------------------------------------
            // 2. TRIGGER NOTIFICATION
            // ---------------------------------------------------------
            if (function_exists('send_notification')) {
                // Fetch sender name for clearer message (optional, as model does it too, but good for custom msg)
                $sender_name = $employee_id;
                $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
                $stmt_name->execute([$employee_id]);
                $user = $stmt_name->fetch();
                if($user) $sender_name = $user['firstname'] . ' ' . $user['lastname'];

                $notif_msg = "$sender_name requested $ot_hours hours of Overtime for $ot_date.";
                
                // Send to Admin
                send_notification($pdo, null, 'Admin', 'overtime', $notif_msg, 'overtime_management.php');
            }
            // ---------------------------------------------------------

            echo json_encode(['status' => 'success', 'message' => 'Overtime request submitted successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database insert failed.']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: FETCH OT HISTORY
// =================================================================================
if ($action === 'fetch') {
    // Simplified Columns for Ordering (Matches frontend index)
    $columns = [
        0 => 'ot_date',
        1 => 'status',
        2 => 'id' // Action column
    ];

    $draw = $_GET['draw'] ?? 1;
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    
    $sql_base = " FROM tbl_overtime o 
                  LEFT JOIN tbl_attendance a ON o.employee_id = a.employee_id AND o.ot_date = a.date
                  WHERE o.employee_id = :emp_id";
    $params = [':emp_id' => $employee_id];

    // Counting
    $stmt_total = $pdo->prepare("SELECT COUNT(o.id) " . $sql_base);
    $stmt_total->execute($params);
    $recordsTotal = $stmt_total->fetchColumn();
    $recordsFiltered = $recordsTotal; 

    // Ordering
    $order_sql = " ORDER BY o.ot_date DESC"; 
    if (isset($_GET['order'])) {
        $col_idx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        if ($col_idx == 0) $order_sql = " ORDER BY o.ot_date " . $dir;
        if ($col_idx == 1) $order_sql = " ORDER BY o.status " . $dir;
    }

    $limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

    // Fetch Details
    $sql_data = "SELECT o.id, o.ot_date, o.reason, o.hours_requested, o.hours_approved, o.status, a.overtime_hr as raw_ot " . $sql_base . $order_sql . $limit_sql;
    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format
    $formatted_data = [];
    foreach ($data as $row) {
        // Status Badge
        $status_badge = '<span class="badge bg-secondary">Unknown</span>';
        if ($row['status'] == 'Approved') $status_badge = '<span class="badge bg-success">Approved</span>';
        elseif ($row['status'] == 'Pending') $status_badge = '<span class="badge bg-warning text-dark">Pending</span>';
        elseif ($row['status'] == 'Rejected') $status_badge = '<span class="badge bg-danger">Rejected</span>';

        $formatted_data[] = [
            'ot_date'         => date('M d, Y', strtotime($row['ot_date'])),
            'status'          => $status_badge,
            'id'              => $row['id'], 
            
            // Hidden Data for Modal
            'raw_ot_hr'       => number_format((float)$row['raw_ot'], 2),
            'hours_requested' => number_format((float)$row['hours_requested'], 2),
            'hours_approved'  => ($row['hours_approved'] > 0) ? number_format((float)$row['hours_approved'], 2) : '—',
            'reason'          => htmlspecialchars($row['reason'])
        ];
    }

    echo json_encode([
        "draw" => (int)$draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $formatted_data
    ]);
    exit;
}
?>