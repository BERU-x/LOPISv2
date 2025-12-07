<?php
// api/update_payslip.php
ob_start();
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../db_connection.php';

$response = ['status' => 'error', 'message' => 'Unknown error'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $payroll_id = $_POST['payroll_id'] ?? null;
    $items = $_POST['items'] ?? []; // Array: [item_id => new_amount]

    if (!$payroll_id || !is_array($items)) {
        throw new Exception("Invalid data submitted.");
    }

    // 1. Check if Payroll is Editable (Status 0 = Pending)
    $stmt_check = $pdo->prepare("SELECT status FROM tbl_payroll WHERE id = ?");
    $stmt_check->execute([$payroll_id]);
    $status = $stmt_check->fetchColumn();

    if ($status === false) throw new Exception("Payroll record not found.");
    if ($status != 0) throw new Exception("Cannot edit payroll that is already Paid or Cancelled.");

    $pdo->beginTransaction();

    // 2. Update Line Items
    $stmt_update_item = $pdo->prepare("UPDATE tbl_payroll_items SET amount = :amt WHERE id = :id AND payroll_id = :pid");

    foreach ($items as $item_id => $amount) {
        $clean_amount = floatval($amount); // Ensure it's a number
        $stmt_update_item->execute([
            ':amt' => $clean_amount,
            ':id'  => $item_id,
            ':pid' => $payroll_id
        ]);
    }

    // 3. Recalculate Header Totals (Gross, Deductions, Net)
    // We fetch from DB to be accurate (don't trust frontend math entirely)
    $stmt_calc = $pdo->prepare("SELECT item_type, SUM(amount) as total FROM tbl_payroll_items WHERE payroll_id = ? GROUP BY item_type");
    $stmt_calc->execute([$payroll_id]);
    $results = $stmt_calc->fetchAll(PDO::FETCH_KEY_PAIR); // ['earning' => 1000, 'deduction' => 200]

    $gross_pay = floatval($results['earning'] ?? 0);
    $total_deductions = floatval($results['deduction'] ?? 0);
    $net_pay = $gross_pay - $total_deductions;

    // 4. Update Header
    $stmt_header = $pdo->prepare("UPDATE tbl_payroll SET gross_pay = :gross, total_deductions = :deduct, net_pay = :net WHERE id = :id");
    $stmt_header->execute([
        ':gross'  => $gross_pay,
        ':deduct' => $total_deductions,
        ':net'    => $net_pay,
        ':id'     => $payroll_id
    ]);

    $pdo->commit();

    $response['status'] = 'success';
    $response['message'] = 'Payslip updated successfully.';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;
?>