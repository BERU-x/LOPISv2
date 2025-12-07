<?php
// user/api/leave_action.php
require_once __DIR__ . '/../../db_connection.php';
// Adjust path to models if necessary (e.g. user/models/global_model.php)
require_once __DIR__ . '/../models/global_model.php'; 

session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

// --- 1. GLOBAL AUTH CHECK ---
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];
$action = $_GET['action'] ?? '';

// --- 2. ROUTER ---
switch ($action) {
    case 'get_credits':
        handleGetCredits($pdo, $employee_id);
        break;

    case 'fetch_history':
        handleFetchHistory($pdo, $employee_id);
        break;

    case 'submit_leave':
        handleSubmitLeave($pdo, $employee_id);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        break;
}

// --- 3. LOGIC FUNCTIONS ---

function handleGetCredits($pdo, $employee_id) {
    $current_year = date('Y');
    try {
        // Fetch Total
        $stmt = $pdo->prepare("SELECT * FROM tbl_leave_credits WHERE employee_id = ? AND year = ?");
        $stmt->execute([$employee_id, $current_year]);
        $credits = $stmt->fetch(PDO::FETCH_ASSOC);

        $vl_total = $credits['vacation_leave_total'] ?? 0;
        $sl_total = $credits['sick_leave_total'] ?? 0;
        $el_total = $credits['emergency_leave_total'] ?? 0;

        // Calculate Used
        $stmtUsed = $pdo->prepare("SELECT leave_type, SUM(days_count) as used_days FROM tbl_leave WHERE employee_id = ? AND status = 1 AND YEAR(start_date) = ? GROUP BY leave_type");
        $stmtUsed->execute([$employee_id, $current_year]);
        $used_leaves = $stmtUsed->fetchAll(PDO::FETCH_KEY_PAIR); 

        // Calculate Remaining
        $vl_used = $used_leaves['Vacation Leave'] ?? 0;
        $sl_used = $used_leaves['Sick Leave'] ?? 0;
        $el_used = $used_leaves['Emergency Leave'] ?? 0;

        echo json_encode([
            'status' => 'success',
            'vl' => ['total' => $vl_total, 'used' => $vl_used, 'remaining' => max(0, $vl_total - $vl_used)],
            'sl' => ['total' => $sl_total, 'used' => $sl_used, 'remaining' => max(0, $sl_total - $sl_used)],
            'el' => ['total' => $el_total, 'used' => $el_used, 'remaining' => max(0, $el_total - $el_used)]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function handleFetchHistory($pdo, $employee_id) {
    $draw   = $_GET['draw'] ?? 1;
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $columns = [0 => 'leave_type', 1 => 'start_date', 2 => 'days_count', 3 => 'status'];
    $order_col_index = $_GET['order'][0]['column'] ?? 1;
    $order_dir       = $_GET['order'][0]['dir'] ?? 'DESC';
    $order_column    = $columns[$order_col_index] ?? 'start_date';

    $sql_base = "FROM tbl_leave WHERE employee_id = :my_id";
    
    $stmt_total = $pdo->prepare("SELECT COUNT(*) $sql_base");
    $stmt_total->execute([':my_id' => $employee_id]);
    $total_records = $stmt_total->fetchColumn();

    $sql_data = "SELECT * $sql_base ORDER BY $order_column $order_dir LIMIT :start, :length";
    $stmt = $pdo->prepare($sql_data);
    $stmt->bindValue(':my_id', $employee_id);
    $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
    $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response_data = [];
    foreach ($data as $row) {
        $status_badge = '<span class="badge bg-warning text-dark">Pending</span>';
        if($row['status'] == 1) $status_badge = '<span class="badge bg-success">Approved</span>';
        if($row['status'] == 2) $status_badge = '<span class="badge bg-danger">Rejected</span>';

        $response_data[] = [
            'leave_type' => $row['leave_type'],
            'dates'      => date("M d", strtotime($row['start_date'])) . ' - ' . date("M d, Y", strtotime($row['end_date'])),
            'days_count' => $row['days_count'],
            'status'     => $status_badge
        ];
    }

    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => $total_records,
        "recordsFiltered" => $total_records,
        "data" => $response_data
    ]);
}

function handleSubmitLeave($pdo, $employee_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
        return;
    }

    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $reason     = trim($_POST['reason'] ?? '');

    if (empty($leave_type) || empty($start_date) || empty($end_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        return;
    }

    if (strtotime($end_date) < strtotime($start_date)) {
        echo json_encode(['status' => 'error', 'message' => 'End date cannot be earlier than start date.']);
        return;
    }

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $diff = $start->diff($end);
    $days_count = $diff->days + 1;

    try {
        $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $days_count, $reason])) {
            
            // Notification Logic
            $sender_name = $employee_id;
            $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
            $stmt_name->execute([$employee_id]);
            $user_info = $stmt_name->fetch(PDO::FETCH_ASSOC);
            if ($user_info) $sender_name = $user_info['firstname'] . ' ' . $user_info['lastname'];

            $notif_msg = "$sender_name has requested $days_count day(s) of $leave_type.";
            if(function_exists('send_notification')) {
                send_notification($pdo, null, 'Admin', 'leave', $notif_msg, 'leave_management.php', $sender_name);
            }

            echo json_encode(['status' => 'success', 'message' => 'Leave request submitted successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database insert failed.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>