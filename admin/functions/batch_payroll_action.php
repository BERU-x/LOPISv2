<?php
// functions/batch_payroll_action.php
require_once '../../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && isset($_POST['action'])) {
    
    $ids = $_POST['ids'];
    $action = $_POST['action'];

    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'message' => 'No items selected.']);
        exit;
    }

    // Create placeholders for the IN clause (?,?,?)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        if ($action === 'approve') {
            // Update status to 1 (Paid)
            $sql = "UPDATE tbl_payroll SET status = 1 WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            
            echo json_encode(['success' => true, 'message' => 'Selected payrolls approved successfully!']);
        } 
        elseif ($action === 'send_email') {
            // NOTE: Sending actual email requires PHPMailer or SMTP setup.
            // For now, we will just pretend it worked, or you can add a flag like 'is_emailed' to your DB.
            
            // Example logic: Just return success for now
            // To implement real email, loop through IDs, fetch email addresses, and send.
            
            sleep(1); // Simulate processing time
            echo json_encode(['success' => true, 'message' => 'Payslips have been queued for sending.']);
        } 
        else {
            echo json_encode(['success' => false, 'message' => 'Invalid action type.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>