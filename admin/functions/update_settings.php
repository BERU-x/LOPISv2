<?php
// admin/functions/update_settings.php

session_start();
require_once '../../db_connection.php';

// --- 1. SECURITY CHECK (Added) ---
// Prevent unauthorized access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['usertype'] !== 1) {
    header("Location: ../../index.php");
    exit;
}

// --- 2. PROCESS FORM ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings_btn'])) {
    
    // Prepare data (Sanitize inputs as floats)
    $updates = [
        'SSS'        => floatval($_POST['SSS']),
        'PhilHealth' => floatval($_POST['PhilHealth']),
        'Tax'        => floatval($_POST['Tax']),
        'Pag-IBIG'   => floatval($_POST['Pag-IBIG'])
    ];

    try {
        $pdo->beginTransaction();

        $sql = "UPDATE tbl_deduction_settings SET amount = :amount WHERE name = :name";
        $stmt = $pdo->prepare($sql);

        foreach ($updates as $name => $amount) {
            $stmt->execute([
                ':amount' => $amount,
                ':name'   => $name
            ]);
        }

        $pdo->commit();

        $_SESSION['status_title'] = "Updated!";
        $_SESSION['status'] = "Deduction rates have been successfully updated.";
        $_SESSION['status_code'] = "success";
        header("Location: ../payroll.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['status_title'] = "Error!";
        $_SESSION['status'] = "Failed to update settings: " . $e->getMessage();
        $_SESSION['status_code'] = "error";
        header("Location: ../payroll.php");
        exit;
    }

} else {
    // Redirect if accessed directly without POST
    header("Location: ../payroll.php");
    exit;
}
?>