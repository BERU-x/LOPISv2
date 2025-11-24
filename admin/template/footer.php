<?php
// admin/template/footer.php
// This file assumes it closes the main <div> tags opened by topbar.php and the <body> and <html> tags
?>
    </div>
</div>
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title font-weight-bold text-teal" id="exampleModalLabel">
                    <i class="fas fa-sign-out-alt me-2"></i>Ready to Leave?
                </h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body text-gray-600">
                Select "Logout" below if you are ready to end your current session.
            </div>

            <div class="modal-footer border-top-0">
                <button class="btn btn-light text-secondary fw-bold" type="button" data-bs-dismiss="modal">
                    Cancel
                </button>
                <a class="btn btn-teal fw-bold shadow-sm" href="../logout.php">
                    Logout
                </a>
            </div>

        </div>
    </div>
</div>

<script src="../assets/js/jquery-3.7.1.min.js"></script> 

<script src="../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

<script src="../assets/js/dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script> 

<script>
    // --- Start of Single, Clean jQuery Ready Function ---
    $(document).ready(function(){
            
    // --- DATA DEPENDENCIES ---
    const genders = {0: 'Male', 1: 'Female'};
    const employment_statuses = {
        0: 'Probationary', 1: 'Regular', 2: 'Part-time', 3: 'Contractual', 
        4: 'OJT', 5: 'Resigned', 6: 'Terminated'
    };

    // --- Dropify Initialization ---
    $('.dropify').dropify({
        messages: {
        'default': 'Drag and drop an image or click',
        'replace': 'Drag and drop or click to replace',
        'remove':  'Remove',
        'error':   'Sorry, this file is too large.'
        },
        error: {
        'fileSize': 'The file size is too big ({{ value }} max).',
        'imageFormat': 'The image format is not allowed ({{ value }} only).'
        }
    });
    
    // --- DataTables Initialization: Employee Management Table (Client-Side) ---
    var employeeTable;
    if ($('#employeesTable').length && !$.fn.DataTable.isDataTable('#employeesTable')) {
        employeeTable = $('#employeesTable').DataTable({
            "dom": 'lrtip', 
            "pageLength": 10, 
            
            // ❌ DELETED: The entire "columns": [ ... ] block. 
            // It is not needed for HTML-generated tables and causes the error.

            // ✅ KEEP: This configuration handles the sorting logic
            "columnDefs": [
                // ID Column (Index 0)
                { "type": "num", "targets": 0 },

                // Name Column (Index 1)
                // 'html' type ensures it sorts by the text content or data-order attribute
                { "type": "html", "targets": 1 }, 

                // Daily Rate (Index 2)
                // 'num-fmt' allows sorting numbers with currencies like ₱ and commas
                { "type": "num-fmt", "targets": 2 },

                // Status (Index 3)
                { "type": "html", "targets": 3 },

                // Actions (Index 4) - Disable sorting
                { "orderable": false, "targets": 4 } 
            ],
            "order": [[1, 'asc']] // Default sort by Name (Index 1)
        });

        // --- Custom Search Integration for Employee Table ---
        $('#searchInput').on('keyup', function() {
            if (employeeTable) {
                employeeTable.search(this.value).draw();
            }
        });
    }

    // ----------------------------------------------------
    // 🔥 DATA TABLES INITIALIZATION: ATTENDANCE (SERVER-SIDE PROCESSING) 🔥
    // ----------------------------------------------------

    if ($('#attendanceTable').length) {
        
        // ✅ FIX: Check if DataTable already exists. If yes, destroy it first.
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
            $('#attendanceTable').DataTable().destroy();
        }

        var attendanceTable = $('#attendanceTable').DataTable({
            processing: true,
            serverSide: true,
            // ✅ OPTIONAL: Add 'destroy: true' inside config as a backup
            destroy: true, 
            
            ajax: {
                url: "fetch/attendance_ssp.php", 
                type: "GET",
                data: function (d) {
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                }
            },
            
            columns: [
                { data: 'employee_id' },
                { data: 'employee_name' },
                { data: 'date' },
                { data: 'time_in' },
                { 
                    data: 'status', 
                    render: function (data) {
                        return data == 1 ? '<span class="badge bg-success">On Time</span>' : '<span class="badge bg-danger">Late</span>';
                    }
                }, 
                { data: 'time_out' },
                { data: 'status_out' },
                { 
                    data: 'num_hr',
                    render: function (data) {
                        return data ? parseFloat(data).toFixed(2) + 'h' : '—';
                    }
                },
                { data: 'overtime_hr' },
                { data: 'status_based' }
            ],
            
            order: [
                [2, 'desc'],
                [3, 'desc'] 
            ],
            columnDefs: [
                { 
                    targets: 2,
                    type: 'date' 
                }, 
                { 
                    targets: [7, 8],
                    render: function (data) {
                        return data ? parseFloat(data).toFixed(2) : '0.00';
                    }
                }
            ]
        });
        
        // Function to enable/disable the filter button
        function toggleFilterButtons(isLoading) {
            $('#applyFilterBtn').prop('disabled', isLoading).html(
                isLoading ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Loading...' : '<i class="fas fa-filter me-1"></i> Apply Filter'
            );
            $('#clearFilterBtn').prop('disabled', isLoading);
        }

        // Attach listeners to DataTables events:
        attendanceTable.on('processing.dt', function (e, settings, processing) {
            toggleFilterButtons(processing);
        });

        // ✅ FIX: Unbind previous click events to prevent double-clicking issues
        $('#applyFilterBtn').off('click').on('click', function() {
            attendanceTable.ajax.reload();
        });
        
        $('#clearFilterBtn').off('click').on('click', function() {
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            attendanceTable.ajax.reload();
        });
    }

    // --- SIDEBAR TOGGLE LOGIC ---
    const sidebarToggle = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');
    if (sidebarToggle) {
        sidebarToggle.forEach(button => {
        button.addEventListener('click', function(e) {
            document.body.classList.toggle('sidebar-toggled');
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
            sidebar.classList.toggle('toggled');
            }
        });
        });
    }
        
    // --- LOADER SCRIPT ---
    const loader = document.getElementById("page-loader");
    if (loader) {
        const progressBar = loader.querySelector(".progress-bar");
        const percentageText = loader.querySelector("#loader-percentage");
        let progress = 0;
        const minLoaderTime = 800;
        const startTime = new Date().getTime();

        function updateProgress() {
        progress += 1;
        progressBar.style.width = progress + "%";
        percentageText.textContent = progress + "%";
        if (progress < 100) {
            let delay = (progress > 70) ? 30 : 10;
            setTimeout(updateProgress, delay);
        } else {
            const elapsedTime = new Date().getTime() - startTime;
            if (elapsedTime < minLoaderTime) {
            setTimeout(finishLoading, minLoaderTime - elapsedTime);
            } else {
            finishLoading();
            }
        }
        }
        function finishLoading() {
        loader.classList.add("hidden");
        setTimeout(() => { loader.remove(); }, 500);
        }
        setTimeout(updateProgress, 10);
    }
    }); // End of $(document).ready
</script>

<footer class="sticky-footer bg-white">
    <div class="container my-auto">
    <div class="copyright text-center my-auto">
        <span>Copyright &copy; LOPISv2 <?php echo date('Y'); ?></span>
    </div>
    </div>
</footer>

</body>
</html>