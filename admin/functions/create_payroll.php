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
    
    // OT multiplier constants for premiums 
    $REGULAR_OT_MULTIPLIER = 1.25; 
    $SUNDAY_OT_PREMIUM_MULTIPLIER = 1.69; 
    $RH_OT_PREMIUM_MULTIPLIER = 2.6; 
    $SNWD_OT_PREMIUM_MULTIPLIER = 1.69;

    if (empty($start_date) || empty($end_date)) {
        $_SESSION['status'] = "Please select a valid date range.";
        $_SESSION['status_code'] = "warning";
        $_SESSION['status_title'] = "Invalid Dates";
        header("Location: ../payroll.php");
        exit();
    }

    try {
        // Fetch Holidays within the cut-off
        $stmt_holidays = $pdo->prepare("SELECT holiday_date, holiday_type, payroll_multiplier FROM tbl_holidays WHERE holiday_date BETWEEN :start AND :end");
        $stmt_holidays->execute([':start' => $start_date, ':end' => $end_date]);
        $holidays = $stmt_holidays->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Employee Data
        $sql_emp = "SELECT 
                        e.employee_id, e.firstname, e.lastname,
                        c.monthly_rate, c.daily_rate, c.hourly_rate, c.food_allowance, c.transpo_allowance,
                        f.sss_loan, f.pagibig_loan, f.company_loan, 
                        f.savings_deduction, f.cash_assist_deduction
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

        // Check if a transaction is already active before beginning a new one
        if ($pdo->inTransaction()) {
             $pdo->rollBack(); 
        }
        
        $pdo->beginTransaction();
        $count = 0;

        // --- PREPARE STATEMENTS ---
        $stmt_late = $pdo->prepare("SELECT SUM(8 - num_hr) as total_late_hrs FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end AND num_hr < 8 AND num_hr > 0");
        $stmt_days = $pdo->prepare("SELECT COUNT(DISTINCT date) as days_present FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end AND num_hr > 0");
        $stmt_sss = $pdo->prepare("SELECT total_contribution FROM tbl_sss_standard WHERE :rate >= min_salary AND :rate <= max_salary ORDER BY id DESC LIMIT 1");
        
        // Leave Days Lookup (for total calculation)
        $stmt_leave_days = $pdo->prepare("SELECT SUM(days_count) FROM tbl_leave WHERE employee_id = :eid AND status = 1 AND leave_type NOT LIKE '%Unpaid%' AND start_date BETWEEN :start AND :end");

        // Cash Advance Lookup
        $stmt_ca_check = $pdo->prepare("SELECT SUM(amount) FROM tbl_cash_advances WHERE employee_id = :eid AND status = 'Pending' AND date_requested BETWEEN :start AND :end");
        
        // Attendance Check on a Specific Day (for holiday eligibility)
        $stmt_check_attendance_single = $pdo->prepare("SELECT num_hr FROM tbl_attendance WHERE employee_id = :eid AND date = :check_date LIMIT 1");
        
        // Check for Paid Leave on a Specific Day (for holiday eligibility)
        $stmt_check_leave_single = $pdo->prepare("SELECT days_count FROM tbl_leave WHERE employee_id = :eid AND status = 1 AND leave_type NOT LIKE '%Unpaid%' AND :check_date BETWEEN start_date AND end_date LIMIT 1");
        
        // Fetch base hours (capped) AND the pre-calculated overtime hours
        $stmt_all_work_hours = $pdo->prepare("
            SELECT date, (CASE WHEN num_hr > 8 THEN 8 ELSE num_hr END) as base_hours, overtime_hr
            FROM tbl_attendance 
            WHERE employee_id = :eid 
            AND date BETWEEN :start AND :end
            AND num_hr > 0
        "); 
        
        // Inserts
        $stmt_header = $pdo->prepare("INSERT INTO tbl_payroll (ref_no, employee_id, cut_off_start, cut_off_end, gross_pay, total_deductions, net_pay, status) VALUES (:ref, :empid, :start, :end, :gross, :deduct, :net, 0)");
        $stmt_item = $pdo->prepare("INSERT INTO tbl_payroll_items (payroll_id, item_name, item_type, amount) VALUES (?, ?, ?, ?)");
        
        // --- HELPER CLOSURE FOR ELIGIBILITY (STRICT BEFORE/AFTER) ---
        $check_eligibility = function($current_emp_id, $check_date) use ($stmt_check_attendance_single, $stmt_check_leave_single) {
            
            // 1. Check for attendance
            $stmt_check_attendance_single->execute([
                ':eid' => $current_emp_id, 
                ':check_date' => $check_date
            ]);
            $worked_hours = floatval($stmt_check_attendance_single->fetchColumn() ?: 0);
            $stmt_check_attendance_single->closeCursor();

            if ($worked_hours > 0) return true;

            // 2. Check for approved paid leave
            $stmt_check_leave_single->execute([
                ':eid' => $current_emp_id, 
                ':check_date' => $check_date
            ]);
            $leave_days = floatval($stmt_check_leave_single->fetchColumn() ?: 0);
            $stmt_check_leave_single->closeCursor();

            return $leave_days > 0;
        };
        // --- END HELPER CLOSURE ---


        foreach ($employees as $emp) {
            $emp_id = $emp['employee_id'];
            $monthly_rate = floatval($emp['monthly_rate']);
            $daily_rate   = floatval($emp['daily_rate']);
            
            // 🛑 USE HOURLY RATE FROM DB, FALLBACK TO CALCULATION 🛑
            $hourly_rate  = floatval($emp['hourly_rate'] ?? 0);
            if ($hourly_rate <= 0 && $daily_rate > 0) {
                 $hourly_rate = $daily_rate / 8; 
            }
            if ($hourly_rate <= 0) $hourly_rate = 0.01; // Avoid division by zero issues later

            // --- Premium Pay Accumulators ---
            $total_ot_hours = 0; 
            $regular_ot_premium = 0; 
            $sunday_ot_premium = 0;
            // $holiday_ot_premium = 0; // <<<< REMOVED >>>> (replaced by rh_ot and snwh_ot)
            
            // <<<< UPDATED SECTION: SEPARATED HOLIDAY ACCUMULATORS >>>>
            $rh_worked_pay_premium = 0; // Premium for Regular Holiday worked (Daily Rate + Hourly Premium)
            $snwh_worked_pay_premium = 0; // Premium for Special Non-Working Holiday worked (Hourly Premium only)
            $rh_ot_premium_only = 0;     // Regular Holiday OT Premium (1.6x portion)
            $snwh_ot_premium_only = 0;   // SNWH OT Premium (0.69x portion)

            $premium_day_ot_hours = 0;

            // $worked_holiday_premium_pay = 0; // <<<< REMOVED >>>> (replaced by rh_worked_pay and snwh_worked_pay)
            $regular_holiday_non_work_pay = 0; 
            $sunday_work_premium = 0; 

            // --- Base Pay Accumulators ---
            $total_base_hours_worked = 0; 
            $total_holiday_hours_worked = 0; 
            $total_sunday_hours_worked = 0; 
            $total_regular_hours_worked = 0;

            // --- 1. PRE-CALCULATIONS (Late, Leave, Base Data) ---
            
            $stmt_late->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $total_late_hours = floatval($stmt_late->fetchColumn() ?: 0);
            $late_deduction_amount = $total_late_hours * $hourly_rate;

            $stmt_days->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $days_present = intval($stmt_days->fetchColumn() ?: 0);
            
            $stmt_leave_days->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $total_paid_leave_days = floatval($stmt_leave_days->fetchColumn() ?: 0);
            $paid_leave_amount = $total_paid_leave_days * $daily_rate;


            // --- 2. ATTENDANCE LOOP: Calculate Base Hours, OT, and Premiums PER DAY ---
            
            $stmt_all_work_hours->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $all_work_hours = $stmt_all_work_hours->fetchAll(PDO::FETCH_ASSOC);

            $holiday_lookup = array_column($holidays, 'holiday_type', 'holiday_date');
            $holiday_multiplier_lookup = array_column($holidays, 'payroll_multiplier', 'holiday_date');

            
            foreach($all_work_hours as $record) {
                $day_date = $record['date'];
                $base_hours = floatval($record['base_hours']); 
                $ot_hours = floatval($record['overtime_hr']); 
                $day_of_week = date('N', strtotime($day_date)); 

                $is_sunday = ($day_of_week == 7);
                $is_holiday = isset($holiday_lookup[$day_date]);

                $total_base_hours_worked += $base_hours;
                $total_ot_hours += $ot_hours; 

                $base_pay_for_worked_day = $base_hours * $hourly_rate;

                if ($is_holiday) {
                    $h_type = $holiday_lookup[$day_date];
                    $total_holiday_hours_worked += $base_hours; 
                    
                    if ($ot_hours > 0) $premium_day_ot_hours += $ot_hours; // Accumulate premium OT hours

                    $h_multiplier = floatval($holiday_multiplier_lookup[$day_date]);
                    $hourly_premium = $base_pay_for_worked_day * ($h_multiplier - 1.0); // Calculate 0.3x or 1.0x hourly premium
                    
                    if ($h_type == 'Regular') {
                        // <<<< UPDATED SECTION: RH WORKED PAY ACCUMULATION >>>>
                        // RH Worked Premiums (100% daily fixed + Hourly Premium)
                        $rh_worked_pay_premium += $hourly_premium; // Hourly Premium (1.0x hourly)
                        
                        // RH OT Premium (2.6x total, so 1.6x premium portion)
                        $rh_ot_premium_only += $ot_hours * $hourly_rate * ($RH_OT_PREMIUM_MULTIPLIER - 1.0);
                    } 
                    elseif ($h_type == 'Special Non-Working') {
                        // <<<< UPDATED SECTION: SNWH WORKED PAY ACCUMULATION >>>>
                        // SNWD Worked Premium (1.3x total, so 0.3x premium only)
                        $snwh_worked_pay_premium += $hourly_premium;
                        
                        // SNWD OT Premium (1.69x total, so 0.69x premium portion)
                        $snwh_ot_premium_only += $ot_hours * $hourly_rate * ($SNWD_OT_PREMIUM_MULTIPLIER - 1.0);
                    }
                    // <<<< OLD: REMOVE COMBINED VARIABLE ACCUMULATION >>>>
                    // $worked_holiday_premium_pay += ...
                    // $holiday_ot_premium += ...
                }
                elseif ($is_sunday) {
                    $total_sunday_hours_worked += $base_hours;
                    
                    if ($ot_hours > 0) $premium_day_ot_hours += $ot_hours; // Accumulate premium OT hours

                    // Sunday Premium (1.3x total, so 0.3x premium)
                    $sunday_work_premium += $base_pay_for_worked_day * 0.30;
                    
                    // Sunday OT Premium (1.69x total, so 0.69x premium)
                    $sunday_ot_premium += $ot_hours * $hourly_rate * ($SUNDAY_OT_PREMIUM_MULTIPLIER - 1.0);
                } 
                else {
                    $total_regular_hours_worked += $base_hours;
                    
                    // Regular Day OT Premium (1.25x total, so 0.25x premium)
                    $regular_ot_premium += $ot_hours * $hourly_rate * ($REGULAR_OT_MULTIPLIER - 1.0);
                }
            }
            
            // 🛑 Consolidation 🛑
            // <<<< UPDATED SECTION: CONSOLIDATING OT PREMIUMS >>>>
            $total_ot_premium_pay = $regular_ot_premium + $sunday_ot_premium + $rh_ot_premium_only + $snwh_ot_premium_only;


            // 🛑 D. Calculate Non-Worked Regular Holiday Pay (If Eligible) 🛑
            foreach ($holidays as $holiday) {
                $h_date = $holiday['holiday_date'];
                $h_type = $holiday['holiday_type'];
                
                if ($h_type != 'Regular') continue; 

                // Check attendance on the holiday itself (must be 0 for non-worked pay)
                $stmt_check_attendance_single->execute([':eid' => $emp_id, ':check_date' => $h_date]);
                $work_hours_on_hday = floatval($stmt_check_attendance_single->fetchColumn() ?: 0);
                $stmt_check_attendance_single->closeCursor();
                if ($work_hours_on_hday > 0) continue; 
                
                
                // 🛑 STRICT ELIGIBILITY CHECK APPLIED (RH ONLY) 🛑
                $prev_day = date('Y-m-d', strtotime($h_date . ' - 1 day'));
                $next_day = date('Y-m-d', strtotime($h_date . ' + 1 day'));

                $preceding_day_covered = $check_eligibility($emp_id, $prev_day);
                $succeeding_day_covered = $check_eligibility($emp_id, $next_day);

                // If NOT covered on the day before OR NOT covered on the day after, the employee is INELIGIBLE.
                if ($preceding_day_covered && $succeeding_day_covered) {
                    $regular_holiday_non_work_pay += $daily_rate;
                }
            }
            
            // <<<< UPDATED SECTION: CONSOLIDATING WORKED PAY >>>>
            $total_holiday_pay_premium_and_non_worked = $rh_worked_pay_premium + $snwh_worked_pay_premium + $regular_holiday_non_work_pay;
            

            // 🛑 BASE PAY (1.0x Compensation) 🛑
            $total_base_pay = ($total_base_hours_worked * $hourly_rate) + 
                              $regular_holiday_non_work_pay + 
                              $paid_leave_amount; 

            // --- 2. FINAL EARNINGS ---
            $food_allow = ($payroll_type == 'semi-monthly') ? floatval($emp['food_allowance']) / 2 : floatval($emp['food_allowance']);
            $transpo_allow = ($payroll_type == 'semi-monthly') ? floatval($emp['transpo_allowance']) / 2 : floatval($emp['transpo_allowance']);
            
            // CONSOLIDATE ALLOWANCES
            $total_allowances = $food_allow + $transpo_allow;

            // <<<< UPDATED SECTION: GROSS PAY FORMULA >>>>
            // Gross Pay = Total Base Pay (1.0x) + Allowances + Total OT Premium + Sunday Premium + Holiday Worked Premiums (RH + SNWH)
            $gross_pay = $total_base_pay + 
                         $total_allowances + 
                         $total_ot_premium_pay + 
                         $sunday_work_premium + 
                         $rh_worked_pay_premium + 
                         $snwh_worked_pay_premium;


            // --- 3. DEDUCTIONS (GOVT & LOANS) ---
            $divider = ($payroll_type == 'semi-monthly') ? 2 : 1;

            // A. SSS (Database)
            $stmt_sss->execute([':rate' => $monthly_rate]);
            $sss_row = $stmt_sss->fetch(PDO::FETCH_ASSOC);
            $sss_full_amount = $sss_row ? floatval($sss_row['total_contribution']) : 0;
            $sss_contrib = $sss_full_amount / $divider;

            // B. PAG-IBIG (Days Present)
            $pagibig_contrib = ($days_present <= 15) ? 100.00 : 200.00;

            // C. PHILHEALTH (60k Cap)
            $ph_basis = $monthly_rate;
            if ($ph_basis >= 60000) {
                $ph_employee_monthly_share = 900.00; 
            } else {
                $ph_employee_monthly_share = ($ph_basis * 0.03) / 2;
            }
            $philhealth_deduct = $ph_employee_monthly_share / $divider;

            // D. CASH ADVANCE (From new table)
            $stmt_ca_check->execute([':eid' => $emp_id, ':start' => $start_date, ':end' => $end_date]);
            $cash_advance = floatval($stmt_ca_check->fetchColumn() ?: 0);


            // =========================================================
            // E. WITHHOLDING TAX CALCULATION 
            // =========================================================
            
            // Taxable income = Gross Pay - Allowances - Govt Deductions 
            $total_govt_deductions = $sss_contrib + $pagibig_contrib + $philhealth_deduct + $late_deduction_amount;
            $taxable_income = $gross_pay - ($total_allowances) - $total_govt_deductions;
            
            $tax_deduction = 0.00;

            if ($taxable_income > 10417) {
                $excess = $taxable_income - 10417;
                $tax_deduction = $excess * 0.15;
            }
            else {
                $tax_deduction = 0.00;
            }
            // =========================================================


            // F. Other Fixed Deductions (From financials table)
            $sss_loan     = floatval($emp['sss_loan'] ?? 0);
            $pagibig_loan = floatval($emp['pagibig_loan'] ?? 0);
            $company_loan = floatval($emp['company_loan'] ?? 0);
            $savings      = floatval($emp['savings_deduction'] ?? 0);
            $cash_assist  = floatval($emp['cash_assist_deduction'] ?? 0); 


            // TOTAL DEDUCTIONS
            $total_deductions = $total_govt_deductions + 
                                $sss_loan + $pagibig_loan + $company_loan + 
                                $savings + $cash_advance + $cash_assist + $tax_deduction;

            // --- 4. NET PAY ---
            $net_pay = $gross_pay - $total_deductions;

            // --- 5. INSERT HEADER ---
            $ref_no = 'LOSI-' . date('Ymd') . '-' . $emp_id; 
            $stmt_header->execute([':ref'=>$ref_no, ':empid'=>$emp_id, ':start'=>$start_date, ':end'=>$end_date, ':gross'=>$gross_pay, ':deduct'=>$total_deductions, ':net'=>$net_pay]);
            $payroll_id = $pdo->lastInsertId();
            
            // --- 6. INSERT ITEMS ---
            // <<<< UPDATED SECTION: CORRECTED ITEMIZATION >>>>

            $total_worked_base_pay = $total_base_hours_worked * $hourly_rate; // 1.0x of all worked hours

            $items = [
                // Earnings
                // Basic Pay now correctly reflects the 1.0x rate for all standard worked hours
                ['name' => 'Basic Pay (' . number_format($total_base_hours_worked, 2) . ' hrs)', 'type' => 'earning', 'amount' => $total_worked_base_pay],
                
                ['name' => 'Leave Pay (' . number_format($total_paid_leave_days, 1) . ' day/s)', 'type' => 'earning', 'amount' => $paid_leave_amount],
                
                // Worked Holiday Premiums (Non-1.0x portions)
                ['name' => 'Regular Holiday Worked Premium', 'type' => 'earning', 'amount' => $rh_worked_pay_premium],
                ['name' => 'SNW Holiday Worked Premium', 'type' => 'earning', 'amount' => $snwh_worked_pay_premium],
                
                // Non-Worked Pay (RH only)
                ['name' => 'Regular Holiday Non-Worked Pay (' . number_format($regular_holiday_non_work_pay / $daily_rate, 1) . ' day/s)', 'type' => 'earning', 'amount' => $regular_holiday_non_work_pay],
                
                // Sunday Premium
                ['name' => 'Sunday Work Premium Pay', 'type' => 'earning', 'amount' => $sunday_work_premium],
                
                // Overtime Premiums (PREMIUM ONLY)
                ['name' => 'Regular OT Premium (' . number_format($total_ot_hours - $premium_day_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $regular_ot_premium],
                ['name' => 'RH OT Premium (' . number_format($premium_day_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $rh_ot_premium_only],
                ['name' => 'SNWH OT Premium (' . number_format($premium_day_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $snwh_ot_premium_only],
                ['name' => 'Sunday OT Premium (' . number_format($premium_day_ot_hours, 2) . ' hr/s)', 'type' => 'earning', 'amount' => $sunday_ot_premium],

                // Allowances 
                ['name' => 'Allowances', 'type' => 'earning', 'amount' => $total_allowances],

                // Deductions
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
            $count++;
        }

        $pdo->commit();
        $_SESSION['status'] = "Generated payroll for $count employees.";
        $_SESSION['status_code'] = "success";
        $_SESSION['status_title'] = "Success";
        header("Location: ../payroll.php");
        exit();

    } catch (Exception $e) {
        // Added safety check for rollback
        if ($pdo->inTransaction()) {
             $pdo->rollBack();
        }
        
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