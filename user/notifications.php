<?php
// user/notifications.php
$page_title = 'My Notifications - LOPISv2';
$current_page = 'notifications';

// The header handles session/auth and includes the global model
require 'template/header.php'; 

// --- SETUP CONTEXT (Guaranteed by header.php) ---
$my_id = $_SESSION['employee_id'] ?? null;
$my_role = 'Employee'; 

// Redirect if context is missing
if (!$my_id) {
    header("Location: dashboard.php");
    exit();
}

// --- HANDLE ACTIONS (Mark Read / Mark All Read) ---
if (isset($_GET['action'])) {
    
    // 1. Mark Single Notification Read
    if ($_GET['action'] === 'mark_read' && isset($_GET['id']) && function_exists('mark_notification_read')) {
        mark_notification_read($pdo, $_GET['id']);
        header("Location: notifications.php");
        exit();
    }
    
    // 2. Mark ALL User Notifications Read
    if ($_GET['action'] === 'mark_all_read') {
        try {
             // We only mark the ones read that were intended for this specific user or role
             $sql = "UPDATE tbl_notifications SET is_read = 1 
                     WHERE (target_user_id = :my_id OR target_role = :my_role)";
             $stmt = $pdo->prepare($sql);
             $stmt->execute([':my_id' => $my_id, ':my_role' => $my_role]);

        } catch (PDOException $e) { /* Log error, but continue */ }
        
        header("Location: notifications.php");
        exit();
    }
}

// --- FETCH DATA (Filtered to the Employee) ---
$all_notifications = [];
try {
    // This custom query ensures only notifications explicitly targeting the user or the Employee role are fetched.
    $sql = "SELECT t1.*, t2.firstname, t2.lastname, t2.photo
            FROM tbl_notifications t1
            LEFT JOIN tbl_employees t2 ON t1.target_user_id = t2.employee_id
            WHERE (
                (t1.target_user_id = :my_id) 
                OR 
                (t1.target_user_id IS NULL AND t1.target_role = :my_role)
                OR 
                (t1.target_role = 'All')
            )
            ORDER BY t1.created_at DESC 
            LIMIT 50"; 

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':my_id'   => $my_id,
        ':my_role' => $my_role 
    ]);
    $all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { /* Log error, but ensure page renders */ }


require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Notifications</h1>
        
        <?php if(count($all_notifications) > 0): ?>
            <a href="notifications.php?action=mark_all_read" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-check-double fa-sm text-white-50 me-2"></i>Mark All as Read
            </a>
        <?php endif; ?>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Recent Alerts</h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                
                <?php if (count($all_notifications) > 0): ?>
                    <?php foreach ($all_notifications as $notif): ?>
                        <?php 
                            // Style Logic
                            $is_read = $notif['is_read'] == 1;
                            $bg_style = $is_read ? 'bg-white' : 'background-color: #f8f9fc; border-left: 4px solid #4e73df;'; 
                            $text_class = $is_read ? 'text-muted' : 'text-dark font-weight-bold';
                            $icon_color = $is_read ? 'text-gray-400' : 'text-primary';
                            
                            // Icon Selection
                            $icon = 'fa-info-circle';
                            if($notif['type'] == 'payroll') $icon = 'fa-file-invoice-dollar';
                            if($notif['type'] == 'leave')   $icon = 'fa-calendar-times';
                            if($notif['type'] == 'warning') $icon = 'fa-exclamation-triangle';
                            if($notif['type'] == 'system')  $icon = 'fa-hdd';
                        ?>

                        <div class="list-group-item d-flex justify-content-between align-items-center p-3" style="<?php echo $bg_style; ?>">
                            
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="icon-circle bg-light <?php echo $icon_color; ?> rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="<?php echo $text_class; ?> mb-1">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </div>
                                    <div class="small text-gray-500">
                                        <?php echo date("F d, Y h:i A", strtotime($notif['created_at'])); ?> 
                                        &middot; 
                                        <?php 
                                            // The time_elapsed_string function comes from global_model.php 
                                            if(function_exists('time_elapsed_string')) {
                                                echo time_elapsed_string($notif['created_at']); 
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="dropdown no-arrow">
                                <?php if (!$is_read): ?>
                                    <a href="notifications.php?action=mark_read&id=<?php echo $notif['id']; ?>" 
                                        class="btn btn-sm btn-light text-primary" 
                                        title="Mark as Read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="btn btn-sm btn-light text-gray-600 ms-1" title="View Details">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-gray-300 mb-3"></i>
                        <p class="text-gray-500 mb-0">No notifications found.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <div class="card-footer text-center small text-muted">
            Showing last 50 notifications
        </div>
    </div>

</div>

<?php require 'template/footer.php'; ?>