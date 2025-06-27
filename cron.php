<?php
// Change to the directory containing this script
chdir(dirname(__FILE__));

require_once 'functions.php';

// Run log rotation script
exec("./rotate_logs.sh");

$log_entry = date('Y-m-d H:i:s') . " - CRON job started\n";

try {
    $subscribers = getVerifiedSubscribers();
    $pending_tasks = getPendingTasks();
    
    if (empty($pending_tasks)) {
        $log_entry .= date('Y-m-d H:i:s') . " - No pending tasks found, no emails sent\n";
        echo "No pending tasks found at " . date('Y-m-d H:i:s') . "\n";
    } else {
        sendTaskReminders();
        
        $log_entry .= date('Y-m-d H:i:s') . " - Task reminders sent successfully to " . count($subscribers) . " subscribers\n";
        $log_entry .= date('Y-m-d H:i:s') . " - Pending tasks count: " . count($pending_tasks) . "\n";
        
        echo "Task reminders sent successfully at " . date('Y-m-d H:i:s') . "\n";
        echo "Subscribers notified: " . count($subscribers) . "\n";
        echo "Pending tasks: " . count($pending_tasks) . "\n";
        
        // Display pending tasks
        echo "\nPending Tasks:\n";
        foreach ($pending_tasks as $task) {
            echo "- " . $task['name'] . "\n";
        }
    }
    
} catch (Exception $e) {
    $log_entry .= date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    echo "Error sending task reminders: " . $e->getMessage() . "\n";
}

$log_entry .= date('Y-m-d H:i:s') . " - CRON job completed\n";
$log_entry .= "----------------------------------------\n";

file_put_contents('cron_log.txt', $log_entry, FILE_APPEND | LOCK_EX);

// Display current status
echo "\n=== Current System Status ===\n";
echo "Total Tasks: " . count(getAllTasks()) . "\n";
echo "Pending Tasks: " . count(getPendingTasks()) . "\n";
echo "Verified Subscribers: " . count(getVerifiedSubscribers()) . "\n";
echo "Pending Verifications: " . count(getPendingSubscriptions()) . "\n";
echo "Check cron_log.txt, email_log.txt, and system_log.txt for detailed logs.\n";

// Clean up old tokens and expired verifications
cleanupOldData();

echo "Log cleanup completed.\n";

// Function to clean up old data
function cleanupOldData() {
    // Clean up old unsubscribe tokens (older than 30 days)
    if (file_exists('unsubscribe_tokens.txt')) {
        $tokens_content = file_get_contents('unsubscribe_tokens.txt');
        if (!empty($tokens_content)) {
            $tokens = json_decode($tokens_content, true) ?: [];
            $current_time = time();
            $cleaned_tokens = [];
            $removed_count = 0;
            
            foreach ($tokens as $token => $data) {
                if ($current_time <= $data['expires']) {
                    $cleaned_tokens[$token] = $data;
                } else {
                    $removed_count++;
                }
            }
            
            if ($removed_count > 0) {
                file_put_contents('unsubscribe_tokens.txt', json_encode($cleaned_tokens, JSON_PRETTY_PRINT));
                logInfo("cleanupOldData: Removed $removed_count expired unsubscribe tokens");
            }
        }
    }
    
    // Clean up old rotated logs (older than 30 days)
    $log_files = glob('*.*.txt');
    foreach ($log_files as $file) {
        if (filemtime($file) < (time() - 30 * 24 * 60 * 60)) {
            unlink($file);
            logInfo("cleanupOldData: Removed old log file: $file");
        }
    }
}
?>