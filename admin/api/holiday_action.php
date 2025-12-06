<?php
// api/holiday_action.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../db_connection.php'; // Adjust path

if (!isset($pdo)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';
$draw = (int)($_GET['draw'] ?? 1); // Get the draw ID from DataTables

// --- 1. FETCH ALL (For DataTables) ---
if ($action === 'fetch') {
    try {
        // --- 1a. Count Total Records (Unfiltered) ---
        $totalStmt = $pdo->query("SELECT COUNT(id) FROM tbl_holidays");
        $recordsTotal = $totalStmt->fetchColumn();

        // --- 1b. Build Query with Search/Order ---
        $search = $_GET['search']['value'] ?? '';
        $query = "SELECT * FROM tbl_holidays";
        $where = "";
        $params = [];

        if (!empty($search)) {
            // Simple search across name and type
            $where = " WHERE holiday_name LIKE ? OR holiday_type LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Count Filtered Records
        $filteredQuery = "SELECT COUNT(id) FROM tbl_holidays" . $where;
        $filteredStmt = $pdo->prepare($filteredQuery);
        $filteredStmt->execute($params);
        $recordsFiltered = $filteredStmt->fetchColumn();

        // Ordering (Default: holiday_date DESC)
        $order = " ORDER BY holiday_date DESC";
        
        // Pagination
        $start = $_GET['start'] ?? 0;
        $length = $_GET['length'] ?? 10;
        $limit = " LIMIT :start, :length";

        // --- 1c. Fetch Data ---
        $finalSql = $query . $where . $order . $limit;
        $stmt = $pdo->prepare($finalSql);
        
        // Bind parameters for search AND limit
        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindValue(($i + 1), $params[$i]);
        }
        $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
        $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 1d. Final Output (CRITICAL FIX) ---
        echo json_encode([
            "draw" => $draw, // Return the draw ID sent by DataTables
            "recordsTotal" => (int)$recordsTotal, // Total UNFILTERED records
            "recordsFiltered" => (int)$recordsFiltered, // Total FILTERED records
            "data" => $data // The row data for the current page
        ]);

    } catch (Exception $e) {
        // Log the error but return empty structure to prevent NaN crash
        error_log("Holiday fetch error: " . $e->getMessage());
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => []
        ]);
    }
    exit;
}

// --- 2. FETCH SINGLE (For Edit/View Modal) ---
if ($action === 'get_details') {
    $id = $_POST['id'] ?? 0;
    try {
        $stmt = $pdo->prepare("SELECT * FROM tbl_holidays WHERE id = ?");
        $stmt->execute([$id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'details' => $details]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 3. CREATE NEW HOLIDAY ---
if ($action === 'create') {
    $date = $_POST['holiday_date'];
    $name = trim($_POST['holiday_name']);
    $type = $_POST['holiday_type'];
    $rate = $_POST['payroll_multiplier'];

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
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 4. UPDATE EXISTING HOLIDAY ---
if ($action === 'update') {
    $id = $_POST['id'] ?? '';
    $date = $_POST['holiday_date'];
    $name = trim($_POST['holiday_name']);
    $type = $_POST['holiday_type'];
    $rate = $_POST['payroll_multiplier'];

    if (empty($id) || empty($date) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
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
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// --- 5. DELETE ---
if ($action === 'delete') {
    $id = $_POST['id'] ?? 0;
    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_holidays WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Holiday deleted.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>