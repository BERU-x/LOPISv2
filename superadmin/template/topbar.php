<?php
// 1. LOGIC: Generate Initials (e.g., "Super Admin" -> "SA")
$display_initials = 'SA'; // Default
if (!empty($fullname)) {
    $parts = explode(' ', trim($fullname));
    $display_initials = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) {
        $display_initials .= strtoupper(substr($parts[1], 0, 1));
    }
}
?>

<div id="content-wrapper" class="d-flex flex-column min-vh-100">

    <div id="content" class="flex-grow-1">

        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top">

            <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3">
                <i class="fa fa-bars"></i>
            </button>
            
            <button id="sidebarToggle" class="btn btn-link d-none d-md-inline-block me-3">
                <i class="fa fa-bars"></i>
            </button>

            <form class="d-none d-sm-inline-block form-inline me-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
                <div class="input-group">
                    <input type="text" class="form-control bg-light border-0 small" placeholder="Search system..."
                        aria-label="Search" style="border-radius: 20px 0 0 20px;">
                    <div class="input-group-append">
                        <button class="btn btn-primary btn-teal" type="button" style="border-radius: 0 20px 20px 0;">
                            <i class="fas fa-search fa-sm"></i>
                        </button>
                    </div>
                </div>
            </form>

            <ul class="navbar-nav ms-auto">

                <li class="nav-item dropdown no-arrow mx-1">
                    <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell fa-fw text-gray-400"></i>
                        <span class="badge badge-danger badge-counter">3+</span>
                    </a>
                    <div class="dropdown-list dropdown-menu dropdown-menu-end shadow animated--grow-in"
                        aria-labelledby="alertsDropdown">
                        <h6 class="dropdown-header bg-info border-0" style="background-color: #0CC0DF !important;">
                            System Alerts
                        </h6>
                        <a class="dropdown-item d-flex align-items-center" href="#">
                            <div class="me-3">
                                <div class="icon-circle bg-warning text-white rounded-circle p-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500">Today at 10:30 AM</div>
                                <span class="font-weight-bold">Failed login attempt detected.</span>
                            </div>
                        </a>
                    </div>
                </li>

                <div class="topbar-divider d-none d-sm-block"></div>

                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        
                        <span class="me-2 d-none d-lg-inline text-gray-600 small font-weight-bold">
                            <?php echo htmlspecialchars($fullname); ?>
                        </span>
                        
                        <div class="topbar-avatar shadow-sm">
                            <?php echo $display_initials; ?>
                        </div>
                    </a>

                    <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in border-0"
                        aria-labelledby="userDropdown" style="border-radius: 1rem;">
                        
                        <div class="px-3 py-2 bg-light rounded-top">
                            <div class="small text-muted text-uppercase font-weight-bold">Superadmin</div>
                            <div class="text-truncate font-weight-bold text-dark"><?php echo htmlspecialchars($email); ?></div>
                        </div>

                        <div class="dropdown-divider mt-0"></div>

                        <a class="dropdown-item py-2" href="#profile">
                            <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                            Profile
                        </a>
                        <a class="dropdown-item py-2" href="#settings">
                            <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item py-2 text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-danger"></i>
                            Logout
                        </a>
                    </div>
                </li>

            </ul>

        </nav>
        <div class="container-fluid">