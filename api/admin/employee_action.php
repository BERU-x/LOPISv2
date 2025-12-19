<?php
// api/admin/employee_action.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION & DEPENDENCIES ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php'; // For tracking changes

// --- 2. CONFIGURATION ---
$UPLOAD_DIR = __DIR__ . '/../../assets/images/users/'; // Standardized user photo path

if (!isset($pdo)) {
    echo json_encode(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'DB Connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION: FETCH EMPLOYEES (DataTables SSP)
// =================================================================================
if ($action === 'fetch') {
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search_value = $_GET['search']['value'] ?? '';
    
    $columns = [
        0 => 'e.employee_id', 1 => 'e.lastname', 2 => 'e.employment_status', 3 => 'c.daily_rate',
    ];

    $base_sql = " FROM tbl_employees e LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id";
    $status_filter = "e.employment_status < 7"; // Exclude deleted/archived
    
    $where_params = [$status_filter];
    $where_bindings = [];

    if (!empty($search_value)) {
        $term = '%' . $search_value . '%';
        $where_params[] = "(e.employee_id LIKE ? OR e.firstname LIKE ? OR e.lastname LIKE ? OR e.position LIKE ?)";
        array_push($where_bindings, $term, $term, $term, $term);
    }

    $where_sql = " WHERE " . implode(' AND ', $where_params);

    try {
        $recordsTotal = (int)$pdo->query("SELECT COUNT(e.employee_id) $base_sql WHERE $status_filter")->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(e.employee_id) $base_sql $where_sql");
        $stmt->execute($where_bindings);
        $recordsFiltered = (int)$stmt->fetchColumn();

        // Ordering Logic
        $order_sql = " ORDER BY e.employment_status ASC, e.lastname ASC";
        if (isset($_GET['order'])) {
            $col_idx = (int)$_GET['order'][0]['column'];
            $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            if (isset($columns[$col_idx])) {
                $order_sql = " ORDER BY " . $columns[$col_idx] . " $dir";
            }
        }

        $sql_data = "SELECT e.employee_id, e.firstname, e.lastname, e.photo, e.employment_status, e.position, c.daily_rate 
                     $base_sql $where_sql $order_sql LIMIT ?, ?";

        $stmt = $pdo->prepare($sql_data);
        $param_index = 1;
        foreach ($where_bindings as $binding) { $stmt->bindValue($param_index++, $binding); }
        $stmt->bindValue($param_index++, $start, PDO::PARAM_INT);
        $stmt->bindValue($param_index++, $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["draw" => $draw, "recordsTotal" => $recordsTotal, "recordsFiltered" => $recordsFiltered, "data" => $data]);
    } catch (PDOException $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: CREATE / UPDATE EMPLOYEE
// =================================================================================
if (($action === 'create' || $action === 'update') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = trim($_POST['employee_id']);
    $is_new = ($action === 'create');
    $photo_filename = null;

    try {
        $pdo->beginTransaction();

        // 1. Handle Photo Upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $photo_filename = "profile_" . $emp_id . "_" . time() . "." . $file_ext;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $UPLOAD_DIR . $photo_filename)) {
                // If updating, delete the old photo
                if (!$is_new) {
                    $old = $pdo->prepare("SELECT photo FROM tbl_employees WHERE employee_id = ?");
                    $old->execute([$emp_id]);
                    $old_file = $old->fetchColumn();
                    if ($old_file && file_exists($UPLOAD_DIR . $old_file)) { unlink($UPLOAD_DIR . $old_file); }
                }
            }
        }

        // 2. Process Employee Table
        if ($is_new) {
            $sql = "INSERT INTO tbl_employees (employee_id, firstname, middlename, lastname, suffix, address, birthdate, contact_info, gender, position, department, employment_status, photo, bank_name, account_number, created_on) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        } else {
            $sql = "UPDATE tbl_employees SET firstname=?, middlename=?, lastname=?, suffix=?, address=?, birthdate=?, contact_info=?, gender=?, position=?, department=?, employment_status=?, bank_name=?, account_number=?, updated_on=NOW() " . ($photo_filename ? ", photo=?" : "") . " WHERE employee_id=?";
        }

        $stmt = $pdo->prepare($sql);
        $params = [
            $emp_id, trim($_POST['firstname']), trim($_POST['middlename']), trim($_POST['lastname']), trim($_POST['suffix']),
            trim($_POST['address']), $_POST['birthdate'], $_POST['contact_info'], (int)$_POST['gender'],
            trim($_POST['position']), trim($_POST['department']), (int)$_POST['employment_status'], 
            ($is_new ? $photo_filename : trim($_POST['bank_name'])), ($is_new ? trim($_POST['bank_name']) : trim($_POST['account_number']))
        ];
        
        // Re-aligning params for Update vs Create
        if (!$is_new) {
            $params = [
                trim($_POST['firstname']), trim($_POST['middlename']), trim($_POST['lastname']), trim($_POST['suffix']),
                trim($_POST['address']), $_POST['birthdate'], $_POST['contact_info'], (int)$_POST['gender'],
                trim($_POST['position']), trim($_POST['department']), (int)$_POST['employment_status'],
                trim($_POST['bank_name']), trim($_POST['account_number'])
            ];
            if ($photo_filename) { $params[] = $photo_filename; }
            $params[] = $emp_id;
        } else {
            // Fix param list for Create specifically to match the INSERT statement
            $params = [
                $emp_id, trim($_POST['firstname']), trim($_POST['middlename']), trim($_POST['lastname']), trim($_POST['suffix']),
                trim($_POST['address']), $_POST['birthdate'], $_POST['contact_info'], (int)$_POST['gender'],
                trim($_POST['position']), trim($_POST['department']), (int)$_POST['employment_status'], $photo_filename,
                trim($_POST['bank_name']), trim($_POST['account_number'])
            ];
        }

        $stmt->execute($params);

        // 3. Compensation Table (Robust Update)
        $sql_comp = "INSERT INTO tbl_compensation (employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance) 
                     VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE daily_rate=VALUES(daily_rate), monthly_rate=VALUES(monthly_rate), food_allowance=VALUES(food_allowance), transpo_allowance=VALUES(transpo_allowance)";
        $pdo->prepare($sql_comp)->execute([$emp_id, (float)$_POST['daily_rate'], (float)$_POST['monthly_rate'], (float)$_POST['food_allowance'], (float)$_POST['transpo_allowance']]);

        // 4. Audit Log
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['usertype'], ($is_new ? 'CREATE_EMPLOYEE' : 'UPDATE_EMPLOYEE'), "Employee ID: $emp_id");

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Employee " . ($is_new ? 'added' : 'updated') . " successfully!"]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}