<?php
// Set configuration
$page_title = 'Edit Employee';
$current_page = 'employee_management';

// --- DATABASE CONNECTION & AUTHENTICATION ---
require 'template/header.php'; 

// --- REQUIRE MODEL & LOOKUP DATA ---
require_once 'models/employee_model.php'; 

// Define lookup arrays (must match those in employee_management.php)
$genders = [0 => 'Male', 1 => 'Female'];
$employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];

// 🔥 ADDED: Hardcoded Department Lookup Array
$departments = [
    'I.T. Department',
    'Operations Department',
    'Field Department',
    'Management Department',
    'CI Department',
    'Finance Department',
    'Compliance Department',
    'HR Department',
    'Training Department',
    'Marketing Department',
    'Corporate Department',
];


$employee = null;
$error = '';
$success = '';

// --- 1. HANDLE EMPLOYEE ID RETRIEVAL ---
$employee_id = $_GET['id'] ?? null;

// FIX: Check if the ID is not numeric or if its integer value is less than or equal to 0
if (!is_numeric($employee_id) || (int)$employee_id <= 0) {
    $error = "Invalid employee ID provided.";
    $employee_id = 0; // Reset ID if invalid
} else {
    // Fetch employee data from the database
    $employee = getEmployeeById($pdo, $employee_id); 

    if (!$employee) {
        $error = "Employee not found.";
    }
}
// --- 2. HANDLE FORM SUBMISSION (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_employee'])) {
    if (!$employee) {
        $error = "Cannot update: Employee record is missing.";
    } else {
        // Collect and sanitize ALL form data fields
        $updated_data = [
            'employee_id' => trim($_POST['employee_id']),
            'firstname' => trim($_POST['firstname']),
            'lastname' => trim($_POST['lastname']),
            'suffix' => trim($_POST['suffix'] ?? ''),
            'address' => trim($_POST['address']),
            'birthdate' => $_POST['birthdate'],
            'contact_info' => trim($_POST['contact_info'] ?? null),
            'gender' => (int)$_POST['gender'],
            'position' => trim($_POST['position']),
            'department' => trim($_POST['department']), // Department is now a selected string
            'employment_status' => (int)$_POST['employment_status'],
            'salary' => (int)$_POST['salary'],
            'food' => (int)($_POST['food'] ?? 0),
            'travel' => (int)($_POST['travel'] ?? 0),
            'bank_name' => trim($_POST['bank_name']),
            'account_type' => trim($_POST['account_type']),
            'account_number' => trim($_POST['account_number'] ?? null),
            // START: Keep the existing photo filename by default
            'photo' => $employee['photo'] ?? null, 
        ];
        
        // --- Process Image Upload (FIXED LOGIC) ---
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            
            // Define the upload directory relative to this script
            // NOTE: This assumes 'edit_employee.php' is in a subdirectory (like 'admin/functions')
            $upload_dir = '../assets/images/'; 
            
            $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            // Ensure the extension is safe before continuing (Optional, but highly recommended)
            if (!in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png'])) {
                 $error = "Invalid file type uploaded. Only JPG and PNG are allowed.";
            } else {
                $new_file_name = uniqid('emp_') . '.' . $file_ext;
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    
                    // 1. New file uploaded successfully, update the data array
                    $updated_data['photo'] = $new_file_name;

                    // 2. CLEANUP: Delete the old photo if one existed
                    $old_photo_filename = $employee['photo'] ?? null;
                    if ($old_photo_filename && $old_photo_filename != 'default.png') {
                        $old_photo_path = $upload_dir . $old_photo_filename;
                        if (file_exists($old_photo_path)) {
                            unlink($old_photo_path);
                        }
                    }
                } else {
                    $error = "Error moving the uploaded file.";
                }
            }
        }
        
        // If there are no immediate file errors, proceed to database update
        if (empty($error)) {
            // Update the employee record
            $result = updateEmployee($pdo, $employee_id, $updated_data);

            if ($result) {
                // ✅ FIX: Set the success message before redirecting
                $_SESSION['message'] = "Employee **{$updated_data['firstname']} {$updated_data['lastname']}** updated successfully!"; 
                
                header("Location: employee_management.php"); // Redirect back to list
                exit;
            } else {
                // Set error message if the database update failed
                $_SESSION['error'] = "Failed to update employee record.";
                header("Location: edit_employee.php?id={$employee_id}"); // Redirect back to show error
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
            <i class="fas fa-edit me-2"></i> Edit Employee Details: <?php echo htmlspecialchars($employee['firstname'] ?? ''); ?>
        </h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($employee): ?>
        <form action="edit_employee.php?id=<?php echo htmlspecialchars($employee_id); ?>" method="POST" enctype="multipart/form-data">
            
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($employee_id); ?>">
            
            <div class="row g-4">
                
                <div class="col-lg-6">
                    <div class="card card-body p-4 border-0 h-100">
                        <h6 class="fw-bold text-teal mb-3">
                            <i class="fas fa-id-card me-2"></i> Personal Information
                        </h6>
                        <hr class="mt-1 mb-4">
                        <div class="row g-3">
                            
                            <div class="col-md-4">
                                <label for="employee_id" class="text-label mb-1">Employee ID (3-digit)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                    <input type="text" name="employee_id" id="employee_id" class="form-control border-start-0 rounded-start-0" 
                                            maxlength="3" required pattern="[0-9]{3}" placeholder="ID #"
                                            value="<?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label for="firstname" class="text-label mb-1">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                    <input type="text" name="firstname" id="firstname" class="form-control border-start-0 rounded-start-0" 
                                            oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="John"
                                            value="<?php echo htmlspecialchars($employee['firstname'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="lastname" class="text-label mb-1">Last Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                    <input type="text" name="lastname" id="lastname" class="form-control border-start-0 rounded-start-0" 
                                            oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="Doe"
                                            value="<?php echo htmlspecialchars($employee['lastname'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="suffix" class="text-label mb-1">Suffix</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <input type="text" name="suffix" id="suffix" class="form-control border-start-0 rounded-start-0" 
                                            placeholder="(Jr., III)"
                                            value="<?php echo htmlspecialchars($employee['suffix'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="birthdate" class="text-label mb-1">Birthdate</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" name="birthdate" id="birthdate" class="form-control border-start-0 rounded-start-0" 
                                            required
                                            value="<?php echo htmlspecialchars($employee['birthdate'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="gender" class="text-label mb-1">Gender</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                    <select name="gender" id="gender" class="form-select border-start-0 rounded-start-0" required>
                                        <option value="">Select</option>
                                        <?php $current_gender = $employee['gender'] ?? null; ?>
                                        <?php foreach ($genders as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ((string)$current_gender === (string)$id) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="contact_info" class="text-label mb-1">Contact Info</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                    <input type="tel" name="contact_info" id="contact_info" class="form-control border-start-0 rounded-start-0" 
                                            maxlength="11" pattern="^09[0-9]{9}$" title="Must be 11 digits starting with 09" placeholder="Mobile (09xxxxxxxxx)"
                                            value="<?php echo htmlspecialchars($employee['contact_info'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label for="address" class="text-label mb-1">Full Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-home"></i></span>
                                    <textarea name="address" id="address" class="form-control border-start-0 rounded-start-0" 
                                             rows="3" required><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-teal mb-3">
                                    <i class="fas fa-camera me-2"></i> Profile Photo
                                </h6>
                                <hr class="mt-1 mb-3">
                                <label class="text-label" for="photo">Upload Image (Max 2MB, JPG/PNG)</label>
                                <?php $default_photo_path = '../assets/images/' . htmlspecialchars($employee['photo'] ?? ''); ?>
                                <input type="file" name="photo" id="photo" class="dropify" data-height="300" 
                                        data-allowed-file-extensions="jpg jpeg png" data-max-file-size="2M" 
                                        data-default-file="<?php echo !empty($employee['photo']) && file_exists($default_photo_path) ? $default_photo_path : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-body p-4 border-0 h-100">
                        
                        <h6 class="fw-bold text-teal mb-3">
                            <i class="fas fa-briefcase me-2"></i> Employment Details
                        </h6>
                        <hr class="mt-1 mb-4">
                        <div class="row g-3">
                            
                            <div class="col-12">
                                <label for="position" class="text-label mb-1">Position</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                                    <input type="text" name="position" id="position" class="form-control border-start-0 rounded-start-0" 
                                            required placeholder="Job Title"
                                            value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label for="department" class="text-label mb-1">Department</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                    <select name="department" id="department" class="form-select border-start-0 rounded-start-0" required>
                                        <option value="">Select Department</option>
                                        <?php $current_department = $employee['department'] ?? null; ?>
                                        <?php foreach ($departments as $name): ?>
                                            <option value="<?php echo htmlspecialchars($name); ?>" <?php echo ($current_department === $name) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="employment_status" class="text-label mb-1">Employment Status</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-clipboard-user"></i></span>
                                    <select name="employment_status" id="employment_status" class="form-select border-start-0 rounded-start-0" required aria-placeholder="Employment Status">
                                        <option value="">Select Employment Status</option>
                                        <?php $current_status = $employee['employment_status'] ?? null; ?>
                                        <?php foreach ($employment_statuses as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ((string)$current_status === (string)$id) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                                                     
                            <div class="col-12">
                                <label for="salary" class="text-label mb-1">Base Salary (Monthly)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hand-holding-dollar"></i></span>
                                    <input type="number" name="salary" id="salary" class="form-control border-start-0 rounded-start-0" 
                                            min="0" required placeholder="₱"
                                            value="<?php echo htmlspecialchars($employee['salary'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="food" class="text-label mb-1">Food Allowance</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-bowl-food"></i></span>
                                    <input type="number" name="food" id="food" class="form-control border-start-0 rounded-start-0" 
                                            min="0" placeholder="₱"
                                            value="<?php echo htmlspecialchars($employee['food'] ?? 0); ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="travel" class="text-label mb-1">Transportation Allowance</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-route"></i></span>
                                    <input type="number" name="travel" id="travel" class="form-control border-start-0 rounded-start-0" 
                                            min="0" placeholder="₱"
                                            value="<?php echo htmlspecialchars($employee['travel'] ?? 0); ?>">
                                </div>
                            </div>
                        
                            <h6 class="fw-bold text-teal mt-5 mb-3">
                                <i class="fas fa-money-check-alt me-2"></i> Banking Information
                            </h6>
                            <hr class="mt-1 mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="bank_name" class="text-label mb-1">Bank Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-university"></i></span>
                                        <input type="text" name="bank_name" id="bank_name" class="form-control border-start-0 rounded-start-0" 
                                                placeholder="e.g. Security Bank"
                                                value="<?php echo htmlspecialchars($employee['bank_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="account_type" class="text-label mb-1">Account Type</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                                        <input type="text" name="account_type" id="account_type" class="form-control border-start-0 rounded-start-0" 
                                                placeholder="e.g. Savings"
                                                value="<?php echo htmlspecialchars($employee['account_type'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-12">
                                       <label for="account_number" class="text-label mb-1">Bank Account Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                        <input type="text" name="account_number" id="account_number" class="form-control border-start-0 rounded-start-0" 
                                                placeholder="Account Number"
                                                value="<?php echo htmlspecialchars($employee['account_number'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-end">
                <a href="employee_management.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_employee" class="btn btn-teal fw-bold shadow-sm">
                    <i class="fas fa-save me-2"></i> Update Employee
                </button>
            </div>
        </form>
    <?php elseif (!$error): ?>
        <div class="alert alert-info">Please select a valid employee to edit.</div>
    <?php endif; ?>

</div>

<?php 
require 'template/footer.php';
?>