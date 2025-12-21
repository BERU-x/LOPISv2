<?php
// template/footer.php

// 1. PATH CONFIGURATION
// Prioritize variables from link_setup.php, with safe fallbacks.
$web_root = isset($web_root) ? $web_root : '../';
$api_root = isset($api_root) ? $api_root : '../api';
?>

    </div> </div> </div> <a class="scroll-to-top rounded" href="#page-top">
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
<script src="<?php echo $web_root; ?>/assets/js/dataTables.min.js"></script>
<script src="<?php echo $web_root; ?>/assets/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script> 
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- GLOBAL VARIABLES ---
    const BASE_TITLE = document.title;
    const API_ROOT = '<?php echo $api_root; ?>'; 
    let previousNotifCount = 0; 
    const NOTIF_API_URL = '<?php echo $api_root; ?>/global_notifications_api.php'; 

    // ==========================================================================
    // 1. UNIVERSAL REFRESHER (The Logic Core)
    // ==========================================================================
    function runGlobalSync(isManual = false) {
        
        // A. Visuals: Start Spinner (Only if clicked manually)
        if (isManual) {
            $('#refreshIcon').addClass('fa-spin');
        }
        
        // Update Text to "Syncing..." (Always)
        if (typeof updateSyncStatus === "function") updateSyncStatus('loading');

        // B. Fetch Notifications (Always runs)
        fetchNotifications();

        // C. STRATEGY 1: Is there a specific Page Function? (e.g. Dashboard Charts)
        if (typeof window.refreshPageContent === "function") {
            window.refreshPageContent(isManual);
        }
        
        // D. STRATEGY 2: Is there a DataTable? (e.g. Employee List)
        // Automatically reload any table found on the page silently
        if ($.fn.DataTable) {
            $('.dataTable').each(function() {
                var dt = $(this).DataTable();
                // reload(callback, resetPaging) -> false means keep current page
                dt.ajax.reload(null, false); 
            });
        }

        // E. Visuals: Stop Spinner (Short delay for UX)
        if (isManual) {
            setTimeout(() => { 
                $('#refreshIcon').removeClass('fa-spin'); 
                // We force success status here because manual refresh implies "done"
                if (typeof updateSyncStatus === "function") updateSyncStatus('success');
            }, 1000);
        }
    }

    // ==========================================================================
    // 2. UI HELPER: SYNC STATUS & ANIMATIONS
    // ==========================================================================
    function updateSyncStatus(state) {
        // Target classes (.) so it updates Topbar AND Footer simultaneously
        const $dot = $('.live-dot');
        const $text = $('.sync-status-text'); 
        const $icon = $('#refreshIcon'); 
        const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

        // Reset classes first
        $dot.removeClass('text-success text-warning text-danger');
        $text.removeClass('text-warning text-success text-danger text-dark');

        if (state === 'loading') {
            $text.text('Syncing...');
            $dot.addClass('text-warning'); // Triggers Yellow Pulse CSS
            $text.addClass('text-warning'); // Triggers Text Fade CSS
        } 
        else if (state === 'success') {
            $text.text(`Live: ${time}`);
            $dot.addClass('text-success'); // Triggers Green Pulse CSS
            $text.addClass('text-dark');
        } 
        else {
            $text.text(`Retry: ${time}`);
            $dot.addClass('text-danger');  // Triggers Red Pulse CSS
            $text.addClass('text-danger');
        }
    }

    // ==========================================================================
    // 3. CORE: FETCH NOTIFICATIONS
    // ==========================================================================
    function fetchNotifications() {
        $.ajax({
            url: NOTIF_API_URL, 
            data: { action: 'fetch' },
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                // Only update status to success if we are NOT in a manual spin cycle
                // (Prevents flickering if user clicked refresh)
                if(!$('#refreshIcon').hasClass('fa-spin')) {
                    updateSyncStatus('success');
                }

                let badge = $('#notif-badge');
                let list = $('#notif-list');
                let currentCount = response.count || 0;
                
                // Badge Logic
                if (currentCount > 0) badge.text(currentCount > 9 ? '9+' : currentCount).show();
                else badge.hide();
                
                // Sound Logic
                if (currentCount > previousNotifCount && previousNotifCount !== 0) playNotificationSound();
                previousNotifCount = currentCount;
                updateBrowserTabNotification(currentCount); 

                // Render Dropdown List
                if (list.length && response.notifications) {
                    let html = '';
                    if (response.notifications.length > 0) {
                        response.notifications.forEach(notif => {
                            let type = notif.type.toLowerCase();
                            let bgClass = 'bg-primary'; let iconClass = 'fa-info-circle';

                            if (type.includes('payroll')) { bgClass = 'bg-success'; iconClass = 'fa-file-invoice-dollar'; }
                            else if (type.includes('leave')) { bgClass = 'bg-warning'; iconClass = 'fa-calendar-times'; }
                            else if (type.includes('warning') || type.includes('alert')) { bgClass = 'bg-danger'; iconClass = 'fa-exclamation-triangle'; }
                            else if (type.includes('system')) { bgClass = 'bg-info'; iconClass = 'fa-server'; }

                            // We use onclick JS redirection to handle marking as read
                            html += `
                                <a class="dropdown-item d-flex align-items-center" href="#" onclick="markAsRead(${notif.id}, '${notif.link}'); return false;">
                                    <div class="me-3">
                                        <div class="icon-circle ${bgClass}">
                                            <i class="fas ${iconClass} text-white"></i>
                                        </div>
                                    </div>
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
            },
            error: function() {
                updateSyncStatus('error');
            }
        });
    }

    // ==========================================================================
    // 4. UTILITIES
    // ==========================================================================
    function updateBrowserTabNotification(count) {
        document.title = (count > 0) ? `(${count}) ${BASE_TITLE}` : BASE_TITLE;
    }

    function playNotificationSound() {
        try {
            const audio = new Audio('<?php echo $web_root; ?>/assets/sounds/notification.mp3'); 
            audio.volume = 0.5; 
            audio.play().catch(e => { /* Browsers block autoplay often, silent fail is ok */ });
        } catch (e) {}
    }

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
        if (interval > 1) return Math.floor(interval) + " mins ago";
        return "Just now";
    }

    function markAsRead(id, link) {
        $.post(NOTIF_API_URL, { action: 'mark_single_read', id: id }, function(resp) {
            if (link && link !== '#' && link !== '') {
                // If link is internal (doesn't start with http), prepend web_root
                if (!link.startsWith('http')) {
                    window.location.href = '<?php echo $web_root; ?>/' + link;
                } else {
                    window.location.href = link;
                }
            } else {
                fetchNotifications(); // Just refresh list if no link
            }
        });
    }

    function markAllRead() {
        $.post(NOTIF_API_URL, { action: 'mark_all_read' }, function(resp) {
            fetchNotifications(); 
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: 'Marked all as read', showConfirmButton: false, timer: 3000
            });
        });
    }

    // ==========================================================================
    // 5. INITIALIZATION
    // ==========================================================================
    $(document).ready(function(){
        // Initialize Plugins
        if ($('.dropify').length) $('.dropify').dropify();
        
        // Sidebar Toggle
        $('#sidebarToggle, #sidebarToggleTop').on('click', function(e) {
            $("body").toggleClass("sidebar-toggled");
            $(".sidebar").toggleClass("toggled");
            if ($(".sidebar").hasClass("toggled")) { $('.sidebar .collapse').collapse('hide'); };
        });

        // Loader Animation
        const loader = document.getElementById("page-loader");
        if (loader) {
            // Fake progress for visual effect, then hide
            setTimeout(() => { 
                loader.classList.add("hidden"); 
                setTimeout(() => { loader.remove(); }, 500); 
            }, 800);
        }

        // --- GLOBAL TRIGGERS ---

        // 1. Run immediately on page load
        runGlobalSync(false); 

        // 2. Auto-Refresh every 15 seconds (Silent)
        // This handles Notifications, Dashboards, and DataTables all in one go.
        setInterval(function() {
            runGlobalSync(false); 
        }, 15000); 

        // 3. Manual Click (Visual)
        // We use delegation on 'body' so it works even if the icon is re-rendered
        $('body').on('click', '#refreshIcon', function() {
             runGlobalSync(true); // true = show spinner
        });

        // Reset Tab Title on Open
        $('#alertsDropdown').on('click', function() { updateBrowserTabNotification(0); });
    }); 
</script>

<footer class="sticky-footer bg-white">
    <div class="container my-auto">
        <div class="copyright text-center my-auto">
            <span>Copyright &copy; LOPISv2 <?php echo date('Y'); ?></span>
            
            <span class="mx-2 text-gray-400">|</span>
            
            <span class="small font-weight-bold">
                <i class="fas fa-circle text-warning live-dot me-1" style="font-size: 8px;"></i>
                <span class="sync-status-text text-secondary">Syncing...</span>
            </span>
        </div>
    </div>
</footer>

</body>
</html>