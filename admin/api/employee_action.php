<?php
// api/employee_action.php
header('Content-Type: application/json');
session_start();

// Adjust paths to your actual file structure
require_once __DIR__ . '/../../db_connection.php'; 

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';

// =================================================================================
// ACTION: FETCH EMPLOYEES (DataTables Server-Side Processing)
// =================================================================================
if ($action === 'fetch') {
    $draw = $_GET['draw'] ?? 1;
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search_value = $_GET['search']['value'] ?? '';
    
    // DataTables columns mapping for sorting
    $columns = [
        0 => 'e.employee_id', 
        1 => 'e.lastname', 
        2 => 'c.daily_rate', 
        3 => 'e.employment_status',
    ];

    $base_sql = " FROM tbl_employees e LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id";
    
    // 1. Initialize params with your hardcoded filter (Status < 7)
    $where_params = ["e.employment_status < 7"];
    $where_bindings = [];

    // Global Search (Appends to the existing status filter)
    if (!empty($search_value)) {
        $term = '%' . $search_value . '%';
        $where_params[] = "(e.employee_id LIKE ? OR e.firstname LIKE ? OR e.lastname LIKE ? OR e.position LIKE ?)";
        $where_bindings[] = $term; $where_bindings[] = $term; $where_bindings[] = $term; $where_bindings[] = $term;
    }

    // Combine constraints
    $where_sql = " WHERE " . implode(' AND ', $where_params);

    // 2. Counting records (Total available vs Filtered by search)
    
    // recordsTotal: Counts all employees with status < 7 (ignoring search bar)
    $recordsTotal = $pdo->query("SELECT COUNT(e.employee_id) $base_sql WHERE e.employment_status < 7")->fetchColumn();
    
    // recordsFiltered: Counts employees with status < 7 AND matching search bar
    $stmt = $pdo->prepare("SELECT COUNT(e.employee_id) $base_sql $where_sql");
    $stmt->execute($where_bindings);
    $recordsFiltered = $stmt->fetchColumn();

    // Ordering
    $order_sql = " ORDER BY e.employment_status ASC, e.lastname ASC";
    if (isset($_GET['order'])) {
        $col_idx = $_GET['order'][0]['column'];
        $dir = $_GET['order'][0]['dir'];
        
        if (isset($columns[$col_idx])) {
            $colName = $columns[$col_idx];
            $order_col = ($colName === 'e.lastname') ? "e.lastname $dir, e.firstname $dir" : "$colName $dir";
            $order_sql = " ORDER BY $order_col";
        }
    }

    $limit_sql = " LIMIT " . (int)$start . ", " . (int)$length;

    // Fetch Data
    $sql_data = "SELECT e.employee_id, e.firstname, e.lastname, e.photo, e.employment_status, e.position, c.daily_rate 
                 $base_sql $where_sql $order_sql $limit_sql";

    $stmt = $pdo->prepare($sql_data);
    $stmt->execute($where_bindings);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    
    // Collect data (Using coalescing operator for optional fields)
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
        
        // Compensation
        'daily_rate'        => (float)($_POST['daily_rate'] ?? 0),       
        'monthly_rate'      => (float)($_POST['monthly_rate'] ?? 0),     
        'food_allowance'    => (float)($_POST['food_allowance'] ?? 0), 
        'transpo_allowance' => (float)($_POST['transpo_allowance'] ?? 0), 
        
        // Banking/Other fields from the form
        'bank_name'         => trim($_POST['bank_name'] ?? null),
        'account_number'    => trim($_POST['account_number'] ?? null),
        // Add schedule_type if it exists in your tbl_employees schema
    ];

    $photo = null;
    $upload_path = null;
    $upload_dir = __DIR__ . '/../../assets/images/'; 

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

        // 2. Insert into tbl_employees (Personal/Employment/Banking)
        // NOTE: Adjusted column list to reflect the fields found in the HTML form
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
            ':employee_id'      => $data['employee_id'],
            ':firstname'        => $data['firstname'],
            ':middlename'       => $data['middlename'],
            ':lastname'         => $data['lastname'],
            ':suffix'           => $data['suffix'],
            ':address'          => $data['address'],
            ':birthdate'        => $data['birthdate'],
            ':contact_info'     => $data['contact_info'],
            ':gender'           => $data['gender'],
            ':position'         => $data['position'],
            ':department'       => $data['department'],
            ':employment_status' => $data['employment_status'],
            ':photo'            => $photo,
            ':bank_name'        => $data['bank_name'],
            ':account_number'   => $data['account_number']
        ]);

        // 3. Insert/Update into tbl_compensation (Compensation)
        // Ensure this is an INSERT for a new record.
        $sql_comp = "INSERT INTO tbl_compensation (
            employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance
        ) VALUES (
            :employee_id, :daily_rate, :monthly_rate, :food_allowance, :transpo_allowance
        )";

        $stmt = $pdo->prepare($sql_comp);
        $stmt->execute([
            ':employee_id'      => $data['employee_id'], 
            ':daily_rate'       => $data['daily_rate'],
            ':monthly_rate'     => $data['monthly_rate'],
            ':food_allowance'   => $data['food_allowance'],
            ':transpo_allowance' => $data['transpo_allowance']
        ]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Employee added successfully!"]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        // Clean up photo if insertion failed
        if ($photo && file_exists($upload_path ?? '')) { unlink($upload_path); }
        
        $msg = (strpos($e->getMessage(), 'Duplicate entry') !== false) ? "Error: Employee ID already exists." : "Database error: " . $e->getMessage();
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
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: UPDATE EMPLOYEE (Transaction)
// =================================================================================
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Collect data (using POST as primary source)
    $employee_id = trim($_POST['employee_id']);
    
    if (empty($employee_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Employee ID cannot be empty.']);
        exit;
    }

    // This section would be complex, requiring fetching the old photo path if a new one is uploaded
    
    try {
        $pdo->beginTransaction();

        // 1. Photo Handling & Old Record Fetch (Omitted for brevity, but required for clean update/delete)

        // 2. Update tbl_employees
        $sql_emp_update = "UPDATE tbl_employees SET 
            firstname=?, middlename=?, lastname=?, suffix=?, address=?, birthdate=?, 
            contact_info=?, gender=?, position=?, department=?, employment_status=?,
            bank_name=?, account_number=?
            WHERE employee_id=?";
        
        $stmt = $pdo->prepare($sql_emp_update);
        $stmt->execute([
            trim($_POST['firstname'] ?? ''), trim($_POST['middlename'] ?? ''), trim($_POST['lastname'] ?? ''), trim($_POST['suffix'] ?? ''), 
            trim($_POST['address'] ?? ''), $_POST['birthdate'] ?? null, trim($_POST['contact_info'] ?? null), (int)$_POST['gender'], 
            trim($_POST['position'] ?? ''), trim($_POST['department'] ?? ''), (int)$_POST['employment_status'], 
            trim($_POST['bank_name'] ?? null), trim($_POST['account_number'] ?? null),
            $employee_id
        ]);
        
        // 3. Update tbl_compensation (Use ON DUPLICATE KEY UPDATE or check if exists, but since CREATE inserted it, UPDATE should work)
        $sql_comp_update = "UPDATE tbl_compensation SET 
            daily_rate=?, monthly_rate=?, food_allowance=?, transpo_allowance=? 
            WHERE employee_id=?";
        
        $stmt = $pdo->prepare($sql_comp_update);
        $stmt->execute([
            (float)($_POST['daily_rate'] ?? 0), (float)($_POST['monthly_rate'] ?? 0), 
            (float)($_POST['food_allowance'] ?? 0), (float)($_POST['transpo_allowance'] ?? 0),
            $employee_id
        ]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Employee profile updated successfully!"]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['status' => 'error', 'message' => "Update failed: " . $e->getMessage()]);
    }
    exit;
}
?>