<?php
// template/topbar.php
// Renders the top navigation, notification dropdown, and user profile menu.
?>

<div id="content-wrapper" class="d-flex flex-column min-vh-100">

    <div id="content" class="flex-grow-1">

        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow-sm">

            <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3 mx-3">
                <i class="fa fa-bars"></i>
            </button>
            
            <button id="sidebarToggle" class="btn btn-link d-none d-md-inline-block me-3 mx-3">
                <i class="fa fa-bars"></i>
            </button>

            <ul class="navbar-nav ms-auto">

                <li class="nav-item d-none d-sm-flex align-items-center me-3">
                    <div class="d-flex flex-column text-end lh-1">
                        <div class="mb-1 d-flex align-items-center justify-content-end">
                            <i class="fas fa-sync-alt me-1 text-secondary" id="refreshIcon" style="font-size: 0.75rem; cursor: pointer;"></i>
                            <span class="small fw-bold text-secondary text-uppercase">SYNC STATUS</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-end">
                            <i class="fas fa-circle text-warning live-dot me-2" style="font-size: 8px;"></i>
                            <span class="sync-status-text small fw-bold text-dark">Syncing...</span>
                        </div>
                    </div>
                </li>

                <li class="nav-item dropdown no-arrow mx-1">
                    
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw"></i>
                        <span class="badge bg-danger badge-counter" id="notif-badge" style="<?php echo ($notif_count > 0) ? '' : 'display:none;'; ?>">
                            <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                        </span>
                    </a>

                    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="alertsDropdown">
                        <h6 class="dropdown-header d-flex justify-content-between align-items-center">
                            Alerts Center
                            <?php if ($notif_count > 0): ?>
                                <small>
                                    <a href="#" class="text-white text-decoration-none" onclick="markAllRead(); return false;">Mark all read</a>
                                </small>
                            <?php endif; ?>
                        </h6>

                        <div id="notif-list" style="max-height: 300px; overflow-y: auto;">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <?php 
                                        $type = strtolower($notif['type']);
                                        $icon_bg = 'bg-primary'; $icon = 'fa-info-circle';

                                        if (strpos($type, 'payroll') !== false) { $icon_bg = 'bg-success'; $icon = 'fa-file-invoice-dollar'; }
                                        elseif (strpos($type, 'leave') !== false) { $icon_bg = 'bg-warning'; $icon = 'fa-calendar-times'; }
                                        elseif (strpos($type, 'warning') !== false || strpos($type, 'alert') !== false) { $icon_bg = 'bg-danger'; $icon = 'fa-exclamation-triangle'; }
                                        elseif (strpos($type, 'system') !== false) { $icon_bg = 'bg-info'; $icon = 'fa-server'; }
                                        
                                        $link = $notif['link']; 
                                    ?>
                                    <a class="dropdown-item d-flex align-items-center" href="#" onclick="markAsRead(<?php echo $notif['id']; ?>, '<?php echo $link; ?>'); return false;">
                                        <div class="me-3">
                                            <div class="icon-circle <?php echo $icon_bg; ?>">
                                                <i class="fas <?php echo $icon; ?> text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="small text-gray-500">
                                                <?php echo function_exists('time_elapsed_string') ? time_elapsed_string($notif['created_at']) : date('M d, H:i', strtotime($notif['created_at'])); ?>
                                            </div>
                                            <span class="font-weight-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($notif['message']); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <a class="dropdown-item text-center small text-gray-500" href="#">No new notifications</a>
                            <?php endif; ?>
                        </div>

                        <a class="dropdown-item text-center small text-gray-500" href="<?php echo isset($base_link) ? $base_link : ''; ?>notifications.php">
                            Show All Alerts
                        </a>
                    </div>
                </li>

                <div class="topbar-divider d-none d-sm-block"></div>

                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small font-weight-bold">
                            <?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?>
                        </span>
                        <div class="topbar-avatar shadow-sm overflow-hidden">
                            <?php
                            $avatar_img = $_SESSION['profile_picture'] ?? 'default.png';
                            $avatar_path_check = __DIR__ . '/../assets/images/' . $avatar_img;
                            if (!file_exists($avatar_path_check)) { $avatar_img = 'default.png'; }
                            ?>
                            <img src="../assets/images/<?php echo htmlspecialchars($avatar_img); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </a>

                    <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in border-0" aria-labelledby="userDropdown" style="border-radius: 1rem;">
                        <div class="px-3 py-2 bg-light rounded-top">
                            <div class="small text-muted text-uppercase font-weight-bold"><?php echo htmlspecialchars($usertype_name ?? 'User'); ?></div>
                            <div class="text-truncate font-weight-bold text-dark"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
                        </div>
                        <div class="dropdown-divider mt-0"></div>
                        <a class="dropdown-item py-2" href="profile.php">
                            <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item py-2 text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-danger"></i> Logout
                        </a>
                    </div>
                </li>

            </ul>

        </nav>
        <div class="container-fluid"></div>