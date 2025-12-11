<?php
// api/employee_action.php
header('Content-Type: application/json');
session_start();

// Adjust paths to your actual file structure
require_once __DIR__ . '/../../db_connection.php'; 

// --- Configuration ---
// Define the absolute path for image uploads relative to this script's location
$UPLOAD_DIR = __DIR__ . '/../../assets/images/'; 

if (!isset($pdo)) {
    // Standardized fatal error response for the SSP endpoint
    echo json_encode([
        'draw' => 1, 
        'recordsTotal' => 0, 
        'recordsFiltered' => 0, 
        'data' => [], 
        'error' => 'Database connection failed. Check db_connection.php.'
    ]);
    exit;
}

$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION: FETCH EMPLOYEES (DataTables Server-Side Processing)
// =================================================================================
if ($action === 'fetch') {
    $draw = $_GET['draw'] ?? 1;
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search_value = $_GET['search']['value'] ?? '';
    
    $columns = [
        0 => 'e.employee_id', 
        1 => 'e.lastname', 
        2 => 'e.employment_status', 
        3 => 'c.daily_rate',
    ];

    $base_sql = " FROM tbl_employees e LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id";
    $status_filter = "e.employment_status < 7"; 
    
    $where_params = [$status_filter];
    $where_bindings = [];

    // Global Search 
    if (!empty($search_value)) {
        $term = '%' . $search_value . '%';
        $where_params[] = "(e.employee_id LIKE ? OR e.firstname LIKE ? OR e.lastname LIKE ? OR e.position LIKE ?)";
        $where_bindings[] = $term; $where_bindings[] = $term; $where_bindings[] = $term; $where_bindings[] = $term;
    }

    $where_sql = " WHERE " . implode(' AND ', $where_params);

    // 2. Counting records
    try {
        $recordsTotal = (int)$pdo->query("SELECT COUNT(e.employee_id) $base_sql WHERE $status_filter")->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(e.employee_id) $base_sql $where_sql");
        $stmt->execute($where_bindings);
        $recordsFiltered = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Employee fetch counting error: " . $e->getMessage());
        echo json_encode(["draw" => (int)$draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [], "error" => "Counting query failed."]);
        exit;
    }

    // Ordering
    $order_sql = " ORDER BY e.employment_status ASC, e.lastname ASC";
    if (isset($_GET['order'])) {
        $col_idx = (int)$_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
        
        if (isset($columns[$col_idx])) {
            $colName = $columns[$col_idx];
            $order_col = ($colName === 'e.lastname') ? "e.lastname $dir, e.firstname $dir" : "$colName $dir";
            $order_sql = " ORDER BY $order_col";
        }
    }

    $limit_sql = " LIMIT ?, ?";

    // Fetch Data
    $sql_data = "SELECT e.employee_id, e.firstname, e.lastname, e.photo, e.employment_status, e.position, c.daily_rate 
                  $base_sql $where_sql $order_sql $limit_sql";

    $stmt = $pdo->prepare($sql_data);
    
    $param_index = 1;
    foreach ($where_bindings as $binding) {
        $stmt->bindValue($param_index++, $binding);
    }
    $stmt->bindValue($param_index++, $start, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $length, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Final Success Output for DataTables SSP
    echo json_encode([
        "draw" => (int)$draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $data
    ]);
    exit;
}

// =================================================================================
// ACTION: CREATE EMPLOYEE (Transaction)
// =================================================================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $data = [
        'employee_id'       => trim($_POST['employee_id']),
        'firstname'         => trim($_POST['firstname']),
        'middlename'        => trim($_POST['middlename'] ?? ''), 
        'lastname'          => trim($_POST['lastname']),
        'suffix'            => trim($_POST['suffix'] ?? ''),
        'address'           => trim($_POST['address'] ?? ''),
        'birthdate'         => $_POST['birthdate'] ?? null,
        'contact_info'      => trim($_POST['contact_info'] ?? null),
        'gender'            => (int)$_POST['gender'],
        'position'          => trim($_POST['position'] ?? ''),
        'department'        => trim($_POST['department'] ?? ''),
        'employment_status' => (int)$_POST['employment_status'],
        
        'daily_rate'        => (float)($_POST['daily_rate'] ?? 0),     
        'monthly_rate'      => (float)($_POST['monthly_rate'] ?? 0),     
        'food_allowance'    => (float)($_POST['food_allowance'] ?? 0), 
        'transpo_allowance' => (float)($_POST['transpo_allowance'] ?? 0), 
        
        'bank_name'         => trim($_POST['bank_name'] ?? null),
        'account_number'    => trim($_POST['account_number'] ?? null),
    ];

    $photo = null;
    $upload_path = null;
    $upload_dir = $UPLOAD_DIR; 

    try {
        $pdo->beginTransaction();

        // 1. Handle Photo Upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = $data['employee_id'] . '_' . time() . '.' . strtolower($file_ext);
            $upload_path = $upload_dir . $photo;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo = null; // Revert if upload fails
            }
        }

        // 2. Insert into tbl_employees
        $sql_emp = "INSERT INTO tbl_employees (
            employee_id, firstname, middlename, lastname, suffix, 
            address, birthdate, contact_info, gender, 
            position, department, employment_status, photo,
            bank_name, account_number, created_on
        ) VALUES (
            :employee_id, :firstname, :middlename, :lastname, :suffix, 
            :address, :birthdate, :contact_info, :gender, 
            :position, :department, :employment_status, :photo,
            :bank_name, :account_number, NOW()
        )";

        $stmt = $pdo->prepare($sql_emp);
        $stmt->execute([
            ':employee_id'       => $data['employee_id'],
            ':firstname'         => $data['firstname'],
            ':middlename'        => $data['middlename'],
            ':lastname'          => $data['lastname'],
            ':suffix'            => $data['suffix'],
            ':address'           => $data['address'],
            ':birthdate'         => $data['birthdate'],
            ':contact_info'      => $data['contact_info'],
            ':gender'            => $data['gender'],
            ':position'          => $data['position'],
            ':department'        => $data['department'],
            ':employment_status' => $data['employment_status'],
            ':photo'             => $photo,
            ':bank_name'         => $data['bank_name'],
            ':account_number'    => $data['account_number']
        ]);

        // 3. Insert into tbl_compensation (Always use INSERT for compensation record linked to new employee)
        $sql_comp = "INSERT INTO tbl_compensation (
            employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance
        ) VALUES (
            :employee_id, :daily_rate, :monthly_rate, :food_allowance, :transpo_allowance
        )";

        $stmt = $pdo->prepare($sql_comp);
        $stmt->execute([
            ':employee_id'       => $data['employee_id'], 
            ':daily_rate'        => $data['daily_rate'],
            ':monthly_rate'      => $data['monthly_rate'],
            ':food_allowance'    => $data['food_allowance'],
            ':transpo_allowance' => $data['transpo_allowance']
        ]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Employee added successfully!"]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        // Clean up photo if insertion failed
        if ($photo && file_exists($upload_path ?? '')) { unlink($upload_path); }
        
        $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? "Error: Employee ID already exists." : "Database error: " . $e->getMessage();
        error_log("Employee Create Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }
    exit;
}


// =================================================================================
// ACTION: GET EMPLOYEE DETAILS (For Edit Modal)
// =================================================================================
if ($action === 'get_details' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? null;
    
    if (!$employee_id) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID is required.']);
        exit;
    }

    $sql = "SELECT 
                e.*, 
                c.daily_rate, c.monthly_rate, c.food_allowance, c.transpo_allowance
            FROM tbl_employees e
            LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
            WHERE e.employee_id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$employee_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
        }
    } catch (Exception $e) {
        error_log("Employee get_details Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: UPDATE EMPLOYEE (Transaction + Photo)
// =================================================================================
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $employee_id = trim($_POST['employee_id']);
    $new_photo = null;
    
    if (empty($employee_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID cannot be empty.']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();

        // 1. Fetch old photo filename
        $stmt_old = $pdo->prepare("SELECT photo FROM tbl_employees WHERE employee_id = ? FOR UPDATE");
        $stmt_old->execute([$employee_id]);
        $old_photo_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
        $old_photo_filename = $old_photo_data['photo'] ?? null;
        
        // 2. Handle New Photo Upload (if submitted)
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_photo = $employee_id . '_' . time() . '.' . strtolower($file_ext);
            $upload_path = $UPLOAD_DIR . $new_photo;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Upload successful. Now, delete the old file.
                if ($old_photo_filename && file_exists($UPLOAD_DIR . $old_photo_filename)) {
                    unlink($UPLOAD_DIR . $old_photo_filename);
                }
            } else {
                // Upload failed, revert the new photo filename
                $new_photo = null; 
            }
        }
        
        // 3. Construct SQL for tbl_employees update
        $sql_emp_update = "UPDATE tbl_employees SET 
            firstname=?, middlename=?, lastname=?, suffix=?, address=?, birthdate=?, 
            contact_info=?, gender=?, position=?, department=?, employment_status=?,
            bank_name=?, account_number=?, updated_on=NOW()";
        
        $params = [
            trim($_POST['firstname'] ?? ''), trim($_POST['middlename'] ?? ''), trim($_POST['lastname'] ?? ''), trim($_POST['suffix'] ?? ''), 
            trim($_POST['address'] ?? ''), $_POST['birthdate'] ?? null, trim($_POST['contact_info'] ?? null), (int)$_POST['gender'], 
            trim($_POST['position'] ?? ''), trim($_POST['department'] ?? ''), (int)$_POST['employment_status'], 
            trim($_POST['bank_name'] ?? null), trim($_POST['account_number'] ?? null)
        ];
        
        // CRITICAL: Update photo column if a new file was successfully uploaded.
        if ($new_photo) {
            $sql_emp_update .= ", photo=?";
            $params[] = $new_photo;
        }

        $sql_emp_update .= " WHERE employee_id=?";
        $params[] = $employee_id;
        
        // 4. Execute tbl_employees update
        $stmt = $pdo->prepare($sql_emp_update);
        $stmt->execute($params);
        
        // 5. Update tbl_compensation (Using INSERT ... ON DUPLICATE KEY UPDATE for robustness)
        $sql_comp_update = "INSERT INTO tbl_compensation 
            (employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            daily_rate=VALUES(daily_rate), 
            monthly_rate=VALUES(monthly_rate), 
            food_allowance=VALUES(food_allowance), 
            transpo_allowance=VALUES(transpo_allowance)";
        
        $stmt = $pdo->prepare($sql_comp_update);
        $stmt->execute([
            $employee_id,
            (float)($_POST['daily_rate'] ?? 0), 
            (float)($_POST['monthly_rate'] ?? 0), 
            (float)($_POST['food_allowance'] ?? 0), 
            (float)($_POST['transpo_allowance'] ?? 0)
        ]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Employee profile updated successfully!"]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        // Clean up the newly uploaded photo if the DB transaction failed
        if (isset($upload_path) && file_exists($upload_path)) { unlink($upload_path); }
        
        error_log("Employee Update Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => "Update failed: " . $e->getMessage()]);
    }
    exit;
}
?>