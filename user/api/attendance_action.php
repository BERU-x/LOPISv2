<?php
// api/attendance_action.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db_connection.php';

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$employee_id = $_SESSION['employee_id'];

if ($action === 'fetch') {
    // 1. Columns for Ordering
    // Note: We use aliases in the query now (a.date, etc)
    $columns = [
        0 => 'a.date',
        1 => 'a.time_in',
        2 => 'a.attendance_status',
        3 => 'a.time_out',
        4 => 'a.num_hr',
        5 => 'a.overtime_hr'
    ];

    $draw = $_GET['draw'] ?? 1;
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;

    // 2. Base Query with JOIN
    // We join tbl_overtime (o) to check if an approved request exists for this specific date
    $sql_base = " FROM tbl_attendance a 
                  LEFT JOIN tbl_overtime o ON a.employee_id = o.employee_id AND a.date = o.ot_date
                  WHERE a.employee_id = :emp_id";
                  
    $params = [':emp_id' => $employee_id];

    // 3. Apply Filters
    if (!empty($start_date) && !empty($end_date)) {
        $sql_base .= " AND a.date BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    }

    // 4. Counting
    $stmt_total = $pdo->prepare("SELECT COUNT(a.id) " . $sql_base);
    $stmt_total->execute($params);
    $recordsTotal = $stmt_total->fetchColumn();
    $recordsFiltered = $recordsTotal; 

    // 5. Ordering
    $order_sql = " ORDER BY a.date DESC"; 
    if (isset($_GET['order'])) {
        $col_idx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        if (isset($columns[$col_idx])) {
            $order_sql = " ORDER BY " . $columns[$col_idx] . " " . $dir;
        }
    }

    // 6. Pagination
    $limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

    // 7. Fetch Data
    // We select 'o.status AS ot_request_status' to check if it is approved
    $sql_data = "SELECT 
                    a.date, a.time_in, a.time_out, a.time_out_date, a.num_hr, a.overtime_hr, a.attendance_status,
                    o.status AS ot_request_status 
                 " . $sql_base . $order_sql . $limit_sql;
    
    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Format Data
    $formatted_data = [];
    foreach ($data as $row) {
        
        // --- MULTI-STATUS LOGIC ---
        $raw_status = $row['attendance_status']; 
        $ot_request_status = $row['ot_request_status'] ?? ''; // Approved, Pending, Rejected, or null
        $status_badges_html = '';

        if (!empty($raw_status)) {
            $status_array = explode(',', $raw_status);
            
            foreach ($status_array as $status_item) {
                $status_item = trim($status_item);
                $badge_class = 'bg-secondary';
                $show_badge = true; // Default to showing the badge

                // Assign colors & Validation
                if (stripos($status_item, 'Ontime') !== false) {
                    $badge_class = 'bg-success text-dark';
                } elseif (stripos($status_item, 'Late') !== false) {
                    $badge_class = 'bg-warning text-dark';
                } elseif (stripos($status_item, 'Undertime') !== false) {
                    $badge_class = 'bg-info text-dark';
                } elseif (stripos($status_item, 'Overtime') !== false) {
                    // *** CRITICAL CHECK ***
                    // Only show "Overtime" badge if the request status is 'Approved'
                    if ($ot_request_status === 'Approved') {
                        $badge_class = 'bg-primary text-dark';
                    } else {
                        $show_badge = false; // Hide badge if Pending/Rejected/None
                    }
                } elseif (stripos($status_item, 'Absent') !== false) {
                    $badge_class = 'bg-danger';
                }

                if ($show_badge) {
                    $status_badges_html .= "<span class='badge {$badge_class} me-1'>{$status_item}</span>";
                }
            }
        } 
        
        // Fallback if badges are empty (e.g., OT was hidden and it was the only status)
        if (empty($status_badges_html)) {
             $status_badges_html = '<span class="badge bg-secondary">--</span>';
        }
        
        // --- TIME FORMATTING ---
        $time_in = ($row['time_in']) ? date('h:i A', strtotime($row['time_in'])) : '--:--';
        
        $time_out_display = '--:--';
        if ($row['time_out']) {
            $time_out_display = date('h:i A', strtotime($row['time_out']));
            
            if (!empty($row['time_out_date']) && $row['time_out_date'] !== '0000-00-00' && $row['time_out_date'] !== $row['date']) {
                if (strtotime($row['time_out_date']) != strtotime($row['date'])) {
                    $diff_date_str = date('M d', strtotime($row['time_out_date']));
                    $time_out_display .= '<br><span class="small text-muted" style="font-size: 0.75rem;">(' . $diff_date_str . ')</span>';
                }
            }
        }

        $formatted_data[] = [
            'date'        => date('M d, Y', strtotime($row['date'])),
            'time_in'     => $time_in,
            'time_out'    => $time_out_display,
            'status'      => $status_badges_html,
            'num_hr'      => $row['num_hr'],
            'overtime_hr' => $row['overtime_hr']
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