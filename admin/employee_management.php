<?php
// employee_management.php

// Set configuration
$page_title = 'Employee Management';
$current_page = 'employee_management';

// --- DATABASE CONNECTION ---
require 'template/header.php'; 

// --- REQUIRE MODEL ---
require 'models/employee_model.php'; 

// --- 4. HANDLE FORM SUBMISSION (CREATE NEW EMPLOYEE) ---
if (isset($_POST['add_employee'])) {
    
    // 1. Basic Validation and Sanitization
    $data = [
        'employee_id' => trim($_POST['employee_id']),
        'firstname'   => trim($_POST['firstname']),
        'middlename'  => trim($_POST['middlename']), // [NEW] Middle Name
        'lastname'    => trim($_POST['lastname']),
        'suffix'      => trim($_POST['suffix'] ?? ''),
        'address'     => trim($_POST['address']),
        'birthdate'   => $_POST['birthdate'],
        'contact_info'=> trim($_POST['contact_info'] ?? null),
        'gender'      => (int)$_POST['gender'],
        'position'    => trim($_POST['position']),
        'department'  => trim($_POST['department']),
        'employment_status' => (int)$_POST['employment_status'],
        
        // [UPDATED] Financial Mapping to tbl_compensation columns
        'daily_rate'        => (float)$_POST['daily_rate'],       // [NEW] Critical for Payroll
        'monthly_rate'      => (float)$_POST['monthly_rate'],     // Was salary
        'food_allowance'    => (float)($_POST['food_allowance'] ?? 0), // Was food
        'transpo_allowance' => (float)($_POST['transpo_allowance'] ?? 0), // Was travel
        
        'bank_name'      => trim($_POST['bank_name']),
        'account_type'   => trim($_POST['account_type'] ?? 'Savings'),
        'account_number' => trim($_POST['account_number'] ?? null),
    ];
    
    $photo = null; 
    
    // 2. Photo Upload Handling
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = '../assets/images/';
        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        // Basic validation for image type
        if(in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png'])) {
            $new_file_name = uniqid('emp_') . '.' . $file_ext;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                $photo = $new_file_name;
            }
        }
    }

    // 3. Execute CREATE function from model
    if (create_new_employee($pdo, $data, $photo)) {
        $_SESSION['message'] = "Employee {$data['firstname']} {$data['lastname']} added successfully!";
    } else {
        $_SESSION['error'] = "Error adding employee. Please try again.";
    }
    
    header("Location: employee_management.php");
    exit;
}

// --- LOOKUP DATA ---
$genders = [0 => 'Male', 1 => 'Female'];
$employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];

function getStatusText($statusId, $array) {
    return $array[$statusId] ?? 'N/A';
}

// --- FETCH DATA (READ OPERATION) ---
$employees = get_all_employees($pdo);


// --- INCLUDE TEMPLATES ---
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Employee Directory (<?php echo count($employees); ?>)</h1>
        
        <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="fas fa-user-plus me-2"></i> Add New Employee
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-gray-800">Employee List</h6>
            
            <div class="input-group" style="max-width: 300px;">
                <input type="text" class="form-control small" id="searchInput" placeholder="Search by name or ID..." aria-label="Search">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle" id="employeesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Daily Rate</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                <tbody id="employeeTableBody">
                    <?php foreach ($employees as $emp): ?>
                    <tr class="employee-row">
                        <td class="fw-bold text-secondary"><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                        
                        <?php
                            $full_name = htmlspecialchars($emp['firstname'] . ' ' . ($emp['middlename'] ? $emp['middlename'].' ' : '') . $emp['lastname']);
                            $sort_name = htmlspecialchars($emp['lastname'] ?? '') . ', ' . htmlspecialchars($emp['firstname'] ?? '');
                        ?>
                        <td data-order="<?php echo $sort_name; ?>" data-search="<?php echo $full_name; ?>">
                            <div class="d-flex align-items-center">
                                <img src="../assets/images/<?php echo htmlspecialchars($emp['photo'] ?? 'default.png'); ?>" 
                                    alt="Photo" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                <div>
                                    <div class="fw-bold text-dark">
                                        <?php echo $full_name; ?>
                                    </div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($emp['department'] . ' • ' . $emp['position']); ?></div>
                                </div>
                            </div>
                        </td>
                        
                        <td class="fw-bold text-teal">
                            ₱<?php echo number_format($emp['daily_rate'] ?? 0, 2); ?>
                            <div class="small text-muted fw-normal" style="font-size: 0.75rem;">
                                Monthly: <?php echo number_format($emp['monthly_rate'] ?? 0, 0); ?>
                            </div>
                        </td>
                        
                        <td>
                            <?php 
                            $status_text = getStatusText((int)$emp['employment_status'], $employment_statuses);
                            $badge_class = match((int)$emp['employment_status']) {
                                1 => 'bg-success',
                                2 => 'bg-warning text-dark',
                                5 => 'bg-danger',
                                6 => 'bg-dark',
                                default => 'bg-secondary',
                            };
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td>
                            <?php $db_id = $emp['id'] ?? 0; ?>
                            <a href="edit_employee.php?id=<?php echo htmlspecialchars($db_id); ?>" 
                                class="btn btn-sm btn-outline-teal me-1" 
                                title="Edit Employee Details">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php 
// employee_management.php (towards the end of the file)
$message_type = null;
$message_text_json = null; // Use a new variable name for clarity

if (isset($_SESSION['message'])) {
    $message_type = 'success';
    // ✅ FIX: Safely encode the message string for JavaScript
    $message_text_json = json_encode($_SESSION['message']); 
    unset($_SESSION['message']);
} elseif (isset($_SESSION['error'])) {
    $message_type = 'error';
    // ✅ FIX: Safely encode the error string for JavaScript
    $message_text_json = json_encode($_SESSION['error']);
    unset($_SESSION['error']);
}

if ($message_type): 
?>
<script>
    Swal.fire({
        toast: true,
        icon: '<?php echo $message_type; ?>',
        // CRITICAL: Must be outputting the clean JSON string without surrounding 'quotes'
        title: <?php echo $message_text_json; ?>, 
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

<?php
// Ensure this file (functions/add_employee.php) contains the UPDATED MODAL CODE I provided earlier
require 'functions/add_employee.php';

// DataTables initialization for #employeesTable and #searchInput handling 
// MUST BE IN THE FOOTER NOW, as confirmed in the previous step.
require 'template/footer.php'; 
?>