<?php
// admin/functions/create_payroll.php

session_start();
ob_start();

// [UPDATE] Set timezone to ensure Reference Numbers (PY-YYMM) use local time
date_default_timezone_set('Asia/Manila');

require_once '../../db_connection.php';

// --- 1. AUTHENTICATION CHECK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['usertype'] !== 1) {
    header("Location: ../../index.php");
    exit;
}

// --- 2. PROCESS FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $payroll_type = $_POST['payroll_type'];

    try {
        // --- A. FETCH DEDUCTION SETTINGS FROM DB ---
        $stmt_settings = $pdo->query("SELECT * FROM tbl_deduction_settings");
        $config = [];
        while($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
            $config[$row['name']] = [
                'type' => $row['rate_type'], 
                'amount' => floatval($row['amount'])
            ];
        }

        $pdo->beginTransaction();

        // --- B. FETCH EMPLOYEES & COMPENSATION DATA ---
        // [UPDATE] We JOIN tbl_employees with tbl_compensation to get the rates
        $sql_emp = "SELECT 
                        e.employee_id, 
                        c.daily_rate, 
                        c.food_allowance, 
                        c.transpo_allowance
                    FROM tbl_employees e
                    LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
                    WHERE e.employment_status != 5 AND e.employment_status != 6";
        
        $stmt_emp = $pdo->query($sql_emp);
        $employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

        $count_generated = 0;

        // --- HELPER FUNCTION ---
        if (!function_exists('calculate_deduction')) {
            function calculate_deduction($base_amount, $setting) {
                if (!isset($setting)) return 0; 
                
                if ($setting['type'] === 'percentage') {
                    return $base_amount * ($setting['amount'] / 100);
                } else {
                    return $setting['amount'];
                }
            }
        }

        // --- C. LOOP THROUGH EMPLOYEES ---
        foreach ($employees as $emp) {
            $emp_id = $emp['employee_id'];
            
            // 1. GET RATES (Handle Nulls safely with ?? 0)
            $daily_rate      = floatval($emp['daily_rate'] ?? 0); 
            $monthly_food    = floatval($emp['food_allowance'] ?? 0);
            $monthly_transpo = floatval($emp['transpo_allowance'] ?? 0);

            // 2. CALCULATE HOURLY RATES
            // Regular Rate = Daily Rate / 8 hours
            $hourly_rate = ($daily_rate > 0) ? ($daily_rate / 8) : 0;
            
            // Overtime Rate = Hourly Rate * 1.25 (125%)
            $ot_rate = $hourly_rate * 1.25;

            // 3. FETCH ATTENDANCE TOTALS (Regular + OT)
            // [UPDATE] We calculate OT hours separately here
            $sql_hours = "SELECT 
                            SUM(num_hr) as total_regular, 
                            SUM(overtime_hr) as total_ot 
                          FROM tbl_attendance 
                          WHERE employee_id = :eid 
                          AND date BETWEEN :start AND :end";
            
            $stmt_hours = $pdo->prepare($sql_hours);
            $stmt_hours->execute([
                ':eid'   => $emp_id,
                ':start' => $start_date,
                ':end'   => $end_date
            ]);
            
            $row_hours = $stmt_hours->fetch(PDO::FETCH_ASSOC);
            $total_hours_worked = $row_hours['total_regular'] ? floatval($row_hours['total_regular']) : 0;
            $total_ot_hours     = $row_hours['total_ot'] ? floatval($row_hours['total_ot']) : 0;

            // 4. CALCULATE PAY CUTOFFS
            $basic_pay_cutoff = $total_hours_worked * $hourly_rate;
            $ot_pay_cutoff    = $total_ot_hours * $ot_rate;

            // 5. CALCULATE ALLOWANCE CUTOFFS (Split logic)
            if ($payroll_type === 'monthly') {
                $food_pay_cutoff    = $monthly_food;
                $transpo_pay_cutoff = $monthly_transpo;
            } else {
                // Semi-Monthly: Divide by 2
                $food_pay_cutoff    = $monthly_food / 2;
                $transpo_pay_cutoff = $monthly_transpo / 2;
            }

            // 6. TOTAL GROSS
            $gross_pay = $basic_pay_cutoff + $ot_pay_cutoff + $food_pay_cutoff + $transpo_pay_cutoff;

            // 7. CALCULATE DEDUCTIONS
            $sss        = calculate_deduction($gross_pay, $config['SSS']);
            $philhealth = calculate_deduction($gross_pay, $config['PhilHealth']);
            $pagibig    = calculate_deduction($gross_pay, $config['Pag-IBIG']);
            $tax        = calculate_deduction($gross_pay, $config['Tax']);

            $total_deductions = $sss + $philhealth + $pagibig + $tax;
            $net_pay = $gross_pay - $total_deductions;

            // --- D. INSERT INTO tbl_payroll (Master Record) ---
            $ref_suffix = rand(1000, 9999);
            $ref_no = "PY-" . date('ym') . "-" . $emp_id . "-" . $ref_suffix;

            $sql_master = "INSERT INTO tbl_payroll 
                (ref_no, employee_id, cut_off_start, cut_off_end, gross_pay, total_deductions, net_pay, status) 
                VALUES 
                (:ref_no, :emp_id, :start, :end, :gross, :deduct, :net, 0)";
            
            $stmt_master = $pdo->prepare($sql_master);
            $stmt_master->execute([
                ':ref_no' => $ref_no,
                ':emp_id' => $emp_id,
                ':start' => $start_date,
                ':end' => $end_date,
                ':gross' => $gross_pay,
                ':deduct' => $total_deductions,
                ':net' => $net_pay
            ]);

            $payroll_id = $pdo->lastInsertId();

            // --- E. INSERT INTO tbl_payroll_items (Details for Payslip) ---
            $items = [
                ['name' => 'Basic Pay (' . number_format($total_hours_worked, 2) . ' hrs)', 'type' => 'earning', 'amount' => $basic_pay_cutoff],
                ['name' => 'Overtime (' . number_format($total_ot_hours, 2) . ' hrs)',      'type' => 'earning', 'amount' => $ot_pay_cutoff],
                ['name' => 'Food Allowance',     'type' => 'earning',   'amount' => $food_pay_cutoff],
                ['name' => 'Transpo Allowance',  'type' => 'earning',   'amount' => $transpo_pay_cutoff],
                
                ['name' => 'SSS Contribution',   'type' => 'deduction', 'amount' => $sss],
                ['name' => 'PhilHealth',         'type' => 'deduction', 'amount' => $philhealth],
                ['name' => 'Pag-IBIG',           'type' => 'deduction', 'amount' => $pagibig],
                ['name' => 'Withholding Tax',    'type' => 'deduction', 'amount' => $tax]
            ];

            $sql_item = "INSERT INTO tbl_payroll_items (payroll_id, item_name, item_type, amount) VALUES (:pid, :name, :type, :amt)";
            $stmt_item = $pdo->prepare($sql_item);

            foreach ($items as $item) {
                // Only insert if amount > 0 (Cleaner database)
                if ($item['amount'] > 0) {
                    $stmt_item->execute([
                        ':pid' => $payroll_id,
                        ':name' => $item['name'],
                        ':type' => $item['type'],
                        ':amt' => $item['amount']
                    ]);
                }
            }

            $count_generated++;
        }

        $pdo->commit();

        $_SESSION['status_title'] = "Success!";
        $_SESSION['status'] = "Generated payroll for $count_generated employees.";
        $_SESSION['status_code'] = "success";
        header("Location: ../payroll.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['status_title'] = "Error!";
        $_SESSION['status'] = "Failed: " . $e->getMessage();
        $_SESSION['status_code'] = "error";
        header("Location: ../payroll.php");
        exit;
    }
} else {
    header("Location: ../payroll.php");
    exit;
}
?>