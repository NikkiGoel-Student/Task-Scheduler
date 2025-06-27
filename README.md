# Task Scheduler
This is a PHP-based task management system where users can add tasks to a common list and subscribe to email with a verification code.

## 📌 Features Implemented

### 1️⃣ **Task Management**

- Add new tasks to the common list
- Duplicate tasks should not be added.
- Mark tasks as complete/incomplete
- Delete tasks
- Store tasks in `tasks.txt`

### 2️⃣ **Email Subscription System**

- Users can subscribe with their email
- Email verification process:
  - System generates a unique 6-digit verification code
  - Sends verification email with activation link
  - Link contains email and verification code
  - User clicks link to verify subscription
  - System moves email from pending to verified subscribers
- Store subscribers in `subscribers.txt`
- Store pending verifications in `pending_subscriptions.txt`

### 3️⃣ **Reminder System**

- CRON job runs every hour
- Sends emails to verified subscribers
- Only includes pending tasks in reminders
- Includes unsubscribe link in emails
- Unsubscribe process:
  - Every email includes an unsubscribe link
  - Link contains encoded email address
  - One-click unsubscribe removes email from subscribers

---
