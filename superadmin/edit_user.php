<?php
// Set configuration
$page_title = 'Edit System User';
$current_page = 'user_management';

// --- DATABASE CONNECTION & AUTHENTICATION ---
require 'template/header.php'; // Assume header.php includes db_connection.php and defines $pdo

// --- REQUIRE MODELS ---
require 'models/user_model.php'; 
require 'models/employee_model.php'; // Needed for employee list/dropdown

// --- LOOKUP DATA & INITIAL VARIABLES ---
$user_roles = [
    0 => 'Super Admin',
    1 => 'Management',
    2 => 'Employee',
];

$user = null;
$error = '';
$success = '';

// --- 1. HANDLE USER ID RETRIEVAL ---
$user_id = $_GET['id'] ?? null;

if (!is_numeric($user_id) || (int)$user_id <= 0) {
    $error = "Invalid user ID provided.";
    $user_id = 0;
} else {
    // Fetch user data from the database
    $user = getUserById($pdo, $user_id); 

    if (!$user) {
        $error = "User not found.";
    }
}

// --- FETCH EMPLOYEE DROPDOWN DATA ---
// Fetches ALL employees to ensure the currently linked employee is available
$all_employees = get_all_employees($pdo);


// ----------------------------------------------------------------------
// --- 2. HANDLE FORM SUBMISSION (UPDATE) ---
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    
    if (!$user) {
        $error = "Cannot update: User record is missing.";
    } else {
        // Collect and sanitize form data
        $updated_data = [
            // Employee ID should be the selected value (it's the 'username' from the form)
            'employee_id' => trim($_POST['employee_id_select']),
            'email'       => filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL),
            'usertype'    => (int)$_POST['role'],
            'status'      => (int)$_POST['status'],
            'password'    => $_POST['new_password'] ?? null,
        ];
        
        // --- Password Handling ---
        $password_to_hash = null;
        if (!empty($updated_data['password'])) {
            // Check if password meets minimum length (optional client-side validation is better)
            if (strlen($updated_data['password']) < 6) {
                $error = "New password must be at least 6 characters long.";
            } else {
                $password_to_hash = password_hash($updated_data['password'], PASSWORD_DEFAULT);
                $updated_data['password'] = $password_to_hash;
            }
        } else {
            // If the field is empty, remove the key so the model doesn't try to update it to an empty string
            unset($updated_data['password']);
        }

        
        // If no errors occurred during validation
        if (empty($error)) {
            // Update the user record
            // NOTE: The updateUserSettings model function expects 'usertype', not 'role'
            $updated_data['usertype'] = $updated_data['usertype']; // Ensure key name match for model
            
            $result = updateUserSettings($pdo, $user_id, $updated_data);

            if ($result) {
                $_SESSION['message'] = "User **{$updated_data['employee_id']}** details updated successfully!";
                header("Location: user_management.php");
                exit;
            } else {
                $_SESSION['error'] = "Failed to update user record. Check if the Employee ID already belongs to another user.";
                header("Location: edit_user.php?id={$user_id}");
                exit;
            }
        }
    }
}

// --- INCLUDE TEMPLATES ---
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-edit me-2"></i> Edit System User: <?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?>
        </h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($user): ?>
        <form action="edit_user.php?id=<?php echo htmlspecialchars($user_id); ?>" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_id); ?>">
            
            <div class="row g-4 justify-content-center">
                <div class="col-lg-8">
                    <div class="card card-body p-4 border-0 shadow">
                        
                        <h6 class="fw-bold text-teal mb-3">User Access and Identity</h6>
                        <hr class="mt-1 mb-4">
                        
                        <div class="row g-3">
                            
                            <div class="col-md-6">
                                <label for="employee_id_select" class="text-label mb-1">Employee ID (FK)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                    <select name="employee_id_select" id="employee_id_select" class="form-select" required>
                                        <option value="">Select Employee</option>
                                        <?php 
                                        $current_employee_id = $user['employee_id'] ?? null;
                                        foreach ($all_employees as $emp): 
                                            // Determine if this is the currently linked employee
                                            $selected = ($current_employee_id === $emp['employee_id']) ? 'selected' : '';
                                            // Check if this ID is currently used by a *different* user account
                                            $is_used = false;
                                            // (Optimization: This check would require a database query to check other user IDs, 
                                            // but for simplicity here, we assume the FK prevents setting a duplicate ID, 
                                            // and just ensure the current user's ID is selected.)
                                        ?>
                                            <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['firstname'] . ' ' . $emp['lastname']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-text">Changing this links the user account to a different employee.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="text-label mb-1">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="email" name="email" id="email" class="form-control" required placeholder="user@domain.com"
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="role" class="text-label mb-1">Access Role</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                                    <select name="role" id="role" class="form-select" required>
                                        <option value="">Select Role</option>
                                        <?php $current_role = $user['usertype'] ?? null; ?>
                                        <?php foreach ($user_roles as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ((string)$current_role === (string)$id) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="status" class="text-label mb-1">Account Status</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-circle"></i></span>
                                    <select name="status" id="status" class="form-select" required>
                                        <?php $current_status = $user['status'] ?? 0; ?>
                                        <option value="1" <?php echo ((int)$current_status === 1) ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?php echo ((int)$current_status === 0) ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            
                        </div>

                        <h6 class="fw-bold text-teal mt-5 mb-3">Reset Password</h6>
                        <hr class="mt-1 mb-4">

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="new_password" class="text-label mb-1">New Password (Leave blank to keep current)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" name="new_password" id="new_password" class="form-control" 
                                           placeholder="Enter new password (min 6 characters)" minlength="6">
                                </div>
                                <div class="form-text">The password will only be changed if you enter a value here.</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="user_management.php" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" name="update_user" class="btn btn-teal fw-bold shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </div>
        </form>
    <?php elseif (!$error): ?>
        <div class="alert alert-info text-center">Please select a valid user to edit.</div>
    <?php endif; ?>

</div>

<?php 
require 'template/footer.php';
?>