<?php
/**
 * api/employee/cash_advance_action.php
 * Handles personal Cash Advance requests, status tracking, and stats.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../../db_connection.php';
require_once __DIR__ . '/../../app/models/global_app_model.php'; // For notifications
require_once __DIR__ . '/../../helpers/audit_helper.php';

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$employee_id = $_SESSION['employee_id'];

// =================================================================================
// ACTION: FETCH STATS (Total of currently pending requests)
// =================================================================================
if ($action === 'stats') {
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM tbl_cash_advances WHERE employee_id = ? AND status = 'Pending'");
        $stmt->execute([$employee_id]);
        $pending_total = (float)($stmt->fetchColumn() ?: 0);
        
        echo json_encode([
            'status' => 'success', 
            'pending_total' => number_format($pending_total, 2)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: CREATE REQUEST
// =================================================================================


if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $date_needed = $_POST['date_needed'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    // Validation
    if ($amount <= 0) { 
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid amount.']); 
        exit; 
    }
    if (empty($date_needed)) { 
        echo json_encode(['status' => 'error', 'message' => 'Date needed is required.']); 
        exit; 
    }

    try {
        // Prevent Duplicate Pending Requests (Internal Policy)
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tbl_cash_advances WHERE employee_id = ? AND status = 'Pending'");
        $stmtCheck->execute([$employee_id]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'You already have an active pending request.']);
            exit;
        }

        // Insert Record
        $sql = "INSERT INTO tbl_cash_advances (employee_id, amount, date_requested, remarks, status) VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $amount, $date_needed, $remarks])) {
            $request_id = $pdo->lastInsertId();
            
            // 2. TRIGGER NOTIFICATION TO ADMINS
            $emp_name = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];
            $formatted_amount = number_format($amount, 2);
            $notif_msg = "$emp_name filed a Cash Advance request of ₱$formatted_amount.";
            
            // Usertype 1 = Admin/HR
            send_notification($pdo, null, 1, 'Cash Advance', $notif_msg, 'cash_advance.php', $request_id);

            echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully!']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: FETCH HISTORY (SSP)
// =================================================================================
if ($action === 'fetch') {
    $draw   = (int)($_GET['draw'] ?? 1);
    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    
    // Column Mapping
    $columns = [0 => 'date_requested', 1 => 'amount', 2 => 'status'];

    try {
        // 1. Total Records Count (Corrected fetch logic)
        $stmt_total = $pdo->prepare("SELECT COUNT(id) FROM tbl_cash_advances WHERE employee_id = ?");
        $stmt_total->execute([$employee_id]);
        $recordsTotal = (int)$stmt_total->fetchColumn();

        // 2. Ordering
        $order_sql = " ORDER BY date_requested DESC"; 
        if (isset($_GET['order'])) {
            $col_idx = (int)$_GET['order'][0]['column'];
            $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            if (isset($columns[$col_idx])) {
                $order_sql = " ORDER BY " . $columns[$col_idx] . " " . $dir;
            }
        }

        // 3. Fetch Data
        $sql_data = "SELECT * FROM tbl_cash_advances WHERE employee_id = :eid $order_sql LIMIT :offset, :limit";
        $stmt = $pdo->prepare($sql_data);
        $stmt->bindValue(':eid', $employee_id, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($data as $row) {
            // Modern "Soft" Badges for ESS portal
            $status_badge = match($row['status']) {
                'Pending'   => '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill">Pending</span>',
                'Deducted'  => '<span class="badge bg-soft-primary text-primary border border-primary px-3 rounded-pill">Approved</span>',
                'Paid'      => '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Paid</span>',
                'Cancelled' => '<span class="badge bg-soft-danger text-danger border border-danger px-3 rounded-pill">Cancelled</span>',
                default     => '<span class="badge bg-soft-secondary text-secondary border px-3 rounded-pill">Unknown</span>'
            };

            $formatted[] = [
                'date_col'    => "<strong>" . date('M d, Y', strtotime($row['date_requested'])) . "</strong>",
                'amount'      => '<span class="fw-bold text-dark">₱' . number_format($row['amount'], 2) . '</span>',
                'status'      => $status_badge,
                'id'          => $row['id'],
                'date_needed' => date('M d, Y', strtotime($row['date_requested'])),
                'remarks'     => htmlspecialchars($row['remarks'])
            ];
        }

        echo json_encode([
            "draw"            => $draw,
            "recordsTotal"    => $recordsTotal,
            "recordsFiltered" => $recordsTotal,
            "data"            => $formatted
        ]);
    } catch (Exception $e) {
        echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    }
    exit;
}