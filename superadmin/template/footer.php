<?php
// admin/template/footer.php
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
                <h5 class="modal-title font-weight-bold text-dark" id="exampleModalLabel">
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="../assets/js/jquery-3.7.1.min.js"></script> 
<script src="../assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
<script src="../assets/js/dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script> 

<script>
// --- GLOBAL VARIABLE FOR BROWSER TITLE ---
    const BASE_TITLE = document.title; 
    
    // NEW: Global variable to track the previous notification count
    let previousNotifCount = 0; 

    function updateBrowserTabNotification(count) {
        if (count > 0) {
            document.title = `(${count}) ${BASE_TITLE}`;
        } else {
            document.title = BASE_TITLE;
        }
    }

    // ⭐ NEW: Function to play a sound
    function playNotificationSound() {
        try {
            // Create a new Audio object pointing to your sound file
            const audio = new Audio('../assets/sounds/notification.mp3'); 
            // Set the volume if desired (0.0 to 1.0)
            audio.volume = 0.5;
            audio.play();
        } catch (e) {
            console.warn("Could not play notification sound:", e);
        }
    }
    // -----------------------------------------
    
    $(document).ready(function(){
            
        // --- 1. NOTIFICATIONS (Global) ---
        function fetchNotifications() {
            $.ajax({
                url: 'fetch/fetch_navbar_notifs.php', 
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    let badge = $('#notif-badge');
                    let currentCount = response.count || 0;
                    
                    if (currentCount > 0) {
                        badge.text(currentCount).show();
                    } else {
                        badge.hide();
                    }
                    
                    // ⭐ CRITICAL SOUND LOGIC: Check if the count has increased
                    if (currentCount > previousNotifCount && previousNotifCount !== 0) {
                        // Only play the sound if the count increased since the last check,
                        // and ignore the first load (when previousNotifCount is 0)
                        playNotificationSound();
                    } 
                    
                    // Update previous count for the next interval check
                    previousNotifCount = currentCount;

                    // Update browser tab title
                    updateBrowserTabNotification(currentCount); 

                    if ($('#notif-list').length) {
                        $('#notif-list').html(response.html);
                    }
                },
                error: function(xhr, status, error) {
                    console.log("Notification sync warning: " + error);
                    updateBrowserTabNotification(0); 
                }
            });
        }

        // Run immediately and then every 5 seconds
        fetchNotifications();
        setInterval(fetchNotifications, 5000);

        // Clear the tab notification when the user opens the dropdown
        $('#alertsDropdown').on('click', function() {
            updateBrowserTabNotification(0); 
            // Also reset the previous count to prevent sound on next refresh
            // if the user cleared the notifications by viewing them.
            previousNotifCount = 0; 
        });


        // --- 2. DROPIFY INIT ---
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

        // --- 3. SIDEBAR TOGGLE LOGIC ---
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
        
        // --- 4. LOADER SCRIPT ---
        const loader = document.getElementById("page-loader");
        if (loader) {
            const progressBar = loader.querySelector(".progress-bar");
            const percentageText = loader.querySelector("#loader-percentage");
            let progress = 0;
            const minLoaderTime = 800; // Minimum time loader is visible
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

        // --- 5. MASTER REFRESHER (Improved) ---
        setInterval(function() {
            
            // A. Check for global page function (for dashboards/custom pages)
            if (typeof window.refreshPageContent === "function") {
                window.refreshPageContent();
            }

            // B. Auto-refresh DataTables (API Method - More Robust)
            if ($.fn.DataTable) {
                var tables = $.fn.dataTable.tables({ api: true }); // Get all tables
                
                tables.each(function(dt) {
                    // Check if this specific table has an AJAX source
                    if (dt.ajax.url()) {
                        // Reload data without resetting paging (User stays on page 2)
                        dt.ajax.reload(null, false); 
                    }
                });
            }

        }, 30000); // 30 seconds
    }); 
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