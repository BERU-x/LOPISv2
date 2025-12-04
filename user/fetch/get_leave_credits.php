<?php
// user/fetch/get_leave_credits.php
require_once '../../db_connection.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];
$current_year = date('Y');

try {
    // 1. Fetch Total Credits for the current year
    $stmt = $pdo->prepare("SELECT * FROM tbl_leave_credits WHERE employee_id = ? AND year = ?");
    $stmt->execute([$employee_id, $current_year]);
    $credits = $stmt->fetch(PDO::FETCH_ASSOC);

    // Default values if no record exists yet for this year
    $vl_total = $credits['vacation_leave_total'] ?? 0;
    $sl_total = $credits['sick_leave_total'] ?? 0;
    $el_total = $credits['emergency_leave_total'] ?? 0;

    // 2. Calculate USED credits (Approved leaves only)
    // We sum up the days_count for approved leaves (status = 1) in the current year
    $stmtUsed = $pdo->prepare("
        SELECT leave_type, SUM(days_count) as used_days 
        FROM tbl_leave 
        WHERE employee_id = ? 
        AND status = 1 
        AND YEAR(start_date) = ? 
        GROUP BY leave_type
    ");
    $stmtUsed->execute([$employee_id, $current_year]);
    $used_leaves = $stmtUsed->fetchAll(PDO::FETCH_KEY_PAIR); // returns ['Vacation Leave' => 5, 'Sick Leave' => 2]

    // 3. Calculate REMAINING
    $vl_used = $used_leaves['Vacation Leave'] ?? 0;
    $sl_used = $used_leaves['Sick Leave'] ?? 0;
    $el_used = $used_leaves['Emergency Leave'] ?? 0;

    $data = [
        'status' => 'success',
        'vl' => [
            'total'     => $vl_total,
            'used'      => $vl_used,
            'remaining' => max(0, $vl_total - $vl_used)
        ],
        'sl' => [
            'total'     => $sl_total,
            'used'      => $sl_used,
            'remaining' => max(0, $sl_total - $sl_used)
        ],
        'el' => [
            'total'     => $el_total,
            'used'      => $el_used,
            'remaining' => max(0, $el_total - $el_used)
        ]
    ];

    echo json_encode($data);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>