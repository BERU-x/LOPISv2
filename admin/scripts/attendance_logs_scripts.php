<script>
$(document).ready(function() {
    
    // Check if table exists
    if ($('#attendanceTable').length) {
        
        // Destroy existing if needed to prevent duplicates on reload
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
            $('#attendanceTable').DataTable().destroy();
        }

        var attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            destroy: true, 
            ordering: false, // Server handles sorting
            
            // Clean DOM (Hides default search 'f' and length 'l')
            dom: 'rtip', 

            ajax: {
                url: "api/attendance_data.php", 
                type: "GET",
                data: function (d) {
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },
            
            columns: [
                // Col 0: Employee
                { 
                    data: 'employee_name',
                    render: function(data, type, row) {
                        // Check if photo exists in row data, else use default
                        var photo = row.photo ? '../assets/images/' + row.photo : '../assets/images/default.png';
                        var id = row.employee_id ? row.employee_id : '';

                        return `
                            <div class="d-flex align-items-center">
                                <img src="${photo}" class="rounded-circle me-3 border shadow-sm" 
                                     style="width: 40px; height: 40px; object-fit: cover;" 
                                     onerror="this.src='../assets/images/default.png'">
                                <div>
                                    <div class="fw-bold text-dark">${data}</div>
                                    <div class="small text-muted">${id}</div>
                                </div>
                            </div>
                        `;
                    }
                },
                
                // Col 1: Date (Server formatted)
                { 
                    data: 'date',
                    className: "text-nowrap"
                },
                
                // Col 2: Time In (Server formatted)
                { 
                    data: 'time_in',
                    className: "fw-bold text-dark"
                },

                // Col 3: Status (Server sends HTML Badge)
                { 
                    data: 'status', 
                    className: "text-center",
                    render: function (data) {
                        return data; // Output the HTML directly
                    }
                }, 
                
                // Col 4: Time Out (Server formatted)
                { 
                    data: 'time_out',
                    className: "fw-bold text-dark",
                },

                // Col 5: Hours
                { 
                    data: 'num_hr',
                    className: "text-center fw-bold text-gray-700",
                    render: function (data) {
                        return data > 0 ? parseFloat(data).toFixed(2) : '—';
                    }
                },

                // Col 6: Overtime
                { 
                    data: 'overtime_hr',
                    render: function (data) {
                        return (data > 0) ? '+' + parseFloat(data).toFixed(2) : '—';
                    }
                }
            ],
            
            language: {
                processing: "<div class='spinner-border text-teal' role='status'><span class='visually-hidden'>Loading...</span></div>",
                emptyTable: "No attendance records found.",
                zeroRecords: "No matching records found."
            }
        });

        // --- Custom Search Binding ---
        $('#customSearch').on('keyup', function() {
            attendanceTable.search(this.value).draw();
        });

        // --- Filter Button Logic ---
        function toggleFilterButtons(isLoading) {
            $('#applyFilterBtn').prop('disabled', isLoading).html(
                isLoading ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' : '<i class="fas fa-filter me-1"></i> Apply'
            );
        }

        attendanceTable.on('processing.dt', function (e, settings, processing) {
            toggleFilterButtons(processing);
        });

        $('#applyFilterBtn').off('click').on('click', function() {
            attendanceTable.ajax.reload();
        });
        
        $('#clearFilterBtn').off('click').on('click', function() {
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            $('#customSearch').val(''); 
            attendanceTable.search('').draw(); 
            attendanceTable.ajax.reload();
        });
    }
});
</script>