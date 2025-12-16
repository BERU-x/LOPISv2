<script>
$(document).ready(function() {
    
    // Toast Config
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });

    // --- 1. LOAD MATRIX DATA ---
    function loadMatrix() {
        $.ajax({
            url: 'api/roles_management_action.php',
            type: 'POST',
            data: { action: 'fetch_matrix' },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    renderTable(res.features, res.permissions);
                } else {
                    $('#matrix-body').html('<tr><td colspan="3" class="text-center text-danger">Failed to load permissions.</td></tr>');
                }
            },
            error: function() {
                $('#matrix-body').html('<tr><td colspan="3" class="text-center text-danger">Server connection error.</td></tr>');
            }
        });
    }

    // --- 2. RENDER TABLE HTML ---
    function renderTable(features, perms) {
        let html = '';
        let currentCat = '';

        features.forEach(f => {
            // Check for Category Change
            if (f.category !== currentCat) {
                currentCat = f.category;
                html += `
                    <tr class="table-secondary">
                        <td colspan="3" class="fw-bold text-gray-800 small text-uppercase px-3">
                            <i class="fas fa-layer-group me-2"></i>${currentCat}
                        </td>
                    </tr>
                `;
            }

            // Check if permission exists in the lookup array
            // perms[f.id] is an array of usertypes (e.g. [1, 2])
            let isAdminChecked = (perms[f.id] && perms[f.id].includes(1)) ? 'checked' : '';
            let isEmpChecked   = (perms[f.id] && perms[f.id].includes(2)) ? 'checked' : '';

            html += `
                <tr>
                    <td class="px-3">
                        <div class="fw-bold text-dark">${f.description}</div>
                        <small class="text-muted font-monospace">${f.feature_code || ''}</small>
                    </td>
                    <td class="text-center bg-light">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input perm-toggle" type="checkbox" 
                                data-usertype="1" data-feature="${f.id}" ${isAdminChecked}>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input perm-toggle" type="checkbox" 
                                data-usertype="2" data-feature="${f.id}" ${isEmpChecked}>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#matrix-body').html(html);
    }

    // --- 3. LISTEN FOR TOGGLE CHANGES (Delegated Event) ---
    // Note: Use $(document).on() because .perm-toggle elements are created dynamically
    $(document).on('change', '.perm-toggle', function() {
        
        let checkbox = $(this);
        let usertype = checkbox.data('usertype');
        let featureId = checkbox.data('feature');
        let isChecked = checkbox.is(':checked') ? 1 : 0;

        checkbox.prop('disabled', true); // Prevent spam clicking

        $.ajax({
            url: 'api/roles_management_action.php',
            type: 'POST',
            data: { 
                action: 'toggle_permission', 
                usertype: usertype, 
                feature_id: featureId, 
                status: isChecked 
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Toast.fire({ icon: 'success', title: 'Saved' });
                } else {
                    checkbox.prop('checked', !isChecked); 
                    Toast.fire({ icon: 'error', title: 'Save Failed' });
                }
            },
            error: function() {
                checkbox.prop('checked', !isChecked);
                Toast.fire({ icon: 'error', title: 'Connection Error' });
            },
            complete: function() {
                checkbox.prop('disabled', false);
            }
        });
    });

    // Initial Load
    loadMatrix();
});
</script>