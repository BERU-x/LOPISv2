<?php
// This is the Admin Sidebar
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
            <img src="../assets/images/lendell_logo.png" class="logo-small" alt="Icon Logo">
        </div>
    </a>

    <hr class="sidebar-divider my-0 mb-3">

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
        
    <li class="nav-item <?php echo isActive('attendance_management', $current_page); ?>">
        <a class="nav-link" href="attendance_management.php">
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
    
    <li class="nav-item <?php echo isActive('overtime_approval', $current_page); ?>">
        <a class="nav-link" href="overtime_approval.php">
            <i class="fas fa-business-time"></i>
            <span>Overtime Management</span>
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
        <a class="nav-link" href="reports.php">
            <i class="fas fa-fw fa-chart-pie"></i>
            <span>Reports</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('policies', $current_page); ?>">
        <a class="nav-link" href="company_policies.php">
            <i class="fas fa-fw fa-book-open"></i>
            <span>Company Policies</span>
        </a>
    </li>

    <li class="nav-item <?php echo isActive('audit_logs', $current_page); ?>">
        <a class="nav-link" href="audit_logs.php">
            <i class="fas fa-fw fa-history"></i>
            <span>Audit Logs</span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block mt-4">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>