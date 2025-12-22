/**
 * assets/js/browser_mailer.js
 * * "Piggyback Auto-Sender"
 * Triggers the email queue every 30 seconds as long as ANY user is logged in.
 */

$(document).ready(function() {
    
    // API_ROOT is defined in footer.php, ensuring we know where to send requests
    if (typeof API_ROOT !== 'undefined') {
        
        // Run immediately on page load (with small delay to let page render)
        setTimeout(triggerQueue, 3000);

        // Run every 30 seconds thereafter
        setInterval(triggerQueue, 30000); 

        function triggerQueue() {
            $.ajax({
                // Point to the file we just updated
                url: API_ROOT + '/superadmin/pending_emails_action.php',
                type: 'POST',
                data: { action: 'process_queue' }, // This action is open to all users
                dataType: 'json',
                success: function(response) {
                    // Optional: Log to console for debugging
                    // console.log("📧 Auto-Sender:", response.message);
                },
                error: function() {
                    // Silent fail - do not disturb the user
                }
            });
        }
    }
});