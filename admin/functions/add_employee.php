<?php
// Note: This file requires $genders, $employment_statuses arrays to be defined in the main scope

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
                                            <input type="text" name="employee_id" id="employee_id" class="form-control border-start-0 rounded-start-0" maxlength="3" required pattern="[0-9A-Za-z]{3}" title="Employee ID must be 3 characters." placeholder="001">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
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

                                    <div class="col-12">
                                        <label for="contact_info" class="text-label mb-1">Contact Info</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                            <input type="tel" name="contact_info" id="contact_info" class="form-control border-start-0 rounded-start-0" maxlength="11" pattern="^09[0-9]{9}$" title="Must be 11 digits starting with 09" placeholder="Mobile (09xxxxxxxxx)">
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="address" class="text-label mb-1">Full Address</label>
                                        <textarea name="address" id="address" class="form-control" rows="2" required></textarea>
                                    </div>
                                    
                                    <div class="col-12 mt-4">
                                        <h6 class="fw-bold text-teal mb-3"><i class="fas fa-camera me-2"></i> Profile Photo</h6>
                                        <hr class="mt-1 mb-3">
                                        <input type="file" name="photo" id="photo" class="dropify" data-height="150" data-allowed-file-extensions="jpg jpeg png" data-max-file-size="2M">
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
                                        <input type="text" name="position" id="position" class="form-control" required placeholder="Job Title">
                                    </div>
                                    <div class="col-12">
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
                                    <div class="col-12">
                                        <label for="employment_status" class="text-label mb-1">Status</label>
                                        <select name="employment_status" id="employment_status" class="form-select" required>
                                            <option value="">Select Employment Status</option>
                                            <?php foreach ($employment_statuses as $id => $name): ?><option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                <h6 class="fw-bold text-teal mt-4 mb-3">
                                    <i class="fas fa-coins me-2"></i>Compensation Details
                                </h6>
                                <hr class="mt-1 mb-4">
                                    <div class="col-12">
                                        <label for="daily_rate" class="text-label mb-1">Daily Rate (Per 8 Hours)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" step="0.01" name="daily_rate" id="daily_rate" class="form-control" min="0" required placeholder="0.00">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="monthly_rate" class="text-label mb-1">Monthly Rate (Reference Only)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" step="0.01" name="monthly_rate" id="monthly_rate" class="form-control" min="0" placeholder="0.00">
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <label for="food_allowance" class="text-label mb-1">Food Allowance</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-bowl-food"></i></span>
                                            <input type="number" step="0.01" name="food_allowance" id="food_allowance" class="form-control" min="0" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                            <label for="transpo_allowance" class="text-label mb-1">Transpo Allowance</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-route"></i></span>
                                            <input type="number" step="0.01" name="transpo_allowance" id="transpo_allowance" class="form-control" min="0" placeholder="0.00">
                                        </div>
                                    </div>

                                </div>
                                
                                <h6 class="fw-bold text-teal mt-4 mb-3">
                                    <i class="fas fa-money-check-alt me-2"></i> Banking Information
                                </h6>
                                <hr class="mt-1 mb-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="text-label mb-1">Bank Name</label>
                                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. BDO">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="text-label mb-1">Account Number</label>
                                        <input type="text" name="account_number" class="form-control" placeholder="Acct #">
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