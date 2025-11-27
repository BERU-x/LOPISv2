<?php
// fetch/get_leave_details.php
// Handles AJAX request from leave_management.php to fetch details and balance for a specific leave ID.

// --- Configuration & Database Connection ---
require_once '../../db_connection.php'; 
require '../models/leave_model.php'; // This file should contain get_leave_balance()

header('Content-Type: application/json');
$response = ['success' => false, 'details' => null, 'balance_html' => null, 'message' => ''];

// Check for required POST data
if (!isset($_POST['leave_id']) || !isset($_POST['employee_id'])) {
    $response['message'] = 'Missing required parameters.';
    echo json_encode($response);
    exit;
}

$leave_id = (int)$_POST['leave_id'];
$employee_id = trim($_POST['employee_id']);

try {
    // 1. Fetch Leave Details and Employee Info
    $sql = "
        SELECT 
            l.id as leave_id, l.leave_type, l.start_date, l.end_date, l.days_count, 
            l.reason, l.status, l.created_on as date_filed, 
            e.employee_id, e.firstname, e.lastname, e.photo, e.department
        FROM tbl_leave l
        JOIN tbl_employees e ON l.employee_id = e.employee_id
        WHERE l.id = :leave_id
        AND l.employee_id = :employee_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':leave_id' => $leave_id, ':employee_id' => $employee_id]);
    $leave_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave_details) {
        $response['message'] = 'Leave record not found.';
        echo json_encode($response);
        exit;
    }

    // 2. Fetch Employee Leave Balances
    // This assumes your get_leave_balance function fetches all types:
    // e.g., ['Sick Leave' => ['max' => 10, 'used' => 2, 'remaining' => 8], ...]
    if (function_exists('get_leave_balance')) {
        $balances = get_leave_balance($pdo, $employee_id);
    } else {
        // Fallback or Error if the model function is missing
        $balances = ['Error' => ['remaining' => 'N/A']];
    }
    
    // 3. Render Balance HTML for injection into the modal
    $balance_html = '
        <table class="table table-bordered table-sm small">
            <thead class="bg-light">
                <tr>
                    <th class="text-uppercase text-gray-600">Type</th>
                    <th class="text-uppercase text-center text-gray-600">Max</th>
                    <th class="text-uppercase text-center text-gray-600">Used</th>
                    <th class="text-uppercase text-center text-gray-600">Remaining</th>
                </tr>
            </thead>
            <tbody>
    ';
    
    foreach ($balances as $type => $data) {
        // Safely access values, defaulting to 0 or 'N/A' if the key is missing
        $max = $data['max'] ?? 0;        // <-- FIX APPLIED HERE
        $used = $data['used'] ?? 0;
        $remaining = $data['remaining'] ?? 'N/A';
        
        // Skip types that don't have balance tracking AND have a Max of 0
        if (in_array($type, ['Maternity/Paternity', 'Unpaid Leave']) && $max === 0) continue;
        
        $remaining_class = ($remaining !== 'N/A' && $remaining <= 0) ? 'text-danger fw-bold' : 'text-black fw-bold';

        $balance_html .= "
            <tr>
                <td>{$type}</td>
                <td class='text-center'>{$max}</td>
                <td class='text-center'>{$used}</td>
                <td class='text-center {$remaining_class}'>{$remaining}</td>
            </tr>
        ";
    }

    $balance_html .= '
            </tbody>
        </table>
        <div class="small text-muted mt-2">Note: This does not include the current request yet.</div>
    ';


    // 4. Final Success Response
    $response['success'] = true;
    $response['details'] = $leave_details;
    $response['balance_html'] = $balance_html;

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>