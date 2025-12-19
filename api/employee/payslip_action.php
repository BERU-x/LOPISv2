<?php
/**
 * api/employee/payslip_action.php
 * Provides personal payroll history and stats for the logged-in employee.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// 1. SECURITY & AUTHENTICATION
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php';

$employee_id = $_SESSION['employee_id'];
$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION 1: FETCH STATS (Quick view cards)
// =================================================================================
if ($action === 'stats') {
    try {
        // Latest Net Pay
        $stmt_last = $pdo->prepare("
            SELECT net_pay, cut_off_end 
            FROM tbl_payroll 
            WHERE employee_id = ? AND status = 1 
            ORDER BY cut_off_end DESC LIMIT 1
        ");
        $stmt_last->execute([$employee_id]);
        $last_pay = $stmt_last->fetch(PDO::FETCH_ASSOC);

        // Total Paid Year-to-Date
        $stmt_ytd = $pdo->prepare("
            SELECT SUM(net_pay) 
            FROM tbl_payroll 
            WHERE employee_id = ? AND status = 1 
            AND YEAR(cut_off_end) = YEAR(CURRENT_DATE())
        ");
        $stmt_ytd->execute([$employee_id]);
        $ytd_pay = $stmt_ytd->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'last_net_pay' => $last_pay ? number_format($last_pay['net_pay'], 2) : '0.00',
            'last_pay_date' => $last_pay ? date('M d, Y', strtotime($last_pay['cut_off_end'])) : 'N/A',
            'ytd_total' => number_format((float)$ytd_pay, 2)
        ]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 2: FETCH TABLE DATA (DataTables SSP)
// =================================================================================
if ($action === 'fetch') {
    $draw   = (int)($_GET['draw'] ?? 1);
    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    
    // Column mapping for ordering
    $columns = [
        0 => 'cut_off_end',
        1 => 'net_pay',
        2 => 'ref_no'
    ];

    try {
        // 1. Total Records Count
        $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM tbl_payroll WHERE employee_id = ? AND status = 1");
        $stmt_count->execute([$employee_id]);
        $recordsTotal = (int)$stmt_count->fetchColumn();

        // 2. Ordering Logic
        $order_sql = " ORDER BY cut_off_end DESC"; 
        if (isset($_GET['order'])) {
            $col_idx = (int)$_GET['order'][0]['column'];
            $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            if (isset($columns[$col_idx])) {
                $order_sql = " ORDER BY " . $columns[$col_idx] . " " . $dir;
            }
        }

        // 3. Data Fetching
        $sql = "SELECT id, ref_no, cut_off_start, cut_off_end, net_pay, status 
                FROM tbl_payroll 
                WHERE employee_id = :eid AND status = 1 
                $order_sql LIMIT :offset, :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':eid', $employee_id, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Formatting for the UI
        $formatted_data = [];
        foreach ($data as $row) {
            $formatted_data[] = [
                'id'            => $row['id'],
                'ref_no'        => $row['ref_no'],
                'period'        => date('M d', strtotime($row['cut_off_start'])) . ' - ' . date('M d, Y', strtotime($row['cut_off_end'])),
                'net_pay'       => number_format($row['net_pay'], 2),
                'status_label'  => '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Paid</span>'
            ];
        }

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsTotal, // No filter used in personal history
            "data" => $formatted_data
        ]);

    } catch (Exception $e) {
        echo json_encode(["draw" => $draw, "error" => $e->getMessage()]);
    }
    exit;
}