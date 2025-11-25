<?php
// admin/fetch/get_balance.php
require_once '../../db_connection.php'; 
require '../models/leave_model.php';

if (isset($_POST['employee_id'])) {
    $balances = get_leave_balance($pdo, $_POST['employee_id']);
    
    echo '<ul class="list-group">';
    foreach ($balances as $type => $data) {
        $color = ($data['remaining'] < 2) ? 'text-danger' : 'text-success';
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
                $type
                <span>
                    Used: <b>{$data['used']}</b> / 
                    Remaining: <b class='$color'>{$data['remaining']}</b>
                </span>
              </li>";
    }
    echo '</ul>';
}
?>