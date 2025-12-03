<?php
// functions/process_ca_approval.php
// DEBUGGING BLOCK
require_once '../../db_connection.php'; 
session_start();

header('Content-Type: application/json');

// --- DEBUG CHECK ---
// This checks if 'usertype' exists. If not, it shows you what DOES exist.
if (!isset($_SESSION['usertype'])) {
    // This sends the list of active session variables back to the browser
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized. Active Session Variables: ' . json_encode($_SESSION)
    ]);
    exit;
}

// 2. INPUT VALIDATION
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null;
$approved_amount = $_POST['amount'] ?? 0;

if (!$id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

try {
    // 3. CHECK CURRENT STATUS
    // We only want to modify requests that are currently 'Pending'.
    // This prevents race conditions where two admins might try to approve the same request.
    $stmt = $pdo->prepare("SELECT id, status, amount FROM tbl_cash_advances WHERE id = ?");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
        exit;
    }

    if ($request['status'] !== 'Pending') {
        echo json_encode(['status' => 'error', 'message' => 'This request has already been processed (Status: ' . $request['status'] . ').']);
        exit;
    }

    // 4. PROCESS LOGIC
    if ($action === 'approve') {
        
        // Validation: Ensure amount is valid
        if (!is_numeric($approved_amount) || $approved_amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid approval amount.']);
            exit;
        }

        // Logic: Update status to 'Approved' (Standard for Approved CA)
        // We also update the 'amount' to the approved amount in case it was modified by the admin.
        $new_status = 'Deducted';
        
        $update_sql = "UPDATE tbl_cash_advances SET status = :status, amount = :amount WHERE id = :id";
        $update_stmt = $pdo->prepare($update_sql);
        $result = $update_stmt->execute([
            ':status' => $new_status,
            ':amount' => $approved_amount, // Save the finalized approved amount
            ':id'     => $id
        ]);
        
        $msg = "Cash Advance approved successfully with amount ₱" . number_format($approved_amount, 2);

    } elseif ($action === 'reject') {
        
        // Logic: Update status to 'Cancelled'
        $new_status = 'Cancelled';
        
        $update_sql = "UPDATE tbl_cash_advances SET status = :status WHERE id = :id";
        $update_stmt = $pdo->prepare($update_sql);
        $result = $update_stmt->execute([
            ':status' => $new_status,
            ':id'     => $id
        ]);
        
        $msg = "Cash Advance request has been rejected.";

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unknown action type.']);
        exit;
    }

    // 5. SUCCESS RESPONSE
    if ($result) {
        // Optional: Log this action to an audit trail table if you have one
        // logAction($_SESSION['user_id'], "Processed CA ID $id as $new_status");

        echo json_encode(['status' => 'success', 'message' => $msg]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
    }

} catch (PDOException $e) {
    // Log the actual error internally
    error_log("Database Error in process_ca_approval: " . $e->getMessage());
    
    // Return a generic error to the user
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}
?>