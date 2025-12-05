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
    <div class="modal-dialog modal-dialog-centered" role="document">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

    function fetchNotifications() {
        $.ajax({
            // Adjust this path if your file is in a sub-folder (e.g., ../fetch/fetch_navbar_notifs.php)
            url: 'fetch/fetch_navbar_notifs.php', 
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                // A. Update Badge Count
                let badge = $('#notif-badge');
                
                // Only show badge if count > 0
                if (response.count > 0) {
                    badge.text(response.count);
                    badge.show(); // Ensure it's visible
                } else {
                    badge.hide(); // Hide if 0
                }

                // B. Update Dropdown List content
                // Check if the element exists to prevent errors
                if ($('#notif-list').length) {
                    $('#notif-list').html(response.html);
                }
            },
            error: function(xhr, status, error) {
                // Silent failure in console is better than alerting the user every 5 seconds
                console.log("Notification sync warning: " + error);
            }
        });
    }

    // Run immediately on page load
    fetchNotifications();

    // Run every 5 seconds (5000ms)
    setInterval(fetchNotifications, 5000);

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