<?php
/**
 * api/employee/attendance_action.php
 * Provides personal attendance history with validated Overtime status for employees.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php';

$employee_id = $_SESSION['employee_id'];
$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION: FETCH DATA (For DataTables SSP)
// =================================================================================
if ($action === 'fetch') {
    
    // Column mapping for sorting
    $columns = [
        0 => 'a.date',
        1 => 'a.time_in',
        2 => 'a.attendance_status',
        3 => 'a.time_out',
        4 => 'a.num_hr',
        5 => 'a.overtime_hr'
    ];

    $draw   = (int)($_GET['draw'] ?? 1);
    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    // --- 2. QUERY CONSTRUCTION ---
    // We use a LEFT JOIN to verify if the Overtime rendered in attendance was actually approved
    $sql_base = " FROM tbl_attendance a 
                  LEFT JOIN tbl_overtime o ON a.employee_id = o.employee_id AND a.date = o.ot_date
                  WHERE a.employee_id = :emp_id";
                  
    $params = [':emp_id' => $employee_id];

    if ($start_date && $end_date) {
        $sql_base .= " AND a.date BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    }

    // --- 3. EXECUTE COUNTS ---
    $stmt_count = $pdo->prepare("SELECT COUNT(a.id) " . $sql_base);
    $stmt_count->execute($params);
    $recordsTotal = (int)$stmt_count->fetchColumn();

    // --- 4. ORDERING & PAGINATION ---
    $order_sql = " ORDER BY a.date DESC"; 
    if (isset($_GET['order'])) {
        $col_idx = (int)$_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
        if (isset($columns[$col_idx])) {
            $order_sql = " ORDER BY " . $columns[$col_idx] . " " . $dir;
        }
    }

    $sql_data = "SELECT a.*, o.status AS ot_request_status " . $sql_base . $order_sql . " LIMIT :offset, :limit";
    $stmt = $pdo->prepare($sql_data);
    
    $stmt->bindValue(':emp_id', $employee_id, PDO::PARAM_INT);
    if ($start_date) {
        $stmt->bindValue(':start_date', $start_date);
        $stmt->bindValue(':end_date', $end_date);
    }
    $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. DATA FORMATTING ---
    $formatted_data = [];
    foreach ($data as $row) {
        
        // --- Badge Rendering Logic ---
        $status_badges = '';
        $raw_status = $row['attendance_status'];
        $ot_approved = ($row['ot_request_status'] === 'Approved');

        if (!empty($raw_status)) {
            $parts = array_map('trim', explode(',', $raw_status));
            foreach ($parts as $item) {
                $class = 'bg-soft-secondary text-secondary';
                $show = true;

                if (stripos($item, 'Ontime') !== false) $class = 'bg-soft-success text-success border-success';
                elseif (stripos($item, 'Late') !== false) $class = 'bg-soft-danger text-danger border-danger';
                elseif (stripos($item, 'Undertime') !== false) $class = 'bg-soft-warning text-warning border-warning';
                elseif (stripos($item, 'Overtime') !== false) {
                    $class = 'bg-soft-primary text-primary border-primary';
                    $show = $ot_approved; // Hide OT badge if no matching approved request
                }

                if ($show) {
                    $status_badges .= "<span class='badge {$class} border me-1 px-2 rounded-pill'>{$item}</span>";
                }
            }
        }

        // --- Night Shift / Cross-Day Display ---
        $time_out = '--:--';
        if ($row['time_out']) {
            $time_out = date('h:i A', strtotime($row['time_out']));
            if ($row['time_out_date'] && $row['time_out_date'] !== $row['date']) {
                $out_date = date('M d', strtotime($row['time_out_date']));
                $time_out .= " <br><small class='text-muted'>({$out_date})</small>";
            }
        }

        $formatted_data[] = [
            'date'     => "<strong>" . date('M d, Y', strtotime($row['date'])) . "</strong>",
            'time_in'  => date('h:i A', strtotime($row['time_in'])),
            'time_out' => $time_out,
            'status'   => $status_badges ?: '<span class="text-muted">--</span>',
            'num_hr'   => number_format($row['num_hr'], 2),
            'ot_hr'    => $ot_approved ? '<span class="text-primary fw-bold">' . number_format($row['overtime_hr'], 2) . '</span>' : '0.00'
        ];
    }

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsTotal,
        "data"            => $formatted_data
    ]);
    exit;
}