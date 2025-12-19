<?php
/**
 * api/employee/leave_action.php
 * Handles personal leave credits, history fetching, and request submission.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_start();

require_once __DIR__ . '/../../db_connection.php';
require_once __DIR__ . '/../../app/models/global_app_model.php'; // For notifications

// 1. GLOBAL AUTH CHECK
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];
$action = $_GET['action'] ?? '';

// 2. ROUTER
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

// =================================================================================
// LOGIC FUNCTIONS
// =================================================================================

/**
 * Fetches allocated credits vs approved usage for the current year.
 */
function handleGetCredits($pdo, $employee_id) {
    $current_year = date('Y');
    try {
        // Fetch Allocated Credits
        $stmt = $pdo->prepare("SELECT vacation_leave_total, sick_leave_total, emergency_leave_total 
                               FROM tbl_leave_credits WHERE employee_id = ? AND year = ?");
        $stmt->execute([$employee_id, $current_year]);
        $credits = $stmt->fetch(PDO::FETCH_ASSOC);

        $vl_total = (float)($credits['vacation_leave_total'] ?? 0);
        $sl_total = (float)($credits['sick_leave_total'] ?? 0);
        $el_total = (float)($credits['emergency_leave_total'] ?? 0);

        // Fetch Approved Usage (Status 1 = Approved)
        $stmtUsed = $pdo->prepare("SELECT leave_type, SUM(days_count) as used_days 
                                   FROM tbl_leave 
                                   WHERE employee_id = ? AND status = 1 
                                   AND YEAR(start_date) = ? GROUP BY leave_type");
        $stmtUsed->execute([$employee_id, $current_year]);
        $used_leaves = $stmtUsed->fetchAll(PDO::FETCH_KEY_PAIR); 

        $vl_used = (float)($used_leaves['Vacation Leave'] ?? 0);
        $sl_used = (float)($used_leaves['Sick Leave'] ?? 0);
        $el_used = (float)($used_leaves['Emergency Leave'] ?? 0);

        echo json_encode([
            'status' => 'success',
            'vl' => ['total' => $vl_total, 'used' => $vl_used, 'remaining' => max(0, $vl_total - $vl_used)],
            'sl' => ['total' => $sl_total, 'used' => $sl_used, 'remaining' => max(0, $sl_total - $sl_used)],
            'el' => ['total' => $el_total, 'used' => $el_used, 'remaining' => max(0, $el_total - $el_used)]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
    }
}

/**
 * Handles DataTables SSP for Leave History.
 */
function handleFetchHistory($pdo, $employee_id) {
    $draw    = (int)($_GET['draw'] ?? 1);
    $start   = (int)($_GET['start'] ?? 0);
    $length  = (int)($_GET['length'] ?? 10);
    $columns = [0 => 'leave_type', 1 => 'start_date', 2 => 'days_count', 3 => 'status'];
    
    $order_col = $columns[$_GET['order'][0]['column'] ?? 1] ?? 'start_date';
    $order_dir = ($_GET['order'][0]['dir'] ?? 'DESC') === 'asc' ? 'ASC' : 'DESC';

    try {
        $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM tbl_leave WHERE employee_id = ?");
        $stmt_total->execute([$employee_id]);
        $total_records = (int)$stmt_total->fetchColumn();

        $sql = "SELECT * FROM tbl_leave WHERE employee_id = :eid 
                ORDER BY $order_col $order_dir LIMIT :offset, :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':eid', $employee_id, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response_data = [];
        foreach ($data as $row) {
            $status_badge = match((int)$row['status']) {
                1 => '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Approved</span>',
                2 => '<span class="badge bg-soft-danger text-danger border border-danger px-3 rounded-pill">Rejected</span>',
                default => '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill">Pending</span>'
            };

            $response_data[] = [
                'leave_type' => "<strong>" . $row['leave_type'] . "</strong>",
                'dates'      => date("M d", strtotime($row['start_date'])) . ' - ' . date("M d, Y", strtotime($row['end_date'])),
                'days_count' => $row['days_count'] . " Day(s)",
                'status'     => $status_badge
            ];
        }

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $total_records,
            "recordsFiltered" => $total_records,
            "data" => $response_data
        ]);
    } catch (Exception $e) {
        echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    }
}

/**
 * Validates and submits a new leave request.
 */
function handleSubmitLeave($pdo, $employee_id) {
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $reason     = trim($_POST['reason'] ?? '');

    if (empty($leave_type) || empty($start_date) || empty($end_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        return;
    }

    $d_start = new DateTime($start_date);
    $d_end   = new DateTime($end_date);
    
    if ($d_end < $d_start) {
        echo json_encode(['status' => 'error', 'message' => 'End date cannot be earlier than start date.']);
        return;
    }

    $days_count = $d_start->diff($d_end)->days + 1;

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO tbl_leave (employee_id, leave_type, start_date, end_date, days_count, reason, status, created_on) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $days_count, $reason])) {
            $leave_id = $pdo->lastInsertId();

            // Trigger Admin Notification
            $emp_name = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];
            $msg = "$emp_name requested $days_count day(s) of $leave_type.";
            
            // Usertype 1 = Admin/HR
            send_notification($pdo, null, 1, 'Leave', $msg, 'leave_management.php', $leave_id);
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Leave request submitted successfully!']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}