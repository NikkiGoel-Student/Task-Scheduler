<?php
require_once 'functions.php';

$message = '';
$success = false;
$debug_info = [];

if (isset($_GET['email']) && isset($_GET['code'])) {
    $email = trim($_GET['email']);
    $code = trim($_GET['code']);
    
    $debug_info['received_email'] = $email;
    $debug_info['received_code'] = $code;
    $debug_info['decoded_email'] = rawurldecode($email);
    $debug_info['decoded_code'] = rawurldecode($code);
    
    // Check if email and code are not empty
    if (empty($email) || empty($code)) {
        $message = 'Invalid verification link. Email or code is missing.';
    } else {
        // Check current pending subscriptions for debugging
        $pending = getPendingSubscriptions();
        $debug_info['pending_subscriptions'] = $pending;
        
        if (verifySubscription($email, $code)) {
            $success = true;
            $message = 'Your email subscription has been successfully verified! You will now receive hourly task reminders.';
        } else {
            // Check if verification expired
            $decoded_email = strtolower(trim(rawurldecode($email)));
            if (isset($pending[$decoded_email])) {
                $verification_data = $pending[$decoded_email];
                if (isset($verification_data['expires_at'])) {
                    $expires_time = strtotime($verification_data['expires_at']);
                    if (time() > $expires_time) {
                        $message = 'Verification link has expired. Please subscribe again to receive a new verification email.';
                    } else {
                        $message = 'Invalid verification code. Please check the link in your email.';
                    }
                } else {
                    $message = 'Invalid verification code. Please check the link in your email.';
                }
            } else {
                $message = 'Invalid or expired verification link. Please try subscribing again.';
            }
            
            // Additional debugging info
            $debug_info['verification_attempt'] = [
                'email_found' => isset($pending[$decoded_email]),
                'current_time' => date('Y-m-d H:i:s'),
                'expires_at' => isset($pending[$decoded_email]['expires_at']) ? $pending[$decoded_email]['expires_at'] : 'N/A'
            ];
        }
    }
} else {
    $message = 'Invalid verification link. Missing required parameters.';
    $debug_info['get_params'] = $_GET;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Task Scheduler</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            padding: 50px 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s ease-out 0.2s both;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
            background-size: 200% 100%;
            animation: gradientShift 3s ease infinite;
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            animation: titlePulse 2s ease-in-out infinite alternate;
        }
        
        @keyframes titlePulse {
            from { text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
            to { text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); }
        }
        
        .message {
            padding: 25px 30px;
            border-radius: 15px;
            margin: 25px 0;
            font-size: 18px;
            line-height: 1.6;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            animation: messageSlide 0.5s ease-out 0.4s both;
        }
        
        @keyframes messageSlide {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .message::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
            border-left: 6px solid #28a745;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.2);
        }
        
        .error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #dc3545;
            border-left: 6px solid #dc3545;
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.2);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 35px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 14px;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .back-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .back-link:hover::before {
            left: 100%;
        }
        
        .back-link:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 123, 255, 0.4);
        }
        
        .back-link:active {
            transform: translateY(-1px) scale(1.02);
        }
        
        .demo-info {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-left: 6px solid #ffc107;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            font-size: 15px;
            color: #856404;
            font-weight: 500;
            animation: infoFloat 0.6s ease-out 0.3s both;
        }
        
        @keyframes infoFloat {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .verification-details {
            margin: 25px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            border: 2px solid #dee2e6;
            animation: detailsSlide 0.6s ease-out 0.5s both;
        }
        
        @keyframes detailsSlide {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .verification-details strong {
            color: #495057;
            font-size: 16px;
        }
        
        .debug-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #6c757d;
            border-left: 6px solid #6c757d;
            padding: 20px;
            border-radius: 15px;
            margin: 25px 0;
            text-align: left;
            font-size: 13px;
            font-family: 'Courier New', Consolas, monospace;
            color: #495057;
            white-space: pre-wrap;
            overflow-x: auto;
            animation: debugSlide 0.6s ease-out 0.6s both;
        }
        
        @keyframes debugSlide {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .container {
                padding: 30px 25px;
                border-radius: 15px;
            }
            
            h1 {
                font-size: 2rem;
                margin-bottom: 25px;
            }
            
            .message {
                padding: 20px;
                font-size: 16px;
            }
            
            .back-link {
                padding: 12px 25px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 1.8rem;
            }
            
            .container {
                padding: 25px 20px;
            }
            
            .message {
                padding: 18px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Verification</h1>
        
        <div class="demo-info">
            <strong>Email System:</strong> This system uses PHP's mail() function to send actual emails. 
            Verification codes expire after 24 hours for security.
        </div>
        
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        
        <?php if (isset($_GET['email']) && isset($_GET['code'])): ?>
            <div class="verification-details">
                <strong>Verification Details:</strong><br>
                Email: <?php echo htmlspecialchars(rawurldecode($_GET['email'])); ?><br>
                Code: <?php echo htmlspecialchars(rawurldecode($_GET['code'])); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($debug_info) && !$success): ?>
            <div class="debug-info">
                <strong>Debug Information:</strong><br>
                <?php echo htmlspecialchars(print_r($debug_info, true)); ?>
            </div>
        <?php endif; ?>
        
        <a href="index.php" class="back-link">Return to Task Scheduler</a>
    </div>
</body>
</html>