<?php
// api/admin/employee_action.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] != 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../db_connection.php'; 
require_once __DIR__ . '/../../helpers/audit_helper.php'; 

// --- 2. CONFIGURATION ---
$UPLOAD_DIR = __DIR__ . '/../../assets/images/users/';

if (!isset($pdo)) {
    echo json_encode(['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'DB Connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION: FETCH EMPLOYEES (Compensation Removed)
// =================================================================================
if ($action === 'fetch') {
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search_value = $_GET['search']['value'] ?? '';
    
    // ⭐ Removed 'c.daily_rate'
    $columns = [
        0 => 'e.employee_id', 1 => 'e.lastname', 2 => 'e.employment_status', 3 => 'e.employee_id'
    ];

    // ⭐ Removed LEFT JOIN tbl_compensation
    $base_sql = " FROM tbl_employees e ";
    $status_filter = "e.employment_status < 7"; 
    
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

        $order_sql = " ORDER BY e.employment_status ASC, e.lastname ASC";
        if (isset($_GET['order'])) {
            $col_idx = (int)$_GET['order'][0]['column'];
            $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
            if (isset($columns[$col_idx])) {
                $order_sql = " ORDER BY " . $columns[$col_idx] . " $dir";
            }
        }

        // ⭐ Removed c.daily_rate from SELECT
        $sql_data = "SELECT e.employee_id, e.firstname, e.lastname, e.photo, e.employment_status, e.position 
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
// ACTION: CREATE / UPDATE EMPLOYEE (Compensation Removed)
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
                if (!$is_new) {
                    $old = $pdo->prepare("SELECT photo FROM tbl_employees WHERE employee_id = ?");
                    $old->execute([$emp_id]);
                    $old_file = $old->fetchColumn();
                    if ($old_file && file_exists($UPLOAD_DIR . $old_file)) { unlink($UPLOAD_DIR . $old_file); }
                }
            }
        }

        // 2. Process Employee Table (Removed Compensation variables)
        if ($is_new) {
            $sql = "INSERT INTO tbl_employees (employee_id, firstname, middlename, lastname, suffix, address, birthdate, contact_info, gender, position, department, employment_status, photo, bank_name, account_number, created_on) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        } else {
            $sql = "UPDATE tbl_employees SET firstname=?, middlename=?, lastname=?, suffix=?, address=?, birthdate=?, contact_info=?, gender=?, position=?, department=?, employment_status=?, bank_name=?, account_number=?, updated_on=NOW() " . ($photo_filename ? ", photo=?" : "") . " WHERE employee_id=?";
        }

        $stmt = $pdo->prepare($sql);
        
        // Prepare Params
        $p_fname = trim($_POST['firstname']);
        $p_mname = trim($_POST['middlename']);
        $p_lname = trim($_POST['lastname']);
        $p_suffix = trim($_POST['suffix']);
        $p_addr = trim($_POST['address']);
        $p_bdate = $_POST['birthdate'];
        $p_contact = $_POST['contact_info'];
        $p_gender = (int)$_POST['gender'];
        $p_pos = trim($_POST['position']);
        $p_dept = trim($_POST['department']);
        $p_stat = (int)$_POST['employment_status'];
        $p_bank = trim($_POST['bank_name']);
        $p_acc = trim($_POST['account_number']);

        if ($is_new) {
            $params = [$emp_id, $p_fname, $p_mname, $p_lname, $p_suffix, $p_addr, $p_bdate, $p_contact, $p_gender, $p_pos, $p_dept, $p_stat, $photo_filename, $p_bank, $p_acc];
        } else {
            $params = [$p_fname, $p_mname, $p_lname, $p_suffix, $p_addr, $p_bdate, $p_contact, $p_gender, $p_pos, $p_dept, $p_stat, $p_bank, $p_acc];
            if ($photo_filename) { $params[] = $photo_filename; }
            $params[] = $emp_id;
        }

        $stmt->execute($params);

        // ⭐ REMOVED: Step 3 (Compensation Table Insert/Update)

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

// =================================================================================
// ACTION: GET DETAILS (No Compensation)
// =================================================================================
if ($action === 'get_details' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = trim($_POST['employee_id'] ?? '');

    if (empty($emp_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID is required.']);
        exit;
    }

    try {
        $sql = "SELECT * FROM tbl_employees WHERE employee_id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $emp_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>