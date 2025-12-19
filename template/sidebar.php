<?php
// template/sidebar.php
// Dynamically renders the sidebar menu based on $_SESSION['usertype']
// 0 = Superadmin | 1 = Admin | 2 = Employee

// 1. Setup Variables
$current_page = $current_page ?? ''; 
$user_type = $_SESSION['usertype'] ?? 99; 

// 2. Helper for Active State
function isActive($page_name, $current_page) {
    return ($current_page == $page_name) ? 'active' : '';
}
?>

<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center py-4" 
       href="dashboard.php" style="text-decoration: none;">
        <div class="sidebar-brand-icon">
            <img src="../assets/images/LOPISv2.png" class="logo-large" alt="Full Logo" style="max-height: 100px;">
            <img src="../assets/images/lendell_logo.png" class="logo-small" alt="Icon Logo" style="display:none;">
        </div>
    </a>

    <hr class="sidebar-divider my-0 mb-3">

    <?php if ($user_type == 0): ?>

        <li class="nav-item <?php echo isActive('dashboard', $current_page); ?>">
            <a class="nav-link" href="dashboard.php">
                <i class="fa-solid fa-fw fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('pending_emails', $current_page); ?>">
            <a class="nav-link" href="pending_emails.php">
                <i class="fa-solid fa-fw fa-envelope"></i>
                <span>Pending Emails</span>
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
            <a class="nav-link" href="coming_soon.php?feature=Payroll Reports">
                <i class="fa-solid fa-fw fa-chart-line"></i>
                <span>Payroll Reports</span>   
            </a>
        </li>

        <li class="nav-item <?php echo isActive('tax_reports', $current_page); ?>">
            <a class="nav-link" href="coming_soon.php?feature=Tax Reports">
                <i class="fa-solid fa-fw fa-file-invoice"></i>
                <span>Tax Reports</span>   
            </a>
        </li>

    <?php endif; ?>


    <?php if ($user_type == 1): ?>

        <li class="nav-item <?php echo isActive('dashboard', $current_page); ?>">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-fw fa-th-large"></i> <span>Dashboard</span>
            </a>
        </li> 
            
        <li class="nav-item <?php echo isActive('today_attendance', $current_page); ?>">
            <a class="nav-link" href="today_attendance.php">
                <i class="fas fa-fw fa-clock"></i> <span>Today Attendance</span>
            </a>
        </li>

        <hr class="sidebar-divider">

        <div class="sidebar-heading">
            Core Operations
        </div>

        <li class="nav-item <?php echo isActive('employee_management', $current_page); ?>">
            <a class="nav-link" href="employee_management.php">
                <i class="fas fa-fw fa-users"></i>
                <span>Employee Management</span>
            </a>
        </li>   
            
        <li class="nav-item <?php echo isActive('financial_management', $current_page); ?>">
            <a class="nav-link" href="financial_management.php">
                <i class="fas fa-fw fa-calculator"></i>
                <span>Financial Management</span>
            </a>
        </li> 

        <li class="nav-item <?php echo isActive('attendance_logs', $current_page); ?>">
            <a class="nav-link" href="attendance_logs.php">
                <i class="fas fa-fw fa-clipboard-list"></i>
                <span>Attendance Logs</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('leave_management', $current_page); ?>">
            <a class="nav-link" href="leave_management.php">
                <i class="fas fa-fw fa-calendar-check"></i>
                <span>Leave Management</span>
            </a>
        </li>
        
        <li class="nav-item <?php echo isActive('overtime_management', $current_page); ?>">
            <a class="nav-link" href="overtime_management.php">
                <i class="fas fa-business-time"></i>
                <span>Overtime Management</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('ca_management', $current_page); ?>">
            <a class="nav-link" href="ca_management.php">
                <i class="fas fa-money-bill"></i>
                <span>Cash Advance Management</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('holidays', $current_page); ?>">
            <a class="nav-link" href="holidays.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Holiday Management</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('payroll', $current_page); ?>">
            <a class="nav-link" href="payroll.php">
                <i class="fas fa-fw fa-file-invoice-dollar"></i>
                <span>Payroll Processing</span>
            </a>
        </li>

        <hr class="sidebar-divider">

        <div class="sidebar-heading">
            System & Reports
        </div>

        <li class="nav-item <?php echo isActive('reports', $current_page); ?>">
            <a class="nav-link" href="coming_soon.php?feature=Reports">
                <i class="fas fa-fw fa-chart-pie"></i>
                <span>Reports</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('policies', $current_page); ?>">
            <a class="nav-link" href="coming_soon.php?feature=Company Policies">
                <i class="fas fa-fw fa-book-open"></i>
                <span>Company Policies</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('audit_logs', $current_page); ?>">
            <a class="nav-link" href="coming_soon.php?feature=Audit Logs">
                <i class="fas fa-fw fa-history"></i>
                <span>Audit Logs</span>
            </a>
        </li>

    <?php endif; ?>


    <?php if ($user_type == 2): ?>

        <li class="nav-item <?php echo isActive('dashboard', $current_page); ?>">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-fw fa-th-large"></i> <span>Dashboard</span>
            </a>
        </li>

        <div class="sidebar-heading">
            My Account
        </div>

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

        <li class="nav-item <?php echo isActive('file_overtime', $current_page); ?>">
            <a class="nav-link" href="file_overtime.php">
                <i class="fas fa-fw fa-clock"></i>
                <span>File Overtime</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('request_ca', $current_page); ?>">
            <a class="nav-link" href="request_ca.php">
                <i class="fas fa-fw fa-money-bill"></i>
                <span>Request Cash Advance</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('request_leave', $current_page); ?>">
            <a class="nav-link" href="request_leave.php">
                <i class="fas fa-fw fa-paper-plane"></i>
                <span>Request Leave</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('balances', $current_page); ?>">
            <a class="nav-link" href="balances.php">
                <i class="fas fa-fw fa-plus-minus"></i>
                <span>Balances</span>
            </a>
        </li>

        <div class="sidebar-heading">
            Organization
        </div>

        <li class="nav-item <?php echo isActive('directory', $current_page); ?>">
            <a class="nav-link" href="coming_soon.php?feature=Team%20Directory">
                <i class="fas fa-fw fa-users"></i>
                <span>Team Directory</span>
            </a>
        </li>

        <li class="nav-item <?php echo isActive('documents', $current_page); ?>">
            <a class="nav-link" href="coming_soon.php?feature=Docs%20%26%20Policies">
                <i class="fas fa-fw fa-folder-open"></i>
                <span>Docs & Policies</span>
            </a>
        </li>
        
        <hr class="sidebar-divider mt-3">

        <li class="nav-item <?php echo isActive('help', $current_page); ?>">
            <a class="nav-link" href="coming_soon.php?feature=Help%20%26%20Support">
                <i class="far fa-fw fa-life-ring"></i>
                <span>Help & Support</span>
            </a>
        </li>

    <?php endif; ?>


    <hr class="sidebar-divider d-none d-md-block mt-4">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
    
</ul>