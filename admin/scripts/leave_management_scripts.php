<script>
// ==============================================================================
// 1. GLOBAL STATE & HELPER FUNCTIONS (Defined Globally)
// ==============================================================================
var leaveTable;
let spinnerStartTime = 0; // Global variable to track when the spin started

// 1.1 HELPER FUNCTION: Updates the final timestamp text
function updateLastSyncTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        second: '2-digit'
    });
    $('#last-updated-time').text(timeString);
}

// 1.2 HELPER FUNCTION: Stops the spinner safely (waits for minDisplayTime = 1000ms)
function stopSpinnerSafely() {
    const icon = $('#refresh-spinner');
    const minDisplayTime = 1000; 
    const timeElapsed = new Date().getTime() - spinnerStartTime;

    const finalizeStop = () => {
        icon.removeClass('fa-spin text-teal');
        updateLastSyncTime(); 
    };

    if (timeElapsed < minDisplayTime) {
        setTimeout(finalizeStop, minDisplayTime - timeElapsed);
    } else {
        finalizeStop();
    }
}

// 1.3 MASTER REFRESHER TRIGGER (Hook for Topbar/Buttons)
window.refreshPageContent = function() {
    // 1. Record Start Time
    spinnerStartTime = new Date().getTime(); 
    
    // 2. Start Visual feedback & Text
    $('#refresh-spinner').addClass('fa-spin text-teal');
    $('#last-updated-time').text('Syncing...');
    
    // 3. Reload Table
    if (leaveTable) {
        // Reloads the table, which triggers the drawCallback for cleanup
        leaveTable.ajax.reload(null, false);
    }
};

// ---------------------------------------------------

$(document).ready(function() {

    // 1. POPULATE EMPLOYEE DROPDOWN
    $.ajax({
        url: 'api/leave_action.php?action=fetch_employees',
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

    // 2. INITIALIZE DATATABLE
    if ($('#leaveTable').length) {
        
        if ($.fn.DataTable.isDataTable('#leaveTable')) {
            $('#leaveTable').DataTable().destroy();
        }

        leaveTable = $('#leaveTable').DataTable({
            processing: true,
            destroy: true, 
            ordering: true, 
            dom: 'rtip', 

            ajax: {
                "url": "api/leave_action.php?action=fetch",
                "type": "GET",
                "dataSrc": function (json) {
                    // Update Stats Cards
                    if (json.stats) {
                        $('#stat-pending').text(json.stats.pending);
                        $('#stat-approved').text(json.stats.approved);
                    }
                    
                    // This line MUST return the array of rows
                    return json.data; 
                }
            },
            
            // --- B. VISUAL FEEDBACK DRAW CALLBACK ---
            "drawCallback": function(settings) {
                const icon = $('#refresh-spinner');
                if (icon.hasClass('fa-spin')) {
                    stopSpinnerSafely();
                } else {
                    updateLastSyncTime(); 
                }
            },
            // ------------------------------------------

            "order": [[ 1, "desc" ]],
            "columns": [
                // Col 0: Employee
                {
                    "data": null,
                    "className": "align-middle",
                    "render": function(data, type, row) {
                        let photo = row.photo ? row.photo : 'default.png';
                        return `
                            <div class="d-flex align-items-center">
                                <img src="../assets/images/${photo}" class="rounded-circle me-3 border shadow-sm" 
                                    style="width: 40px; height: 40px; object-fit: cover;"
                                    onerror="this.src='../assets/images/default.png'">
                                <div>
                                    <div class="fw-bold text-dark">${row.firstname} ${row.lastname}</div>
                                    <div class="small text-muted">${row.department}</div>
                                </div>
                            </div>`;
                    }
                },
                // Col 1: Details
                {
                    "data": null,
                    "className": "align-middle",
                    "render": function(data, type, row) {
                        let start = new Date(row.start_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                        let end = new Date(row.end_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
                        return `
                            <div class="d-flex flex-column">
                                <span class="fw-bold small text-uppercase mb-1">${row.leave_type}</span>
                                <div class="small text-muted">
                                    <i class="fa-solid fa-calendar-days me-1"></i> ${start} 
                                    <i class="fa-solid fa-arrow-right mx-1 text-xs"></i> ${end}
                                </div>
                            </div>`;
                    }
                },
                // Col 2: Days
                { "data": "days_count", "className": "fw-bold text-center text-gray-700 align-middle" },
                // Col 3: Status (FA6 icons)
                {
                    "data": "status",
                    "className": "text-center align-middle",
                    "render": function(data) {
                        if(data == 0) return '<span class="badge bg-soft-warning text-warning border border-warning px-3 shadow-sm rounded-pill"><i class="fa-solid fa-clock me-1"></i> Pending</span>';
                        if(data == 1) return '<span class="badge bg-soft-success text-success border border-success px-3 shadow-sm rounded-pill"><i class="fa-solid fa-check me-1"></i> Approved</span>';
                        return '<span class="badge bg-soft-danger text-danger border border-danger px-3 shadow-sm rounded-pill"><i class="fa-solid fa-times me-1"></i> Rejected</span>';
                    }
                },
                // Col 4: Actions (FA6 icon)
                {
                    "data": null,
                    "className": "text-center align-middle",
                    "render": function(data, type, row) {
                        return `
                            <button onclick="viewDetails(${row.leave_id})" 
                                class="btn btn-sm btn-outline-teal shadow-sm fw-bold" 
                                data-bs-toggle="modal" data-bs-target="#detailsModal">
                                <i class="fa-solid fa-eye me-1"></i> Details
                            </button>`;
                    }
                }
            ],
            "dom": 'rtip',
            "language": { "emptyTable": "No leave requests found." }
        });
    }

    // 4. SEARCH & FORMS
    $('#customSearch').on('keyup', function() {
        if(leaveTable) leaveTable.search(this.value).draw();
    });

    $('#applyLeaveForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({ title: 'Submitting Leave...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        $.ajax({
            url: 'api/leave_action.php?action=create',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if(res.status === 'success') {
                    $('#applyLeaveModal').modal('hide');
                    $('#applyLeaveForm')[0].reset();
                    Swal.fire('Success', res.message, 'success');
                    window.refreshPageContent(); 
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server Error', 'error');
            }
        });
    });
});

// 6. VIEW DETAILS LOGIC 
function viewDetails(id) {
    const content = $('#leave-details-content');
    const footer = $('#modal-footer-actions');
    
    // Reset Modal State
    content.html('<div class="text-center py-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>');
    footer.find('.btn-action').remove(); 

    $.ajax({
        url: 'api/leave_action.php?action=get_details',
        type: 'POST',
        data: { leave_id: id },
        dataType: 'json',
        success: function(res) {
            if(res.success) {
                const d = res.details;
                
                // Status Badge Logic
                let badge = '';
                if(d.status == 0) badge = '<span class="badge bg-warning">Pending</span>';
                else if(d.status == 1) badge = '<span class="badge bg-success">Approved</span>';
                else badge = '<span class="badge bg-danger">Rejected</span>';

                let html = `
                    <div class="row">
                        <div class="col-md-5 text-center border-end">
                            <img src="../assets/images/${d.photo || 'default.png'}" class="rounded-circle border shadow-sm mb-3" 
                                style="width: 100px; height: 100px; object-fit: cover;"
                                onerror="this.src='../assets/images/default.png'">
                            <h5 class="fw-bold">${d.firstname} ${d.lastname}</h5>
                            <p class="text-muted small">${d.department}</p>
                        </div>
                        <div class="col-md-7">
                            <h6 class="fw-bold text-gray-600 mb-3">Request Overview</h6>
                            <table class="table table-sm small">
                                <tr><td class="fw-bold">Status:</td><td>${badge}</td></tr>
                                <tr><td class="fw-bold">Type:</td><td>${d.leave_type}</td></tr>
                                <tr><td class="fw-bold">Days:</td><td>${d.days_count}</td></tr>
                                <tr><td class="fw-bold">Dates:</td><td>${d.start_date} to ${d.end_date}</td></tr>
                            </table>
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-bold text-gray-600 mb-3">Reason</h6>
                    <div class="alert alert-light border small">${d.reason || 'No reason provided.'}</div>
                `;
                content.html(html);

                // Add Buttons only if Pending (0)
                if(d.status == 0) {
                    footer.append(`
                        <button onclick="updateStatus(${d.id}, 'reject')" class="btn btn-danger btn-action fw-bold shadow-sm me-2">Reject</button>
                        <button onclick="updateStatus(${d.id}, 'approve')" class="btn btn-success btn-action fw-bold shadow-sm">Approve</button>
                    `);
                }
            } else {
                content.html('<div class="alert alert-danger">Could not fetch details.</div>');
            }
        },
        error: function() {
            content.html('<div class="alert alert-danger">Server request failed. Check API endpoint.</div>');
        }
    });
}

// 7. UPDATE STATUS (Approve/Reject)
function updateStatus(id, action) {
    Swal.fire({
        title: action === 'approve' ? 'Approve Request?' : 'Reject Request?',
        text: "You can update this later if needed.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: action === 'approve' ? '#1cc88a' : '#e74a3b',
        confirmButtonText: 'Yes, ' + action
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/leave_action.php?action=update_status',
                type: 'POST',
                data: { id: id, status_action: action },
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        $('#detailsModal').modal('hide');
                        Swal.fire('Updated!', res.message, 'success');
                        window.refreshPageContent(); 
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server Error', 'error');
                }
            });
        }
    });
}
</script>