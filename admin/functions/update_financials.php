<?php
// functions/update_financials.php
require '../../db_connection.php';
session_start();

if (isset($_POST['update_financials'])) {
    
    $db_id = $_POST['db_id'];          // Used only for redirecting back
    $string_id = $_POST['string_id'];  // Used for saving to BOTH tables (employee_id column)
    
    try {
        // Start Transaction
        $pdo->beginTransaction();

        // ---------------------------------------------------------
        // 1. UPDATE COMPENSATIONS (Linked by string ID)
        // ---------------------------------------------------------
        $checkComp = $pdo->prepare("SELECT id FROM tbl_compensation WHERE employee_id = ?");
        $checkComp->execute([$string_id]);
        
        if ($checkComp->fetch()) {
            $sql_comp = "UPDATE tbl_compensation SET 
                         daily_rate = :daily, monthly_rate = :monthly, 
                         food_allowance = :food, transpo_allowance = :transpo
                         WHERE employee_id = :eid";
        } else {
            $sql_comp = "INSERT INTO tbl_compensation (employee_id, daily_rate, monthly_rate, food_allowance, transpo_allowance)
                         VALUES (:eid, :daily, :monthly, :food, :transpo)";
        }
        
        $stmt_comp = $pdo->prepare($sql_comp);
        $stmt_comp->execute([
            ':daily'   => (float)$_POST['daily_rate'],
            ':monthly' => (float)$_POST['monthly_rate'],
            ':food'    => (float)$_POST['food_allowance'],
            ':transpo' => (float)$_POST['transpo_allowance'],
            ':eid'     => $string_id
        ]);

        // ---------------------------------------------------------
        // 2. UPDATE FINANCIALS (Linked by string ID)
        // ---------------------------------------------------------
        $checkFin = $pdo->prepare("SELECT id FROM tbl_employee_financials WHERE employee_id = ?");
        $checkFin->execute([$string_id]);

        if ($checkFin->fetch()) {
            // Update Existing Record
            $sql_fin = "UPDATE tbl_employee_financials SET 
                        sss_loan = :sss, pagibig_loan = :pagibig, company_loan = :company, 
                        cash_advance = :ca, 
                        cash_assist_total = :ca_total, cash_assist_deduction = :ca_deduct,
                        savings_deduction = :savings,
                        -- ✅ NEW BALANCE COLUMNS ADDED HERE
                        sss_loan_balance = :sss_bal,
                        pagibig_loan_balance = :pagibig_bal,
                        company_loan_balance = :company_bal
                        WHERE employee_id = :eid";
        } else {
            // Insert New Record
            $sql_fin = "INSERT INTO tbl_employee_financials 
                        (employee_id, sss_loan, pagibig_loan, company_loan, cash_advance, 
                         cash_assist_total, cash_assist_deduction, savings_deduction,
                         sss_loan_balance, pagibig_loan_balance, company_loan_balance) 
                        VALUES (:eid, :sss, :pagibig, :company, :ca, 
                        :ca_total, :ca_deduct, :savings,
                        :sss_bal, :pagibig_bal, :company_bal)";
        }

        $stmt_fin = $pdo->prepare($sql_fin);
        $stmt_fin->execute([
            ':sss'      => (float)$_POST['sss_loan'],
            ':pagibig'  => (float)$_POST['pagibig_loan'],
            ':company'  => (float)$_POST['company_loan'],
            ':ca'       => (float)$_POST['cash_advance'],
            ':ca_total' => (float)$_POST['cash_assist_total'],
            ':ca_deduct'=> (float)$_POST['cash_assist_deduction'],
            ':savings'  => (float)$_POST['savings_deduction'],
            
            // ✅ New Balance Parameters
            ':sss_bal'     => (float)$_POST['sss_loan_balance'],
            ':pagibig_bal' => (float)$_POST['pagibig_loan_balance'],
            ':company_bal' => (float)$_POST['company_loan_balance'],

            ':eid'      => $string_id
        ]);

        $pdo->commit();
        $_SESSION['message'] = "Financial profile updated successfully!";

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }

    header("Location: ../manage_financials.php?id=" . $db_id);
    exit;
}
?>