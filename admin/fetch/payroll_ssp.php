<?php
// admin/fetch/payroll_ssp.php
require_once '../../db_connection.php';

// --- CONFIGURATION ---
$table = 'tbl_payroll';
$primaryKey = 'id';

// SQL Join to get Employee Names, Department, and Photo
$sql_details = "
    FROM tbl_payroll p
    LEFT JOIN tbl_employees e ON p.employee_id = e.employee_id
";

// Columns configuration (Used for sorting logic)
$columns = array(
    array( 'db' => 'p.ref_no',          'dt' => 'ref_no' ),
    array( 'db' => 'p.employee_id',     'dt' => 'employee_id' ),
    array( 
        // [UPDATED] Use CONCAT_WS for Middle Name support
        'db' => "CONCAT_WS(' ', e.firstname, e.middlename, e.lastname)", 
        'dt' => 'employee_name',
        'alias' => 'employee_name'
    ),
    array( 'db' => 'e.department',      'dt' => 'department' ),
    array( 'db' => 'p.cut_off_start',   'dt' => 'cut_off_start' ),
    array( 'db' => 'p.cut_off_end',     'dt' => 'cut_off_end' ),
    array( 'db' => 'p.gross_pay',       'dt' => 'gross_pay' ),
    array( 'db' => 'p.total_deductions','dt' => 'total_deductions' ),
    array( 'db' => 'p.net_pay',         'dt' => 'net_pay' ),
    array( 'db' => 'p.status',          'dt' => 'status' )
);

// --- DATATABLES PARAMETERS ---
$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$search = $_GET['search']['value'] ?? '';

// --- CUSTOM FILTERS ---
$filter_start = $_GET['filter_start_date'] ?? null;
$filter_end   = $_GET['filter_end_date'] ?? null;

// --- ORDERING ---
$order_sql = " ORDER BY p.id DESC"; // Default
if (isset($_GET['order'])) {
    $colIdx = $_GET['order'][0]['column'];
    $colDir = $_GET['order'][0]['dir'];
    
    if(isset($columns[$colIdx])) {
        $colName = $columns[$colIdx]['db'];
        $order_sql = " ORDER BY $colName $colDir";
    }
}

// --- FILTERING (WHERE CLAUSE) ---
$where_conditions = [];
$params = [];

// 1. Global Search
if (!empty($search)) {
    $search_term = "%$search%";
    // [UPDATED] Added middlename to search logic
    $where_conditions[] = "(p.ref_no LIKE ? OR CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
}

// 2. Date Range Filter (Based on Cut-Off End Date)
if (!empty($filter_start) && !empty($filter_end)) {
    $where_conditions[] = "p.cut_off_end BETWEEN ? AND ?";
    $params[] = $filter_start;
    $params[] = $filter_end;
} elseif (!empty($filter_start)) {
    $where_conditions[] = "p.cut_off_end >= ?";
    $params[] = $filter_start;
} elseif (!empty($filter_end)) {
    $where_conditions[] = "p.cut_off_end <= ?";
    $params[] = $filter_end;
}

// Combine WHERE conditions
$where_sql = "";
if (count($where_conditions) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_conditions);
}

// --- EXECUTE QUERY ---
try {
    // 1. Total Records (No filtering)
    $stmt_total = $pdo->query("SELECT COUNT(*) $sql_details");
    $recordsTotal = $stmt_total->fetchColumn();

    // 2. Filtered Records
    $stmt_filtered = $pdo->prepare("SELECT COUNT(*) $sql_details $where_sql");
    $stmt_filtered->execute($params);
    $recordsFiltered = $stmt_filtered->fetchColumn();

    // 3. Data Fetch
    // [UPDATED] 
    // - Uses CONCAT_WS for full name
    // - Maps 'e.photo' to 'picture' so your JavaScript (row.picture) works correctly
    $col_names = "p.id, p.employee_id, p.ref_no, 
                  CONCAT_WS(' ', e.firstname, e.middlename, e.lastname) as employee_name, 
                  e.department, p.cut_off_start, p.cut_off_end, 
                  p.gross_pay, p.total_deductions, p.net_pay, p.status, 
                  e.photo as picture"; 

    $sql = "SELECT $col_names $sql_details $where_sql $order_sql LIMIT $start, $length";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// --- OUTPUT ---
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data" => $data
]);
?>