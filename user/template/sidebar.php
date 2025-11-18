<?php
// This is the Employee Sidebar
$current_page = $current_page ?? ''; 
$firstName = $firstName ?? 'User';

// Helper function to determine active class
function isActive($page_name, $current_page) {
    return ($current_page == $page_name) ? 'active' : '';
}
?>

<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center py-4" href="dashboard.php" style="text-decoration: none;">
        <div class="sidebar-brand-icon">
            <img src="../assets/images/LOPISv2.png" class="logo-large" alt="Full Logo" style="max-height: 100px;">
            <img src="../assets/images/lendell_logo.png" class="logo-small" alt="Icon Logo">
        </div>
    </a>

    <hr class="sidebar-divider my-0 mb-3">

    <li class="nav-item <?php echo isActive('dashboard', $current_page); ?>">
        <a class="nav-link" href="dashboard.php">
            <i class="fas fa-fw fa-th-large"></i> <span>Dashboard</span>
        </a>
    </li>

    <div class="sidebar-heading">
        My Account
    </div>

    <li class="nav-item <?php echo isActive('profile', $current_page); ?>">
        <a class="nav-link" href="profile.php">
            <i class="fas fa-fw fa-user-circle"></i>
            <span>Profile</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('payslips', $current_page); ?>">
        <a class="nav-link" href="payslips.php">
            <i class="fas fa-fw fa-receipt"></i>
            <span>Payslips</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('attendance', $current_page); ?>">
        <a class="nav-link" href="attendance.php">
            <i class="fas fa-fw fa-fingerprint"></i> <span>Attendance</span>
        </a>
    </li>

    <div class="sidebar-heading">
        Time Off
    </div>

    <li class="nav-item <?php echo isActive('request_leave', $current_page); ?>">
        <a class="nav-link" href="request_leave.php">
            <i class="fas fa-fw fa-paper-plane"></i>
            <span>Request Leave</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('leave_balances', $current_page); ?>">
        <a class="nav-link" href="leave_balances.php">
            <i class="fas fa-fw fa-umbrella-beach"></i>
            <span>Leave Balances</span>
        </a>
    </li>

    <div class="sidebar-heading">
        Organization
    </div>

    <li class="nav-item <?php echo isActive('directory', $current_page); ?>">
        <a class="nav-link" href="directory.php">
            <i class="fas fa-fw fa-users"></i>
            <span>Team Directory</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('documents', $current_page); ?>">
        <a class="nav-link" href="documents.php">
            <i class="fas fa-fw fa-folder-open"></i>
            <span>Docs & Policies</span>
        </a>
    </li>
    
    <hr class="sidebar-divider mt-3">

    <li class="nav-item <?php echo isActive('help', $current_page); ?>">
        <a class="nav-link" href="help.php">
            <i class="far fa-fw fa-life-ring"></i>
            <span>Help & Support</span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block mt-4">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
    
</ul>