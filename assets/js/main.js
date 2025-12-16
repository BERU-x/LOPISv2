// assets/js/main.js

// --- GLOBAL VARIABLE FOR BROWSER TITLE ---
const BASE_TITLE = document.title; 
let previousNotifCount = 0; 
const DASHBOARD_URL = window.location.pathname; // Used for the 503 check

function updateBrowserTabNotification(count) {
    if (count > 0) {
        document.title = `(${count}) ${BASE_TITLE}`;
    } else {
        document.title = BASE_TITLE;
    }
}

// Function to play a sound
function playNotificationSound() {
    try {
        // NOTE: Path needs to be verified relative to where main.js is executed
        const audio = new Audio('../assets/sounds/notification.mp3'); 
        audio.volume = 0.5;
        audio.play();
    } catch (e) {
        console.warn("Could not play notification sound:", e);
    }
}

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
                
                // CRITICAL SOUND LOGIC: Check if the count has increased
                if (currentCount > previousNotifCount && previousNotifCount !== 0) {
                    playNotificationSound();
                } 
                
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

// ==============================================================================
    // MASTER AUTO-REFRESHER SETUP
    // ==============================================================================
    
    let maintenanceIntervalId; // ID for the main 30-second refresher
    let logoutTimerIntervalId; // ID for the 1-second countdown timer
    let countdownSeconds = 30;
    let isMaintenanceActive = false;

    // Function to handle the maintenance warning and countdown
    function startMaintenanceCountdown() {
        if (isMaintenanceActive) return; // Prevent multiple countdowns

        isMaintenanceActive = true;
        
        // 1. Stop the main auto-refresher
        clearInterval(maintenanceIntervalId); 

        // 2. Display the initial SweetAlert
        Swal.fire({
            icon: 'warning',
            title: 'System Maintenance Alert',
            html: `The system is entering **Maintenance Mode**. You will be automatically logged out in <b>${countdownSeconds} seconds</b>. Please save any unsaved work immediately.`,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            timer: countdownSeconds * 1000,
            timerProgressBar: true,
            didOpen: () => {
                const timerElement = Swal.getHtmlContainer().querySelector('b');
                
                // 3. Start the 1-second interval for countdown display
                logoutTimerIntervalId = setInterval(() => {
                    countdownSeconds--;
                    timerElement.textContent = `${countdownSeconds} seconds`;
                    
                    if (countdownSeconds <= 0) {
                        clearInterval(logoutTimerIntervalId);
                    }
                }, 1000);
            },
            willClose: () => {
                // 4. Perform final logout/redirect when timer expires
                clearInterval(logoutTimerIntervalId);
                console.log("Countdown finished. Logging out.");
                window.location.href = '../index.php'; // Redirect to the main login page
            }
        });
    }


    // The main 30-second interval function
    function masterAutoRefresher() {
        if (document.hidden) {
            console.log("Tab hidden, skipping global auto-refresh.");
            return; 
        }

        // --- MAINTENANCE MODE CHECK (CRITICAL) ---
        $.ajax({
            url: DASHBOARD_URL, 
            type: 'GET',
            cache: false,
            statusCode: {
                503: function() {
                    console.warn("Server returned 503 Maintenance.");
                    startMaintenanceCountdown(); // Trigger the warning and logout sequence
                },
                401: function() {
                    console.warn("Session expired (401). Redirecting to login.");
                    clearInterval(maintenanceIntervalId); // Stop interval
                    window.location.href = '../index.php'; 
                }
            },
            error: function(xhr, status, error) {
                if (xhr.status !== 503 && xhr.status !== 401) {
                    console.error("Master Refresher Error:", xhr.status, error);
                }
            }
        });


        // 3. CUSTOM PAGE FUNCTIONS (Dashboards, etc.) - Only run if not in maintenance countdown
        if (!isMaintenanceActive && typeof window.refreshPageContent === "function") {
            window.refreshPageContent(false); 
        }

        // 4. DATATABLES AUTO-REFRESH - Only run if not in maintenance countdown
        if (!isMaintenanceActive && $.fn.DataTable) {
            var tables = $.fn.dataTable.tables({ api: true }); 
            
            tables.each(function(dt) {
                if (dt.ajax.url()) {
                    dt.ajax.reload(null, false); 
                }
            });
        }
    }

    // Initialize the main 30-second interval
    maintenanceIntervalId = setInterval(masterAutoRefresher, 30000); 

});