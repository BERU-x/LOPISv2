<?php
// employee_management.php

// --- 1. DEFINE REQUIRED ARRAYS ---
// These arrays are necessary for populating the select fields in the modal.
$genders = [0 => 'Male', 1 => 'Female'];
$employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated'];

// --- 2. SET PAGE CONFIGURATION ---
$page_title = 'Employee Management';
$current_page = 'employee_management';

// --- TEMPLATE INCLUDES ---
require 'template/header.php'; 
require 'template/sidebar.php';
require 'template/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 font-weight-bold">Employee Directory</h1>
            <p class="mb-0 text-muted">Manage your workforce records.</p>
        </div>
        
        <button class="btn btn-teal shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-user-plus me-2"></i> Add New Employee
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
            <h6 class="m-0 font-weight-bold text-gray-800"><i class="fas fa-users me-2"></i>Masterlist</h6>
            
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" id="customSearch" class="form-control bg-light border-0 small" placeholder="Search employees..." aria-label="Search">
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle" id="employeesTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-uppercase text-label text-xs font-weight-bold">
                        <tr>
                            <th class="border-0">ID</th>
                            <th class="border-0">Name & Position</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"> 
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0 p-4"> 
                <h5 class="modal-title fw-bold text-label" id="addModalLabel">
                    <i class="fas fa-user-plus me-3"></i> Add New Employee Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="addEmployeeForm" enctype="multipart/form-data">
                <div class="modal-body pt-0 px-4">
                    
                    <div class="card card-body p-4 border-0">
                        
                        <h6 class="fw-bold text-label mb-3"><i class="fas fa-id-card me-2"></i> Personal Information</h6>
                        <hr class="mt-1 mb-4">
                        <div class="row g-3">
                            
                            <div class="col-md-3">
                                <label for="employee_id" class="text-label mb-1">ID #</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                    <input type="text" name="employee_id" id="employee_id" class="form-control border-start-0 rounded-start-0" maxlength="3" required pattern="[0-9A-Za-z]{3}" title="Employee ID must be 3 characters." placeholder="001">
                                </div>
                            </div>
                            <div class="col-md-9">
                                <label for="firstname" class="text-label mb-1">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                    <input type="text" name="firstname" id="firstname" class="form-control border-start-0 rounded-start-0" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="John">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="middlename" class="text-label mb-1">Middle Name</label>
                                <input type="text" name="middlename" id="middlename" class="form-control" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" placeholder="(Optional)">
                            </div>
                            <div class="col-md-4">
                                <label for="lastname" class="text-label mb-1">Last Name</label>
                                <input type="text" name="lastname" id="lastname" class="form-control" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="Doe">
                            </div>
                            <div class="col-md-4">
                                <label for="suffix" class="text-label mb-1">Suffix</label>
                                <input type="text" name="suffix" id="suffix" class="form-control" placeholder="(Jr.)">
                            </div>

                            <div class="col-md-6">
                                <label for="birthdate" class="text-label mb-1">Birthdate</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" name="birthdate" id="birthdate" class="form-control border-start-0 rounded-start-0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="text-label mb-1">Gender</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                    <select name="gender" id="gender" class="form-select border-start-0 rounded-start-0" required>
                                        <option value="">Select</option>
                                        <?php foreach ($genders as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="contact_info" class="text-label mb-1">Contact Info</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                    <input type="tel" name="contact_info" id="contact_info" class="form-control border-start-0 rounded-start-0" maxlength="11" pattern="^09[0-9]{9}$" title="Must be 11 digits starting with 09" placeholder="Mobile (09xxxxxxxxx)">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="address" class="text-label mb-1">Full Address</label>
                                <textarea name="address" id="address" class="form-control" rows="1" required></textarea>
                            </div>
                        </div>

                        <h6 class="fw-bold text-label mt-4 mb-3"><i class="fas fa-briefcase me-2"></i> Employment Details</h6>
                        <hr class="mt-1 mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="position" class="text-label mb-1">Position</label>
                                <input type="text" name="position" id="position" class="form-control" required placeholder="Job Title">
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="text-label mb-1">Department</label>
                                <select name="department" id="department" class="form-select" required>
                                    <option value="">Select Department</option>
                                    <option value="I.T. Department">I.T. Department</option>
                                    <option value="Operations Department">Operations Department</option>
                                    <option value="Field Department">Field Department</option>
                                    <option value="Management Department">Management Department</option>
                                    <option value="CI Department">CI Department</option>
                                    <option value="Finance Department">Finance Department</option>
                                    <option value="Compliance Department">Compliance Department</option>
                                    <option value="HR Department">HR Department</option>
                                    <option value="Training Department">Training Department</option>
                                    <option value="Marketing Department">Marketing Department</option>
                                    <option value="Corporate Department">Corporate Department</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="employment_status" class="text-label mb-1">Status</label>
                                <select name="employment_status" id="employment_status" class="form-select" required>
                                    <option value="">Select Employment Status</option>
                                    <?php foreach ($employment_statuses as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h6 class="fw-bold text-label mt-4 mb-3"><i class="fas fa-camera me-2"></i> Profile Photo</h6>
                        <hr class="mt-1 mb-3">
                        <div class="col-12">
                            <input type="file" name="photo" id="photo" class="dropify" data-height="150" data-allowed-file-extensions="jpg jpeg png" data-max-file-size="2M">
                        </div>
                    </div>
                    
                </div>
                <div class="modal-footer border-top-0 p-4"> 
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Close</button>
                    <button type="submit" name="add_employee" class="btn btn-teal fw-bold shadow-sm">
                        <i class="fas fa-save me-2"></i> Save New Employee
                    </button>
                    <input type="hidden" name="form_action_type" id="add_form_action_type" value="create">
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"> 
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0 p-4"> 
                <h5 class="modal-title fw-bold text-label" id="editModalLabel">
                    <i class="fas fa-user-edit me-3"></i> Edit Employee Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="editEmployeeForm" enctype="multipart/form-data">
                <input type="hidden" name="employee_id" id="edit_employee_id_hidden">
                <input type="hidden" name="form_action_type" value="update">

                <div class="modal-body pt-0 px-4">
                    
                    <div class="card card-body p-4 border-0">
                        
                        <h6 class="fw-bold text-label mb-3"><i class="fas fa-id-card me-2"></i> Personal Information</h6>
                        <hr class="mt-1 mb-4">
                        <div class="row g-3">
                            
                            <div class="col-md-3">
                                <label for="edit_employee_id_display" class="text-label mb-1">ID #</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                    <input type="text" id="edit_employee_id_display" class="form-control border-start-0 rounded-start-0" readonly>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <label for="edit_firstname" class="text-label mb-1">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                    <input type="text" name="firstname" id="edit_firstname" class="form-control border-start-0 rounded-start-0" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="John">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="edit_middlename" class="text-label mb-1">Middle Name</label>
                                <input type="text" name="middlename" id="edit_middlename" class="form-control" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" placeholder="(Optional)">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_lastname" class="text-label mb-1">Last Name</label>
                                <input type="text" name="lastname" id="edit_lastname" class="form-control" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="Doe">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_suffix" class="text-label mb-1">Suffix</label>
                                <input type="text" name="suffix" id="edit_suffix" class="form-control" placeholder="(Jr.)">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_birthdate" class="text-label mb-1">Birthdate</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" name="birthdate" id="edit_birthdate" class="form-control border-start-0 rounded-start-0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_gender" class="text-label mb-1">Gender</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                    <select name="gender" id="edit_gender" class="form-select border-start-0 rounded-start-0" required>
                                        <option value="">Select</option>
                                        <?php foreach ($genders as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_contact_info" class="text-label mb-1">Contact Info</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                    <input type="tel" name="contact_info" id="edit_contact_info" class="form-control border-start-0 rounded-start-0" maxlength="11" pattern="^09[0-9]{9}$" title="Must be 11 digits starting with 09" placeholder="Mobile (09xxxxxxxxx)">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_address" class="text-label mb-1">Full Address</label>
                                <textarea name="address" id="edit_address" class="form-control" rows="1" required></textarea>
                            </div>
                        </div>

                        <h6 class="fw-bold text-label mt-4 mb-3"><i class="fas fa-briefcase me-2"></i> Employment Details</h6>
                        <hr class="mt-1 mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_position" class="text-label mb-1">Position</label>
                                <input type="text" name="position" id="edit_position" class="form-control" required placeholder="Job Title">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_department" class="text-label mb-1">Department</label>
                                <select name="department" id="edit_department" class="form-select" required>
                                    <option value="">Select Department</option>
                                    <option value="I.T. Department">I.T. Department</option>
                                    <option value="Operations Department">Operations Department</option>
                                    <option value="Field Department">Field Department</option>
                                    <option value="Management Department">Management Department</option>
                                    <option value="CI Department">CI Department</option>
                                    <option value="Finance Department">Finance Department</option>
                                    <option value="Compliance Department">Compliance Department</option>
                                    <option value="HR Department">HR Department</option>
                                    <option value="Training Department">Training Department</option>
                                    <option value="Marketing Department">Marketing Department</option>
                                    <option value="Corporate Department">Corporate Department</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_employment_status" class="text-label mb-1">Status</label>
                                <select name="employment_status" id="edit_employment_status" class="form-select" required>
                                    <option value="">Select Employment Status</option>
                                    <?php foreach ($employment_statuses as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h6 class="fw-bold text-label mt-4 mb-3"><i class="fas fa-camera me-2"></i> Profile Photo</h6>
                        <hr class="mt-1 mb-3">
                        <div class="col-12">
                            <input type="file" name="photo" id="photo_edit" class="dropify" data-height="150" data-allowed-file-extensions="jpg jpeg png" data-max-file-size="2M">
                        </div>

                    </div>
                </div>
                <div class="modal-footer border-top-0 p-4"> 
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Close</button>
                    <button type="submit" name="update_employee" class="btn btn-teal fw-bold shadow-sm">
                        <i class="fas fa-save me-2"></i> Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'template/footer.php'; ?>
<?php require 'scripts/employee_management_scripts.php'; ?>