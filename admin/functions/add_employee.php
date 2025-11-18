<?php
// Note: This file requires $genders, $employment_statuses arrays to be defined in the main scope (employee_management.php)

// Check if $genders or $employment_statuses are defined, if not, define defaults to prevent errors
if (!isset($genders)) { $genders = [0 => 'Male', 1 => 'Female']; }
if (!isset($employment_statuses)) { $employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated']; }

?>

<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered"> 
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0 p-4"> 
                <h5 class="modal-title fw-bold text-teal" id="addEmployeeModalLabel">
                    <i class="fas fa-user-plus me-3"></i> Add New Employee Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="employee_management.php?action=create" method="POST" enctype="multipart/form-data">
                <div class="modal-body pt-0 px-4">
                    
                    <div class="row g-4">
                        
                        <div class="col-lg-6">
                            <div class="card card-body p-4 border-0 h-100">
                                <h6 class="fw-bold text-teal mb-3">
                                    <i class="fas fa-id-card me-2"></i> Personal Information
                                </h6>
                                <hr class="mt-1 mb-4">
                                <div class="row g-3">
                                    
                                    <div class="col-md-4">
                                        <label for="employee_id" class="text-label mb-1">ID #</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                            <input type="text" name="employee_id" id="employee_id" class="form-control border-start-0 rounded-start-0" maxlength="3" required pattern="[0-9]{3}" title="Employee ID must be 3 numbers." placeholder="Employee ID (3-digit)">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="firstname" class="text-label mb-1">First Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                            <input type="text" name="firstname" id="firstname" class="form-control border-start-0 rounded-start-0" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="John">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <label for="lastname" class="text-label mb-1">Last Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                            <input type="text" name="lastname" id="lastname" class="form-control border-start-0 rounded-start-0" oninput="this.value=this.value.charAt(0).toUpperCase()+this.value.slice(1)" required placeholder="Doe">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="suffix" class="text-label mb-1">Suffix</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                            <input type="text" name="suffix" id="suffix" class="form-control border-start-0 rounded-start-0" placeholder="(Jr., III)">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="birthdate" class="text-label mb-1">Birthdate</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                            <input type="date" name="birthdate" id="birthdate" class="form-control border-start-0 rounded-start-0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="gender" class="text-label mb-1">Gender</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                            <select name="gender" id="gender" class="form-select border-start-0 rounded-start-0" required>
                                                <option value="">Select</option>
                                                <?php foreach ($genders as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="contact_info" class="text-label mb-1">Contact Info</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                            <input type="tel" name="contact_info" id="contact_info" class="form-control border-start-0 rounded-start-0" maxlength="11" pattern="^09[0-9]{9}$" title="Must be 11 digits starting with 09" placeholder="Mobile (09xxxxxxxxx)">
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="address" class="text-label mb-1">Full Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-home"></i></span>
                                            
                                            <textarea name="address" id="address" class="form-control border-start-0 rounded-start-0" 
                                                    rows="3" required></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 mt-4">
                                        <h6 class="fw-bold text-teal mb-3">
                                            <i class="fas fa-camera me-2"></i> Profile Photo
                                        </h6>
                                        <hr class="mt-1 mb-3">
                                        <label class="text-label" for="photo">Upload Image (Max 2MB, JPG/PNG)</label>
                                        <input type="file" name="photo" id="photo" class="dropify" data-height="100" data-allowed-file-extensions="jpg jpeg png" data-max-file-size="2M" data-default-file="">
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
                                        <label for="position" class="text-label mb-1">&nbsp;</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                                            <input type="text" name="position" id="position" class="form-control border-start-0 rounded-start-0" required placeholder="Position Title">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="department" class="text-label mb-1">&nbsp;</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                                            <input type="text" name="department" id="department" class="form-control border-start-0 rounded-start-0" required placeholder="Department">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="employment_status" class="text-label mb-1">&nbsp;</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-clipboard-user"></i></span>
                                            <select name="employment_status" id="employment_status" class="form-select border-start-0 rounded-start-0" required aria-placeholder="Employment Status">
                                                <option value="">Select Employment Status</option>
                                                <?php foreach ($employment_statuses as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <h6 class="text-label mt-4 mb-1 col-12">Compensation Details</h6>
                                    
                                    <div class="col-12">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-hand-holding-dollar"></i></span>
                                            <input type="number" name="salary" id="salary" class="form-control border-start-0 rounded-start-0" min="0" required placeholder="Base Salary (Monthly)">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-bowl-food"></i></span>
                                            <input type="number" name="food" id="food" class="form-control border-start-0 rounded-start-0" min="0" placeholder="Food Allowance">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-route"></i></span>
                                            <input type="number" name="travel" id="travel" class="form-control border-start-0 rounded-start-0" min="0" placeholder="Travel/Transportation Allowance">
                                        </div>
                                    </div>
                                </div>
                                
                                <h6 class="fw-bold text-teal mt-5 mb-3">
                                    <i class="fas fa-money-check-alt me-2"></i> Banking Information
                                </h6>
                                <hr class="mt-1 mb-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-university"></i></span>
                                            <input type="text" name="bank_name" id="bank_name" class="form-control border-start-0 rounded-start-0" placeholder="Bank Name">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                                            <input type="text" name="account_type" id="account_type" class="form-control border-start-0 rounded-start-0" placeholder="Account Type (e.g., Savings)">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                            <input type="text" name="account_number" id="account_number" class="form-control border-start-0 rounded-start-0" placeholder="Bank Account Number">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 p-4"> 
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Close</button>
                    <button type="submit" name="add_employee" class="btn btn-teal fw-bold shadow-sm">
                        <i class="fas fa-save me-2"></i> Save New Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>