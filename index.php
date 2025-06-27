<?php
require_once 'functions.php';

// Initialize files if they don't exist
if (!file_exists('tasks.txt')) {
    file_put_contents('tasks.txt', '[]');
}
if (!file_exists('subscribers.txt')) {
    file_put_contents('subscribers.txt', '[]');
}
if (!file_exists('pending_subscriptions.txt')) {
    file_put_contents('pending_subscriptions.txt', '{}');
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['task-name']) && !empty(trim($_POST['task-name']))) {
        if (addTask($_POST['task-name'])) {
            $message = 'Task added successfully!';
            $message_type = 'success';
        } else {
            $message = 'Failed to add task. It may already exist.';
            $message_type = 'error';
        }
    }
    
    if (isset($_POST['email']) && !empty(trim($_POST['email']))) {
        if (subscribeEmail($_POST['email'])) {
            $message = 'Your verification link is saved in email_log.txt. Please open this file, find the link labeled "Verify Subscription," and click it to confirm your subscription.';
            $message_type = 'success';
        } else {
            $message = 'Failed to subscribe. Email may be invalid or already subscribed.';
            $message_type = 'error';
        }
    }
    
    if (isset($_POST['toggle-task']) && isset($_POST['task-id'])) {
        $tasks = getAllTasks();
        foreach ($tasks as $task) {
            if ($task['id'] === $_POST['task-id']) {
                if (markTaskAsCompleted($_POST['task-id'], !$task['completed'])) {
                    $message = 'Task status updated!';
                    $message_type = 'success';
                }
                break;
            }
        }
    }
    

    if (isset($_POST['unsubscribe_email']) && !empty(trim($_POST['unsubscribe_email']))) {
        $email_to_unsubscribe = trim($_POST['unsubscribe_email']);
        
        if (unsubscribeEmail($email_to_unsubscribe)) {
            $message = "Successfully unsubscribed '$email_to_unsubscribe' from task reminders!";
            $message_type = 'success';
        } else {
            $message = "Failed to unsubscribe '$email_to_unsubscribe'. Email not found or system error occurred.";
            $message_type = 'error';
        }
    }

    if (isset($_POST['delete-task']) && isset($_POST['task-id'])) {
        if (deleteTask($_POST['task-id'])) {
            $message = 'Task deleted successfully!';
            $message_type = 'success';
        }
    }
    
    // Redirect to prevent form resubmission
    if (!empty($message)) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode($message) . '&type=' . $message_type);
        exit;
    }
}

// Get message from URL parameters
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'info';
}

$tasks = getAllTasks();
$subscribers = getVerifiedSubscribers();
$pending = getPendingSubscriptions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow Pro - Professional Task Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-navy: #1e3a8a;
            --primary-blue: #2563eb;
            --secondary-gray: #64748b;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --neutral-50: #f8fafc;
            --neutral-100: #f1f5f9;
            --neutral-200: #e2e8f0;
            --neutral-300: #cbd5e1;
            --neutral-700: #334155;
            --neutral-800: #1e293b;
            --neutral-900: #0f172a;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--neutral-50) 0%, var(--neutral-100) 100%);
            min-height: 100vh;
            color: var(--neutral-700);
            line-height: 1.6;
        }
        
        /* Header Styles */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--neutral-200);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logo-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, var(--primary-navy) 0%, var(--primary-blue) 100%);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .logo-text {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--neutral-900);
            letter-spacing: -0.025em;
        }
        
        .logo-subtitle {
            font-size: 0.875rem;
            color: var(--secondary-gray);
            font-weight: 500;
            margin-top: -0.25rem;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--neutral-100);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-dot {
            width: 0.5rem;
            height: 0.5rem;
            background: var(--accent-green);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        /* Card Styles */
        .card {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--neutral-200);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--neutral-200);
            background: var(--neutral-50);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--neutral-900);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-title i {
            color: var(--primary-blue);
        }
        
        .card-content {
            padding: 2rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--neutral-200);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-blue);
        }
        
        .stat-card.accent-green::before { background: var(--accent-green); }
        .stat-card.accent-amber::before { background: var(--accent-amber); }
        .stat-card.accent-red::before { background: var(--accent-red); }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .stat-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--secondary-gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--white);
            background: var(--primary-blue);
        }
        
        .stat-icon.accent-green { background: var(--accent-green); }
        .stat-icon.accent-amber { background: var(--accent-amber); }
        .stat-icon.accent-red { background: var(--accent-red); }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--neutral-900);
            line-height: 1;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--neutral-700);
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--neutral-300);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: var(--white);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 0.75rem;
            align-items: end;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--primary-blue);
            color: var(--white);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-primary:hover {
            background: var(--primary-navy);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--accent-green);
            color: var(--white);
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: var(--accent-red);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--neutral-200);
            color: var(--neutral-700);
        }
        
        .btn-secondary:hover {
            background: var(--neutral-300);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }
        
        /* Task List Styles */
        .task-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .task-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            background: var(--white);
            border: 1px solid var(--neutral-200);
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .task-item:hover {
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-md);
        }
        
        .task-item.completed {
            background: var(--neutral-50);
            border-color: var(--accent-green);
        }
        
        .task-item.completed::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--accent-green);
            border-radius: 0 0.75rem 0.75rem 0;
        }
        
        .task-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            margin-right: 1rem;
            accent-color: var(--primary-blue);
            cursor: pointer;
        }
        
        .task-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .task-name {
            font-size: 1rem;
            font-weight: 500;
            color: var(--neutral-900);
            transition: all 0.2s ease;
        }
        
        .task-item.completed .task-name {
            text-decoration: line-through;
            color: var(--secondary-gray);
        }
        
        .task-meta {
            font-size: 0.875rem;
            color: var(--secondary-gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .task-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 0.75rem;
            border: 1px solid var(--neutral-200);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }
        
        .table th {
            background: var(--neutral-50);
            padding: 1rem;
            text-align: left;
            font-weight: 500;
            color: var(--neutral-700);
            font-size: 0.875rem;
            border-bottom: 1px solid var(--neutral-200);
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--neutral-200);
            font-size: 0.875rem;
        }
        
        .table tr:hover {
            background: var(--neutral-50);
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--secondary-gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--neutral-700);
        }
        
        .empty-state p {
            font-size: 0.875rem;
        }
        
        /* Email Section */
        .email-section {
            grid-column: 1 / -1;
        }
        
        .email-info {
            background: var(--neutral-50);
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-blue);
        }
        
        .email-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .email-info p {
            font-size: 0.875rem;
            color: var(--secondary-gray);
            line-height: 1.6;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
            
            .header-content {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .header-actions {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .task-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .task-actions {
                align-self: flex-end;
            }
            
            .table-container {
                font-size: 0.8125rem;
            }
            
            .card-content {
                padding: 1.5rem;
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div>
                    <div class="logo-text">TaskFlow Pro</div>
                    <div class="logo-subtitle">Professional Task Management</div>
                </div>
            </div>
            <div class="header-actions">
                <div class="status-indicator">
                    <div class="status-dot"></div>
                    <span>System Online</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <!-- Alerts -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>" style="grid-column: 1 / -1;">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Tasks</div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($tasks); ?></div>
            </div>
            
            <div class="stat-card accent-amber">
                <div class="stat-header">
                    <div class="stat-title">Pending Tasks</div>
                    <div class="stat-icon accent-amber">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count(array_filter($tasks, function($t) { return !$t['completed']; })); ?></div>
            </div>
            
            <div class="stat-card accent-green">
                <div class="stat-header">
                    <div class="stat-title">Active Subscribers</div>
                    <div class="stat-icon accent-green">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($subscribers); ?></div>
            </div>
            
            <div class="stat-card accent-red">
                <div class="stat-header">
                    <div class="stat-title">Pending Verifications</div>
                    <div class="stat-icon accent-red">
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($pending); ?></div>
            </div>
        </div>

        <!-- Add Task -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-plus-circle"></i>
                    Create New Task
                </h2>
            </div>
            <div class="card-content">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="task-name">Task Description</label>
                            <input type="text" 
                                   class="form-input" 
                                   name="task-name" 
                                   id="task-name" 
                                   placeholder="Enter task description..." 
                                   required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Task
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Task List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list-check"></i>
                    Task Overview
                </h2>
            </div>
            <div class="card-content">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard"></i>
                        <h3>No Tasks Yet</h3>
                        <p>Create your first task to get started with productivity tracking.</p>
                    </div>
                <?php else: ?>
                    <ul class="task-list">
                        <?php foreach ($tasks as $task): ?>
                            <li class="task-item <?php echo $task['completed'] ? 'completed' : ''; ?>">
                                <form method="POST" style="display: inline; margin: 0;">
                                    <input type="hidden" name="task-id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                    <input type="checkbox" 
                                           class="task-checkbox" 
                                           <?php echo $task['completed'] ? 'checked' : ''; ?>
                                           onchange="this.form.submit()"
                                           name="toggle-task">
                                </form>
                                
                                <div class="task-content">
                                    <div class="task-name"><?php echo htmlspecialchars($task['name']); ?></div>
                                    <div class="task-meta">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Created: <?php echo $task['created_at']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="task-actions">
                                    <form method="POST" style="display: inline; margin: 0;">
                                        <input type="hidden" name="task-id" value="<?php echo htmlspecialchars($task['id']); ?>">
                                        <button type="submit" 
                                                class="btn btn-danger btn-sm" 
                                                name="delete-task"
                                                onclick="return confirm('Are you sure you want to delete this task?')">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Email Notifications -->
        <div class="card email-section">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-bell"></i>
                    Email Notifications
                </h2>
            </div>
            <div class="card-content">
                <div class="email-info">
                    <h4>
                        <i class="fas fa-info-circle"></i>
                        Smart Reminder System
                    </h4>
                    <p>
                        Subscribe to receive intelligent hourly email reminders for your pending tasks. 
                        Our system uses secure verification with 24-hour expiration for enhanced security.
                        All verification links are logged in email_log.txt for easy access.
                    </p>
                </div>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="email">
                                 <label class="form-label" for="email">Email Address</label>
                            <input type="email" 
                                   class="form-input" 
                                   name="email" 
                                   id="email" 
                                   placeholder="Enter your email address..." 
                                   required>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-envelope"></i>
                            Subscribe
                        </button>
                    </div>
                </form>
                
                <!-- Unsubscribe Form -->
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                    <h4 style="margin-bottom: 1rem; color: var(--text-primary); font-weight: 600;">
                        <i class="fas fa-user-minus" style="margin-right: 0.5rem; color: var(--danger-color);"></i>
                        Unsubscribe from Notifications
                    </h4>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="unsubscribe_email">Email to Unsubscribe</label>
                                <input type="email" 
                                       class="form-input" 
                                       name="unsubscribe_email" 
                                       id="unsubscribe_email" 
                                       placeholder="Enter email to unsubscribe..." 
                                       required>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-user-times"></i>
                                Unsubscribe
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Subscribers Table -->
<div style="margin-top: 3rem;">
    <h4 style="margin-bottom: 1.5rem; color: var(--text-primary); font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-users" style="color: var(--success-color);"></i>
        Active Subscribers
    </h4>
    
    <?php if (empty($subscribers)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No Active Subscribers</h3>
            <p>No verified email subscribers yet. Subscribe above to start receiving task reminders.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="fas fa-envelope" style="margin-right: 0.5rem;"></i>Email Address</th>
                        <th><i class="fas fa-calendar-alt" style="margin-right: 0.5rem;"></i>Subscribed Date</th>
                        <th><i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td style="font-weight: 500;">
                                <?php 
                                // Handle both string and array formats
                                if (is_array($subscriber)) {
                                    echo htmlspecialchars($subscriber['email']);
                                } else {
                                    echo htmlspecialchars($subscriber);
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                // Handle both string and array formats
                                if (is_array($subscriber) && isset($subscriber['subscribed_at'])) {
                                    echo htmlspecialchars($subscriber['subscribed_at']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i>
                                    Verified
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
                
           <!-- Pending Subscriptions -->
<?php if (!empty($pending)): ?>
    <div style="margin-top: 3rem;">
        <h4 style="margin-bottom: 1.5rem; color: var(--text-primary); font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-hourglass-half" style="color: var(--warning-color);"></i>
            Pending Verifications
        </h4>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="fas fa-envelope" style="margin-right: 0.5rem;"></i>Email Address</th>
                        <th><i class="fas fa-clock" style="margin-right: 0.5rem;"></i>Requested Date</th>
                        <th><i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $email => $data): ?>
                        <tr>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($email); ?></td>
                            <td>
                                <?php 
                                // Handle both array and string formats
                                if (is_array($data) && isset($data['requested_at'])) {
                                    echo htmlspecialchars($data['requested_at']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge badge-warning">
                                    <i class="fas fa-clock"></i>
                                    Pending
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>