// Initialize persistent state globally to be accessible by the Global Sync Engine
window.selectedIds = []; 

$(document).ready(function() {
    // --- 1. Initialize DataTable ---
    var table = $('#superAttendanceTable').DataTable({
        "processing": true,
        "serverSide": true,
        "drawCallback": function() {
            // RE-SELECT: Check boxes for IDs currently in our persistent array
            $('.row-select').each(function() {
                if (window.selectedIds.includes($(this).val())) {
                    $(this).prop('checked', true);
                }
            });
            updateBulkUI();
        },
        "ajax": {
            "url": "../api/superadmin/attendance_ssp.php",
            "type": "GET",
            "data": function(d) {
                if(window.AppUtility) window.AppUtility.updateSyncStatus('loading');
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
                d.department = $('#filter_dept').val();
                d.employee_id = $('#filter_employee_id').val();
            },
            "dataSrc": function(json) {
                if(window.AppUtility) window.AppUtility.updateSyncStatus('success');
                return json.data;
            }
        },
        "columns": [
            { 
                "data": null,
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row) {
                    if (!row.raw_out || row.raw_out === '00:00:00') {
                        const isChecked = window.selectedIds.includes(row.id.toString()) ? 'checked' : '';
                        return `<input type="checkbox" class="row-select cursor-pointer" value="${row.id}" ${isChecked}>`;
                    }
                    return `<i class="fas fa-check-circle text-gray-300"></i>`;
                }
            },
            { "data": "employee_name" },
            { "data": "date" },
            { "data": "status_based",
                "render": function(data) {
                    let color = (data === 'WFH') ? 'info' : (data === 'FIELD' ? 'warning' : 'teal');
                    return `<span class="badge bg-soft-${color} text-${color} border border-${color} px-2 rounded-pill">${data}</span>`;
                }
            },
            { "data": "time_in" },
            { "data": "time_out" },
            { "data": "time_out_date" },
            { 
                "data": "attendance_status",
                "render": function(data) {
                    if (!data) return '--';
                    const statuses = data.split(', ');
                    let badges = '';
                    statuses.forEach(status => {
                        let color = 'secondary';
                        const s = status.trim();
                        if (s === 'Ontime') color = 'success';
                        else if (s === 'Late') color = 'warning';
                        else if (s === 'Undertime') color = 'info';
                        else if (s === 'Overtime') color = 'primary';
                        badges += `<span class="badge bg-soft-${color} text-${color} border border-${color} px-2 rounded-pill me-1">${s}</span>`;
                    });
                    return badges;
                }
            },
            { "data": "num_hr", "className": "text-center fw-bold" },
            { "data": "overtime_hr", "className": "text-center fw-bold" },
            { "data": "total_deduction_hr", "className": "text-center fw-bold" },
            { 
                "data": null,
                "className": "text-center",
                "orderable": false,
                "render": function(data, type, row) {
                    return `<button class="btn btn-sm btn-outline-teal edit-btn" 
                            data-id="${row.id}" data-in="${row.raw_in}" data-out="${row.raw_out}" 
                            data-outdate="${row.raw_out_date}" data-status="${row.attendance_status}">
                            <i class="fas fa-edit"></i></button>`;
                }
            }
        ],
        "dom": 'lrtip',
        "order": [[2, 'desc']], 
        "pageLength": 15
    });

    // --- 2. GLOBAL REFRESH HOOK ---
    // This allows the runGlobalSync() function in your layout to safely refresh this table
    window.refreshPageContent = function(isManual = false) {
        if (window.selectedIds.length === 0) {
            table.ajax.reload(null, false);
        } else {
            // If the sync engine tries to run while checkboxes are active, tell it we are paused
            if(window.AppUtility) window.AppUtility.updateSyncStatus('paused');
        }
    };

    // --- 3. SELECTION LOGIC ---
    $(document).on('change', '.row-select', function() {
        const id = $(this).val();
        if ($(this).is(':checked')) {
            if (!window.selectedIds.includes(id)) window.selectedIds.push(id);
        } else {
            window.selectedIds = window.selectedIds.filter(item => item !== id);
        }
        updateBulkUI();
    });

    $(document).on('change', '#selectAll', function() {
        const isChecked = $(this).prop('checked');
        $('.row-select').each(function() {
            $(this).prop('checked', isChecked).trigger('change');
        });
    });

    // Deselect Logic
    $(document).on('click', '#clearSelectionBtn', function() {
        window.selectedIds = [];
        $('#selectAll').prop('checked', false);
        $('.row-select').prop('checked', false);
        updateBulkUI();
        if (window.AppUtility) window.AppUtility.updateSyncStatus('success');
    });

    function updateBulkUI() {
        const count = window.selectedIds.length;
        $('#selectedCount').text(count);
        
        if (count > 0) {
            $('#bulkActions').removeClass('d-none');
            $('#bulkSeparator').show();
            // Communicate with the Topbar Utility
            if (window.AppUtility) window.AppUtility.updateSyncStatus('paused');
        } else {
            $('#bulkActions').addClass('d-none');
            $('#bulkSeparator').hide();
            // Return to success/live state if we were paused
            if (window.AppUtility && document.getElementById('last-updated-time').innerText.includes('Paused')) {
                window.AppUtility.updateSyncStatus('success');
            }
        }
    }

    // --- 4. Bulk Time Out Action ---
    $('#bulkTimeoutBtn').on('click', function() {
        Swal.fire({
            title: 'Bulk Time Out',
            html: `
                <div class="text-start p-2">
                    <p class="text-muted small">Clock out ${window.selectedIds.length} employees with the same time.</p>
                    <label class="form-label small fw-bold">Time Out Clock</label>
                    <input type="time" id="bulk_time" class="form-control">
                    <small class="text-muted italic">Note: System will automatically use the correct Time In date for each record.</small>
                </div>`,
            showCancelButton: true,
            confirmButtonText: 'Confirm Bulk Out',
            confirmButtonColor: '#e74a3b',
            preConfirm: () => {
                const time = document.getElementById('bulk_time').value;
                if (!time) {
                    Swal.showValidationMessage('Time Out Clock is required');
                }
                return { time };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if(window.AppUtility) window.AppUtility.updateSyncStatus('loading');
                
                $.ajax({
                    url: '../api/superadmin/attendance_ssp.php',
                    type: 'POST',
                    data: {
                        sub_action: 'bulk_timeout',
                        ids: window.selectedIds,
                        time_out: result.value.time
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('Success', res.message, 'success');
                            window.selectedIds = []; // Reset persistence
                            $('#selectAll').prop('checked', false);
                            updateBulkUI();
                            table.ajax.reload(null, false);
                        } else {
                            if(window.AppUtility) window.AppUtility.updateSyncStatus('error');
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // --- 5. Other Handlers ---
    $('#applyFilterBtn').on('click', function() { table.draw(); });
    $('#clearFilterBtn').on('click', function() {
        $('#filter_start_date, #filter_end_date, #filter_dept, #filter_employee_id').val('');
        table.draw();
    });
    
    $(document).on('click', '.edit-btn', function() {
        const btn = $(this);
        $('#edit_log_id').val(btn.data('id'));
        $('#edit_time_in').val(btn.data('in'));
        $('#edit_time_out').val(btn.data('out'));
        $('#edit_out_date').val(btn.data('outdate'));
        const statusData = btn.data('status');
        $('#edit_status').val(statusData ? statusData.split(', ') : []);
        $('#editAttendanceModal').modal('show');
    });

    $('#editAttendanceForm, #addAttendanceForm').on('submit', function(e) {
        e.preventDefault();
        const subAction = $(this).attr('id') === 'addAttendanceForm' ? 'add_manual' : 'update';
        if(window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        $.ajax({
            url: '../api/superadmin/attendance_ssp.php',
            type: 'POST',
            data: $(this).serialize() + '&sub_action=' + subAction,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    if(window.AppUtility) window.AppUtility.updateSyncStatus('success');
                    Swal.fire('Success', res.message, 'success');
                    $('.modal').modal('hide');
                    table.ajax.reload(null, false);
                }
            }
        });
    });
});