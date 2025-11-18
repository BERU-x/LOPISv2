<?php
// Note: Requires lookup arrays from employee_management.php
if (!isset($genders)) { $genders = [0 => 'Male', 1 => 'Female']; }
if (!isset($employment_statuses)) { $employment_statuses = [0 => 'Probationary', 1 => 'Regular', 2 => 'Part-time', 3 => 'Contractual', 4 => 'OJT', 5 => 'Resigned', 6 => 'Terminated']; }
?>

<div class="modal fade" id="viewEmployeeModal" tabindex="-1" aria-labelledby="viewEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-teal" id="viewEmployeeModalLabel">
                    <i class="fas fa-eye me-2"></i> Employee Details: <span id="view_employee_name"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body pt-0">
                
                <div class="text-center mb-4">
                    <img id="view_employee_photo" src="" alt="Employee Photo" class="rounded-circle shadow" style="width: 120px; height: 120px; object-fit: cover;">
                    <h4 class="mt-2 mb-0 fw-bold" id="view_employee_full_name"></h4>
                    <span class="text-muted small" id="view_employee_id_display"></span>
                </div>

                <h6 class="text-primary mt-3"><i class="fas fa-id-card me-2"></i> Personal Information</h6>
                <hr class="mt-1 mb-3">
                <div class="row g-3">
                    <div class="col-md-3"><span class="text-span">Birthdate:</span> <p class="mb-0 fw-bold" id="view_birthdate"></p></div>
                    <div class="col-md-3"><span class="text-span">Gender:</span> <p class="mb-0 fw-bold" id="view_gender"></p></div>
                    <div class="col-md-3"><span class="text-span">Contact:</span> <p class="mb-0 fw-bold" id="view_contact_info"></p></div>
                    <div class="col-md-12"><span class="text-span">Address:</span> <p class="mb-0 fw-bold" id="view_address"></p></div>
                </div>

                <h6 class="text-primary mt-5"><i class="fas fa-briefcase me-2"></i> Employment Details</h6>
                <hr class="mt-1 mb-3">
                <div class="row g-3">
                    <div class="col-md-4"><span class="text-span">Position:</span> <p class="mb-0 fw-bold" id="view_position"></p></div>
                    <div class="col-md-4"><span class="text-span">Department:</span> <p class="mb-0 fw-bold" id="view_department"></p></div>
                    <div class="col-md-4"><span class="text-span">Status:</span> <p class="mb-0 fw-bold" id="view_employment_status"></p></div>
                    
                    <div class="col-md-4"><span class="text-span">Base Salary:</span> <p class="mb-0 fw-bold" id="view_salary"></p></div>
                    <div class="col-md-4"><span class="text-span">Food Allowance:</span> <p class="mb-0 fw-bold" id="view_food"></p></div>
                    <div class="col-md-4"><span class="text-span">Travel Allowance:</span> <p class="mb-0 fw-bold" id="view_travel"></p></div>
                </div>

                <h6 class="text-primary mt-5"><i class="fas fa-money-check-alt me-2"></i> Banking Information</h6>
                <hr class="mt-1 mb-3">
                <div class="row g-3">
                    <div class="col-md-4"><span class="text-span">Bank Name:</span> <p class="mb-0 fw-bold" id="view_bank_name"></p></div>
                    <div class="col-md-4"><span class="text-span">Account Type:</span> <p class="mb-0 fw-bold" id="view_account_type"></p></div>
                    <div class="col-md-4"><span class="text-span">Account Number:</span> <p class="mb-0 fw-bold" id="view_account_number"></p></div>
                </div>

            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>