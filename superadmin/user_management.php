<?php
// Set configuration
$page_title = 'User Management';
$current_page = 'user_management';

// --- DATABASE CONNECTION & AUTHENTICATION ---
require 'template/header.php'; 

// --- REQUIRE MODELS ---
require 'models/user_model.php'; 
require 'models/employee_model.php'; 


// --- LOOKUP DATA (FOR DISPLAY/FORMS) ---
$user_roles = [
    0 => 'Super Admin',
    1 => 'Management',
    2 => 'Employee',
];

// Helper to get role text
function getRoleText($roleId, $array) {
    return $array[$roleId] ?? 'N/A';
}

// --- FETCH EMPLOYEE DROPDOWN DATA ---
if (function_exists('get_employee_ids_for_dropdown')) {
    $available_employees = get_employee_ids_for_dropdown($pdo);
} else {
    $available_employees = [];
    error_log("Error: get_employee_ids_for_dropdown function not found.");
}


// --- HANDLE FORM SUBMISSION (CREATE/ADD NEW USER) ---
if (isset($_POST['add_user'])) {

    $default_password = "losi123";

    // 1. Collect and sanitize data
    $employee_id = trim($_POST['username']); 

    $data = [
        'username' => $employee_id,
        'email'      => trim($_POST['email']),
        'password' => password_hash($default_password, PASSWORD_DEFAULT), 
        'role' => (int)$_POST['role'],
        'status' => 1, 
    ];
    
    if (create_new_user($pdo, $data)) {
        $_SESSION['message'] = "User **{$employee_id}** created successfully! Login ID: **{$data['email']}**";
    } else {
        $_SESSION['error'] = "Error creating user. Employee ID or Login ID might already be registered.";
    }
    
    header("Location: user_management.php");
    exit;
}

// ----------------------------------------------------------------------
// --- NEW LOGIC: HANDLE URL ACTIONS (TOGGLE USER STATUS) ---
// ----------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    if ($user_id > 0) {
        $user_to_toggle = getUserById($pdo, $user_id); 
        
        if ($user_to_toggle) {
            $new_status = ((int)$user_to_toggle['status'] === 1) ? 0 : 1;
            $status_text = ($new_status === 1) ? 'Activated' : 'Deactivated';
            
            if (toggleUserStatus($pdo, $user_id, $new_status)) {
                $_SESSION['message'] = "User **{$user_to_toggle['employee_id']}** status set to **{$status_text}**."; 
            } else {
                $_SESSION['error'] = "Failed to change user status.";
            }
        } else {
            $_SESSION['error'] = "User not found.";
        }
    } else {
        $_SESSION['error'] = "Invalid user ID provided.";
    }

    header("Location: user_management.php");
    exit;
}

// --- FETCH DATA FROM tbl_users (READ OPERATION) ---
$users = get_all_users($pdo);


// --- INCLUDE TEMPLATES ---
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold">System Users (<?php echo count($users); ?>)</h1>
        
        <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-2"></i> Add New User
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-gray-800">User Access List</h6>
            
            <div class="input-group" style="max-width: 300px;">
                <input type="text" class="form-control small" id="searchInput" placeholder="Search by Employee ID or role..." aria-label="Search">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="usersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Employee ID</th> 
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th> 
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr class="user-row">
                                <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($user['employee_id']); ?></span></td> 
                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $role_text = getRoleText((int)$user['usertype'], $user_roles); 
                                    $user_type_int = (int)$user['usertype'];
                                    $role_badge = 'bg-secondary'; 

                                    switch ($user_type_int) {
                                        case 0:
                                            $role_badge = 'bg-danger'; 
                                            break;
                                        case 1:
                                            $role_badge = 'bg-primary'; 
                                            break;
                                        case 2:
                                            $role_badge = 'bg-info'; 
                                            break;
                                        default:
                                            break;
                                    }
                                    ?>
                                    <span class="d-none"><?php echo $user_type_int; ?></span>
                                    <span class="badge <?php echo $role_badge; ?>"><?php echo $role_text; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $status_text = ((int)$user['status'] === 1) ? 'Active' : 'Inactive';
                                    $status_class = ((int)$user['status'] === 1) ? 'bg-success' : 'bg-secondary';
                                    $toggle_icon = ((int)$user['status'] === 1) ? 'fas fa-toggle-on' : 'fas fa-toggle-off';
                                    ?>
                                    <span class="d-none"><?php echo (int)$user['status']; ?></span>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <?php $user_db_id = $user['id'] ?? 0; ?>
                                    
                                    <a href="edit_user.php?id=<?php echo htmlspecialchars($user_db_id); ?>" 
                                    class="btn btn-sm btn-outline-info me-1" 
                                    title="Edit User Details">
                                        <i class="fas fa-user-edit"></i>
                                    </a>
                                    
                                    <a href="user_management.php?action=toggle_status&id=<?php echo htmlspecialchars($user_db_id); ?>" 
                                    class="btn btn-sm btn-outline-secondary" 
                                    title="Toggle User Status">
                                        <i class="<?php echo $toggle_icon; ?>"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No system users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php 
// --- TOASTR MESSAGE DISPLAY ---
$message_type = null;
$message_text = null;

if (isset($_SESSION['message'])) {
    $message_type = 'success';
    $message_text = $_SESSION['message'];
    unset($_SESSION['message']);
} elseif (isset($_SESSION['error'])) {
    $message_type = 'error';
    $message_text = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($message_type): 
?>
<script>
    Swal.fire({
        toast: true,
        icon: '<?php echo $message_type; ?>',
        title: '<?php echo htmlspecialchars($message_text); ?>',
        position: 'top-end', 
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
</script>
<?php endif; ?>

<?php require 'functions/add_user_modal.php'; ?>

<?php
require 'template/footer.php';
?>