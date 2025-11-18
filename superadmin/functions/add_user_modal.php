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
                            <label for="employee_id" class="text-label mb-1">Employee ID (3-digit)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                <input type="text" 
                                       name="username" 
                                       id="employee_id" 
                                       class="form-control" 
                                       required 
                                       placeholder="Must match existing Employee ID (e.g., 001)" 
                                       maxlength="3"
                                       pattern="[0-9]{3}" 
                                       title="Must be exactly 3 digits.">
                            </div>
                            <div class="form-text">This must match a valid Employee ID.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="text-label mb-1">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="email" name="email" id="email" class="form-control" required placeholder="user@domain.com">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="password" class="text-label mb-1">Temporary Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" required placeholder="********" minlength="6">
                            </div>
                            <div class="form-text">Minimum 6 characters.</div>
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