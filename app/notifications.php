<?php
// notifications.php
// GLOBAL NOTIFICATION PAGE

$page_title = 'Notifications';
$current_page = 'notifications';

// 1. INCLUDE HEADER (Which includes checking.php & starts session)
require __DIR__ . '/../template/header.php'; 

// 2. SETUP VARIABLES (Now that session is started)
// We extract these from the session for easier use below
$my_id   = $_SESSION['employee_id'] ?? 0;
$my_role = $_SESSION['usertype'] ?? 2; // Default to Employee (2)

// 3. DEFINE PERMISSIONS
// Employee (2) -> Sees 2
// Admin (1)    -> Sees 1
// Super (0)    -> Sees 0 AND 1
$allowed_roles = [$my_role];
if ($my_role == 0) {
    $allowed_roles = [0, 1]; 
}
$allowed_roles_str = implode(',', $allowed_roles);

// =========================================================
// ACTION: HANDLE MARK AS READ
// =========================================================
if (isset($_GET['action'])) {
    
    // A. Mark Single Item Read
    if ($_GET['action'] === 'mark_read' && isset($_GET['id'])) {
        $notif_id = intval($_GET['id']);
        
        // Security: Ensure the notification belongs to this user or their role
        $sql = "UPDATE tbl_notifications 
                SET is_read = 1 
                WHERE id = :id 
                AND (
                    target_user_id = :my_id 
                    OR target_role IN ($allowed_roles_str)
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $notif_id, ':my_id' => $my_id]);
        
        // Redirect to refresh the page and remove the GET params
        // This works because checking.php enabled ob_start()
        header("Location: notifications.php");
        exit();
    }
    
    // B. Mark ALL Read
    if ($_GET['action'] === 'mark_all_read') {
        try {
             $sql = "UPDATE tbl_notifications 
                     SET is_read = 1 
                     WHERE is_read = 0 
                     AND (
                        target_user_id = :my_id 
                        OR target_role IN ($allowed_roles_str)
                     )";
             $stmt = $pdo->prepare($sql);
             $stmt->execute([':my_id' => $my_id]);

        } catch (PDOException $e) { /* Ignore errors */ }
        
        header("Location: notifications.php");
        exit();
    }
}

// =========================================================
// FETCH DATA
// =========================================================
$my_notifications = [];
try {
    $sql = "SELECT t1.*, t2.firstname, t2.lastname, t2.photo
            FROM tbl_notifications t1
            LEFT JOIN tbl_employees t2 ON t1.sender_name = t2.employee_id 
            WHERE (
                (t1.target_user_id = :my_id) 
                OR 
                (t1.target_role IN ($allowed_roles_str))
            )
            ORDER BY t1.created_at DESC 
            LIMIT 50"; 

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':my_id' => $my_id]);
    $my_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // error_log($e->getMessage());
}

// =========================================================
// VIEW (Sidebar/Topbar)
// =========================================================
// Ensure these files exist in your template folder
require __DIR__ . '/../template/sidebar.php'; 
require __DIR__ . '/../template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Notifications 
        </h1>
        
        <?php if(count($my_notifications) > 0): ?>
            <a href="notifications.php?action=mark_all_read" class="btn btn-sm btn-teal shadow-sm">
                <i class="fas fa-check-double fa-sm text-white-50 me-2"></i>Mark All as Read
            </a>
        <?php endif; ?>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-label">Recent Alerts</h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                
                <?php if (count($my_notifications) > 0): ?>
                    <?php foreach ($my_notifications as $notif): ?>
                        <?php 
                            // Style Logic
                            $is_read = $notif['is_read'] == 1;
                            $bg_style = $is_read ? 'bg-white' : 'background-color: #f0fdf4; border-left: 4px solid #0CC0DF;'; 
                            $text_class = $is_read ? 'text-muted' : 'text-dark font-weight-bold';
                            $icon_color = $is_read ? 'text-gray-400' : 'text-teal';
                            
                            // Icons
                            $icon = 'fa-info-circle';
                            $type_lower = strtolower($notif['type']);
                            if(strpos($type_lower, 'payroll') !== false) $icon = 'fa-file-invoice-dollar';
                            if(strpos($type_lower, 'leave') !== false)   $icon = 'fa-calendar-times';
                            if(strpos($type_lower, 'warning') !== false) $icon = 'fa-exclamation-triangle';
                            if(strpos($type_lower, 'system') !== false)  $icon = 'fa-hdd';
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
                                        <span class="fw-bold me-1"><?php echo htmlspecialchars($notif['sender_name']); ?></span>
                                        &middot; 
                                        <?php echo date("F d, h:i A", strtotime($notif['created_at'])); ?> 
                                        &middot; 
                                        <?php 
                                            if(function_exists('time_elapsed_string')) {
                                                echo time_elapsed_string($notif['created_at']); 
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="dropdown no-arrow">
                                <?php if (!$is_read): ?>
                                    <a href="notifications.php?action=mark_read&id=<?php echo $notif['id']; ?>" class="btn btn-sm btn-light text-teal" title="Mark as Read">
                                       <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($notif['link']) && $notif['link'] != '#'): ?>
                                    <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="btn btn-sm btn-light text-gray-600 ms-1" title="View Details">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-gray-300 mb-3"></i>
                        <p class="text-gray-500 mb-0">No new notifications.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>

<?php require __DIR__ . '/../template/footer.php'; ?>