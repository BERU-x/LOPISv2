<?php
// Note: Requires $user_roles lookup array defined in user_management.php
if (!isset($user_roles)) {
    // Default roles (adjust if needed, but ensure IDs match user_management.php)
    $user_roles = [
        0 => 'Super Admin',
        1 => 'HR/Management',
        2 => 'Employee (Basic)',
    ];
}

// Ensure $available_employees is defined (assumed to be loaded in user_management.php)
if (!isset($available_employees)) {
    $available_employees = [];
}
?>

<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title font-weight-bold" id="addUserModalLabel">
                    <i class="fas fa-user-plus me-2"></i> Add New System User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="user_management.php" method="POST">
                <div class="modal-body">
                    
                    <h6 class="text-teal mb-3"><i class="fas fa-id-card me-2"></i> User Credentials</h6>
                    <div class="row g-3 mb-4">
                        
                        <div class="col-md-6">
                            <label for="employee_id_select" class="text-label mb-1">Select Employee ID</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                <select name="username" id="employee_id_select" class="form-select" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($available_employees as $emp): ?>
                                        <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                            <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['firstname'] . ' ' . $emp['lastname']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="login_type_selector" class="text-label mb-1">Login Identity Type</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-sign-in-alt"></i></span>
                                <select id="login_type_selector" class="form-select" required>
                                    <option value="">Select Login Type</option>
                                    <option value="corporate">Corporate Email</option>
                                    <option value="id_only">Employee ID (Field Staff)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6 login-input-container" id="corporate_email_group" style="display: none;">
                            <label for="corporate_email" class="text-label mb-1">Corporate Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="email" name="email" id="corporate_email" class="form-control" placeholder="user@company.com">
                            </div>
                        </div>

                        <div class="col-md-6 login-input-container" id="id_only_group" style="display: none;">
                            <label class="text-label mb-1">System Login ID</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                <input type="hidden" name="email" id="id_only_hidden_email_input" value="">
                                <input type="text" id="id_only_display" class="form-control" value="[Will use selected Employee ID]" disabled>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="text-label mb-1">Temporary Password</label> 
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="hidden" name="password" id="password" value="losi123" required>
                                <input type="text" class="form-control" value="[Auto-Generated: losi123]" disabled>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="role" class="text-label mb-1">Access Role</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                                <select name="role" id="role" class="form-select" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($user_roles as $id => $name): ?>
                                        <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-teal fw-bold shadow-sm">
                        <i class="fas fa-plus me-2"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('login_type_selector');
    const empIdDropdown = document.getElementById('employee_id_select');
    
    const corporateGroup = document.getElementById('corporate_email_group');
    const idOnlyGroup = document.getElementById('id_only_group');
    
    const corporateEmailInput = document.getElementById('corporate_email');
    const hiddenIdInput = document.getElementById('id_only_hidden_email_input');
    const idOnlyDisplay = document.getElementById('id_only_display');

    function toggleLoginInput(type) {
        // Hide and reset all dynamic input fields
        corporateGroup.style.display = 'none';
        idOnlyGroup.style.display = 'none';
        
        corporateEmailInput.removeAttribute('required');
        hiddenIdInput.removeAttribute('required');
        corporateEmailInput.value = '';
        hiddenIdInput.value = '';

        // Crucially, temporarily disable the 'name' attribute for the input not in use
        corporateEmailInput.removeAttribute('name');
        hiddenIdInput.removeAttribute('name');


        if (type === 'corporate') {
            corporateGroup.style.display = 'block';
            corporateEmailInput.setAttribute('required', 'required');
            corporateEmailInput.setAttribute('name', 'email'); // Re-enable name for submission
            
        } else if (type === 'id_only') {
            idOnlyGroup.style.display = 'block';
            
            // Set required on the hidden field
            hiddenIdInput.setAttribute('required', 'required');
            hiddenIdInput.setAttribute('name', 'email'); // Re-enable name for submission
            
            // Trigger update to display the selected ID
            updateIdDisplay(); 
        }
    }
    
    function updateIdDisplay() {
        // Runs only if the ID_ONLY option is selected
        if (selector.value === 'id_only') {
             const selectedId = empIdDropdown.value;
             if (selectedId) {
                hiddenIdInput.value = selectedId;
                idOnlyDisplay.value = selectedId;
             } else {
                 hiddenIdInput.value = '';
                 idOnlyDisplay.value = "Select Employee ID first";
             }
        }
    }

    // Event Listeners
    selector.addEventListener('change', (e) => toggleLoginInput(e.target.value));

    // Update the hidden email field whenever the employee ID dropdown changes (only if 'id_only' is selected)
    empIdDropdown.addEventListener('change', updateIdDisplay);

    // Initial load state: Hide both if nothing is selected
    toggleLoginInput(selector.value);
});
</script>