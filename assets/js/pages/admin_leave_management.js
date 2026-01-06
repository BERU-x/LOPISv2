/**
 * Leave Management Controller
 * Updated for Credit Deduction & Real-time Balance Checking
 */

if (window.LeaveControllerLoaded) {
    console.warn("Leave Controller already active.");
} else {
    window.LeaveControllerLoaded = true;
    
    var leaveTable;
    window.isProcessing = false; 
    const LEAVE_API = '../api/admin/leave_action.php';

    // ==============================================================================
    // 2. REFRESHER & CREDIT HELPERS
    // ==============================================================================
    window.refreshPageContent = function(isManual = false) {
        if (window.isProcessing) return;
        if (leaveTable && $.fn.DataTable.isDataTable('#leaveTable')) {
            window.isProcessing = true;
            if(isManual && window.AppUtility) {
                $('#refreshIcon').addClass('fa-spin');
                window.AppUtility.updateSyncStatus('loading');
            }
            leaveTable.ajax.reload(null, false);
        }
    };

    const fetchEmployeeCredits = (empId) => {
        const display = $('#creditDisplay');
        if (!empId) {
            display.addClass('d-none');
            return;
        }

        $.ajax({
            url: LEAVE_API + '?action=get_employee_credits',
            type: 'POST',
            data: { employee_id: empId },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#val_vl').text(res.credits.vacation_leave_total);
                    $('#val_sl').text(res.credits.sick_leave_total);
                    $('#val_el').text(res.credits.emergency_leave_total);
                    display.removeClass('d-none').hide().fadeIn();
                }
            }
        });
    };

    const loadEmployeeDropdown = () => {
        const dropdown = $('#empDropdown');
        if (!dropdown.length) return;

        $.ajax({
            url: LEAVE_API + '?action=fetch_employees',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let options = '<option value="">-- Choose Employee --</option>';
                if (data && data.length > 0) {
                    data.forEach(emp => {
                        options += `<option value="${emp.employee_id}">${emp.lastname}, ${emp.firstname}</option>`;
                    });
                }
                dropdown.html(options);
            }
        });
    };

    // ==============================================================================
    // 3. ACTION LOGIC: UPDATE STATUS (Approve/Reject)
    // ==============================================================================
    window.updateStatus = function(id, action) {
        const verb = action === 'approve' ? 'approve' : 'reject';
        const color = action === 'approve' ? '#28a745' : '#dc3545';
        const confirmText = action === 'approve' 
            ? "This will deduct credits from the employee's balance." 
            : "This request will be marked as rejected.";

        Swal.fire({
            title: `Are you sure you want to ${verb}?`,
            text: confirmText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: color,
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, ${verb} it!`
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                $.ajax({
                    url: LEAVE_API + '?action=update_status',
                    type: 'POST',
                    data: { id: id, status_action: action },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('Success', res.message, 'success');
                            $('#detailsModal').modal('hide'); // Close view modal if open
                            window.refreshPageContent(true);
                        } else {
                            Swal.fire('Failed', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Internal Server Error', 'error');
                    }
                });
            }
        });
    };

    window.viewDetails = function(id) {
        const content = $('#leave-details-content');
        const footer = $('#modal-footer-actions');
        
        $('#detailsModal').modal('show');
        content.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div></div>');
        footer.find('.btn-action').remove(); 

        $.post(LEAVE_API + '?action=get_details', { leave_id: id }, function(res) {
            if(res.status === 'success') { 
                const d = res.details;
                let badgeClass = d.status == 0 ? 'warning' : (d.status == 1 ? 'success' : 'danger');
                let badgeText = d.status == 0 ? 'Pending' : (d.status == 1 ? 'Approved' : 'Rejected');

                content.html(`
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center border-end">
                            <img src="../assets/images/users/${d.photo || 'default.png'}" class="rounded-circle border shadow-sm mb-2" style="width: 85px; height: 85px; object-fit: cover;">
                            <h6 class="fw-bold mb-0">${d.firstname} ${d.lastname}</h6>
                            <small class="text-muted">${d.employee_id}</small>
                        </div>
                        <div class="col-md-8 ps-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold text-uppercase text-teal mb-0">${d.leave_type}</h6>
                                <span class="badge bg-soft-${badgeClass} text-${badgeClass} border border-${badgeClass}">${badgeText}</span>
                            </div>
                            <p class="mb-1 small"><strong>Duration:</strong> ${d.days_count} Day(s)</p>
                            <p class="mb-0 small"><strong>Date Range:</strong> ${d.start_date} to ${d.end_date}</p>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="bg-light p-3 rounded border font-italic small">${d.reason || 'No reason provided.'}</div>
                `);

                if(d.status == 0) {
                    footer.prepend(`
                        <button onclick="updateStatus(${d.id}, 'reject')" class="btn btn-outline-danger btn-action fw-bold me-auto">Reject</button>
                        <button onclick="updateStatus(${d.id}, 'approve')" class="btn btn-success btn-action fw-bold shadow-sm">Approve & Deduct</button>
                    `);
                }
            }
        }, 'json');
    };

    // ==============================================================================
    // 4. INITIALIZATION
    // ==============================================================================
    $(document).ready(function() {
        loadEmployeeDropdown();

        $('#empDropdown').on('change', function() {
            fetchEmployeeCredits($(this).val());
        });

        if ($('#leaveTable').length) {
            window.isProcessing = true;
            leaveTable = $('#leaveTable').DataTable({
                processing: true,
                serverSide: false, 
                retrieve: true,
                dom: 'rtip', 
                ajax: {
                    url: LEAVE_API + "?action=fetch",
                    type: "GET",
                    dataSrc: function (json) {
                        if (json.stats) {
                            $('#stat-pending').text(json.stats.pending);
                            $('#stat-approved').text(json.stats.approved);
                        }
                        return json.data; 
                    }
                },
                drawCallback: function() {
                    if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
                    setTimeout(() => {
                        $('#refreshIcon').removeClass('fa-spin');
                        window.isProcessing = false; 
                    }, 500);
                },
                columns: [
                    { data: null, render: function(data, type, row) {
                        return `<div class="fw-bold text-dark">${row.lastname}, ${row.firstname}</div><small class="text-muted">${row.employee_id}</small>`;
                    }},
                    { data: null, render: function(data, type, row) {
                        return `<span class="badge bg-light text-primary border">${row.leave_type}</span><br><small class="text-muted"><i class="far fa-calendar-alt me-1"></i>${row.start_date}</small>`;
                    }},
                    { data: "days_count", className: "text-center fw-bold" },
                    { data: "status", className: "text-center", render: function(data) {
                        if(data == 0) return '<span class="badge bg-soft-warning text-warning border px-3 rounded-pill">Pending</span>';
                        if(data == 1) return '<span class="badge bg-soft-success text-success border px-3 rounded-pill">Approved</span>';
                        return '<span class="badge bg-soft-danger text-danger border px-3 rounded-pill">Rejected</span>';
                    }},
                    { data: null, className: "text-center", render: function(data, type, row) {
                        return `<button onclick="viewDetails(${row.leave_id})" class="btn btn-sm btn-outline-secondary fw-bold"><i class="fa-solid fa-eye me-1"></i> Details</button>`;
                    }}
                ]
            });
        }

        // --- SUBMIT HANDLER ---
        $('#applyLeaveForm').off('submit').on('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({ 
                title: 'Processing Leave...', 
                text: 'Deducting credits and notifying employee',
                allowOutsideClick: false, 
                didOpen: () => { Swal.showLoading(); } 
            });

            $.ajax({
                url: LEAVE_API + '?action=create',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        $('#applyLeaveModal').modal('hide');
                        $('#applyLeaveForm')[0].reset();
                        $('#creditDisplay').addClass('d-none');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Leave Filed',
                            text: res.message,
                            confirmButtonColor: '#0cc0df'
                        });
                        
                        window.refreshPageContent(true);
                    } else {
                        Swal.fire('Filing Failed', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Server Error', 'Could not reach the server.', 'error');
                }
            });
        });

        $('#btn-refresh').off('click').on('click', function(e) { 
            e.preventDefault(); 
            window.refreshPageContent(true); 
        });
    });
}