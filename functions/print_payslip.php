<?php
// functions/print_payslip.php

// --- 1. START SESSION (To get User Type) ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- FIX: Use 'usertype' (no underscore) to match checking.php ---
// Default to 0 (Superadmin/Standard View) if not set
$user_type = $_SESSION['usertype'] ?? 0;

date_default_timezone_set('Asia/Manila');

// Adjust path relative to where this file is located (functions/)
require '../db_connection.php'; 
require '../vendor/autoload.php'; 

// --- LOAD PHPQRCODE LIBRARY ---
require_once '../assets/libs/phpqrcode/qrlib.php'; 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No Payroll ID provided.");
}

$payroll_id = $_GET['id'];
$output_dest = (isset($_GET['action']) && $_GET['action'] == 'download') ? 'D' : 'I';

// ==========================================
// 0. FONT SETUP
// ==========================================
$font_folder = __DIR__ . '/../assets/fonts/';
$font_path_reg  = $font_folder . 'centurygothic.ttf';     
$font_path_bold = $font_folder . 'centurygothic_bold.ttf'; 

// Default fallback
$font_reg_name  = 'helvetica';
$font_bold_name = 'helvetica'; 

if (file_exists($font_path_reg)) {
    $font_reg_name = TCPDF_FONTS::addTTFfont($font_path_reg, 'TrueTypeUnicode', '', 96);
    if (file_exists($font_path_bold)) {
        $font_bold_name = TCPDF_FONTS::addTTFfont($font_path_bold, 'TrueTypeUnicode', '', 96);
    } else {
        $font_bold_name = $font_reg_name; 
    }
}

// ==========================================
// HELPER: NUMBER TO WORDS
// ==========================================
function numberToWords($number) {
    $prefix = '';
    if ($number < 0) {
        $prefix = 'Negative ';
        $number = abs($number); 
    }

    $number = number_format($number, 2, '.', '');
    list($whole, $fraction) = explode('.', $number);

    $dictionary  = array(
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    );

    $convert_chunk = function ($num) use (&$convert_chunk, $dictionary) {
        $output = '';
        if ($num >= 1000000000) {
            $output .= $convert_chunk(floor($num / 1000000000)) . ' Billion ';
            $num %= 1000000000;
        }
        if ($num >= 1000000) {
            $output .= $convert_chunk(floor($num / 1000000)) . ' Million ';
            $num %= 1000000;
        }
        if ($num >= 1000) {
            $output .= $convert_chunk(floor($num / 1000)) . ' Thousand ';
            $num %= 1000;
        }
        if ($num >= 100) {
            $output .= $dictionary[floor($num / 100)] . ' Hundred ';
            $num %= 100;
        }
        if ($num >= 20) {
            $output .= $dictionary[floor($num / 10) * 10];
            $num %= 10;
            if ($num > 0) $output .= '-';
        }
        if ($num >= 1) {
            $output .= $dictionary[$num];
        }
        return trim(str_replace('  ', ' ', $output));
    };

    $whole_words = $convert_chunk(intval($whole));
    if (intval($whole) == 0) {
        $whole_words = 'Zero';
    }

    $final_words = $prefix . $whole_words . ' Pesos';
    $fraction_int = intval(str_pad($fraction, 2, '0', STR_PAD_RIGHT));

    if ($fraction_int > 0) {
        $fraction_words = $convert_chunk($fraction_int);
        $final_words .= ' and ' . $fraction_words . ' Centavos';
    } else {
        $final_words .= ' Only';
    }

    return ucwords(trim($final_words));
}

try {
    // ==========================================
    // 1. FETCH PAYROLL DATA
    // ==========================================
    $stmt = $pdo->prepare("
        SELECT 
            p.employee_id, p.cut_off_start, p.cut_off_end, p.gross_pay, p.total_deductions, p.net_pay,
            e.employee_id as emp_code, e.firstname, e.lastname, e.department,
            e.position as designation, 
            c.daily_rate, c.monthly_rate
        FROM tbl_payroll p
        JOIN tbl_employees e ON p.employee_id = e.employee_id
        LEFT JOIN tbl_compensation c ON e.employee_id = c.employee_id
        WHERE p.id = :pid
    ");
    $stmt->execute([':pid' => $payroll_id]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) die("Error: Payroll record not found.");

    $pay_period = date('M d', strtotime($header['cut_off_start'])) . ' - ' . date('M d, Y', strtotime($header['cut_off_end']));
    $full_name = $header['firstname'] . ' ' . $header['lastname'];
    
    $annual_salary = floatval($header['monthly_rate']) * 12;
    $daily_rate = floatval($header['daily_rate']);
    
    // --- FETCH ITEMS ---
    $stmt_items = $pdo->prepare("SELECT item_name, item_type, amount FROM tbl_payroll_items WHERE payroll_id = :pid");
    $stmt_items->execute([':pid' => $payroll_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $earnings = [];
    $deductions = [];
    $deduction_lookup = []; 
    $days_present = 0; 

    foreach ($items as $item) {
        if ($item['item_type'] == 'earning') {
            $clean_name = preg_replace('/(\d+)\.0 day\/s/', '$1 day/s', $item['item_name']);
            $earnings[] = [$clean_name, floatval($item['amount'])];
            
            if (strpos($item['item_name'], 'Basic Pay') !== false) {
                if (preg_match('/\((\d+(\.\d+)?) day\/s\)/', $item['item_name'], $matches)) {
                    $days_present = $matches[1];
                }
            }
        } elseif ($item['item_type'] == 'deduction') {
            $deductions[] = [$item['item_name'], floatval($item['amount'])];
            $deduction_lookup[$item['item_name']] = floatval($item['amount']);
        }
    }

    $days_present_display = (0 + $days_present); 
    $total_earn = array_sum(array_column($earnings, 1));
    $total_ded  = array_sum(array_column($deductions, 1));
    $net_pay    = $total_earn - $total_ded;
    $amount_in_words = numberToWords($net_pay);

    // ==========================================
    // 2. FETCH LEDGER BALANCES
    // ==========================================
    $stmt_ledger = $pdo->prepare("
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
    $stmt_ledger->execute([':eid' => $header['employee_id']]);
    $ledger_balances = $stmt_ledger->fetchAll(PDO::FETCH_KEY_PAIR);

    // ==========================================
    // 3. IMAGES & QR
    // ==========================================
    $company_name = 'Lendell Outsourcing Solutions, Inc.';
    
    $image_path = __DIR__ . '/../../assets/images/LOPISv2_payslip.jpg'; 
    $png_path = __DIR__ . '/../../assets/images/LOPISv2.png'; 
    if (!file_exists($image_path) && file_exists($png_path)) $image_path = $png_path;
    
    $img_html = '';
    if (file_exists($image_path)) {
        $type = pathinfo($image_path, PATHINFO_EXTENSION);
        $data = file_get_contents($image_path);
        $logo_src = 'data:image/' . $type . ';base64,' . base64_encode($data);
        $img_html = '<img src="'.$logo_src.'" height="40" />'; 
    }

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_path = $_SERVER['PHP_SELF']; 
    $base_url = $protocol . "://" . $host . $script_path;
    $download_link = $base_url . "?id=" . $payroll_id . "&action=download";

    ob_start();
    QRcode::png($download_link, null, QR_ECLEVEL_L, 3, 1);
    $qr_image_string = ob_get_contents();
    ob_end_clean();
    $qr_base64 = 'data:image/png;base64,' . base64_encode($qr_image_string);
    $qr_html = '<img src="'.$qr_base64.'" height="40" />';

    // ==========================================
    // 4. DESIGN & LAYOUT
    // ==========================================
    $css = '
    <style>
        .payslip-border-box {
            border: 2px solid #000000;
            color: #333; 
            font-family: '.$font_reg_name.'; 
            font-size: 4pt; 
            line-height: 2;
        }
        .header-txt { 
            font-family: '.$font_bold_name.'; 
            color: #000; 
            font-size: 5pt; 
        }
        .label { 
            font-family: '.$font_bold_name.'; 
            font-size: 5pt; 
            color: #000; 
            background-color: #f0f0f0; 
        } 
        .tbl-head { 
            background-color: #eee; 
            font-family: '.$font_bold_name.'; 
            font-size: 5.5pt; 
            border-bottom: 1px solid #000; 
        }
        .net-pay-box { 
            background-color: #ddd; 
            font-family: '.$font_bold_name.'; 
            font-size: 5pt; 
            color: #000; 
            border: 1px solid #000; 
        }
        .loan-head { 
            background-color: #f9f9f9; 
            font-family: '.$font_bold_name.'; 
            font-size: 5.5pt; 
            border-bottom: 1px solid #ccc; 
        }
        b, strong { font-family: '.$font_bold_name.'; font-weight: normal; }
        .sub-header { font-size: 6.5pt; color: #555; }
        .data { font-size: 5pt; color: #000; }
        .footer { font-size: 3.5pt; color: #777; text-align: center; }
        .amount-words { font-style: italic; font-size: 5.5pt; color: #444; }
        .summary-row { background-color: #f9f9f9; font-family: '.$font_bold_name.'; }
    </style>';

    function buildRows($items) {
        $html = '';
        foreach($items as $item) {
            $html .= '<tr>
                        <td style="border-bottom: 0.5px solid #eee; padding-top: 2px; padding-bottom: 2px;">'.$item[0].'</td>
                        <td align="right" style="border-bottom: 0.5px solid #eee; padding-top: 2px; padding-bottom: 2px;">'.number_format($item[1], 2).'</td>
                      </tr>';
        }
        return $html;
    }

    $rows_earn = buildRows($earnings);
    $rows_ded = buildRows($deductions);
    
    // --- Build Account Balance Rows ---
    $get_cur_deduction = function($keyword) use ($deductions) {
        foreach($deductions as $d) {
            if (stripos($d[0], $keyword) !== false) return $d[1];
        }
        return 0;
    };

    $account_map = [
        'SSS_Loan'     => 'SSS Loan',
        'Pagibig_Loan' => 'Pag-IBIG Loan',
        'Company_Loan' => 'Company Loan',
        'Cash_Assist'  => 'Cash Assistance',
        'Savings'      => 'Company Savings'
    ];

    $rows_loans = '';
    $has_loans = false;

    foreach ($account_map as $db_cat => $label) {
        $ledger_bal = floatval($ledger_balances[$db_cat] ?? 0);
        $cur_ded = $get_cur_deduction($label);
        
        if ($db_cat === 'Savings') {
            $rem_bal = $ledger_bal + $cur_ded;
            $row_label = "Total Savings";
        } else {
            $rem_bal = $ledger_bal - $cur_ded;
            $row_label = "$label Balance";
        }

        if ($ledger_bal > 0 || $cur_ded > 0) {
            $rows_loans .= '<tr>
                                <td style="border-bottom:0.5px solid #eee;">'.$row_label.'</td>
                                <td align="right" style="border-bottom:0.5px solid #eee;">PHP '.number_format($rem_bal, 2).'</td>
                            </tr>';
            $has_loans = true;
        }
    }
    
    if (!$has_loans) {
        $rows_loans = '<tr><td colspan="2" align="center" style="color:#999;">- No Active Accounts -</td></tr>';
    }

    // PAYSLIP BODY HTML (Reused for both layouts)
    $payslip_body = '
    <table class="payslip-border-box" width="100%" cellpadding="8" cellspacing="0">
        <tr>
            <td>
                <table width="100%" cellpadding="0" border="0">
                    <tr>
                        <td width="23%" align="center" valign="middle">'.$img_html.'</td>
                        <td width="52%" align="center" valign="middle">
                            <div class="header-txt">'.$company_name.'</div>
                            <div style="font-size: 8pt; margin-top: 3px;" class="header-txt">OFFICIAL SALARY SLIP</div>
                        </td>
                        <td width="25%" align="center" valign="middle">'.$qr_html.'</td>
                    </tr>
                </table>
                <div style="height: 5px;"></div>
                <table width="100%" cellpadding="3" border="1" style="border-collapse: collapse; border-color: #000;">
                    <tr>
                        <td width="18%" class="label">Employee:</td>
                        <td width="35%" class="data">'.$full_name.'</td>
                        <td width="20%" class="label">Pay Period:</td>
                        <td width="27%" class="data">'.$pay_period.'</td>
                    </tr>
                    <tr>
                        <td class="label">ID No:</td>
                        <td width="35%" class="data">'.$header['emp_code'].'</td>
                        <td class="label">Role:</td>
                        <td width="27%" class="data">'.$header['designation'].'</td>
                    </tr>
                    <tr>
                        <td class="label">Dept:</td>
                        <td width="35%" class="data">'.$header['department'].'</td>
                        <td class="label">Days Present:</td>
                        <td width="27%" class="data">'.$days_present_display.' day/s</td>
                    </tr>
                    <tr>
                        <td class="label">Daily Rate:</td>
                        <td width="35%" class="data">PHP '.number_format($daily_rate, 2).'</td>
                        <td class="label">Annual Salary:</td>
                        <td width="27%" class="data">PHP '.number_format($annual_salary, 2).'</td>
                    </tr>
                </table>
                <div style="height: 5px;"></div>
                <table width="100%" cellpadding="2" border="1" style="border-color: #ccc;">
                    <tr class="tbl-head">
                        <th width="50%" align="center">EARNINGS</th>
                        <th width="50%" align="center">DEDUCTIONS</th>
                    </tr>
                    <tr>
                        <td width="50%" valign="top"><table width="100%" cellpadding="1" border="0">'.$rows_earn.'</table></td>
                        <td width="50%" valign="top"><table width="100%" cellpadding="1" border="0">'.$rows_ded.'</table></td>
                    </tr>
                    <tr class="summary-row">
                        <td align="right">Gross Pay: '.number_format($total_earn, 2).'</td>
                        <td align="right">Total Deductions: '.number_format($total_ded, 2).'</td>
                    </tr>
                </table>
                <div style="height: 5px;"></div> 
                <table width="100%" cellpadding="4" border="0">
                    <tr class="net-pay-box">
                        <td width="60%">NET PAYABLE</td>
                        <td width="40%" align="right">PHP '.number_format($net_pay, 2).'</td>
                    </tr>
                </table>
                <div style="height: 2px;"></div>
                <div class="amount-words" align="center">"'.$amount_in_words.'"</div>
                <div style="height: 8px;"></div>
                <table width="100%" cellpadding="2" border="1" style="border-color: #ccc;">
                     <tr class="loan-head"><th colspan="2" align="center">FINANCIAL ACCOUNTS SUMMARY</th></tr>
                     '.$rows_loans.'
                </table>
                <div style="height: 15px;"></div>
                <table width="100%" cellpadding="1" border="0">
                    <tr>
                        <td width="10%"></td><td width="35%" style="border-bottom: 1px solid #000;"></td><td width="10%"></td><td width="35%" style="border-bottom: 1px solid #000;"></td><td width="10%"></td>
                    </tr>
                    <tr>
                        <td></td><td align="center" style="font-size:5.5pt;">Conrado Villena</td><td></td><td align="center" style="font-size:5.5pt;">'.$full_name.'</td><td></td>
                    </tr>
                </table>
                <div class="footer">Generated on '.date('Y-m-d H:i').'</div>
            </td>
        </tr>
    </table>';

    // ==========================================
    // 6. PDF GENERATION
    // ==========================================
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetTitle('Payslip - ' . $header['emp_code']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    $top_margin = 40; 
    $pdf->SetMargins(10, $top_margin, 10); 
    $pdf->SetAutoPageBreak(FALSE, 0); 
    
    $pdf->AddPage();

    // Set Default Font
    $pdf->SetFont($font_reg_name, '', 7);

    // --- WATERMARK LOGIC (Based on User Type) ---
    $wm_text = 'CONFIDENTIAL';
    $pdf->SetFont($font_bold_name, '', 30); 
    $pdf->SetTextColor(220, 220, 220);
    $pdf->SetAlpha(0.2);

    $text_width = $pdf->GetStringWidth($wm_text);
    $half_width = $text_width / 2;
    $center_y = 105;

    // IF EMPLOYEE (2): Single Watermark in Center
    if ($user_type == 2) {
        $center_x = 148.5; // Middle of A4 Landscape
        $pdf->StartTransform();
        $pdf->Rotate(45, $center_x, $center_y);
        $pdf->Text($center_x - $half_width, $center_y, $wm_text);
        $pdf->StopTransform();
    } 
    // IF ADMIN (0/1): Double Watermark
    else {
        $left_center_x = 74.25;
        $right_center_x = 222.75;

        $pdf->StartTransform();
        $pdf->Rotate(45, $left_center_x, $center_y);
        $pdf->Text($left_center_x - $half_width, $center_y, $wm_text);
        $pdf->StopTransform();

        $pdf->StartTransform();
        $pdf->Rotate(45, $right_center_x, $center_y);
        $pdf->Text($right_center_x - $half_width, $center_y, $wm_text);
        $pdf->StopTransform();
    }

    $pdf->SetAlpha(1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(10, $top_margin); 

// --- FINAL LAYOUT LOGIC (Based on User Type) ---
    if ($user_type == 2) {
        // === SINGLE COPY (CENTERED USING SPACERS) ===
        // We use a 3-column layout to force the content to the middle.
        // 27.5% Left + 45% Content + 27.5% Right = 100% width
        $layout = $css . '
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td width="27.5%"></td>
                <td width="45%">
                    '.$payslip_body.'
                </td>
                <td width="27.5%"></td>
            </tr>
        </table>';
    } else {
        // === DOUBLE COPY (SIDE-BY-SIDE) ===
        // Admin View: Two 50% columns
        $layout = $css . '
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td width="50%" align="center" valign="top">
                    <table width="85%" align="center" cellpadding="0" cellspacing="0" border="0">
                        <tr><td>'.$payslip_body.'</td></tr>
                    </table>
                </td>
                
                <td width="50%" align="center" valign="top" style="border-left: 1px dashed #999;">
                    <table width="85%" align="center" cellpadding="0" cellspacing="0" border="0">
                        <tr><td>'.$payslip_body.'</td></tr>
                    </table>
                </td>
            </tr>
        </table>';
    }

    $pdf->writeHTML($layout, true, false, true, false, '');
    $pdf->Output('LOSI_payslip_'.$header['emp_code'].'_'.date('Y-m-d').'.pdf', $output_dest);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>