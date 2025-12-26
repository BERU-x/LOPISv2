<?php
// template/footer.php

// 1. PATH CONFIGURATION
$web_root = isset($web_root) ? $web_root : '../';
$api_root = isset($api_root) ? $api_root : '../api';
?>

            </div> 
            <footer class="sticky-footer bg-white no-print">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; LOPISv2 <?php echo date("Y"); ?></span>
                    </div>
                </div>
            </footer>
        </div> 
    </div> 

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title font-weight-bold text-dark">
                        <i class="fas fa-sign-out-alt me-2"></i>Ready to Leave?
                    </h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-gray-600">
                    Select "Logout" below if you are ready to end your current session.
                </div>
                <div class="modal-footer border-top-0">
                    <button class="btn btn-light text-secondary fw-bold" type="button" data-bs-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary fw-bold shadow-sm" href="<?php echo $web_root; ?>/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo $web_root; ?>/assets/js/jquery-3.7.1.min.js"></script> 
    <script src="<?php echo $web_root; ?>/assets/vendor/bs5/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 

    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.datatables.net/buttons/3.2.0/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.bootstrap5.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.print.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    /**
     * LOPISv2 MASTER CONTROLLER
     * Handles: Global State, Auto-Sync Engine, Notifications, Session Watchdog
     */

    // ==========================================================================
    // 1. GLOBAL STATE & CONFIGURATION
    // ==========================================================================
    const BASE_TITLE = document.title;
    const API_ROOT = '<?php echo $api_root; ?>'; 
    const WEB_ROOT = '<?php echo $web_root; ?>';
    const NOTIF_API_URL = '<?php echo $api_root; ?>/global_notifications_api.php'; 
    
    // State Tracking
    let previousNotifCount = 0; 
    
    // ⭐ GLOBAL MUTEX LOCK
    // This variable is the "Traffic Cop". If true, no new auto-refreshes will run.
    window.isProcessing = false;

    // ==========================================================================
    // 2. UI UTILITY (The "Syncing..." Indicator)
    // ==========================================================================
    window.AppUtility = {
        /**
         * Updates the Topbar Sync Indicator
         * @param {string} state - 'loading', 'success', or 'error'
         */
        updateSyncStatus: function(state) {
            const dot = document.querySelector('.live-dot');
            const text = document.getElementById('last-updated-time');
            const icon = document.getElementById('refresh-spinner');

            if (!dot || !text || !icon) return;

            // 1. Reset all classes
            dot.classList.remove('text-success', 'text-warning', 'text-danger');
            icon.classList.remove('fa-spin', 'text-teal', 'text-danger', 'text-gray-400');

            // 2. Apply new state
            if (state === 'loading') {
                text.innerText = 'Syncing...';
                dot.classList.add('text-warning');
                icon.classList.add('fa-spin', 'text-teal');
            } 
            else if (state === 'success') {
                const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                text.innerText = `Synced: ${time}`;
                dot.classList.add('text-success');
                icon.classList.add('text-gray-400');
            } 
            else if (state === 'error') {
                const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                text.innerText = `Failed: ${time}`;
                dot.classList.add('text-danger');
                icon.classList.add('text-danger');
            }
        }
    };

    // ==========================================================================
    // 3. THE SYNC ENGINE (Universal Refresher)
    // ==========================================================================
    function runGlobalSync(isManual = false) {
        // [LOCK CHECK]: Stop if system is busy
        if (window.isProcessing === true) {
            if(isManual) console.warn("Sync skipped: System is busy.");
            return;
        }

        // 1. Update UI
        if (window.AppUtility) window.AppUtility.updateSyncStatus('loading');

        // 2. Fetch Notifications (Always runs)
        fetchNotifications();

        // 3. Trigger Page-Specific Logic (The Hook)
        // Checks if the current page has defined a custom refresh function
        if (typeof window.refreshPageContent === "function") {
            window.refreshPageContent(isManual);
        }
        
        // 4. Sync Generic DataTables
        // Refreshes any standard DataTable that IS NOT the main table handled by step 3
        if ($.fn.DataTable) {
            $('.dataTable').each(function() {
                // Ignore specific tables we know are handled by the page controller
                if (this.id !== 'leaveTable' && this.id !== 'todayTable' && this.id !== 'employeesTable') {
                    var dt = $(this).DataTable();
                    if (dt.ajax && dt.ajax.url()) {
                        dt.ajax.reload(null, false); 
                    }
                }
            });
        }

        // 5. Fallback Cleanup
        // If the page doesn't have complex logic to unlock itself, we do it here after 1s
        setTimeout(() => { 
            if (window.isProcessing === false && window.AppUtility) {
                window.AppUtility.updateSyncStatus('success');
            }
        }, 1000);
    }

    // ==========================================================================
    // 4. NOTIFICATION LOGIC
    // ==========================================================================
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
                
                // Badge Logic
                if (currentCount > 0) badge.text(currentCount > 9 ? '9+' : currentCount).show();
                else badge.hide();
                
                // Sound & Tab Title
                if (currentCount > previousNotifCount && previousNotifCount !== 0) playNotificationSound();
                previousNotifCount = currentCount;
                updateBrowserTabNotification(currentCount); 

                // Render List
                if (list.length && response.notifications) {
                    let html = '';
                    if (response.notifications.length > 0) {
                        response.notifications.forEach(notif => {
                            html += `
                                <a class="dropdown-item d-flex align-items-center" href="#" onclick="markAsRead(${notif.id}, '${notif.link}'); return false;">
                                    <div>
                                        <div class="small text-gray-500">${timeAgo(notif.created_at)}</div>
                                        <span class="font-weight-bold" style="font-size: 0.9rem;">${notif.message}</span>
                                    </div>
                                </a>`;
                        });
                    } else {
                        html = '<a class="dropdown-item text-center small text-gray-500" href="#">No new notifications</a>';
                    }
                    list.html(html);
                }
            }
        });
    }

    // ==========================================================================
    // 5. INITIALIZATION & AUTOMATION
    // ==========================================================================
    $(document).ready(function(){
        
        // A. Plugin Initialization
        if ($('.dropify').length) $('.dropify').dropify();
        
        // B. Sidebar Toggle
        $('#sidebarToggle, #sidebarToggleTop').on('click', function() {
            $("body").toggleClass("sidebar-toggled");
            $(".sidebar").toggleClass("toggled");
        });

        // C. Manual Refresh Button (The "Click" Trigger)
        $('body').on('click', '.btn-refresh-trigger', function(e) {
             e.preventDefault();
             runGlobalSync(true); // true = show spinners forcefully
        });

        // D. 🚀 AUTOMATIC INTERVAL
        // Runs every 10 seconds, but ONLY if the tab is visible
        setInterval(function() { 
            if (!document.hidden) runGlobalSync(false); 
        }, 10000); 

        // E. Session Watchdog (Every 15s)
        setInterval(checkSessionHealth, 15000);
    });

    // ==========================================================================
    // 6. HELPER FUNCTIONS
    // ==========================================================================
    
    // Session Checker
    function checkSessionHealth() {
        $.ajax({
            url: API_ROOT + '/auth/session_health_check.php',
            type: 'GET',
            cache: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'conflict') {
                    Swal.fire({
                        title: 'Session Expired',
                        text: 'Your account was accessed from another device.',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = WEB_ROOT + '/index.php?error=session_conflict';
                    });
                }
            }
        });
    }

    // Utilities
    function updateBrowserTabNotification(count) { document.title = (count > 0) ? `(${count}) ${BASE_TITLE}` : BASE_TITLE; }
    function playNotificationSound() { try { const audio = new Audio(WEB_ROOT + '/assets/sounds/notification.mp3'); audio.play(); } catch (e) {} }
    function markAsRead(id, link) { $.post(NOTIF_API_URL, { action: 'mark_single_read', id: id }, function() { window.location.href = WEB_ROOT + '/' + link; }); }
    
    function timeAgo(date) {
        const seconds = Math.floor((new Date() - new Date(date)) / 1000);
        let interval = Math.floor(seconds / 31536000);
        if (interval > 1) return interval + " years ago";
        interval = Math.floor(seconds / 2592000);
        if (interval > 1) return interval + " months ago";
        interval = Math.floor(seconds / 86400);
        if (interval > 1) return interval + " days ago";
        interval = Math.floor(seconds / 3600);
        if (interval > 1) return interval + " hours ago";
        interval = Math.floor(seconds / 60);
        if (interval > 1) return interval + " minutes ago";
        return Math.floor(seconds) + " seconds ago";
    }

    // Page Loader Logic
    const loader = document.getElementById("page-loader");
    const progressBar = document.querySelector(".progress-bar");
    const percentageText = document.getElementById("loader-percentage");
    if (loader && progressBar && percentageText) {
        let progress = 0;
        const interval = setInterval(() => {
            if (progress < 100) {
                progress += 1;
                progressBar.style.width = progress + "%";
                percentageText.textContent = progress + "%";
            } else {
                clearInterval(interval);
                setTimeout(() => { loader.classList.add("hidden"); }, 300);
            }
        }, 20);
    }
</script>
    
    <?php if (isset($_SESSION['user_id'])) { ?>
        <script src="<?php echo $web_root; ?>/assets/js/browser_mailer.js"></script>
    <?php } ?>

</body>
</html>