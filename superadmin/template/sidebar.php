<?php
// This is the Super Admin Sidebar
// Ensure variable is set to avoid errors if forgotten in a page
$current_page = $current_page ?? ''; 

// Helper function for Pill Styling (Active State)
function isActive($page_name, $current_page) {
    return ($current_page == $page_name) ? 'active' : '';
}
?>

<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center py-4" href="dashboard.php" style="text-decoration: none;">
        <div class="sidebar-brand-icon">
            <img src="../assets/images/LOPISv2.png" class="logo-large" alt="Full Logo" style="max-height: 100px;">
            <img src="../assets/images/lendell_logo.png" class="logo-small" alt="Icon Logo" style="display:none;">
        </div>
    </a>

    <hr class="sidebar-divider my-0 mb-3">

    <li class="nav-item <?php echo isActive('dashboard', $current_page); ?>">
        <a class="nav-link" href="dashboard.php">
            <i class="fa-solid fa-fw fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
    </li> 
        
    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        User Management
    </div>

    <li class="nav-item <?php echo isActive('admin_management', $current_page); ?>">
        <a class="nav-link" href="admin_management.php">
            <i class="fa-solid fa-fw fa-user-shield"></i>
            <span>Admins</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('employee_management', $current_page); ?>">
        <a class="nav-link" href="employee_management.php">
            <i class="fa-solid fa-fw fa-users"></i>
            <span>Employees</span>
        </a>
    </li>   
    
    <li class="nav-item <?php echo isActive('roles_management', $current_page); ?>">
        <a class="nav-link" href="roles_management.php">
            <i class="fa-solid fa-fw fa-user-gear"></i>
            <span>User Roles & Permissions</span>
        </a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        Payroll Configuration
    </div>

    <li class="nav-item <?php echo isActive('pay_components', $current_page); ?>">
        <a class="nav-link" href="pay_components.php">
            <i class="fa-solid fa-fw fa-money-bill-transfer"></i>
            <span>Pay Components</span>
        </a>
    </li> 

    <li class="nav-item <?php echo isActive('tax_settings', $current_page); ?>">
        <a class="nav-link" href="tax_settings.php">
            <i class="fa-solid fa-fw fa-landmark"></i>
            <span>Tax Settings</span>
        </a>
    </li> 

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        Company Settings
    </div>

    <li class="nav-item <?php echo isActive('company_details', $current_page); ?>">
        <a class="nav-link" href="company_details.php">
            <i class="fa-solid fa-fw fa-building"></i>
            <span>Company Details</span>
        </a>
    </li> 

    <li class="nav-item <?php echo isActive('financial_settings', $current_page); ?>">
        <a class="nav-link" href="financial_settings.php">
            <i class="fa-solid fa-fw fa-sack-dollar"></i>
            <span>Financial Settings</span>
        </a>
    </li> 

    <li class="nav-item <?php echo isActive('policy_settings', $current_page); ?>">
        <a class="nav-link" href="policy_settings.php">
            <i class="fa-solid fa-fw fa-file-contract"></i>
            <span>Policy Settings</span>   
        </a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        System Settings
    </div>

    <li class="nav-item <?php echo isActive('general_settings', $current_page); ?>">
        <a class="nav-link" href="general_settings.php">
            <i class="fa-solid fa-fw fa-sliders"></i>
            <span>General Settings</span>   
        </a>
    </li>

    <li class="nav-item <?php echo isActive('security_settings', $current_page); ?>">
        <a class="nav-link" href="security_settings.php">
            <i class="fa-solid fa-fw fa-shield-halved"></i>
            <span>Security Settings</span>   
        </a>
    </li>

    <li class="nav-item <?php echo isActive('audit_logs', $current_page); ?>">
        <a class="nav-link" href="audit_logs.php">
            <i class="fa-solid fa-fw fa-clock-rotate-left"></i>
            <span>Audit Logs</span>   
        </a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        Reports
    </div>

    <li class="nav-item <?php echo isActive('payroll_reports', $current_page); ?>">
        <a class="nav-link" href="payroll_reports.php">
            <i class="fa-solid fa-fw fa-chart-line"></i>
            <span>Payroll Reports</span>   
        </a>
    </li>

    <li class="nav-item <?php echo isActive('tax_reports', $current_page); ?>">
        <a class="nav-link" href="tax_reports.php">
            <i class="fa-solid fa-fw fa-file-invoice"></i>
            <span>Tax Reports</span>   
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block mt-4">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>