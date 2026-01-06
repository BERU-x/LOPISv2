<?php
// functions/create_payroll.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../../db_connection.php'; 
date_default_timezone_set('Asia/Manila');

$payroll_type = $_REQUEST['payroll_type'] ?? 'semi-monthly'; 
$start_date   = $_REQUEST['start_date'] ?? '';
$end_date     = $_REQUEST['end_date'] ?? '';
$target_emp   = $_REQUEST['employee_id'] ?? 'all';

try {
    if (empty($start_date) || empty($end_date)) throw new Exception("Date range missing.");

    // --- 1. FETCH DYNAMIC PAY COMPONENTS ---
    $stmt_comp = $pdo->query("SELECT name, type, is_taxable, is_recurring FROM tbl_pay_components");
    $pay_components = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. VERIFY EMPLOYEE ---
    $check_sql = "SELECT employee_id, employment_status FROM tbl_employees WHERE employee_id = ?";
    $stmt_check = $pdo->prepare($check_sql);
    $stmt_check->execute([$target_emp]);
    $emp_raw = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$emp_raw) throw new Exception("Employee ID $target_emp not found.");

    // --- 3. DATA LOOKUP PREPARATION ---
    $stmt_h = $pdo->prepare("SELECT holiday_date, holiday_type, payroll_multiplier FROM tbl_holidays WHERE holiday_date BETWEEN ? AND ?");
    $stmt_h->execute([$start_date, $end_date]);
    $holiday_lookup = $stmt_h->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    $sql_emp = "SELECT e.employee_id, e.firstname, e.lastname, c.* FROM tbl_employees e 
                JOIN tbl_compensation c ON e.employee_id = c.employee_id 
                WHERE e.employee_id = ? AND e.employment_status < 5";
    $stmt_main = $pdo->prepare($sql_emp);
    $stmt_main->execute([$target_emp]);
    $employees = $stmt_main->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    // Prepared Statements for Attendance, OT, Tax, and Financials
    $stmt_att = $pdo->prepare("SELECT date, num_hr, total_deduction_hr FROM tbl_attendance WHERE employee_id = :eid AND date BETWEEN :start AND :end");
    $stmt_ot  = $pdo->prepare("SELECT ot_date, hours_approved FROM tbl_overtime WHERE employee_id = :eid AND ot_date BETWEEN :start AND :end AND status = 'Approved'");
    $stmt_sss = $pdo->prepare("SELECT total_contribution FROM tbl_sss_standard WHERE :rate >= min_salary AND :rate <= max_salary LIMIT 1");
    $stmt_tax = $pdo->prepare("SELECT * FROM tbl_tax_table WHERE :income >= min_income AND (:income <= max_income OR max_income IS NULL) LIMIT 1");
    
    // ⭐ Financial Records Statement
    $stmt_fin = $pdo->prepare("SELECT * FROM tbl_financial_records WHERE employee_id = ?");

    foreach ($employees as $emp) {
        $eid = $emp['employee_id'];
        $monthly_rate = floatval($emp['monthly_rate']);
        $daily_rate   = floatval($emp['daily_rate']);
        $hourly_rate  = floatval($emp['hourly_rate'] ?: ($daily_rate / 8));
        $divider      = ($payroll_type === 'semi-monthly') ? 2 : 1;

        // --- A. Attendance & OT Metrics ---
        $stmt_att->execute([':eid' => $eid, ':start' => $start_date, ':end' => $end_date]);
        $logs = $stmt_att->fetchAll(PDO::FETCH_ASSOC);
        $stmt_ot->execute([':eid' => $eid, ':start' => $start_date, ':end' => $end_date]);
        $approved_ot = $stmt_ot->fetchAll(PDO::FETCH_KEY_PAIR);

        $days_worked = 0; $late_undertime_total = 0; $holiday_pay = 0; $ot_pay = 0;
        foreach ($logs as $log) {
            $curr_date = $log['date'];
            if (floatval($log['num_hr']) > 0) $days_worked++;
            $late_undertime_total += (floatval($log['total_deduction_hr']) * $hourly_rate);
            if (isset($holiday_lookup[$curr_date])) {
                $h = $holiday_lookup[$curr_date];
                $holiday_pay += (floatval($log['num_hr']) * $hourly_rate * ($h['payroll_multiplier'] - 1));
            }
            if (isset($approved_ot[$curr_date])) {
                $ot_multiplier = isset($holiday_lookup[$curr_date]) ? 2.6 : 1.25;
                $ot_pay += (floatval($approved_ot[$curr_date]) * $hourly_rate * $ot_multiplier);
            }
        }

        // --- B. Categorize Items ---
        $payroll_items = [];
        $total_earnings = 0; $total_deductions = 0; $taxable_income = 0;

        // 1. Basic Pay
        $basic_pay = $days_worked * $daily_rate;
        $payroll_items[] = ['name' => 'Basic Pay', 'type' => 'earning', 'amt' => $basic_pay];
        $total_earnings += $basic_pay; $taxable_income += $basic_pay;

        // 2. Overtime & Holiday
        if ($ot_pay > 0) { $payroll_items[] = ['name' => 'Overtime Pay', 'type' => 'earning', 'amt' => $ot_pay]; $total_earnings += $ot_pay; $taxable_income += $ot_pay; }
        if ($holiday_pay > 0) { $payroll_items[] = ['name' => 'Holiday Pay', 'type' => 'earning', 'amt' => $holiday_pay]; $total_earnings += $holiday_pay; $taxable_income += $holiday_pay; }

        // 3. Dynamic Pay Components (Statutory & Allowances)
        foreach ($pay_components as $comp) {
            $amt = 0; $name = $comp['name'];
            if ($name == 'SSS Premium') {
                $stmt_sss->execute([':rate' => $monthly_rate]);
                $amt = (floatval($stmt_sss->fetchColumn()) ?: 0) / $divider;
            } elseif ($name == 'PhilHealth') {
                $amt = ($monthly_rate >= 60000 ? 900 : ($monthly_rate * 0.03 / 2)) / $divider;
            } elseif ($name == 'Pag-IBIG') {
                $amt = (($days_worked <= 15) ? 100 : 200) / $divider;
            } elseif ($name == 'Food Allowance' || $name == 'Transpo Allowance') {
                $key = ($name == 'Food Allowance') ? 'food_allowance' : 'transpo_allowance';
                $amt = floatval($emp[$key]) / $divider;
            }

            if ($amt > 0) {
                $payroll_items[] = ['name' => $name, 'type' => $comp['type'], 'amt' => $amt];
                if ($comp['type'] == 'earning') { $total_earnings += $amt; if ($comp['is_taxable']) $taxable_income += $amt; }
                else { $total_deductions += $amt; if (in_array($name, ['SSS Premium', 'PhilHealth', 'Pag-IBIG'])) $taxable_income -= $amt; }
            }
        }

        // 4. ⭐ LOAN DEDUCTIONS (from tbl_financial_records)
        $stmt_fin->execute([$eid]);
        $fin = $stmt_fin->fetch(PDO::FETCH_ASSOC);
        if ($fin) {
            $loan_map = [
                'sss' => 'SSS Loan', 
                'pagibig' => 'Pag-IBIG Loan', 
                'company' => 'Company Loan', 
                'cash' => 'Cash Assist'
            ];
            foreach ($loan_map as $key => $label) {
                if (floatval($fin[$key.'_bal']) > 0) {
                    $period_amort = floatval($fin[$key.'_amort']) / $divider;
                    $deduction = min(floatval($fin[$key.'_bal']), $period_amort);
                    if ($deduction > 0) {
                        $payroll_items[] = ['name' => $label, 'type' => 'deduction', 'amt' => $deduction];
                        $total_deductions += $deduction;
                    }
                }
            }
            // Savings Contribution
            if (floatval($fin['savings_contrib']) > 0) {
                $s_contrib = floatval($fin['savings_contrib']) / $divider;
                $payroll_items[] = ['name' => 'Savings', 'type' => 'deduction', 'amt' => $s_contrib];
                $total_deductions += $s_contrib;
            }
        }

        // 5. Late/Undertime
        if ($late_undertime_total > 0) {
            $payroll_items[] = ['name' => 'Late/Undertime', 'type' => 'deduction', 'amt' => $late_undertime_total];
            $total_deductions += $late_undertime_total;
        }

        // --- C. Tax & Final Net ---
        $stmt_tax->execute([':income' => $taxable_income]);
        $t = $stmt_tax->fetch(PDO::FETCH_ASSOC);
        $tax_amt = $t ? (floatval($t['base_tax']) + (($taxable_income - floatval($t['min_income'])) * (floatval($t['excess_rate'])/100))) : 0;
        if ($tax_amt > 0) { $payroll_items[] = ['name' => 'Withholding Tax', 'type' => 'deduction', 'amt' => $tax_amt]; $total_deductions += $tax_amt; }

        $net_pay = $total_earnings - $total_deductions;

        // --- D. Insert to Database ---
        $stmt_h = $pdo->prepare("INSERT INTO tbl_payroll (ref_no, employee_id, cut_off_start, cut_off_end, gross_pay, total_deductions, net_pay, status) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt_h->execute(['LOSI-'.date('YmdH').'-'.$eid, $eid, $start_date, $end_date, $total_earnings, $total_deductions, $net_pay]);
        $pid = $pdo->lastInsertId();

        $stmt_i = $pdo->prepare("INSERT INTO tbl_payroll_items (payroll_id, item_name, item_type, amount) VALUES (?, ?, ?, ?)");
        foreach ($payroll_items as $pi) { $stmt_i->execute([$pid, $pi['name'], $pi['type'], $pi['amt']]); }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "Payroll updated with loans.", 'new_id' => $pid ?? 0]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}