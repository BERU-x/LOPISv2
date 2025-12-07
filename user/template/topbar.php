<div id="content-wrapper" class="d-flex flex-column min-vh-100">

    <div id="content" class="flex-grow-1">

        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top">
                        
        <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3 mx-3">
            <i class="fa fa-bars"></i>
        </button>
        
        <button id="sidebarToggle" class="btn btn-link d-none d-md-inline-block me-3 mx-3">
            <i class="fa fa-bars"></i>
        </button>

            <ul class="navbar-nav ms-auto">

                <li class="nav-item d-none d-sm-flex align-items-center me-3">
                    <div class="d-flex flex-column text-end lh-1">
                        
                        <div class="mb-1">
                            <i class="fas fa-sync-alt me-1 text-gray-400" id="refresh-spinner" style="font-size: 0.75rem;"></i>
                            <span class="text-xs font-weight-bold text-gray-500 text-uppercase">Last Sync</span>
                        </div>

                        <div class="small font-weight-bold text-gray-800 d-flex align-items-center justify-content-end">
                            <i class="fas fa-circle text-success live-dot me-2" style="font-size: 8px;"></i>
                            <span id="last-updated-time">--:--</span>
                        </div>

                    </div>
                </li>

                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link d-flex align-items-center mt-2" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw text-gray-400"></i>

                        <span class="badge badge-danger badge-counter" id="notif-badge" style="display:none;">
                            0
                        </span>
                    </a>

                    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in"
                        aria-labelledby="alertsDropdown">

                        <div id="notif-list">
                            <a class="dropdown-item text-center small text-gray-500" href="#">
                                Loading...
                            </a>
                        </div>

                    </div>
                </li>

                <div class="topbar-divider d-none d-sm-block"></div>

                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link d-flex align-items-center" href="#" id="userDropdown" role="button"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                        <span class="me-2 d-none d-lg-inline text-gray-600 small font-weight-bold">
                            <?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?>
                        </span>

                        <div class="topbar-avatar shadow-sm overflow-hidden">
                            <?php
                            // ... (PHP Image Logic remains the same) ...
                            $img_file_name = 'default.png';

                            if (!empty($profile_picture) && $profile_picture !== 'default.png') {
                                $img_file_name = $profile_picture;
                            } elseif (!empty($_SESSION['profile_picture'])) {
                                $img_file_name = $_SESSION['profile_picture'];
                            }

                            if ($img_file_name === 'default.png' && isset($_SESSION['user_id']) && isset($pdo)) {
                                try {
                                    $uid = $_SESSION['user_id'];
                                    $stmt_img = $pdo->prepare("SELECT e.photo FROM tbl_employees e JOIN tbl_users u ON u.employee_id = e.employee_id WHERE u.id = ?");
                                    $stmt_img->execute([$uid]);
                                    $img_row = $stmt_img->fetch(PDO::FETCH_ASSOC);

                                    if ($img_row && !empty($img_row['photo'])) {
                                        $img_file_name = $img_row['photo'];
                                        $_SESSION['profile_picture'] = $img_file_name;
                                    }
                                } catch (Exception $e) {
                                }
                            }

                            $physical_path = __DIR__ . '/../../assets/images/' . $img_file_name;

                            if (file_exists($physical_path) && $img_file_name !== 'default.png') {
                                $web_path = '../assets/images/' . $img_file_name;
                            } else {
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
                                <?php echo (isset($_SESSION['usertype']) && ($_SESSION['usertype'] == 0 || $_SESSION['usertype'] == 1)) ? 'Administrator' : 'Employee'; ?>
                            </div>
                            <div class="text-truncate font-weight-bold text-dark">
                                <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
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