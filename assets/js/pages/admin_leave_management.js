/**
 * Leave Management Controller
 * Handles Leave requests, approvals, and dynamic UI updates.
 */

// ==============================================================================
// 1. GLOBAL STATE & UI HELPERS
// ==============================================================================
var leaveTable; 

/**
 * 1.1 HELPER: Updates the Topbar Status (Text + Dot Color)
 */
function updateSyncStatus(state) {
    const $dot = $('.live-dot');
    const $text = $('#last-updated-time');
    const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    $dot.removeClass('text-success text-warning text-danger');

    if (state === 'loading') {
        $text.text('Syncing...');
        $dot.addClass('text-warning'); 
    } 
    else if (state === 'success') {
        $text.text(`Synced: ${time}`);
        $dot.addClass('text-success'); 
    } 
    else {
        $text.text(`Failed: ${time}`);
        $dot.addClass('text-danger');  
    }
}

// 1.2 MASTER REFRESHER TRIGGER
window.refreshPageContent = function(isManual = false) {
    if (leaveTable) {
        if(isManual) {
            $('#refreshIcon').addClass('fa-spin');
            updateSyncStatus('loading');
        }
        leaveTable.ajax.reload(null, false);
    }
};

// ==============================================================================
// 2. INITIALIZATION
// ==============================================================================
$(document).ready(function() {

    // 2.1 POPULATE EMPLOYEE DROPDOWN (Active Employees Only)
    $.ajax({
        url: '../api/admin/leave_action.php?action=fetch_employees',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            let options = '<option value="">-- Choose Employee --</option>';
            data.forEach(emp => {
                options += `<option value="${emp.employee_id}">${emp.lastname}, ${emp.firstname}</option>`;
            });
            $('#empDropdown').html(options);
        }
    });

    // 2.2 INITIALIZE DATATABLE
    if ($('#leaveTable').length) {
        leaveTable = $('#leaveTable').DataTable({
            processing: true,
            serverSide: false, 
            ordering: true, 
            dom: 'rtip', 
            ajax: {
                url: "../api/admin/leave_action.php?action=fetch",
                type: "GET",
                dataSrc: function (json) {
                    // Auto-update Stats Cards on Dashboard
                    if (json.stats) {
                        $('#stat-pending').text(json.stats.pending);
                        $('#stat-approved').text(json.stats.approved);
                    }
                    return json.data; 
                }
            },
            drawCallback: function() {
                updateSyncStatus('success');
                setTimeout(() => $('#refreshIcon').removeClass('fa-spin'), 500);
            },
            order: [[ 1, "desc" ]], // Order by Request Date primarily
            columns: [
                // Col: Employee Profile
                {
                    data: null,
                    className: "align-middle",
                    render: function(data, type, row) {
                        let photo = row.photo ? `../assets/images/users/${row.photo}` : '../assets/images/users/default.png';
                        return `
                            <div class="d-flex align-items-center">
                                <img src="${photo}" class="rounded-circle me-3 border shadow-sm" 
                                    style="width: 40px; height: 40px; object-fit: cover;"
                                    onerror="this.src='../assets/images/users/default.png'">
                                <div>
                                    <div class="fw-bold text-dark mb-0">${row.firstname} ${row.lastname}</div>
                                    <div class="small text-muted">${row.department}</div>
                                </div>
                            </div>`;
                    }
                },
                // Col: Leave Period & Type
                {
                    data: null,
                    className: "align-middle",
                    render: function(data, type, row) {
                        let start = new Date(row.start_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                        let end = new Date(row.end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
                        return `
                            <div class="d-flex flex-column">
                                <span class="badge bg-light text-primary border mb-1" style="width:fit-content">${row.leave_type}</span>
                                <div class="small text-muted fw-bold">
                                    <i class="fa-solid fa-calendar-day me-1"></i> ${start} - ${end}
                                </div>
                            </div>`;
                    }
                },
                // Col: Days Count
                { 
                    data: "days_count", 
                    className: "fw-bold text-center text-dark align-middle",
                    render: d => `${d} Day${d > 1 ? 's' : ''}`
                },
                // Col: Status (Soft Badges)
                {
                    data: "status",
                    className: "text-center align-middle",
                    render: function(data) {
                        if(data == 0) return '<span class="badge bg-soft-warning text-warning border border-warning px-3 rounded-pill">Pending</span>';
                        if(data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 rounded-pill">Approved</span>';
                        return '<span class="badge bg-soft-danger text-danger border border-danger px-3 rounded-pill">Rejected</span>';
                    }
                },
                // Col: Actions
                {
                    data: null,
                    className: "text-center align-middle",
                    render: function(data, type, row) {
                        return `
                            <button onclick="viewDetails(${row.leave_id})" 
                                class="btn btn-sm btn-outline-teal fw-bold shadow-sm" 
                                data-bs-toggle="modal" data-bs-target="#detailsModal">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Review
                            </button>`;
                    }
                }
            ],
            language: { emptyTable: "No leave records available." }
        });
    }

    // 2.3 UI LISTENERS
    $('#customSearch').on('keyup', function() { if(leaveTable) leaveTable.search(this.value).draw(); });

    $('#applyLeaveForm').on('submit', function(e) {
        e.preventDefault();
        Swal.fire({ title: 'Processing Leave...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: '../api/admin/leave_action.php?action=create',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    $('#applyLeaveModal').modal('hide');
                    $('#applyLeaveForm')[0].reset();
                    Swal.fire('Success', res.message, 'success');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    $('#btn-refresh').on('click', function(e) { e.preventDefault(); window.refreshPageContent(true); });
});

// ==============================================================================
// 3. ACTION LOGIC
// ==============================================================================

function viewDetails(id) {
    const content = $('#leave-details-content');
    const footer = $('#modal-footer-actions');
    
    content.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div></div>');
    footer.find('.btn-action').remove(); 

    $.post('../api/admin/leave_action.php?action=get_details', { leave_id: id }, function(res) {
        if(res.status === 'success') { 
            const d = res.details;
            
            let badgeClass = d.status == 0 ? 'warning' : (d.status == 1 ? 'success' : 'danger');
            let badgeText = d.status == 0 ? 'Pending' : (d.status == 1 ? 'Approved' : 'Rejected');

            let html = `
                <div class="row align-items-center">
                    <div class="col-md-4 text-center border-end">
                        <img src="../assets/images/users/${d.photo || 'default.png'}" class="rounded-circle border shadow-sm mb-2" 
                            style="width: 85px; height: 85px; object-fit: cover;" onerror="this.src='../assets/images/users/default.png'">
                        <h6 class="fw-bold mb-0">${d.firstname} ${d.lastname}</h6>
                        <span class="text-muted small">${d.position}</span>
                    </div>
                    <div class="col-md-8 ps-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold text-uppercase text-teal mb-0">${d.leave_type}</h6>
                            <span class="badge bg-soft-${badgeClass} text-${badgeClass} border border-${badgeClass}">${badgeText}</span>
                        </div>
                        <p class="mb-1 small"><strong>Duration:</strong> ${d.days_count} Working Day(s)</p>
                        <p class="mb-0 small"><strong>Date Range:</strong> ${d.start_date} to ${d.end_date}</p>
                    </div>
                </div>
                <hr class="my-3">
                <p class="fw-bold small text-muted text-uppercase mb-2">Reason for Leave</p>
                <div class="bg-light p-3 rounded border font-italic small">${d.reason || 'No specific reason provided.'}</div>
            `;
            content.html(html);

            if(d.status == 0) {
                footer.prepend(`
                    <button onclick="updateStatus(${d.id}, 'reject')" class="btn btn-light-danger btn-action fw-bold me-auto">Reject</button>
                    <button onclick="updateStatus(${d.id}, 'approve')" class="btn btn-success btn-action fw-bold shadow-sm">Approve Request</button>
                `);
            }
        }
    }, 'json');
}

function updateStatus(id, action) {
    Swal.fire({
        title: action === 'approve' ? 'Approve this request?' : 'Reject this request?',
        text: "The employee will be notified via email immediately.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'approve' ? '#1cc88a' : '#e74a3b',
        confirmButtonText: 'Yes, ' + action
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Syncing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            $.post('../api/admin/leave_action.php?action=update_status', { id: id, status_action: action }, function(res) {
                if(res.status === 'success') {
                    $('#detailsModal').modal('hide');
                    Swal.fire('Updated!', res.message, 'success');
                    window.refreshPageContent(true);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}