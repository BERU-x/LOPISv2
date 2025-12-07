<?php
// user/api/cash_advance_action.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db_connection.php';

// 1. INCLUDE GLOBAL MODEL
require_once __DIR__ . '/../models/global_model.php'; 

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$employee_id = $_SESSION['employee_id'];

// =================================================================================
// ACTION: FETCH STATS (Pending Total)
// =================================================================================
if ($action === 'stats') {
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM tbl_cash_advances WHERE employee_id = ? AND status = 'Pending'");
        $stmt->execute([$employee_id]);
        $pending_total = $stmt->fetchColumn() ?: 0;
        echo json_encode(['status' => 'success', 'pending_total' => number_format($pending_total, 2)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: CREATE REQUEST
// =================================================================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? 0;
    $date_needed = $_POST['date_needed'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    // Basic Validation
    if ($amount <= 0) { echo json_encode(['status' => 'error', 'message' => 'Amount must be greater than 0.']); exit; }
    if (empty($date_needed)) { echo json_encode(['status' => 'error', 'message' => 'Date needed is required.']); exit; }

    try {
        // Prevent Multiple Pending Requests
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tbl_cash_advances WHERE employee_id = ? AND status = 'Pending'");
        $stmtCheck->execute([$employee_id]);
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'You already have a Pending request.']);
            exit;
        }

        // Insert Record
        $sql = "INSERT INTO tbl_cash_advances (employee_id, amount, date_requested, remarks, status) VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $amount, $date_needed, $remarks])) {
            
            // ---------------------------------------------------------
            // 2. TRIGGER NOTIFICATION
            // ---------------------------------------------------------
            if (function_exists('send_notification')) {
                // Fetch sender name for clearer message
                $sender_name = $employee_id;
                $stmt_name = $pdo->prepare("SELECT firstname, lastname FROM tbl_employees WHERE employee_id = ?");
                $stmt_name->execute([$employee_id]);
                $user = $stmt_name->fetch();
                if($user) $sender_name = $user['firstname'] . ' ' . $user['lastname'];

                $formatted_amount = number_format($amount, 2);
                $notif_msg = "$sender_name requested a Cash Advance of ₱$formatted_amount needed by $date_needed.";
                
                // Send to Admin
                send_notification($pdo, null, 'Admin', 'cash_advance', $notif_msg, 'cash_advance.php');
            }
            // ---------------------------------------------------------

            echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database insert failed.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: FETCH HISTORY
// =================================================================================
if ($action === 'fetch') {
    // Columns for Ordering
    $columns = [
        0 => 'date_requested',
        1 => 'amount',
        2 => 'status',
        3 => 'id'
    ];

    $draw = $_GET['draw'] ?? 1;
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    
    $sql_base = " FROM tbl_cash_advances WHERE employee_id = :emp_id";
    $params = [':emp_id' => $employee_id];

    // Count
    $stmt_total = $pdo->prepare("SELECT COUNT(id) " . $sql_base);
    $stmt_total->execute($params);
    $recordsTotal = $stmt_total->fetchColumn();
    $recordsFiltered = $recordsTotal; 

    // Order
    $order_sql = " ORDER BY date_requested DESC"; 
    if (isset($_GET['order'])) {
        $col_idx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        if (isset($columns[$col_idx])) {
            $order_sql = " ORDER BY " . $columns[$col_idx] . " " . $dir;
        }
    }

    $limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

    // Fetch
    $sql_data = "SELECT * " . $sql_base . $order_sql . $limit_sql;
    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format
    $formatted_data = [];
    foreach ($data as $row) {
        
        // --- STATUS LOGIC ---
        $status_badge = '<span class="badge bg-secondary">Unknown</span>';
        
        if ($row['status'] == 'Pending') {
            $status_badge = '<span class="badge bg-warning text-dark">Pending</span>';
        
        } elseif ($row['status'] == 'Deducted') {
            // "Deducted" = Approved/Active
            $status_badge = '<span class="badge bg-primary">Deducted</span>';
        
        } elseif ($row['status'] == 'Paid') {
            // "Paid" = Payroll Approved/Completed
            $status_badge = '<span class="badge bg-success">Paid</span>';
        
        } elseif ($row['status'] == 'Cancelled') {
            // "Cancelled" = Disapproved
            $status_badge = '<span class="badge bg-danger">Cancelled</span>';
        }

        $formatted_data[] = [
            'date_col'    => date('M d, Y', strtotime($row['date_requested'])),
            'amount'      => '₱' . number_format($row['amount'], 2),
            'status'      => $status_badge,
            'id'          => $row['id'],
            
            // Hidden Data for View Details
            'date_needed' => date('M d, Y', strtotime($row['date_requested'])),
            'remarks'     => htmlspecialchars($row['remarks'])
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