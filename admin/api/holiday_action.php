<?php
// api/holiday_action.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../db_connection.php'; // Adjust path

if (!isset($pdo)) {
    // Standardized fatal error response
    echo json_encode(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';
$draw = (int)($_GET['draw'] ?? 1); // Get the draw ID from DataTables

// =================================================================================
// ACTION 1: FETCH ALL (For DataTables SSP)
// =================================================================================
if ($action === 'fetch') {
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $search = trim($_GET['search']['value'] ?? '');
    
    try {
        // --- 1a. Count Total Records (Unfiltered) ---
        $totalStmt = $pdo->query("SELECT COUNT(id) FROM tbl_holidays");
        $recordsTotal = (int)$totalStmt->fetchColumn();

        // --- 1b. Build Query with Search/Order ---
        $query = "SELECT * FROM tbl_holidays";
        $where_params = [];
        $where_bindings = [];

        if (!empty($search)) {
            // Simple search across name and type
            $where_params[] = " (holiday_name LIKE :search_val OR holiday_type LIKE :search_val) ";
            $where_bindings[':search_val'] = "%$search%";
        }
        
        $where_sql = !empty($where_params) ? " WHERE " . implode(' AND ', $where_params) : "";
        
        // Count Filtered Records
        $filteredQuery = "SELECT COUNT(id) FROM tbl_holidays" . $where_sql;
        $filteredStmt = $pdo->prepare($filteredQuery);
        $filteredStmt->execute($where_bindings);
        $recordsFiltered = (int)$filteredStmt->fetchColumn();

        // Ordering (Default: holiday_date DESC)
        $order_sql = " ORDER BY holiday_date DESC";
        
        // --- 1c. Fetch Data ---
        $finalSql = $query . $where_sql . $order_sql . " LIMIT :start_limit, :length_limit";
        $stmt = $pdo->prepare($finalSql);
        
        // Bind parameters for search
        foreach ($where_bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        // Bind LIMIT parameters (using named placeholders for safety)
        $stmt->bindValue(':start_limit', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length_limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 1d. Final Output (Standardized SSP) ---
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ]);

    } catch (Exception $e) {
        // Standardized SSP Error Response
        error_log("Holiday fetch error: " . $e->getMessage());
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => [],
            "error" => "Failed to fetch data: " . $e->getMessage()
        ]);
    }
    exit;
}

// =================================================================================
// ACTION 2: FETCH SINGLE (For Edit/View Modal)
// =================================================================================
if ($action === 'get_details') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_holidays WHERE id = ?");
        $stmt->execute([$id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($details) {
            echo json_encode(['status' => 'success', 'details' => $details]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Holiday not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 3: CREATE NEW HOLIDAY
// =================================================================================
if ($action === 'create') {
    $date = trim($_POST['holiday_date'] ?? '');
    $name = trim($_POST['holiday_name'] ?? '');
    $type = trim($_POST['holiday_type'] ?? '');
    $rate = (float)($_POST['payroll_multiplier'] ?? 1.00);

    if (empty($date) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Date and Name are required.']);
        exit;
    }

    try {
        // Check for duplicates
        $check = $pdo->prepare("SELECT id FROM tbl_holidays WHERE holiday_date = ?");
        $check->execute([$date]);
        if($check->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'A holiday already exists on this date.']);
            exit;
        }

        $sql = "INSERT INTO tbl_holidays (holiday_date, holiday_name, holiday_type, payroll_multiplier) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date, $name, $type, $rate]);
        $msg = "New holiday added successfully.";
        
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error creating holiday: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION 4: UPDATE EXISTING HOLIDAY
// =================================================================================
if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $date = trim($_POST['holiday_date'] ?? '');
    $name = trim($_POST['holiday_name'] ?? '');
    $type = trim($_POST['holiday_type'] ?? '');
    $rate = (float)($_POST['payroll_multiplier'] ?? 1.00);

    if ($id === 0 || empty($date) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields (ID, Date, Name).']);
        exit;
    }

    try {
        // Check for duplicates (excluding the current ID)
        $check = $pdo->prepare("SELECT id FROM tbl_holidays WHERE holiday_date = ? AND id != ?");
        $check->execute([$date, $id]);
        if($check->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Another holiday already exists on this date.']);
            exit;
        }

        $sql = "UPDATE tbl_holidays SET holiday_date=?, holiday_name=?, holiday_type=?, payroll_multiplier=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$date, $name, $type, $rate, $id]);
        $msg = "Holiday updated successfully.";
        
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error updating holiday: ' . $e->getMessage()]);
    }
    exit;
}


// =================================================================================
// ACTION 5: DELETE
// =================================================================================
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Missing ID for deletion.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_holidays WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Holiday deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Holiday not found or already deleted.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting holiday: ' . $e->getMessage()]);
    }
    exit;
}