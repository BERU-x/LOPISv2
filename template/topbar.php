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
                        <i class="fas fa-sync-alt me-1 text-secondary" id="refreshIcon" style="font-size: 0.75rem;"></i>
                        <span class="small fw-bold text-secondary text-uppercase">SYNC STATUS</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-end">
                        <i class="fas fa-circle text-warning live-dot me-2" style="font-size: 8px;"></i>
                        
                        <span id="last-updated-time" class="small fw-bold text-dark">Syncing...</span>
                    </div>

                    </div>
                </li>

                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link d-flex align-items-center mt-2" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw text-gray-400"></i>

                        <span class="badge badge-danger badge-counter" id="notif-badge" style="<?php echo ($notif_count > 0) ? '' : 'display:none;'; ?>">
                            <?php echo $notif_count; ?>
                        </span>
                    </a>

                    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in"
                        aria-labelledby="alertsDropdown">
                        <h6 class="dropdown-header">
                            Notifications
                            <?php if ($notif_count > 0): ?>
                                <a href="#" id="mark-all-read-btn" class="float-end small text-white text-decoration-underline" onclick="markAllNotificationsRead(); return false;">Mark All Read</a>
                            <?php endif; ?>
                        </h6>

                        <div id="notif-list">
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <a class="dropdown-item d-flex align-items-center <?php echo ($notif['is_read'] == 0) ? 'bg-light-unread' : ''; ?>" 
                                       href="<?php echo htmlspecialchars($notif['link']); ?>" data-id="<?php echo $notif['id']; ?>">
                                        <div class="me-3">
                                            <div class="icon-circle bg-primary">
                                                <i class="fas fa-fw <?php echo (strpos($notif['type'], 'LEAVE') !== false) ? 'fa-plane' : 'fa-file-alt'; ?> text-white"></i> 
                                            </div>
                                        </div>
                                        <div>
                                            <div class="small text-gray-500"><?php echo htmlspecialchars(time_elapsed_string($notif['created_at'])); ?></div>
                                            <span class="<?php echo ($notif['is_read'] == 0) ? 'fw-bold text-dark' : 'text-gray-700'; ?>"><?php echo htmlspecialchars($notif['message']); ?></span>
                                            <div class="small text-muted fst-italic">Sent by: <?php echo htmlspecialchars($notif['sender_name']); ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                                <a class="dropdown-item text-center small text-gray-500" href="notifications.php">Show All Notifications</a>
                            <?php else: ?>
                                <a class="dropdown-item text-center small text-gray-500">No new notifications.</a>
                            <?php endif; ?>
                        </div>

                    </div>
                </li>

                <div class="topbar-divider d-none d-sm-block"></div>

                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link d-flex align-items-center" href="#" id="userDropdown" role="button"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                        <span class="me-2 d-none d-lg-inline text-gray-600 small font-weight-bold">
                            <?php echo htmlspecialchars($fullname); ?>
                        </span>

                        <div class="topbar-avatar shadow-sm overflow-hidden">
                            <?php
                            // --- Image Path Logic (Optimized for global usage) ---
                            // Rely on $profile_picture variable being the filename.
                            $img_file_name = $profile_picture ?? 'default.png';
                            
                            // Determine the correct web path based on file existence check 
                            // This check must use a relative path from the current executing script to the profile image directory
                            $physical_path = __DIR__ . '/../../assets/images/profile/' . $img_file_name;
                            $web_path = '../assets/images/profile/' . $img_file_name;

                            if (!file_exists($physical_path)) {
                                // Fallback to a global default image if the specific photo is missing
                                $web_path = '../assets/images/default.png';
                            }
                            ?>

                            <img src="<?php echo htmlspecialchars($web_path); ?>" alt="Profile"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </a>

                    <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in border-0"
                        aria-labelledby="userDropdown" style="border-radius: 1rem;">

                        <div class="px-3 py-2 bg-light rounded-top">
                            <div class="small text-muted text-uppercase font-weight-bold">
                                <?php 
                                    // $usertype_name should be defined in header.php
                                    echo htmlspecialchars($usertype_name);
                                ?>
                            </div>
                            <div class="text-truncate font-weight-bold text-dark">
                                <?php echo htmlspecialchars($email); ?>
                            </div>
                        </div>

                        <div class="dropdown-divider mt-0"></div>

                        <a class="dropdown-item py-2" href="profile.php">
                            <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                            Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item py-2 text-danger" href="#" data-bs-toggle="modal"
                            data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-danger"></i>
                            Logout
                        </a>
                    </div>
                </li>

            </ul>

        </nav>
        <div class="container-fluid">