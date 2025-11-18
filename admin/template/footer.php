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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <script src="../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        
        <script src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script> 

<script>
    // --- Start of Single, Clean jQuery Ready Function ---
    $(document).ready(function(){
        
        // --- DATA DEPENDENCIES (Move constants outside the ready function if they are global) ---
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
        
        // --- DataTables Initialization ---
        var employeeTable = $('#employeesTable').DataTable({
            // 'f' (filtering/search box) is intentionally omitted here to hide it.
            "dom": 'lrtip', 
            "pageLength": 10, 
            "columnDefs": [
                { "orderable": false, "targets": 4 } 
            ]
        });

        // --- Custom Search Integration ---
        $('#searchInput').on('keyup', function() {
            employeeTable.search(this.value).draw();
        });
        // --- End DataTables Initialization ---
        
        // ----------------------------------------------------
        // --- MODAL AJAX AND POPULATION LOGIC ---
        // ----------------------------------------------------

        // --- Function to Populate Modals (Remains the same) ---
        function populateEmployeeModals(data, modalType) {
            const fullName = data.firstname + ' ' + data.lastname + (data.suffix ? ' ' + data.suffix : '');
            
            // --- POPULATE VIEW MODAL ---
            if (modalType === 'view') {
                $('#view_employee_name').text(fullName);
                $('#view_employee_full_name').text(fullName);
                $('#view_employee_id_display').text('ID: ' + data.employee_id);
                $('#view_employee_photo').attr('src', '../assets/images/' + (data.photo || 'default.png'));
                
                $('#view_birthdate').text(data.birthdate);
                $('#view_gender').text(genders[data.gender]);
                $('#view_contact_info').text(data.contact_info);
                $('#view_address').text(data.address);
                
                $('#view_position').text(data.position);
                $('#view_department').text(data.department);
                $('#view_employment_status').text(employment_statuses[data.employment_status]);
                
                $('#view_salary').text('₱' + parseFloat(data.salary).toLocaleString('en-US', {minimumFractionDigits: 2}));
                $('#view_food').text('₱' + parseFloat(data.food).toLocaleString('en-US', {minimumFractionDigits: 2}));
                $('#view_travel').text('₱' + parseFloat(data.travel).toLocaleString('en-US', {minimumFractionDigits: 2}));
                
                $('#view_bank_name').text(data.bank_name);
                $('#view_account_type').text(data.account_type);
                $('#view_account_number').text(data.account_number);
            } 
            
            // --- POPULATE EDIT MODAL ---
            else if (modalType === 'edit') {
                $('#edit_employee_name_display').text(fullName);
                $('#edit_employee_db_id').val(data.id); 
                $('#edit_employee_id').val(data.employee_id);
                $('#edit_firstname').val(data.firstname);
                $('#edit_lastname').val(data.lastname);
                $('#edit_suffix').val(data.suffix);
                $('#edit_birthdate').val(data.birthdate);
                $('#edit_contact_info').val(data.contact_info);
                $('#edit_address').val(data.address);
                $('#edit_gender').val(data.gender);
                $('#edit_employment_status').val(data.employment_status);
                $('#edit_position').val(data.position);
                $('#edit_department').val(data.department);
                $('#edit_salary').val(data.salary);
                $('#edit_food').val(data.food);
                $('#edit_travel').val(data.travel);
                $('#edit_bank_name').val(data.bank_name);
                $('#edit_account_type').val(data.account_type);
                $('#edit_account_number').val(data.account_number);

                // Dropify Update (Remains correct)
                const dropify = $('#edit_photo').data('dropify');
                if (dropify) {
                    dropify.destroy();
                    dropify.init();
                    if (data.photo) {
                        const filePath = '../assets/images/' + data.photo;
                        dropify.setFileData({'filename': data.photo, 'filesize': 0});
                        dropify.resetPreview();
                        dropify.setPreview(true, filePath);
                    } else {
                        dropify.clearElement(true);
                    }
                }
            }
        }


        // --- AJAX HANDLER HOOKED TO MODAL SHOW EVENT (Best practice) ---
        $('#viewEmployeeModal, #editEmployeeModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget); // The button that triggered the modal
            const employeeId = button.data('id');
            const modalType = button.hasClass('view-btn') ? 'view' : 'edit';
            const modal = $(this);

            // Fetch data *before* showing
            $.ajax({
                url: 'fetch/employee_data.php', 
                type: 'POST',
                dataType: 'json',
                data: { id: employeeId },
                success: function(response) {
                    if (response.success) {
                        populateEmployeeModals(response.data, modalType);
                        // The modal is already showing/transitioning, just need to populate it.
                    } else {
                        // If data fetch fails, hide the modal and notify
                        modal.modal('hide'); 
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    modal.modal('hide'); 
                    Swal.fire('Error', 'Could not retrieve employee data. Check console.', 'error');
                    console.error("AJAX Error: ", status, error);
                }
            });
        });

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
    </body>
</html>