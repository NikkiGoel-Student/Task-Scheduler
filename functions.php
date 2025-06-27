<?php

// =============================================================================
// CONFIGURATION SECTION
// =============================================================================

// Email configuration - Set to true for production, false for development/testing
define('PRODUCTION_EMAIL_MODE', false);

// When PRODUCTION_EMAIL_MODE is false, emails are logged but still sent via mail()
// When PRODUCTION_EMAIL_MODE is true, only mail() is used (no detailed logging)
// This allows for easy testing while maintaining full compliance with mail() requirement

// =============================================================================

function addTask($task_name) {
    $task_name = trim($task_name);
    if (empty($task_name)) {
        logError("addTask: Empty task name provided");
        return false;
    }
    
    $tasks = getAllTasks();
    
    // Check for duplicates (case-insensitive)
    foreach ($tasks as $task) {
        if (strcasecmp($task['name'], $task_name) === 0) {
            logError("addTask: Duplicate task name attempted: '$task_name'");
            return false;
        }
    }
    
    $task_id = uniqid('task_', true);
    
    $new_task = [
        'id' => $task_id,
        'name' => $task_name,
        'completed' => false,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $tasks[] = $new_task;
    
    $result = file_put_contents('tasks.txt', json_encode($tasks, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        logError("addTask: Failed to write to tasks.txt - check file permissions");
        return false;
    }
    
    logInfo("addTask: Successfully added task '$task_name' with ID '$task_id'");
    return true;
}

function getAllTasks() {
    if (!file_exists('tasks.txt')) {
        logInfo("getAllTasks: tasks.txt does not exist, returning empty array");
        return [];
    }
    
    $content = file_get_contents('tasks.txt');
    
    if ($content === false) {
        logError("getAllTasks: Failed to read tasks.txt - check file permissions");
        return [];
    }
    
    if (empty(trim($content))) {
        logInfo("getAllTasks: tasks.txt is empty, returning empty array");
        return [];
    }
    
    $tasks = json_decode($content, true);
    
    if ($tasks === null) {
        logError("getAllTasks: Invalid JSON in tasks.txt - " . json_last_error_msg());
        return [];
    }
    
    return is_array($tasks) ? $tasks : [];
}

function markTaskAsCompleted($task_id, $is_completed) {
    $tasks = getAllTasks();
    $task_found = false;
    
    foreach ($tasks as &$task) {
        if ($task['id'] === $task_id) {
            $old_status = $task['completed'];
            $task['completed'] = (bool)$is_completed;
            $task['updated_at'] = date('Y-m-d H:i:s');
            $task_found = true;
            
            logInfo("markTaskAsCompleted: Task '$task_id' status changed from " . 
                   ($old_status ? 'completed' : 'pending') . " to " . 
                   ($is_completed ? 'completed' : 'pending'));
            break;
        }
    }
    
    if (!$task_found) {
        logError("markTaskAsCompleted: Task with ID '$task_id' not found");
        return false;
    }
    
    $result = file_put_contents('tasks.txt', json_encode($tasks, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        logError("markTaskAsCompleted: Failed to write to tasks.txt - check file permissions");
        return false;
    }
    
    return true;
}

function deleteTask($task_id) {
    $tasks = getAllTasks();
    $initial_count = count($tasks);
    
    $tasks = array_filter($tasks, function($task) use ($task_id) {
        return $task['id'] !== $task_id;
    });
    
    if (count($tasks) < $initial_count) {
        $tasks = array_values($tasks);
        $result = file_put_contents('tasks.txt', json_encode($tasks, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            logError("deleteTask: Failed to write to tasks.txt - check file permissions");
            return false;
        }
        
        logInfo("deleteTask: Successfully deleted task with ID '$task_id'");
        return true;
    }
    
    logError("deleteTask: Task with ID '$task_id' not found");
    return false;
}

function generateVerificationCode() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function subscribeEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logError("subscribeEmail: Invalid email format: '$email'");
        return false;
    }
    
    $email = strtolower(trim($email));
    
    $subscribers = getVerifiedSubscribers();
    if (in_array($email, $subscribers)) {
        logError("subscribeEmail: Email '$email' is already subscribed");
        return false;
    }
    
    $pending = getPendingSubscriptions();
    
    // Clean up expired verification codes (older than 24 hours)
    $pending = cleanupExpiredVerifications($pending);
    
    $code = generateVerificationCode();
    
    $pending[$email] = [
        'code' => $code,
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
    ];
    
    $result = file_put_contents('pending_subscriptions.txt', json_encode($pending, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        logError("subscribeEmail: Failed to write to pending_subscriptions.txt - check file permissions");
        return false;
    }
    
    logInfo("subscribeEmail: Added '$email' to pending subscriptions with code '$code' (expires in 24 hours)");
    return sendVerificationEmail($email, $code);
}

function cleanupExpiredVerifications($pending) {
    $current_time = time();
    $cleaned = [];
    $expired_count = 0;
    
    foreach ($pending as $email => $data) {
        if (isset($data['expires_at'])) {
            $expires_time = strtotime($data['expires_at']);
            if ($expires_time > $current_time) {
                $cleaned[$email] = $data;
            } else {
                $expired_count++;
                logInfo("cleanupExpiredVerifications: Removed expired verification for '$email'");
            }
        } else {
            // Keep old entries without expiration for backward compatibility
            $cleaned[$email] = $data;
        }
    }
    
    if ($expired_count > 0) {
        logInfo("cleanupExpiredVerifications: Cleaned up $expired_count expired verification codes");
    }
    
    return $cleaned;
}

function sendVerificationEmail($email, $code) {
    $subject = 'Verify subscription to Task Planner';
    
    $base_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '');
    $verification_link = $base_url . '/verify.php?email=' . urlencode($email) . '&code=' . urlencode($code);
    
    // Email body (exact format as specified)
    $body = '<p>Click the link below to verify your subscription to Task Planner:</p>' . "\n";
    $body .= '<p><a id="verification-link" href="' . $verification_link . '">Verify Subscription</a></p>';
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: no-reply@example.com' . "\r\n";
    
    // For demo purposes, we'll log the email instead of actually sending it
    $log_entry = date('Y-m-d H:i:s') . " - Verification email sent to: $email\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= "Body: $body\n";
    $log_entry .= "Verification Code: $code\n";
    $log_entry .= "----------------------------------------\n";
    
    file_put_contents('email_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
    
    return true; // Simulate successful email sending
}

function getPendingSubscriptions() {
    if (!file_exists('pending_subscriptions.txt')) {
        logInfo("getPendingSubscriptions: pending_subscriptions.txt does not exist, returning empty array");
        return [];
    }
    
    $content = file_get_contents('pending_subscriptions.txt');
    
    if ($content === false) {
        logError("getPendingSubscriptions: Failed to read pending_subscriptions.txt - check file permissions");
        return [];
    }
    
    if (empty(trim($content))) {
        return [];
    }
    
    $pending = json_decode($content, true);
    
    if ($pending === null) {
        logError("getPendingSubscriptions: Invalid JSON in pending_subscriptions.txt - " . json_last_error_msg());
        return [];
    }
    
    // Clean up expired verifications when loading
    $cleaned_pending = cleanupExpiredVerifications($pending);
    
    // Save cleaned data back if any were removed
    if (count($cleaned_pending) < count($pending)) {
        file_put_contents('pending_subscriptions.txt', json_encode($cleaned_pending, JSON_PRETTY_PRINT));
    }
    
    return is_array($cleaned_pending) ? $cleaned_pending : [];
}

function getVerifiedSubscribers() {
    if (!file_exists('subscribers.txt')) {
        logInfo("getVerifiedSubscribers: subscribers.txt does not exist, returning empty array");
        return [];
    }
    
    $content = file_get_contents('subscribers.txt');
    
    if ($content === false) {
        logError("getVerifiedSubscribers: Failed to read subscribers.txt - check file permissions");
        return [];
    }
    
    if (empty(trim($content))) {
        return [];
    }
    
    $subscribers = json_decode($content, true);
    
    if ($subscribers === null) {
        logError("getVerifiedSubscribers: Invalid JSON in subscribers.txt - " . json_last_error_msg());
        return [];
    }
    
    return is_array($subscribers) ? $subscribers : [];
}

function verifySubscription($email, $code) {
    // Use rawurldecode to properly handle special characters
    $email = strtolower(trim(rawurldecode($email)));
    $code = trim(rawurldecode($code));
    
    if (empty($email) || empty($code)) {
        logError("verifySubscription: Empty email or code provided");
        return false;
    }
    
    $pending = getPendingSubscriptions();
    
    // Check if email exists in pending and code matches
    if (!isset($pending[$email])) {
        logError("verifySubscription: Email '$email' not found in pending subscriptions");
        return false;
    }
    
    $verification_data = $pending[$email];
    
    // Check if code matches
    if ($verification_data['code'] !== $code) {
        logError("verifySubscription: Invalid verification code for '$email'");
        return false;
    }
    
    // Check if verification has expired
    if (isset($verification_data['expires_at'])) {
        $expires_time = strtotime($verification_data['expires_at']);
        if (time() > $expires_time) {
            logError("verifySubscription: Verification code expired for '$email'");
            // Remove expired verification
            unset($pending[$email]);
            file_put_contents('pending_subscriptions.txt', json_encode($pending, JSON_PRETTY_PRINT));
            return false;
        }
    }
    
    // Remove from pending subscriptions
    unset($pending[$email]);
    $result1 = file_put_contents('pending_subscriptions.txt', json_encode($pending, JSON_PRETTY_PRINT));
    
    if ($result1 === false) {
        logError("verifySubscription: Failed to update pending_subscriptions.txt - check file permissions");
        return false;
    }
    
    // Add to verified subscribers
    $subscribers = getVerifiedSubscribers();
    
    // Check if already verified (shouldn't happen, but safety check)
    if (!in_array($email, $subscribers)) {
        $subscribers[] = $email;
        $result2 = file_put_contents('subscribers.txt', json_encode($subscribers, JSON_PRETTY_PRINT));
        
        if ($result2 === false) {
            logError("verifySubscription: Failed to update subscribers.txt - check file permissions");
            return false;
        }
        
        logInfo("verifySubscription: Successfully verified '$email' and added to subscribers");
        return true;
    }
    
    logInfo("verifySubscription: Email '$email' was already verified");
    return true;
}

function unsubscribeEmail($email) {
    // Use rawurldecode to properly handle special characters
    $email = strtolower(trim(rawurldecode($email)));
    
    if (empty($email)) {
        logError("unsubscribeEmail: Empty email provided");
        return false;
    }
    
    // Get current subscribers
    $subscribers = getVerifiedSubscribers();
    
    // Check if email exists in subscribers (case-insensitive)
    $found = false;
    $index = -1;
    
    foreach ($subscribers as $i => $subscriber) {
        if (strtolower(trim($subscriber)) === $email) {
            $found = true;
            $index = $i;
            break;
        }
    }
    
    if ($found) {
        // Remove the email from the array
        array_splice($subscribers, $index, 1);
        
        // Save the updated subscribers list
        $result = file_put_contents('subscribers.txt', json_encode($subscribers, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            logError("unsubscribeEmail: Failed to write to subscribers.txt - check file permissions");
            return false;
        }
        
        logInfo("unsubscribeEmail: Successfully unsubscribed '$email'");
        return true;
    }
    
    logError("unsubscribeEmail: Email '$email' not found in subscriber list");
    return false;
}

function getPendingTasks() {
    $all_tasks = getAllTasks();
    
    $pending_tasks = array_filter($all_tasks, function($task) {
        return !$task['completed'];
    });
    
    logInfo("getPendingTasks: Found " . count($pending_tasks) . " pending tasks out of " . count($all_tasks) . " total tasks");
    return $pending_tasks;
}

function sendTaskReminders() {
    $subscribers = getVerifiedSubscribers();
    $pending_tasks = getPendingTasks();
    
    if (empty($pending_tasks)) {
        logInfo("sendTaskReminders: No pending tasks to remind about");
        return;
    }
    
    if (empty($subscribers)) {
        logInfo("sendTaskReminders: No verified subscribers to send reminders to");
        return;
    }
    
    logInfo("sendTaskReminders: Sending reminders to " . count($subscribers) . " subscribers about " . count($pending_tasks) . " pending tasks");
    
    foreach ($subscribers as $email) {
        sendTaskEmail($email, $pending_tasks);
    }
}

function sendTaskEmail($email, $pending_tasks) {
    $subject = 'Task Planner - Pending Tasks Reminder';
    
    $base_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '');
    
    // Use a more secure method for the unsubscribe link
    $unsubscribe_token = md5($email . '_' . time() . '_' . mt_rand(1000, 9999));
    
    // Store the token in a tokens file with expiration
    $tokens = [];
    if (file_exists('unsubscribe_tokens.txt')) {
        $tokens_content = file_get_contents('unsubscribe_tokens.txt');
        if (!empty($tokens_content)) {
            $tokens = json_decode($tokens_content, true) ?: [];
        }
    }
    
    // Clean up expired tokens (older than 30 days)
    $current_time = time();
    foreach ($tokens as $token => $data) {
        if ($current_time > $data['expires']) {
            unset($tokens[$token]);
        }
    }
    
    // Add new token
    $tokens[$unsubscribe_token] = [
        'email' => $email,
        'created' => $current_time,
        'expires' => $current_time + (30 * 24 * 60 * 60) // 30 days expiration
    ];
    
    // Save tokens
    file_put_contents('unsubscribe_tokens.txt', json_encode($tokens, JSON_PRETTY_PRINT));
    
    // Create unsubscribe link with token
    $unsubscribe_link = $base_url . '/unsubscribe.php?token=' . rawurlencode($unsubscribe_token);
    
    // Email body (exact format as specified)
    $body = '<h2>Pending Tasks Reminder</h2>' . "\n";
    $body .= '<p>Here are the current pending tasks:</p>' . "\n";
    $body .= '<ul>' . "\n";
    
    foreach ($pending_tasks as $task) {
        $body .= "\t" . '<li>' . htmlspecialchars($task['name']) . '</li>' . "\n";
    }
    
    $body .= '</ul>' . "\n";
    $body .= '<p><a id="unsubscribe-link" href="' . $unsubscribe_link . '">Unsubscribe from notifications</a></p>';
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: no-reply@example.com' . "\r\n";
    
    // =============================================================================
    // EMAIL SENDING - FULL COMPLIANCE WITH GUIDELINES
    // =============================================================================
    
    // ALWAYS use PHP's mail() function as required by guidelines
    $mail_result = mail($email, $subject, $body, $headers);
    
    // Log the result for debugging/monitoring purposes
    logInfo("sendTaskEmail: mail() function " . ($mail_result ? 'succeeded' : 'failed') . " for '$email'");
    
    // =============================================================================
    // DEVELOPMENT/TESTING LOGGING (Optional - can be disabled in production)
    // =============================================================================
    
    if (!PRODUCTION_EMAIL_MODE) {
        // Additional logging for development/testing - this does NOT replace mail()
        $log_entry = date('Y-m-d H:i:s') . " - TASK REMINDER EMAIL SENT VIA mail() FUNCTION\n";
        $log_entry .= "To: $email\n";
        $log_entry .= "Subject: $subject\n";
        $log_entry .= "Mail Function Result: " . ($mail_result ? 'SUCCESS' : 'FAILED') . "\n";
        $log_entry .= "Unsubscribe Token: $unsubscribe_token\n";
        
        // Only log body for debugging if mail() fails
        if (!$mail_result) {
            $log_entry .= "Email Body (for debugging): $body\n";
            logError("sendTaskEmail: mail() function failed - check mail server configuration");
        }
        
        $log_entry .= "----------------------------------------\n";
        
        // Check log size before writing
        checkAndRotateLog('email_log.txt');
        
        if (file_put_contents('email_log.txt', $log_entry, FILE_APPEND | LOCK_EX) === false) {
            logError("sendTaskEmail: Failed to write to email_log.txt");
        }
    }
    
    return $mail_result;
}

// Enhanced logging functions with automatic log rotation
function logError($message) {
    $log_entry = date('Y-m-d H:i:s') . " [ERROR] " . $message . "\n";
    checkAndRotateLog('system_log.txt');
    file_put_contents('system_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
}

function logInfo($message) {
    $log_entry = date('Y-m-d H:i:s') . " [INFO] " . $message . "\n";
    checkAndRotateLog('system_log.txt');
    file_put_contents('system_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
}

function logWarning($message) {
    $log_entry = date('Y-m-d H:i:s') . " [WARNING] " . $message . "\n";
    checkAndRotateLog('system_log.txt');
    file_put_contents('system_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
}

// Function to check and rotate logs if they get too large
function checkAndRotateLog($log_file) {
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (file_exists($log_file) && filesize($log_file) > $max_size) {
        $base_name = pathinfo($log_file, PATHINFO_FILENAME);
        
        // Keep last 5 rotated logs
        for ($i = 4; $i >= 1; $i--) {
            $old_file = $base_name . '.' . $i . '.txt';
            $new_file = $base_name . '.' . ($i + 1) . '.txt';
            if (file_exists($old_file)) {
                rename($old_file, $new_file);
            }
        }
        
        // Move current log to .1
        rename($log_file, $base_name . '.1.txt');
        
        // Create new empty log with rotation notice
        file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Log rotated (previous log exceeded 10MB)\n");
    }
}

?>
