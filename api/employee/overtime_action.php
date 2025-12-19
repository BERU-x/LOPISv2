<?php
/**
 * api/employee/overtime_action.php
 * Handles personal OT requests, biometric validation, and history tracking.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../../db_connection.php';
require_once __DIR__ . '/../../app/models/global_app_model.php'; // For notifications
require_once __DIR__ . '/../../helpers/audit_helper.php';       // For tracking

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_GET['action'] ?? '';
$employee_id = $_SESSION['employee_id'];

// =================================================================================
// ACTION: VALIDATE OT REQUEST (Checks Biometric Logs)
// =================================================================================


if ($action === 'validate_ot_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ot_date = $_POST['ot_date'] ?? null;
    
    if (empty($ot_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Date is required.']);
        exit;
    }

    try {
        // Fetch raw overtime from attendance logs calculated by the biometric system
        $stmt = $pdo->prepare("SELECT overtime_hr FROM tbl_attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee_id, $ot_date]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        $raw_ot_hr = (float)($attendance['overtime_hr'] ?? 0);

        echo json_encode([
            'status' => 'success',
            'raw_ot_hr' => $raw_ot_hr,
            'message' => ($raw_ot_hr > 0) ? "Biometric log found." : "No biometric overtime logged for this date."
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve biometric log.']);
    }
    exit;
}

// =================================================================================
// ACTION: CREATE OT REQUEST
// =================================================================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $ot_date = $_POST['ot_date'] ?? null;
    $ot_hours = (float)($_POST['hours_requested'] ?? 0); 
    $reason = trim($_POST['reason'] ?? '');

    if (empty($ot_date) || $ot_hours <= 0 || empty($reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill out all required fields with valid hours.']);
        exit;
    }

    try {
        // 1. Server-Side Biometric Validation (Prevent Fraud)
        $stmt = $pdo->prepare("SELECT overtime_hr FROM tbl_attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee_id, $ot_date]);
        $raw_ot_hr = (float)($stmt->fetchColumn() ?: 0);

        if ($raw_ot_hr <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot request OT: No biometric overtime logged for this date.']);
            exit;
        }
        
        if ($ot_hours > $raw_ot_hr) {
            echo json_encode(['status' => 'error', 'message' => "Requested hours ($ot_hours) exceed your logged biometric OT ($raw_ot_hr)."]);
            exit;
        }

        // 2. Duplicate Check
        $stmt = $pdo->prepare("SELECT id FROM tbl_overtime WHERE employee_id = ? AND ot_date = ? AND status = 'Pending'");
        $stmt->execute([$employee_id, $ot_date]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'warning', 'message' => 'You already have a pending request for this date.']);
            exit;
        }

        // 3. Insert Request
        $sql = "INSERT INTO tbl_overtime (employee_id, ot_date, hours_requested, reason, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$employee_id, $ot_date, $ot_hours, $reason])) {
            $req_id = $pdo->lastInsertId();

            // 4. Trigger Admin Notification
            $emp_name = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];
            $msg = "$emp_name filed an OT request: $ot_hours hrs for $ot_date.";
            send_notification($pdo, null, 1, 'Overtime', $msg, 'overtime_management.php', $req_id);

            echo json_encode(['status' => 'success', 'message' => 'Overtime request submitted successfully!']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
    }
    exit;
}

// =================================================================================
// ACTION: FETCH HISTORY (SSP)
// =================================================================================
if ($action === 'fetch') {
    $draw   = (int)($_GET['draw'] ?? 1);
    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    
    $sql_base = " FROM tbl_overtime o 
                  LEFT JOIN tbl_attendance a ON o.employee_id = a.employee_id AND o.ot_date = a.date
                  WHERE o.employee_id = :emp_id";
    $params = [':emp_id' => $employee_id];

    try {
        $stmt_total = $pdo->prepare("SELECT COUNT(id) FROM tbl_overtime WHERE employee_id = ?");
        $stmt_total = $pdo->prepare("SELECT COUNT(id) FROM tbl_overtime WHERE employee_id = ?");
        $stmt_total->execute([$employee_id]);
        $recordsTotal = (int)$stmt_total->fetchColumn(); // This converts the result to an int

        $sql_data = "SELECT o.*, a.overtime_hr as raw_ot " . $sql_base . " ORDER BY o.ot_date DESC LIMIT :offset, :limit";
        $stmt = $pdo->prepare($sql_data);
        $stmt->bindValue(':emp_id', $employee_id, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($data as $row) {
            $badge = match($row['status']) {
                'Approved' => '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Approved</span>',
                'Rejected' => '<span class="badge bg-soft-danger text-danger border border-danger px-3 rounded-pill">Rejected</span>',
                default    => '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill">Pending</span>'
            };

            $formatted[] = [
                'ot_date'         => "<strong>" . date('M d, Y', strtotime($row['ot_date'])) . "</strong>",
                'status'          => $badge,
                'id'              => $row['id'], 
                'raw_ot_hr'       => number_format((float)$row['raw_ot'], 2),
                'hours_requested' => number_format((float)$row['hours_requested'], 2),
                'hours_approved'  => ($row['hours_approved'] > 0) ? number_format((float)$row['hours_approved'], 2) : '—',
                'reason'          => htmlspecialchars($row['reason'])
            ];
        }

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsTotal,
            "data" => $formatted
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}