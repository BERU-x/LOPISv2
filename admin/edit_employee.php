<?php
// edit_employee.php
// Set configuration
$page_title = 'Edit Employee';
$current_page = 'employee_management';

// --- DATABASE CONNECTION & AUTHENTICATION ---
require 'template/header.php'; 

// --- REQUIRE MODEL & LOOKUP DATA ---
require_once 'models/employee_model.php'; 

// Define lookup arrays
$genders = [0 => 'Male', 1 => 'Female'];
$employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];

$departments = [
    'I.T. Department', 'Operations Department', 'Field Department', 'Management Department',
    'CI Department', 'Finance Department', 'Compliance Department', 'HR Department',
    'Training Department', 'Marketing Department', 'Corporate Department',
];

$employee = null;
$error = '';
$success = '';

// --- 1. HANDLE EMPLOYEE ID RETRIEVAL ---
$employee_id = $_GET['id'] ?? null;

if (!is_numeric($employee_id) || (int)$employee_id <= 0) {
    $error = "Invalid employee ID provided.";
    $employee_id = 0; 
} else {
    // Fetch employee data (Make sure your model performs the JOIN with tbl_compensation)
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
            'firstname'   => trim($_POST['firstname']),
            'middlename'  => trim($_POST['middlename']), // [NEW]
            'lastname'    => trim($_POST['lastname']),
            'suffix'      => trim($_POST['suffix'] ?? ''),
            'address'     => trim($_POST['address']),
            'birthdate'   => $_POST['birthdate'],
            'contact_info'=> trim($_POST['contact_info'] ?? null),
            'gender'      => (int)$_POST['gender'],
            'position'    => trim($_POST['position']),
            'department'  => trim($_POST['department']),
            'employment_status' => (int)$_POST['employment_status'],
            
            // [UPDATED] Financial Data matching tbl_compensation
            'daily_rate'        => (float)$_POST['daily_rate'],       // [NEW]
            'monthly_rate'      => (float)$_POST['monthly_rate'],     // Was salary
            'food_allowance'    => (float)($_POST['food_allowance'] ?? 0), // Was food
            'transpo_allowance' => (float)($_POST['transpo_allowance'] ?? 0), // Was travel
            
            'bank_name'      => trim($_POST['bank_name']),
            'account_type'   => trim($_POST['account_type']),
            'account_number' => trim($_POST['account_number'] ?? null),
            'photo'          => $employee['photo'] ?? null, 
        ];
        
        // --- Process Image Upload ---
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/'; 
            $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            
            if (!in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png'])) {
                 $error = "Invalid file type uploaded. Only JPG and PNG are allowed.";
            } else {
                $new_file_name = uniqid('emp_') . '.' . $file_ext;
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    $updated_data['photo'] = $new_file_name;

                    // Clean up old photo
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
        
        if (empty($error)) {
            // Update the employee record (Ensure your Model handles the separation of tables)
            $result = updateEmployee($pdo, $employee_id, $updated_data);

            if ($result) {
                $_SESSION['status_title'] = "Success!";
                $_SESSION['status'] = "Employee updated successfully."; 
                $_SESSION['status_code'] = "success";
                header("Location: employee_management.php");
                exit;
            } else {
                $error = "Failed to update employee record.";
            }
        }
    }
}

require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit me-2"></i> Edit Employee Details
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
                                <label for="employee_id" class="text-label mb-1">Employee ID</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                    <input type="text" name="employee_id" id="employee_id" class="form-control border-start-0 rounded-start-0" 
                                           maxlength="3" required pattern="[0-9]{3}" 
                                           value="<?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label for="firstname" class="text-label mb-1">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                    <input type="text" name="firstname" id="firstname" class="form-control border-start-0 rounded-start-0" 
                                           oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required 
                                           value="<?php echo htmlspecialchars($employee['firstname'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="middlename" class="text-label mb-1">Middle Name</label>
                                <input type="text" name="middlename" id="middlename" class="form-control" 
                                       oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" 
                                       value="<?php echo htmlspecialchars($employee['middlename'] ?? ''); ?>" placeholder="(Optional)">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="lastname" class="text-label mb-1">Last Name</label>
                                <input type="text" name="lastname" id="lastname" class="form-control" 
                                       oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required 
                                       value="<?php echo htmlspecialchars($employee['lastname'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="suffix" class="text-label mb-1">Suffix</label>
                                <input type="text" name="suffix" id="suffix" class="form-control" 
                                       value="<?php echo htmlspecialchars($employee['suffix'] ?? ''); ?>" placeholder="(Jr.)">
                            </div>

                            <div class="col-md-4">
                                <label for="birthdate" class="text-label mb-1">Birthdate</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" name="birthdate" id="birthdate" class="form-control border-start-0 rounded-start-0" 
                                           required value="<?php echo htmlspecialchars($employee['birthdate'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="gender" class="text-label mb-1">Gender</label>
                                <select name="gender" id="gender" class="form-select" required>
                                    <option value="">Select</option>
                                    <?php $current_gender = $employee['gender'] ?? null; ?>
                                    <?php foreach ($genders as $id => $name): ?>
                                        <option value="<?php echo $id; ?>" <?php echo ((string)$current_gender === (string)$id) ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="contact_info" class="text-label mb-1">Contact Info</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                    <input type="tel" name="contact_info" id="contact_info" class="form-control border-start-0 rounded-start-0" 
                                           maxlength="11" pattern="^09[0-9]{9}$" 
                                           value="<?php echo htmlspecialchars($employee['contact_info'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label for="address" class="text-label mb-1">Full Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-home"></i></span>
                                    <textarea name="address" id="address" class="form-control border-start-0 rounded-start-0" 
                                              rows="2" required><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <h6 class="fw-bold text-teal mb-3">
                                    <i class="fas fa-camera me-2"></i> Profile Photo
                                </h6>
                                <hr class="mt-1 mb-3">
                                <?php $default_photo_path = '../assets/images/' . htmlspecialchars($employee['photo'] ?? ''); ?>
                                <input type="file" name="photo" id="photo" class="dropify" data-height="200" 
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
                                <input type="text" name="position" id="position" class="form-control" 
                                       required value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-12">
                                <label for="department" class="text-label mb-1">Department</label>
                                <select name="department" id="department" class="form-select" required>
                                    <option value="">Select Department</option>
                                    <?php $current_department = $employee['department'] ?? null; ?>
                                    <?php foreach ($departments as $name): ?>
                                        <option value="<?php echo htmlspecialchars($name); ?>" <?php echo ($current_department === $name) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="employment_status" class="text-label mb-1">Employment Status</label>
                                <select name="employment_status" id="employment_status" class="form-select" required>
                                    <option value="">Select Status</option>
                                    <?php $current_status = $employee['employment_status'] ?? null; ?>
                                    <?php foreach ($employment_statuses as $id => $name): ?>
                                        <option value="<?php echo $id; ?>" <?php echo ((string)$current_status === (string)$id) ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                        <h6 class="fw-bold text-teal mb-3">
                            <i class="fas fa-coins me-2"></i>Compensation Details
                        </h6>
                            <hr class="mt-1 mb-4">
                                     
                            <div class="col-12">
                                <label for="daily_rate" class="text-label">Daily Rate (Per 8 Hours)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" name="daily_rate" id="daily_rate" class="form-control" 
                                           min="0" required placeholder="0.00"
                                           value="<?php echo htmlspecialchars($employee['daily_rate'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="monthly_rate" class="text-label mb-1">Monthly Rate (Reference)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" name="monthly_rate" id="monthly_rate" class="form-control" 
                                           min="0" placeholder="0.00"
                                           value="<?php echo htmlspecialchars($employee['monthly_rate'] ?? $employee['salary'] ?? 0); ?>">
                                </div>
                            </div>

                            <div class="col-6">
                                <label for="food_allowance" class="text-label mb-1">Food Allowance</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-bowl-food"></i></span>
                                    <input type="number" step="0.01" name="food_allowance" id="food_allowance" class="form-control" 
                                           min="0" placeholder="0.00"
                                           value="<?php echo htmlspecialchars($employee['food_allowance'] ?? $employee['food'] ?? 0); ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <label for="transpo_allowance" class="text-label mb-1">Transpo Allowance</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-route"></i></span>
                                    <input type="number" step="0.01" name="transpo_allowance" id="transpo_allowance" class="form-control" 
                                           min="0" placeholder="0.00"
                                           value="<?php echo htmlspecialchars($employee['transpo_allowance'] ?? $employee['travel'] ?? 0); ?>">
                                </div>
                            </div>
                        
                            <h6 class="fw-bold text-teal mt-4 mb-3">
                                <i class="fas fa-money-check-alt me-2"></i> Banking Information
                            </h6>
                            <hr class="mt-1 mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="bank_name" class="text-label mb-1">Bank Name</label>
                                    <input type="text" name="bank_name" id="bank_name" class="form-control" 
                                           placeholder="e.g. Security Bank"
                                           value="<?php echo htmlspecialchars($employee['bank_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="account_number" class="text-label mb-1">Account Number</label>
                                    <input type="text" name="account_number" id="account_number" class="form-control" 
                                           placeholder="Account Number"
                                           value="<?php echo htmlspecialchars($employee['account_number'] ?? ''); ?>">
                                </div>
                                <input type="hidden" name="account_type" value="<?php echo htmlspecialchars($employee['account_type'] ?? 'Savings'); ?>">
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