<?php
require_once 'functions.php';

$message = '';
$success = false;
$debug_info = [];

// Handle token-based unsubscribe
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $debug_info['token'] = $token;
    
    // Load tokens
    $tokens = [];
    if (file_exists('unsubscribe_tokens.txt')) {
        $tokens_content = file_get_contents('unsubscribe_tokens.txt');
        if (!empty($tokens_content)) {
            $tokens = json_decode($tokens_content, true) ?: [];
        }
    }
    
    $debug_info['tokens_found'] = count($tokens);
    
    // Check if token exists and is valid
    if (isset($tokens[$token])) {
        $token_data = $tokens[$token];
        $debug_info['token_data'] = $token_data;
        
        // Check if token is expired
        if (time() <= $token_data['expires']) {
            $email = $token_data['email'];
            
            // Unsubscribe the email
            if (unsubscribeEmail($email)) {
                $success = true;
                $message = 'You have been successfully unsubscribed from task reminder emails.';
                
                // Remove the used token
                unset($tokens[$token]);
                file_put_contents('unsubscribe_tokens.txt', json_encode($tokens, JSON_PRETTY_PRINT));
                
                logInfo("unsubscribe.php: Successfully unsubscribed '$email' using token");
            } else {
                $message = 'Email address not found in our subscriber list or already unsubscribed.';
                logError("unsubscribe.php: Failed to unsubscribe email '$email' - not found in subscribers");
            }
        } else {
            $message = 'This unsubscribe link has expired. Please use a more recent email to unsubscribe.';
            logError("unsubscribe.php: Expired token used");
        }
    } else {
        $message = 'Invalid or expired unsubscribe link.';
        logError("unsubscribe.php: Invalid token '$token'");
    }
}
// Handle legacy base64 email parameter for backward compatibility
else if (isset($_GET['email'])) {
    try {
        $email = base64_decode($_GET['email']);
        $debug_info['decoded_email'] = $email;
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (unsubscribeEmail($email)) {
                $success = true;
                $message = 'You have been successfully unsubscribed from task reminder emails.';
                logInfo("unsubscribe.php: Successfully unsubscribed '$email' using legacy method");
            } else {
                $message = 'Email address not found in our subscriber list or already unsubscribed.';
                logError("unsubscribe.php: Failed to unsubscribe email '$email' - not found in subscribers");
            }
        } else {
            $message = 'Invalid email address in unsubscribe link.';
            logError("unsubscribe.php: Invalid email format after base64 decode: '$email'");
        }
    } catch (Exception $e) {
        $message = 'Invalid unsubscribe link format.';
        logError("unsubscribe.php: Exception decoding email: " . $e->getMessage());
    }
} else {
    $message = 'Invalid unsubscribe link. Missing required parameters.';
    logError("unsubscribe.php: No token or email parameter provided");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - Task Scheduler</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .message {
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 16px;
            line-height: 1.5;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .back-link:hover {
            background-color: #0056b3;
        }
        .security-info {
            background-color: #e7f3ff;
            border: 1px solid #b8daff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
        }
        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: left;
            font-size: 12px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Unsubscribe</h1>
        
        <div class="security-info">
            <strong>🔒 Security Update:</strong> We've enhanced our unsubscribe system with secure tokens that expire after 30 days. This prevents unauthorized unsubscribes and protects your email privacy.
        </div>
        
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        
        <?php if (!empty($debug_info)): ?>
            <div class="debug-info">
                <strong>Debug Information:</strong><br>
                <?php echo htmlspecialchars(print_r($debug_info, true)); ?>
            </div>
        <?php endif; ?>
        
        <a href="index.php" class="back-link">Return to Task Scheduler</a>
    </div>
</body>
</html>
