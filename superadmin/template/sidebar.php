<?php
// This is the Superadmin Sidebar
$current_page = $current_page ?? ''; 

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
        System Management
    </div>

    <li class="nav-item <?php echo isActive('company_management', $current_page); ?>">
        <a class="nav-link" href="company_management.php">
            <i class="fas fa-fw fa-building"></i>
            <span>Companies</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('user_management', $current_page); ?>">
        <a class="nav-link" href="user_management.php">
            <i class="fas fa-fw fa-users-cog"></i>
            <span>Users</span>
        </a>
    </li>

    <div class="sidebar-heading">
        Configuration
    </div>

    <li class="nav-item <?php echo isActive('pay_components', $current_page); ?>">
        <a class="nav-link" href="pay_components.php">
            <i class="fas fa-fw fa-calculator"></i>
            <span>Pay Components</span>
        </a>
    </li>
    
    <li class="nav-item <?php echo isActive('tax_settings', $current_page); ?>">
        <a class="nav-link" href="tax_settings.php">
            <i class="fas fa-fw fa-percentage"></i>
            <span>Tax Settings</span>
        </a>
    </li>
    
    <li class="nav-item <?php echo isActive('financial_year', $current_page); ?>">
        <a class="nav-link" href="financial_year.php">
            <i class="fas fa-fw fa-calendar-alt"></i>
            <span>Financial Year</span>
        </a>
    </li>

    <div class="sidebar-heading">
        Operations
    </div>

    <li class="nav-item <?php echo isActive('employee_management', $current_page); ?>">
        <a class="nav-link" href="employee_management.php">
            <i class="fas fa-fw fa-id-card"></i>
            <span>Employees</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('leave_management', $current_page); ?>">
        <a class="nav-link" href="leave_management.php">
            <i class="fas fa-fw fa-calendar-check"></i>
            <span>Leave Mgmt</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('payroll', $current_page); ?>">
        <a class="nav-link" href="payroll.php">
            <i class="fas fa-fw fa-file-invoice-dollar"></i>
            <span>Payroll Processing</span>
        </a>
    </li>
    
    <div class="sidebar-heading">
        System Health
    </div>

    <li class="nav-item <?php echo isActive('reports', $current_page); ?>">
        <a class="nav-link" href="reports.php">
            <i class="fas fa-fw fa-file-export"></i>
            <span>Reports</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('audit_logs', $current_page); ?>">
        <a class="nav-link" href="audit_logs.php">
            <i class="fas fa-fw fa-clipboard-list"></i>
            <span>Audit Logs</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('security', $current_page); ?>">
        <a class="nav-link" href="security.php">
            <i class="fas fa-fw fa-shield-alt"></i>
            <span>Security</span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block mt-4">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>