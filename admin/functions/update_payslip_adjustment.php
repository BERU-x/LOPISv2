<?php
// functions/update_payroll_items.php

require '../db_connection.php';

// Set response header to JSON
header('Content-Type: application/json');

// 1. Validate Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['payroll_id']) || !isset($_POST['items']) || !is_array($_POST['items'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data parameters.']);
    exit;
}

$payroll_id = (int)$_POST['payroll_id'];
$items = $_POST['items']; // Array where Key = item_id, Value = new amount

try {
    // Start Transaction to ensure data integrity
    $pdo->beginTransaction();

    // 2. Update Each Item Amount
    // We strictly check payroll_id to ensure we only update items belonging to this payslip
    $sql_update_item = "UPDATE tbl_payroll_items SET amount = :amount WHERE id = :id AND payroll_id = :pid";
    $stmt_update_item = $pdo->prepare($sql_update_item);

    foreach ($items as $item_id => $amount) {
        // Sanitize amount (ensure it's a float, non-negative)
        $clean_amount = floatval($amount);
        if ($clean_amount < 0) $clean_amount = 0;

        $stmt_update_item->execute([
            ':amount' => $clean_amount,
            ':id'     => $item_id,
            ':pid'    => $payroll_id
        ]);
    }

    // 3. Recalculate Totals (Gross, Deductions, Net)
    // We query the DB again to get the fresh sums of all items for this payroll ID
    $sql_recalc = "SELECT item_type, SUM(amount) as total_amount 
                   FROM tbl_payroll_items 
                   WHERE payroll_id = :pid 
                   GROUP BY item_type";
    $stmt_recalc = $pdo->prepare($sql_recalc);
    $stmt_recalc->execute([':pid' => $payroll_id]);
    
    $results = $stmt_recalc->fetchAll(PDO::FETCH_ASSOC);

    $new_gross = 0;
    $new_deductions = 0;

    foreach ($results as $row) {
        if ($row['item_type'] == 'earning') {
            $new_gross = floatval($row['total_amount']);
        } elseif ($row['item_type'] == 'deduction') {
            $new_deductions = floatval($row['total_amount']);
        }
    }

    $new_net = $new_gross - $new_deductions;

    // 4. Update Main Payroll Header (tbl_payroll)
    $sql_update_header = "UPDATE tbl_payroll 
                          SET gross_pay = :gross, 
                              total_deductions = :deduct, 
                              net_pay = :net 
                          WHERE id = :id";
    $stmt_header = $pdo->prepare($sql_update_header);
    $stmt_header->execute([
        ':gross'  => $new_gross,
        ':deduct' => $new_deductions,
        ':net'    => $new_net,
        ':id'     => $payroll_id
    ]);

    // Commit changes
    $pdo->commit();

    // 5. Return Success Response with new values
    echo json_encode([
        'success' => true, 
        'message' => 'Payslip adjustments saved successfully.',
        'new_gross' => number_format($new_gross, 2),
        'new_deductions' => number_format($new_deductions, 2),
        'new_net' => number_format($new_net, 2)
    ]);

} catch (PDOException $e) {
    // Rollback if anything failed
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>