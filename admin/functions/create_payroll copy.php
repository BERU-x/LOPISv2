<?php
// functions/create_payroll.php

session_start();
require '../../db_connection.php'; 

// Set Timezone
date_default_timezone_set('Asia/Manila');

if (isset($_POST['generate_btn'])) {

    $payroll_type = $_POST['payroll_type']; 
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'];
    $target_employee = $_POST['employee_id'] ?? 'all';
    $ot_multiplier = 1.25; 

    if (empty($start_date) || empty($end_date)) {
        $_SESSION['status'] = "Please select a valid date range.";
        $_SESSION['status_code'] = "warning";
        $_SESSION['status_title'] = "Invalid Dates";
        header("Location: ../payroll.php");
        exit();
    }

    try {
        // Fetch Settings
        $settings = [];
        $stmt_settings = $pdo->query("SELECT * FROM tbl_deduction_settings");
        while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['name']] = $row['amount'];
        }
        $philhealth_rate = $settings['PhilHealth'] ?? 2.5; 

        // Fetch Employees
        $sql_emp = "SELECT 
                        e.employee_id, e.firstname, e.lastname,
                        c.monthly_rate, c.daily_rate, c.food_allowance, c.transpo_allowance,
                        f.sss_loan, f.pagibig_loan, f.company_loan, 
                        f.savings_deduction, f.cash_advance, f.cash_assist_deduction
                    FROM tbl_employees e
                    JOIN tbl_compensation c ON e.employee_id = c.employee_id
                    LEFT JOIN tbl_employee_financials f ON e.employee_id = f.employee_id
                    WHERE e.employment_status = 1"; 

        if ($target_employee !== 'all') {
            $sql_emp .= " AND e.employee_id = :target_id";
            $stmt_emp = $pdo->prepare($sql_emp);
            $stmt_emp->execute([':target_id' => $target_employee]);
        } else {
            $stmt_emp = $pdo->query($sql_emp);
        }
        
        $employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

        if (!$employees) throw new Exception("No eligible employees found.");

        $pdo->beginTransaction();
        $count = 0;

        // --- PREPARE STATEMENTS ---
        $stmt_ot = $pdo->prepare("SELECT SUM(overtime_hr) as total_ot FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end");
        $stmt_late = $pdo->prepare("SELECT SUM(8 - num_hr) as total_late_hrs FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end AND num_hr < 8 AND num_hr > 0");
        $stmt_days = $pdo->prepare("SELECT COUNT(DISTINCT date) as days_present FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end AND num_hr > 0");
        $stmt_sss = $pdo->prepare("SELECT total_contribution FROM tbl_sss_standard WHERE :rate >= min_salary AND :rate <= max_salary ORDER BY id DESC LIMIT 1");
        $stmt_header = $pdo->prepare("INSERT INTO tbl_payroll (ref_no, employee_id, cut_off_start, cut_off_end, gross_pay, total_deductions, net_pay, status) VALUES (:ref, :empid, :start, :end, :gross, :deduct, :net, 0)");
        $stmt_item = $pdo->prepare("INSERT INTO tbl_payroll_items (payroll_id, item_name, item_type, amount) VALUES (?, ?, ?, ?)");


        foreach ($employees as $emp) {
            $emp_id = $emp['employee_id'];
            $monthly_rate = floatval($emp['monthly_rate']);
            $daily_rate   = floatval($emp['daily_rate']);
            $hourly_rate  = ($daily_rate > 0) ? ($daily_rate / 8) : 0;

            // --- 1. ATTENDANCE DATA ---
            $stmt_ot->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $total_ot_hours = floatval($stmt_ot->fetchColumn() ?: 0);
            $ot_pay_amount = $total_ot_hours * $hourly_rate * $ot_multiplier;

            $stmt_late->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $total_late_hours = floatval($stmt_late->fetchColumn() ?: 0);
            $late_deduction_amount = $total_late_hours * $hourly_rate;

            $stmt_days->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $days_present = intval($stmt_days->fetchColumn() ?: 0);


            // --- 2. EARNINGS ---
            if ($payroll_type == 'semi-monthly') {
                $basic_pay = $monthly_rate / 2;
                $food_allow = floatval($emp['food_allowance']) / 2;
                $transpo_allow = floatval($emp['transpo_allowance']) / 2;
            } else {
                $basic_pay = $monthly_rate;
                $food_allow = floatval($emp['food_allowance']);
                $transpo_allow = floatval($emp['transpo_allowance']);
            }
            $gross_pay = $basic_pay + $food_allow + $transpo_allow + $ot_pay_amount;


            // --- 3. DEDUCTIONS (GOVT) ---
            $divider = ($payroll_type == 'semi-monthly') ? 2 : 1;

            // A. SSS
            $stmt_sss->execute([':rate' => $monthly_rate]);
            $sss_row = $stmt_sss->fetch(PDO::FETCH_ASSOC);
            $sss_full_amount = $sss_row ? floatval($sss_row['total_contribution']) : 0;
            $sss_contrib = $sss_full_amount / $divider;

            // B. PAG-IBIG (HDMF)
            $pagibig_contrib = ($days_present <= 15) ? 100.00 : 200.00;

            // C. PHILHEALTH
            $ph_basis = $monthly_rate;
            if ($ph_basis >= 60000) {
                $ph_employee_monthly_share = 900.00;
            } else {
                $ph_employee_monthly_share = ($ph_basis * 0.03) / 2;
            }
            $philhealth_deduct = $ph_employee_monthly_share / $divider;


            // =========================================================
            // D. WITHHOLDING TAX CALCULATION (REVISED 2023)
            // =========================================================
            
            // 1. Calculate Taxable Income
            // Formula: Gross Pay - (SSS + HDMF + PhilHealth + Late/Undertime)
            $non_taxable_deductions = $sss_contrib + $pagibig_contrib + $philhealth_deduct + $late_deduction_amount;
            $taxable_amount = $gross_pay - $non_taxable_deductions;
            
            // 2. Apply Tax Table (Semi-Monthly Rates)
            $tax_deduction = 0.00;

            if ($taxable_amount <= 10417) {
                // EXEMPT
                $tax_deduction = 0.00;
            } 
            elseif ($taxable_amount > 10417 && $taxable_amount <= 16666) {
                // 15% in excess of 10,417
                $excess = $taxable_amount - 10417;
                $tax_deduction = $excess * 0.15;
            } 
            elseif ($taxable_amount > 16666 && $taxable_amount <= 33332) {
                // 937.50 + 20% in excess of 16,667
                $excess = $taxable_amount - 16667;
                $tax_deduction = 937.50 + ($excess * 0.20);
            } 
            elseif ($taxable_amount > 33332 && $taxable_amount <= 83332) {
                // 4,270.83 + 25% in excess of 33,333
                $excess = $taxable_amount - 33333;
                $tax_deduction = 4270.83 + ($excess * 0.25);
            }
            elseif ($taxable_amount > 83332 && $taxable_amount <= 333332) {
                // 16,770.83 + 30% in excess of 83,333
                $excess = $taxable_amount - 83333;
                $tax_deduction = 16770.83 + ($excess * 0.30);
            }
            else {
                // 91,770.83 + 35% in excess of 333,333
                $excess = $taxable_amount - 333333;
                $tax_deduction = 91770.83 + ($excess * 0.35);
            }
            // =========================================================


            // E. Fixed Loans & Savings
            $sss_loan     = floatval($emp['sss_loan'] ?? 0);
            $pagibig_loan = floatval($emp['pagibig_loan'] ?? 0);
            $company_loan = floatval($emp['company_loan'] ?? 0);
            $savings      = floatval($emp['savings_deduction'] ?? 0);
            $cash_advance = floatval($emp['cash_advance'] ?? 0);
            $cash_assist  = floatval($emp['cash_assist_deduction'] ?? 0);


            // TOTAL DEDUCTIONS (Add Tax here)
            $total_deductions = $sss_contrib + $philhealth_deduct + $pagibig_contrib + 
                                $sss_loan + $pagibig_loan + $company_loan + 
                                $savings + $cash_advance + $cash_assist + $tax_deduction + 
                                $late_deduction_amount;

            // --- 4. NET PAY ---
            $net_pay = $gross_pay - $total_deductions;

            // --- 5. INSERT HEADER ---
            $ref_no = 'PAY-' . date('Ymd') . '-' . $emp_id . '-' . rand(100,999);
            $stmt_header->execute([':ref'=>$ref_no, ':empid'=>$emp_id, ':start'=>$start_date, ':end'=>$end_date, ':gross'=>$gross_pay, ':deduct'=>$total_deductions, ':net'=>$net_pay]);
            $payroll_id = $pdo->lastInsertId();

            // --- 6. INSERT ITEMS ---
            $items = [
                ['name' => 'Basic Pay',        'type' => 'earning',   'amount' => $basic_pay],
                ['name' => 'Food Allowance',   'type' => 'earning',   'amount' => $food_allow],
                ['name' => 'Transpo Allowance','type' => 'earning',   'amount' => $transpo_allow],
                ['name' => 'Overtime Pay (' . number_format($total_ot_hours, 2) . ' hrs)', 'type' => 'earning', 'amount' => $ot_pay_amount],
                
                ['name' => 'Late / Undertime (' . number_format($total_late_hours, 2) . ' hrs)', 'type' => 'deduction', 'amount' => $late_deduction_amount],
                ['name' => 'SSS Contribution', 'type' => 'deduction', 'amount' => $sss_contrib],
                ['name' => 'PhilHealth',       'type' => 'deduction', 'amount' => $philhealth_deduct],
                ['name' => 'Pag-IBIG (' . $days_present . ' days)', 'type' => 'deduction', 'amount' => $pagibig_contrib],
                
                // New Tax Item
                ['name' => 'Withholding Tax',  'type' => 'deduction', 'amount' => $tax_deduction],
                
                ['name' => 'SSS Loan',         'type' => 'deduction', 'amount' => $sss_loan],
                ['name' => 'Pag-IBIG Loan',    'type' => 'deduction', 'amount' => $pagibig_loan],
                ['name' => 'Company Loan',     'type' => 'deduction', 'amount' => $company_loan],
                ['name' => 'Cash Advance',     'type' => 'deduction', 'amount' => $cash_advance],
                ['name' => 'Cash Assistance',  'type' => 'deduction', 'amount' => $cash_assist],
                ['name' => 'Coop Savings',     'type' => 'deduction', 'amount' => $savings],
            ];

            foreach ($items as $item) {
                if ($item['amount'] > 0) $stmt_item->execute([$payroll_id, $item['name'], $item['type'], $item['amount']]);
            }
            $count++;
        }

        $pdo->commit();
        $_SESSION['status'] = "Generated payroll for $count employees.";
        $_SESSION['status_code'] = "success";
        $_SESSION['status_title'] = "Success";
        header("Location: ../payroll.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['status'] = "Error: " . $e->getMessage();
        $_SESSION['status_code'] = "error";
        $_SESSION['status_title'] = "Error";
        header("Location: ../payroll.php");
        exit();
    }
} else {
    header("Location: ../payroll.php");
    exit();
}
?>