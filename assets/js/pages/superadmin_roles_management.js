// assets/js/pages/roles_management.js

$(document).ready(function() {
    
    // Toast Configuration (SweetAlert2 Mixin)
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // =========================================================================
    // 1. LOAD MATRIX DATA
    // =========================================================================
    function loadMatrix() {
        // Show a loading spinner in the table body while fetching
        $('#matrix-body').html('<tr><td colspan="3" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted small">Loading permissions...</div></td></tr>');

        $.ajax({
            // ⭐ UPDATED PATH
            url: '../api/superadmin/roles_management_action.php',
            type: 'POST',
            data: { action: 'fetch_matrix' },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    renderTable(res.features, res.permissions);
                } else {
                    $('#matrix-body').html('<tr><td colspan="3" class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load permissions.</td></tr>');
                }
            },
            error: function() {
                $('#matrix-body').html('<tr><td colspan="3" class="text-center text-danger py-3"><i class="fas fa-wifi me-2"></i>Server connection error.</td></tr>');
            }
        });
    }

    // =========================================================================
    // 2. RENDER TABLE HTML
    // =========================================================================
    function renderTable(features, perms) {
        let html = '';
        let currentCat = '';

        if (features.length === 0) {
            $('#matrix-body').html('<tr><td colspan="3" class="text-center text-muted">No features defined in the system.</td></tr>');
            return;
        }

        features.forEach(f => {
            // Check for Category Change (Group headers)
            if (f.category !== currentCat) {
                currentCat = f.category;
                html += `
                    <tr class="table-secondary border-bottom border-secondary">
                        <td colspan="3" class="fw-bold text-dark small text-uppercase px-3 py-2">
                            <i class="fas fa-layer-group me-2 text-secondary"></i>${currentCat}
                        </td>
                    </tr>
                `;
            }

            // Check if permission exists in the lookup array
            // perms[f.id] is an array of usertypes (e.g. [1, 2])
            let isAdminChecked = (perms[f.id] && perms[f.id].includes(1)) ? 'checked' : '';
            let isEmpChecked   = (perms[f.id] && perms[f.id].includes(2)) ? 'checked' : '';

            html += `
                <tr class="align-middle">
                    <td class="px-3 py-3">
                        <div class="fw-bold text-dark mb-0">${f.description}</div>
                        <small class="text-muted font-monospace" style="font-size: 0.75rem;">${f.feature_code || ''}</small>
                    </td>
                    <td class="text-center bg-light border-start border-end">
                        <div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input perm-toggle shadow-sm" type="checkbox" 
                                style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                data-usertype="1" data-feature="${f.id}" ${isAdminChecked}>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input perm-toggle shadow-sm" type="checkbox" 
                                style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                data-usertype="2" data-feature="${f.id}" ${isEmpChecked}>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#matrix-body').html(html);
    }

    // =========================================================================
    // 3. LISTEN FOR TOGGLE CHANGES (Delegated Event)
    // =========================================================================
    $(document).on('change', '.perm-toggle', function() {
        
        let checkbox = $(this);
        let usertype = checkbox.data('usertype');
        let featureId = checkbox.data('feature');
        let isChecked = checkbox.is(':checked') ? 1 : 0;

        // Visual Feedback: Temporarily disable input
        checkbox.prop('disabled', true); 
        // Optional: Add a spinner or opacity change if desired

        $.ajax({
            // ⭐ UPDATED PATH
            url: '../api/superadmin/roles_management_action.php',
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
                    Toast.fire({ icon: 'success', title: 'Permission Updated' });
                } else {
                    // Revert check state on logic error
                    checkbox.prop('checked', !isChecked); 
                    Toast.fire({ icon: 'error', title: 'Update Failed: ' + (res.message || 'Unknown error') });
                }
            },
            error: function() {
                // Revert check state on network error
                checkbox.prop('checked', !isChecked);
                Toast.fire({ icon: 'error', title: 'Connection Error' });
            },
            complete: function() {
                // Re-enable input
                checkbox.prop('disabled', false);
            }
        });
    });

    // Initial Load
    loadMatrix();
});