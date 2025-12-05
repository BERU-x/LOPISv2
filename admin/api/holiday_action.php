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

// --- 1. FETCH ALL (For DataTables) ---
if ($action === 'fetch') {
    try {
        $stmt = $pdo->query("SELECT * FROM tbl_holidays ORDER BY holiday_date DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for JSON
        $formatted = [];
        foreach($data as $row) {
            $row['formatted_date'] = date('M d, Y', strtotime($row['holiday_date']));
            $formatted[] = $row;
        }

        echo json_encode(['data' => $formatted]);
    } catch (Exception $e) {
        echo json_encode(['data' => []]);
    }
    exit;
}

// --- 2. FETCH SINGLE (For Edit Modal) ---
if ($action === 'get_one') {
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM tbl_holidays WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

// --- 3. CREATE / UPDATE ---
if ($action === 'save') {
    $id = $_POST['id'] ?? ''; // If ID exists, it's an update
    $date = $_POST['holiday_date'];
    $name = trim($_POST['holiday_name']);
    $type = $_POST['holiday_type'];
    $rate = $_POST['payroll_multiplier'];

    if (empty($date) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Date and Name are required.']);
        exit;
    }

    try {
        if (!empty($id)) {
            // UPDATE
            $sql = "UPDATE tbl_holidays SET holiday_date=?, holiday_name=?, holiday_type=?, payroll_multiplier=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date, $name, $type, $rate, $id]);
            $msg = "Holiday updated successfully.";
        } else {
            // INSERT
            // Check duplicates
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
        }
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 4. DELETE ---
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