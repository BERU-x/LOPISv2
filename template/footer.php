<?php
// template/footer.php
// Closes the page wrapper, includes scripts, and handles global JS logic (notifications, loader).
?>
    </div>
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
                <a class="btn btn-primary fw-bold shadow-sm" href="../logout.php">
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- GLOBAL VARIABLES ---
    const BASE_TITLE = document.title; 
    let previousNotifCount = 0; 
    const NOTIF_API_URL = '../api/global_notifications_api.php'; // Centralized API Path

    // --- UTILITY: TITLE UPDATER ---
    function updateBrowserTabNotification(count) {
        if (count > 0) {
            document.title = `(${count}) ${BASE_TITLE}`;
        } else {
            document.title = BASE_TITLE;
        }
    }

    // --- UTILITY: SOUND PLAYER ---
    function playNotificationSound() {
        try {
            const audio = new Audio('../assets/sounds/notification.mp3'); 
            audio.volume = 0.5;
            audio.play().catch(e => console.warn("Audio play blocked by browser policy:", e));
        } catch (e) {
            console.warn("Could not play notification sound:", e);
        }
    }

    // --- UTILITY: TIME AGO FORMATTER ---
    function timeAgo(dateString) {
        const date = new Date(dateString);
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return "Just now";
    }

    // --- ACTION: MARK SINGLE READ ---
    function markAsRead(id, link) {
        $.post(NOTIF_API_URL, { action: 'mark_single_read', id: id }, function(resp) {
            if (link && link !== '#') {
                window.location.href = link;
            } else {
                fetchNotifications(); // Refresh list if no link
            }
        });
    }

    // --- ACTION: MARK ALL READ ---
    function markAllRead() {
        $.post(NOTIF_API_URL, { action: 'mark_all_read' }, function(resp) {
            fetchNotifications(); // Refresh list immediately
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'All notifications marked as read',
                showConfirmButton: false,
                timer: 3000
            });
        });
    }

    // --- CORE: FETCH NOTIFICATIONS ---
    function fetchNotifications() {
        $.ajax({
            url: NOTIF_API_URL, 
            data: { action: 'fetch' },
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                let badge = $('#notif-badge');
                let list = $('#notif-list');
                let currentCount = response.count || 0;
                
                // 1. Update Badge
                if (currentCount > 0) {
                    badge.text(currentCount).show();
                } else {
                    badge.hide();
                }
                
                // 2. Play Sound (if count increased)
                if (currentCount > previousNotifCount && previousNotifCount !== 0) {
                    playNotificationSound();
                }
                
                previousNotifCount = currentCount;
                updateBrowserTabNotification(currentCount); 

                // 3. Render HTML List
                if (list.length) {
                    let html = '';
                    if (response.notifications && response.notifications.length > 0) {
                        response.notifications.forEach(notif => {
                            html += `
                                <a class="dropdown-item d-flex align-items-center" href="#" onclick="markAsRead(${notif.id}, '${notif.link}'); return false;">
                                    <div class="me-3">
                                        <div class="icon-circle bg-primary">
                                            <i class="fas fa-info text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">${timeAgo(notif.created_at)}</div>
                                        <span class="font-weight-bold">${notif.message}</span>
                                    </div>
                                </a>
                            `;
                        });
                    } else {
                        html = '<a class="dropdown-item text-center small text-gray-500" href="#">No new notifications</a>';
                    }
                    list.html(html);
                }
            },
            error: function(xhr, status, error) {
                // Silently fail on network error to avoid annoying the user
                console.warn("Notification sync failed:", error);
            }
        });
    }
    
    // --- DOCUMENT READY ---
    $(document).ready(function(){
            
        // 1. Initialize Global Plugins
        if ($('.dropify').length) {
            $('.dropify').dropify();
        }

        // 2. Sidebar Toggles
        $('#sidebarToggle, #sidebarToggleTop').on('click', function(e) {
            $("body").toggleClass("sidebar-toggled");
            $(".sidebar").toggleClass("toggled");
            if ($(".sidebar").hasClass("toggled")) {
                $('.sidebar .collapse').collapse('hide');
            };
        });

        // 3. Loader Animation
        const loader = document.getElementById("page-loader");
        if (loader) {
            const progressBar = loader.querySelector(".progress-bar");
            const percentageText = loader.querySelector("#loader-percentage");
            let progress = 0;
            const minLoaderTime = 500; 
            const startTime = new Date().getTime();

            function updateProgress() {
                progress += 2; // Faster load for better UX
                if(progressBar) progressBar.style.width = progress + "%";
                if(percentageText) percentageText.textContent = progress + "%";
                
                if (progress < 100) {
                    requestAnimationFrame(updateProgress);
                } else {
                    const elapsedTime = new Date().getTime() - startTime;
                    setTimeout(finishLoading, Math.max(0, minLoaderTime - elapsedTime));
                }
            }

            function finishLoading() {
                loader.classList.add("hidden");
                setTimeout(() => { loader.remove(); }, 500);
            }
            requestAnimationFrame(updateProgress);
        }

        // 4. Start Notification Polling
        fetchNotifications(); // Run once immediately
        setInterval(fetchNotifications, 10000); // Poll every 10 seconds

        // 5. Clear Title on Dropdown Open
        $('#alertsDropdown').on('click', function() {
            updateBrowserTabNotification(0); 
            // We don't reset previousNotifCount here so sound doesn't re-trigger on next poll
        });

        // 6. Master Auto-Refresher (Optional: Keep your datatable refresh logic)
        setInterval(function() {
            if (document.hidden) return;
            // Refreshes DataTables if they exist on the page
            if ($.fn.DataTable) {
                $.fn.dataTable.tables({ api: true }).each(function(dt) {
                    if (dt.ajax.url()) dt.ajax.reload(null, false); 
                });
            }
            // Custom page hook
            if (typeof window.refreshPageContent === "function") {
                window.refreshPageContent(false); 
            }
        }, 30000); 

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