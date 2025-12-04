<?php
// admin/profile.php

// --- 1. CONFIGURATION & SETUP ---
$page_title = 'My Profile';
$current_page = 'profile';

require 'template/header.php'; // Includes checking.php ($pdo) and starts session
require 'template/sidebar.php';
require 'template/topbar.php';

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = ''; // 'success' or 'error'

// --- 2. HANDLE FORM SUBMISSIONS ---

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // A. UPDATE PROFILE PICTURE
    if (isset($_POST['btn_update_photo'])) {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $filetype = $_FILES['profile_image']['type'];
            $filesize = $_FILES['profile_image']['size'];
            
            // Verify extension
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), $allowed)) {
                $message = "Invalid file format. Only JPG, PNG, and GIF are allowed.";
                $message_type = 'error';
            } else {
                // Generate unique name: USERID_TIMESTAMP.jpg
                $new_filename = $user_id . '_' . time() . '.' . $ext;
                $upload_path = __DIR__ . '/../assets/images/' . $new_filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Update Database (Targeting tbl_employees via employee_id)
                    $emp_id = $_SESSION['employee_id']; 
                    $stmt = $pdo->prepare("UPDATE tbl_employees SET photo = ? WHERE employee_id = ?");
                    if ($stmt->execute([$new_filename, $emp_id])) {
                        
                        // Update Session immediately so Topbar updates on refresh
                        $_SESSION['profile_picture'] = $new_filename;
                        
                        $message = "Profile picture updated successfully!";
                        $message_type = 'success';
                    } else {
                        $message = "Database update failed.";
                        $message_type = 'error';
                    }
                } else {
                    $message = "Failed to upload file. Check folder permissions.";
                    $message_type = 'error';
                }
            }
        }
    }

    // B. CHANGE PASSWORD
    if (isset($_POST['btn_change_pass'])) {
        $current_pass = $_POST['current_password'];
        $new_pass     = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if ($new_pass !== $confirm_pass) {
            $message = "New passwords do not match.";
            $message_type = 'error';
        } else {
            // Get current hash
            $stmt = $pdo->prepare("SELECT password FROM tbl_users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_row = $stmt->fetch();

            if ($user_row && password_verify($current_pass, $user_row['password'])) {
                // Hash new password
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                
                $upd = $pdo->prepare("UPDATE tbl_users SET password = ? WHERE id = ?");
                if ($upd->execute([$new_hash, $user_id])) {
                    $message = "Password changed successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Failed to update password.";
                    $message_type = 'error';
                }
            } else {
                $message = "Incorrect current password.";
                $message_type = 'error';
            }
        }
    }
}

// --- 3. FETCH CURRENT USER DATA ---
$stmt = $pdo->prepare("
    SELECT u.email, u.usertype, u.created_at,
           e.firstname, e.lastname, e.employee_id, e.department, e.position, e.photo, e.contact_info
    FROM tbl_users u
    LEFT JOIN tbl_employees e ON u.employee_id = e.employee_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Safe fallback for image
$photo_src = (!empty($profile['photo']) && file_exists(__DIR__ . '/../assets/images/' . $profile['photo'])) 
             ? '../assets/images/' . $profile['photo'] 
             : '../assets/images/default.png';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold">My Profile</h1>
    </div>

    <div class="row mb-4"> 
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow h-100"> <div class="card-body text-center p-5 d-flex flex-column justify-content-center"> 
                    <div class="position-relative d-inline-block mb-3">
                        <img class="img-profile rounded-circle shadow-lg border-white border border-4" 
                            src="<?php echo $photo_src; ?>" 
                            style="width: 160px; height: 160px; object-fit: cover;">
                        
                        <button class="btn btn-sm btn-teal rounded-circle position-absolute bottom-0 end-0 mb-2 me-2 shadow" 
                                data-bs-toggle="modal" data-bs-target="#photoModal" title="Change Photo">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>

                    <h4 class="font-weight-bold text-dark mb-1">
                        <?php echo htmlspecialchars($profile['firstname'] . ' ' . $profile['lastname']); ?>
                    </h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($profile['position'] ?? 'Administrator'); ?></p>

                    <div class="d-flex justify-content-center align-items-center gap-2 w-100">
                        <span class="badge bg-secondary px-3 py-2">ID: <?php echo htmlspecialchars($profile['employee_id']); ?></span>
                        <span class="badge bg-teal px-3 py-2"><?php echo htmlspecialchars($profile['department'] ?? 'Admin Dept'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            
            <div class="card shadow h-100"> <div class="card-header py-3 bg-white border-bottom-0">
                    <h6 class="m-0 font-weight-bold text-label">Account Information</h6>
                </div>
                <div class="card-body">
                    <form>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">Employee ID</label>
                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($profile['employee_id']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">Email Address</label>
                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">First Name</label>
                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($profile['firstname']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">Last Name</label>
                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($profile['lastname']); ?>" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">Contact Number</label>
                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($profile['contact_info'] ?? 'N/A'); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">Role</label>
                                <?php 
                                    $role_label = 'Unknown';
                                    if ($profile['usertype'] == 0) $role_label = 'Superadmin';
                                    elseif ($profile['usertype'] == 1) $role_label = 'Administrator';
                                    elseif ($profile['usertype'] == 2) $role_label = 'Employee';
                                ?>
                                <input class="form-control" type="text" value="<?php echo $role_label; ?>" readonly>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white border-bottom-0">
                    <h6 class="m-0 font-weight-bold text-label">Security Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <div class="mb-3">
                            <label class="small mb-1 font-weight-bold text-gray-600">Current Password</label>
                            <input class="form-control" type="password" name="current_password" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">New Password</label>
                                <input class="form-control" type="password" name="new_password" required minlength="6">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1 font-weight-bold text-gray-600">Confirm New Password</label>
                                <input class="form-control" type="password" name="confirm_password" required minlength="6">
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="btn_change_pass" class="btn btn-teal fw-bold">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div> </div>

<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title font-weight-bold text-gray-800">Update Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <img id="preview_image" src="<?php echo $photo_src; ?>" class="rounded-circle shadow-sm border mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                        <p class="small text-muted">Allowed formats: JPG, PNG. Max size: 2MB.</p>
                    </div>
                    <div class="mb-3">
                        <input class="form-control" type="file" name="profile_image" id="profile_image" accept="image/*" required onchange="previewFile()">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="btn_update_photo" class="btn btn-teal">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>

<script>
    // Preview Image Logic
    function previewFile() {
        const preview = document.querySelector('#preview_image');
        const file = document.querySelector('#profile_image').files[0];
        const reader = new FileReader();

        reader.addEventListener("load", function () {
            preview.src = reader.result;
        }, false);

        if (file) {
            reader.readAsDataURL(file);
        }
    }

    // Display PHP Messages via SweetAlert
    <?php if ($message != ''): ?>
        Swal.fire({
            title: "<?php echo ($message_type == 'success') ? 'Success!' : 'Error!'; ?>",
            text: "<?php echo $message; ?>",
            icon: "<?php echo $message_type; ?>",
            confirmButtonColor: "<?php echo ($message_type == 'success') ? '#20c997' : '#d33'; ?>"
        });
    <?php endif; ?>
</script>