<?php
// functions/create_payroll.php

// 1. Start Output Buffering & Session
ob_start();
session_start();

// 2. Set JSON Header
header('Content-Type: application/json');

require __DIR__ . '/../../db_connection.php'; 

// Set Timezone
date_default_timezone_set('Asia/Manila');

$response = ['status' => 'error', 'message' => 'Unknown error occurred.'];

try {
    // 3. Validation
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $payroll_type = $_POST['payroll_type'] ?? ''; 
    $start_date   = $_POST['start_date'] ?? '';
    $end_date     = $_POST['end_date'] ?? '';
    $target_employee = $_POST['employee_id'] ?? 'all';
    
    // --- OT MULTIPLIERS (Full Rates: Base + Premium) ---
    $REGULAR_OT_MULTIPLIER = 1.25;          // 125%
    $SUNDAY_OT_PREMIUM_MULTIPLIER = 1.69;   // 169%
    $RH_OT_PREMIUM_MULTIPLIER = 2.6;        // 260%
    $SNWD_OT_PREMIUM_MULTIPLIER = 1.69;     // 169%

    if (empty($start_date) || empty($end_date)) {
        throw new Exception("Please select a valid date range.");
    }

    // --- FETCH DATA ---

    // Fetch Holidays
    $stmt_holidays = $pdo->prepare("SELECT holiday_date, holiday_type, payroll_multiplier FROM tbl_holidays WHERE holiday_date BETWEEN :start AND :end");
    $stmt_holidays->execute([':start' => $start_date, ':end' => $end_date]);
    $holidays = $stmt_holidays->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Employees
    $sql_emp = "SELECT 
                    e.employee_id, e.firstname, e.lastname,
                    c.monthly_rate, c.daily_rate, c.hourly_rate, c.food_allowance, c.transpo_allowance
                FROM tbl_employees e
                JOIN tbl_compensation c ON e.employee_id = c.employee_id
                WHERE e.employment_status < 5"; 
    
    if ($target_employee !== 'all') {
        $sql_emp .= " AND e.employee_id = :target_id";
        $stmt_emp = $pdo->prepare($sql_emp);
        $stmt_emp->execute([':target_id' => $target_employee]);
    } else {
        $stmt_emp = $pdo->query($sql_emp);
    }
    
    $employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

    if (!$employees) throw new Exception("No eligible employees found.");

    // --- TRANSACTION ---
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); 
    }
    
    $pdo->beginTransaction();
    $count = 0;

    // --- PREPARE STATEMENTS ---

    // 1. Attendance & Leave
    $stmt_late = $pdo->prepare("SELECT SUM(total_deduction_hr) as total_late_hrs FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end AND total_deduction_hr > 0");
    $stmt_days = $pdo->prepare("SELECT COUNT(DISTINCT date) as days_present FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end AND num_hr > 0");
    
    // 2. OT Queries
    $stmt_approved_ot_map = $pdo->prepare("
        SELECT ot_date, SUM(hours_approved) as approved_hrs 
        FROM tbl_overtime 
        WHERE employee_id = :eid 
        AND ot_date BETWEEN :start AND :end 
        AND status = 'Approved' 
        GROUP BY ot_date
    ");
    $stmt_total_approved_ot = $pdo->prepare("
        SELECT SUM(hours_approved) 
        FROM tbl_overtime 
        WHERE employee_id = :eid 
        AND ot_date BETWEEN :start AND :end 
        AND status = 'Approved'
    ");

    // 3. Ledger Balances
    $stmt_ledger_balances = $pdo->prepare("
        SELECT category, running_balance 
        FROM tbl_employee_ledger 
        WHERE employee_id = :eid 
        AND (category, id) IN (
            SELECT category, MAX(id)
            FROM tbl_employee_ledger 
            WHERE employee_id = :eid
            GROUP BY category
        )
    ");
    
    // 4. Fixed Deduction Rates
    $stmt_fixed_rates = $pdo->prepare("
        SELECT category, amortization 
        FROM tbl_employee_ledger 
        WHERE employee_id = :eid 
        AND amortization > 0
        AND (category, id) IN (
            SELECT category, MAX(id) 
            FROM tbl_employee_ledger 
            WHERE employee_id = :eid 
              AND amortization > 0
            GROUP BY category
        )
    ");
    
    // 5. Lookups
    $stmt_sss = $pdo->prepare("SELECT total_contribution FROM tbl_sss_standard WHERE :rate >= min_salary AND :rate <= max_salary ORDER BY id DESC LIMIT 1");
    $stmt_leave_days = $pdo->prepare("SELECT SUM(days_count) FROM tbl_leave WHERE employee_id = :eid AND status = 1 AND leave_type NOT LIKE '%Unpaid%' AND start_date BETWEEN :start AND :end");
    $stmt_ca_check = $pdo->prepare("SELECT SUM(amount) FROM tbl_cash_advances WHERE employee_id = :eid AND status = 'Pending' AND date_requested BETWEEN :start AND :end");
    
    // Single Day Checks (For Holiday Eligibility)
    $stmt_check_attendance_single = $pdo->prepare("SELECT num_hr FROM tbl_attendance WHERE employee_id = :eid AND date = :check_date LIMIT 1");
    $stmt_check_leave_single = $pdo->prepare("SELECT days_count FROM tbl_leave WHERE employee_id = :eid AND status = 1 AND leave_type NOT LIKE '%Unpaid%' AND :check_date BETWEEN start_date AND end_date LIMIT 1");
    
    // Inserts
    $stmt_header = $pdo->prepare("INSERT INTO tbl_payroll (ref_no, employee_id, cut_off_start, cut_off_end, gross_pay, total_deductions, net_pay, status) VALUES (:ref, :empid, :start, :end, :gross, :deduct, :net, 0)");
    $stmt_item = $pdo->prepare("INSERT INTO tbl_payroll_items (payroll_id, item_name, item_type, amount) VALUES (?, ?, ?, ?)");
    $stmt_update_emp_ts = $pdo->prepare("UPDATE tbl_employees SET updated_on = NOW() WHERE employee_id = :eid");
    
    // --- HELPER CLOSURES ---
    $check_eligibility = function($current_emp_id, $check_date) use ($stmt_check_attendance_single, $stmt_check_leave_single) {
        // 1. Check Attendance
        $stmt_check_attendance_single->execute([':eid' => $current_emp_id, ':check_date' => $check_date]);
        $worked_hours = floatval($stmt_check_attendance_single->fetchColumn() ?: 0);
        $stmt_check_attendance_single->closeCursor();
        if ($worked_hours > 0) return true;

        // 2. Check Paid Leave
        $stmt_check_leave_single->execute([':eid' => $current_emp_id, ':check_date' => $check_date]);
        $leave_days = floatval($stmt_check_leave_single->fetchColumn() ?: 0);
        $stmt_check_leave_single->closeCursor();
        return $leave_days > 0;
    };

    $get_deduction = function($scheduled_amount, $current_balance) {
        $scheduled_amount = floatval($scheduled_amount);
        $current_balance  = floatval($current_balance);
        if ($current_balance <= 0) return 0.00;
        return ($scheduled_amount > $current_balance) ? $current_balance : $scheduled_amount;
    };

    // --- MAIN EMPLOYEE LOOP ---
    foreach ($employees as $emp) {
        $emp_id = $emp['employee_id'];
        $monthly_rate = floatval($emp['monthly_rate']);
        $daily_rate   = floatval($emp['daily_rate']);
        $hourly_rate  = floatval($emp['hourly_rate'] ?? 0);
        
        if ($hourly_rate <= 0 && $daily_rate > 0) {
            $hourly_rate = $daily_rate / 8; 
        }
        if ($hourly_rate <= 0) $hourly_rate = 0.01;

        // 1. Fetch Balances
        $stmt_ledger_balances->execute([':eid' => $emp_id]);
        $balances = $stmt_ledger_balances->fetchAll(PDO::FETCH_KEY_PAIR);
        $sss_loan_balance = $balances['SSS_Loan'] ?? 0.00;
        $pagibig_loan_balance = $balances['Pagibig_Loan'] ?? 0.00;
        $company_loan_balance = $balances['Company_Loan'] ?? 0.00;
        $cash_assist_balance = $balances['Cash_Assist'] ?? 0.00;
        
        // 2. Fetch Fixed Rates
        $stmt_fixed_rates->execute([':eid' => $emp_id]);
        $rates_data = $stmt_fixed_rates->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $divider = ($payroll_type == 'semi-monthly') ? 2 : 1;
        $sss_loan_rate = floatval($rates_data['SSS_Loan'] ?? 0) / $divider;
        $pagibig_loan_rate = floatval($rates_data['Pagibig_Loan'] ?? 0) / $divider;
        $company_loan_rate = floatval($rates_data['Company_Loan'] ?? 0) / $divider;
        $cash_assist_rate = floatval($rates_data['Cash_Assist'] ?? 0) / $divider; 
        $savings_rate = floatval($rates_data['Savings'] ?? 0) / $divider; 
        
        // 3. Accumulators
        $regular_ot_premium = 0; 
        $sunday_ot_premium = 0;
        $rh_worked_pay_premium = 0; 
        $snwh_worked_pay_premium = 0; 
        $rh_ot_premium_only = 0;     
        $snwh_ot_premium_only = 0;   
        $regular_holiday_non_work_pay = 0; 
        $sunday_work_premium = 0; 

        // 4. Pre-Calculations
        $stmt_late->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
        $total_late_hours = floatval($stmt_late->fetchColumn() ?: 0);
        $late_deduction_amount = $total_late_hours * $hourly_rate;

        $stmt_days->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
        $days_present = intval($stmt_days->fetchColumn() ?: 0);
        
        $stmt_leave_days->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
        $total_paid_leave_days = floatval($stmt_leave_days->fetchColumn() ?: 0);
        $paid_leave_amount = $total_paid_leave_days * $daily_rate;

        $stmt_total_approved_ot->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
        $total_ot_hours = floatval($stmt_total_approved_ot->fetchColumn() ?: 0);
        
        $stmt_approved_ot_map->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
        $approved_ot_records = $stmt_approved_ot_map->fetchAll(PDO::FETCH_KEY_PAIR); 

        $premium_day_ot_hours = 0; 
        
        // --- 5. ATTENDANCE LOOP (Calculate Premiums) ---
        $stmt_all_daily_records = $pdo->prepare("SELECT date, num_hr FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end AND num_hr > 0"); 
        $stmt_all_daily_records->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
        $all_work_hours = $stmt_all_daily_records->fetchAll(PDO::FETCH_ASSOC);

        $holiday_lookup = array_column($holidays, 'holiday_type', 'holiday_date');
        $holiday_multiplier_lookup = array_column($holidays, 'payroll_multiplier', 'holiday_date');
        
        foreach($all_work_hours as $record) {
            $day_date = $record['date'];
            $base_hours = min(8, floatval($record['num_hr'])); 
            $ot_hours = isset($approved_ot_records[$day_date]) ? floatval($approved_ot_records[$day_date]) : 0;
            $day_of_week = date('N', strtotime($day_date)); 
            $is_sunday = ($day_of_week == 7);
            $is_holiday = isset($holiday_lookup[$day_date]);
            
            $base_pay_for_worked_day = $base_hours * $hourly_rate;

            if ($is_holiday) {
                $h_type = $holiday_lookup[$day_date];
                if ($ot_hours > 0) $premium_day_ot_hours += $ot_hours; 
                
                $h_multiplier = floatval($holiday_multiplier_lookup[$day_date]);
                $hourly_premium = $base_pay_for_worked_day * ($h_multiplier - 1.0); 
                
                if ($h_type == 'Regular') {
                    $rh_worked_pay_premium += $hourly_premium; 
                    $rh_ot_premium_only += $ot_hours * $hourly_rate * $RH_OT_PREMIUM_MULTIPLIER;
                }
                elseif ($h_type == 'Special Non-Working') {
                    $snwh_worked_pay_premium += $hourly_premium;
                    $snwh_ot_premium_only += $ot_hours * $hourly_rate * $SNWD_OT_PREMIUM_MULTIPLIER;
                }
            }
            elseif ($is_sunday) {
                if ($ot_hours > 0) $premium_day_ot_hours += $ot_hours; 
                $sunday_work_premium += $base_pay_for_worked_day * 0.30;
                $sunday_ot_premium += $ot_hours * $hourly_rate * $SUNDAY_OT_PREMIUM_MULTIPLIER;
            } 
            else {
                $regular_ot_premium += $ot_hours * $hourly_rate * $REGULAR_OT_MULTIPLIER;
            }
        }
        
        $total_ot_premium_pay = $regular_ot_premium + $sunday_ot_premium + $rh_ot_premium_only + $snwh_ot_premium_only;

        // --- 6. REGULAR HOLIDAY PAY (UNWORKED) ---
        foreach ($holidays as $holiday) {
            $h_date = $holiday['holiday_date'];
            $h_type = $holiday['holiday_type'];
            
            // SKIP Special Holidays (No Work, No Pay)
            if ($h_type != 'Regular') continue; 

            // Check if worked (If worked, they get premium, so skip "unworked" bonus)
            $stmt_check_attendance_single->execute([':eid' => $emp_id, ':check_date' => $h_date]);
            $work_hours_on_hday = floatval($stmt_check_attendance_single->fetchColumn() ?: 0);
            $stmt_check_attendance_single->closeCursor();
            if ($work_hours_on_hday > 0) continue; 
            
            // --- STRICT CHECK: PREVIOUS & NEXT WORKING DAY ---
            
            // 1. Previous Working Day (Skip Sat=6, Sun=7)
            $prev_day = date('Y-m-d', strtotime($h_date . ' - 1 day'));
            while (date('N', strtotime($prev_day)) >= 6) { 
                $prev_day = date('Y-m-d', strtotime($prev_day . ' - 1 day'));
            }

            // 2. Next Working Day (Skip Sat=6, Sun=7)
            $next_day = date('Y-m-d', strtotime($h_date . ' + 1 day'));
            while (date('N', strtotime($next_day)) >= 6) { 
                $next_day = date('Y-m-d', strtotime($next_day . ' + 1 day'));
            }

            // Must be Present/On Leave for BOTH days to be eligible
            if ($check_eligibility($emp_id, $prev_day) && $check_eligibility($emp_id, $next_day)) {
                $regular_holiday_non_work_pay += $daily_rate;
            }
        }
        
        $total_worked_base_pay_GROSS = $daily_rate * $days_present;
        $total_base_pay = $total_worked_base_pay_GROSS + $regular_holiday_non_work_pay + $paid_leave_amount; 

        // --- 7. FINAL GROSS ---
        $food_allow = ($payroll_type == 'semi-monthly') ? floatval($emp['food_allowance']) / 2 : floatval($emp['food_allowance']);
        $transpo_allow = ($payroll_type == 'semi-monthly') ? floatval($emp['transpo_allowance']) / 2 : floatval($emp['transpo_allowance']);
        $total_allowances = $food_allow + $transpo_allow;

        $gross_pay = $total_base_pay + $total_allowances + $total_ot_premium_pay + $sunday_work_premium + $rh_worked_pay_premium + $snwh_worked_pay_premium;

        // --- 8. DEDUCTIONS ---
        
        $stmt_sss->execute([':rate' => $monthly_rate]);
        $sss_row = $stmt_sss->fetch(PDO::FETCH_ASSOC);
        $sss_contrib = ($sss_row ? floatval($sss_row['total_contribution']) : 0) / $divider;

        $pagibig_contrib = ($days_present <= 15) ? 100.00 : 200.00;

        $ph_basis = $monthly_rate;
        if ($ph_basis >= 60000) { $ph_employee_monthly_share = 900.00; } 
        else { $ph_employee_monthly_share = ($ph_basis * 0.03) / 2; }
        $philhealth_deduct = $ph_employee_monthly_share / $divider;

        $stmt_ca_check->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
        $cash_advance = floatval($stmt_ca_check->fetchColumn() ?: 0);

        $total_govt_deductions = $sss_contrib + $pagibig_contrib + $philhealth_deduct + $late_deduction_amount;

        // --- TAX COMPUTATION (Flat 15% on Excess of 10,417) ---
        $taxable_income = $gross_pay - ($total_allowances) - $total_govt_deductions;
        $tax_deduction = 0.00;
        
        if ($taxable_income > 10417) {
            $excess = $taxable_income - 10417;
            $tax_deduction = $excess * 0.15;
        }

        // Ledger Deductions
        $sss_loan    = $get_deduction($sss_loan_rate, $sss_loan_balance);
        $pagibig_loan = $get_deduction($pagibig_loan_rate, $pagibig_loan_balance);
        $company_loan = $get_deduction($company_loan_rate, $company_loan_balance);
        $cash_assist  = $get_deduction($cash_assist_rate, $cash_assist_balance);
        $savings      = $savings_rate; 

        // Total Deductions
        $total_deductions = $total_govt_deductions + 
                             $sss_loan + $pagibig_loan + $company_loan + 
                             $savings + $cash_advance + $cash_assist + $tax_deduction; 

        // --- 9. NET PAY & INSERTS ---
        $net_pay = $gross_pay - $total_deductions;

        $ref_no = 'LOSI-' . date('YmdHis') . '-' . $emp_id; 
        $stmt_header->execute([':ref'=>$ref_no, ':empid'=>$emp_id, ':start'=>$start_date, ':end'=>$end_date, ':gross'=>$gross_pay, ':deduct'=>$total_deductions, ':net'=>$net_pay]);
        $payroll_id = $pdo->lastInsertId();
        
        // Reverse Calculate Hours for Display
        $rate_rh_ot   = $hourly_rate * $RH_OT_PREMIUM_MULTIPLIER;
        $rate_snwh_ot = $hourly_rate * $SNWD_OT_PREMIUM_MULTIPLIER;
        $rate_sun_ot  = $hourly_rate * $SUNDAY_OT_PREMIUM_MULTIPLIER;
        
        $rh_ot_hours     = ($rh_ot_premium_only > 0 && $rate_rh_ot > 0)     ? $rh_ot_premium_only / $rate_rh_ot : 0;
        $snwh_ot_hours   = ($snwh_ot_premium_only > 0 && $rate_snwh_ot > 0) ? $snwh_ot_premium_only / $rate_snwh_ot : 0;
        $sunday_ot_hours = ($sunday_ot_premium > 0 && $rate_sun_ot > 0)     ? $sunday_ot_premium / $rate_sun_ot : 0;

        $items = [
            ['name' => 'Basic Pay (' . number_format($days_present, 1) . ' day/s)', 'type' => 'earning', 'amount' => $total_worked_base_pay_GROSS],
            ['name' => 'Leave Pay (' . number_format($total_paid_leave_days, 1) . ' day/s)', 'type' => 'earning', 'amount' => $paid_leave_amount],
            ['name' => 'RH Premium Pay', 'type' => 'earning', 'amount' => $rh_worked_pay_premium],
            ['name' => 'SNW Holiday Premium Pay', 'type' => 'earning', 'amount' => $snwh_worked_pay_premium],
            ['name' => 'Regular Holiday Pay (Non-Work)', 'type' => 'earning', 'amount' => $regular_holiday_non_work_pay],
            ['name' => 'Sunday Work Premium Pay', 'type' => 'earning', 'amount' => $sunday_work_premium],
            ['name' => 'Regular OT (' . number_format($total_ot_hours - $premium_day_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $regular_ot_premium],
            ['name' => 'RH OT (' . number_format($rh_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $rh_ot_premium_only],
            ['name' => 'SNWH OT (' . number_format($snwh_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $snwh_ot_premium_only],
            ['name' => 'Sunday OT (' . number_format($sunday_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $sunday_ot_premium],
            ['name' => 'Allowances', 'type' => 'earning', 'amount' => $total_allowances],
            
            ['name' => 'Late / Undertime (' . number_format($total_late_hours, 2) . ' hrs)', 'type' => 'deduction', 'amount' => $late_deduction_amount],
            ['name' => 'SSS Contribution', 'type' => 'deduction', 'amount' => $sss_contrib],
            ['name' => 'PhilHealth', 'type' => 'deduction', 'amount' => $philhealth_deduct],
            ['name' => 'Pag-IBIG', 'type' => 'deduction', 'amount' => $pagibig_contrib],
            ['name' => 'Withholding Tax', 'type' => 'deduction', 'amount' => $tax_deduction],
            ['name' => 'SSS Loan', 'type' => 'deduction', 'amount' => $sss_loan],
            ['name' => 'Pag-IBIG Loan', 'type' => 'deduction', 'amount' => $pagibig_loan],
            ['name' => 'Company Loan', 'type' => 'deduction', 'amount' => $company_loan],
            ['name' => 'Cash Advance', 'type' => 'deduction', 'amount' => $cash_advance],
            ['name' => 'Cash Assistance', 'type' => 'deduction', 'amount' => $cash_assist],
            ['name' => 'Company Savings', 'type' => 'deduction', 'amount' => $savings],
        ];

        foreach ($items as $item) {
            if ($item['amount'] > 0) $stmt_item->execute([$payroll_id, $item['name'], $item['type'], $item['amount']]);
        }
        
        $stmt_update_emp_ts->execute([':eid' => $emp_id]);
        $count++;
    }

    $pdo->commit();
    $response['status'] = 'success';
    $response['message'] = "Generated payroll for $count employees.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['status'] = 'error';
    $response['message'] = "Error: " . $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit();
?>