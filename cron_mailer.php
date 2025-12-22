<?php
/**
 * cron_mailer.php
 * BACKGROUND WORKER
 * * This script processes the Email Queue.
 * Setup: Run this every minute via Cron Job or Task Scheduler.
 * Example Cron: * * * * * /usr/bin/php /path/to/your/site/cron_mailer.php
 */

// 1. Load Environment
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/helpers/email_handler.php';

// 2. Configuration
$batch_size = 10; // Number of emails to send per run

// 3. Execution
echo "[" . date('Y-m-d H:i:s') . "] Starting Email Queue Processor...\n";

try {
    // Call the batch processor from email_handler.php
    $result = processEmailQueue($pdo, $batch_size);
    
    // Output result for Cron logs
    if ($result === 'disabled') {
        echo " > Email System is DISABLED in General Settings.\n";
    } elseif ($result === 'empty') {
        echo " > Queue is empty. No emails to send.\n";
    } elseif ($result === 'processed') {
        echo " > Batch processed successfully.\n";
    }

} catch (Exception $e) {
    echo " > CRITICAL ERROR: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Finished.\n";
?>