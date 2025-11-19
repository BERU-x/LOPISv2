</div>
            </div>
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>Copyright &copy; LOPISv2 <?php echo date('Y'); ?></span>
                </div>
            </div>
        </footer>
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
                'remove':  'Remove',
                'error':   'Sorry, this file is too large.'
            },
            error: {
                'fileSize': 'The file size is too big ({{ value }} max).',
                'imageFormat': 'The image format is not allowed ({{ value }} only).'
            }
        });
        
        // --- DataTables Initialization: Employee Management Table (Client-Side) ---
        var employeeTable = $('#employeesTable').DataTable({
            "dom": 'lrtip', 
            "pageLength": 10, 
            "columnDefs": [
                { "orderable": false, "targets": 4 } 
            ]
        });

        // --- Custom Search Integration for Employee Table ---
        $('#searchInput').on('keyup', function() {
            employeeTable.search(this.value).draw();
        });
        // --- End DataTables Initialization: Employee Management Table ---

        
        // ----------------------------------------------------
        // 🔥 DATA TABLES INITIALIZATION: ATTENDANCE (SERVER-SIDE PROCESSING) 🔥
        // ----------------------------------------------------

        if ($('#attendanceTable').length) {
            var attendanceTable = $('#attendanceTable').DataTable({ // <-- Assign to a variable
                processing: true,
                serverSide: true, 
                
                ajax: {
                    url: "fetch/attendance_ssp.php", 
                    type: "GET",
                    data: function (d) { // <-- Function to send filter data to server
                        d.start_date = $('#filter_start_date').val();
                        d.end_date = $('#filter_end_date').val();
                    }
                },
                
                // Define columns based on the JSON structure returned by attendance_ssp.php
                columns: [
                    { data: 'employee_id' },
                    { data: 'employee_name' },
                    { data: 'date' },
                    { data: 'time_in' },
                    { data: 'status' }, 
                    { data: 'time_out' },
                    { data: 'status_out' },
                    { data: 'num_hr' },
                    { data: 'status_based' }
                ],
                
                // Configure rendering and default sorting
                order: [
                    [2, 'desc'], // Date DESC
                    [3, 'desc']  // Time In DESC 
                ],
                columnDefs: [
                    { 
                        targets: 2, // Date column
                        type: 'date' 
                    }, 
                    {
                        targets: 4, // Status In column
                        render: function (data, type, row) {
                            if (data == 1) {
                                return '<span class="badge bg-success">On Time</span>';
                            } else {
                                return '<span class="badge bg-danger">Late</span>';
                            }
                        }
                    },
                    {
                        targets: 7, // Hours Worked column
                        render: function (data, type, row) {
                            return data ? parseFloat(data).toFixed(2) + 'h' : '—';
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

            // Update the click handler:
            $('#applyFilterBtn').on('click', function() {
                attendanceTable.ajax.reload();
            });
            
            $('#clearFilterBtn').on('click', function() {
                // Clear the input fields
                $('#filter_start_date').val('');
                $('#filter_end_date').val('');
                attendanceTable.ajax.reload(); // Reloads DataTables with empty filter values
            });
        }

        // --- SIDEBAR TOGGLE LOGIC (Remains the same) ---
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
        
        // --- LOADER SCRIPT (Remains the same) ---
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
    </body>
</html>