# Fee Payment Notification System

## Overview
This system allows principals to send fee payment reminders to parents with unpaid invoices. It supports both instant notifications and scheduled automated reminders through a **single unified endpoint**.

## Features
- ✅ **Single Endpoint**: One API handles both instant and scheduled notifications
- ✅ **Instant Notifications**: Send immediate push & in-app notifications to all parents with unpaid fees
- ✅ **Scheduled Reminders**: Configure automatic daily notifications at specific times
- ✅ **Flexible Scheduling**: Choose specific days of the week or every day
- ✅ **Customizable Messages**: Set custom title and message for notifications
- ✅ **Real-time Broadcasting**: WebSocket-based in-app notifications
- ✅ **Push Notifications**: Firebase Cloud Messaging for offline users
- ✅ **Detailed Statistics**: Track total unpaid invoices, amounts, and notification results
- ✅ **Duplicate Prevention**: Prevents sending multiple notifications on the same day

---

## API Endpoints

### 1. Send Fee Notifications (Instant or Scheduled)
**Endpoint:** `POST /api/v1/principal/fee-notifications/notify`

**Access:** Principal, School Admin (with OTP verification)

#### **A. Instant Notification**

**Request Body:**
```json
{
  "type": "instant",
  "title": "Optional custom title",
  "message": "Optional custom message"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Fee reminders sent successfully",
  "data": {
    "type": "instant",
    "success": true,
    "notified": 15,
    "failed": 0,
    "total_unpaid_invoices": 23,
    "total_amount_due": 45000,
    "unique_parents": 15,
    "parents_notified": [
      {
        "parent_id": 123,
        "parent_name": "John Doe",
        "total_due": 3000,
        "invoice_count": 2,
        "status": "success"
      }
    ]
  }
}
```

---

#### **B. Create/Update Schedule**

**Request Body:**
```json
{
  "type": "scheduled",
  "notification_time": "09:00",
  "days_of_week": [1, 2, 3, 4, 5],
  "title": "Fee Payment Due",
  "message": "Please pay your outstanding fees before the due date.",
  "is_enabled": true
}
```

**Fields:**
- `type` (required): Must be `"scheduled"`
- `notification_time` (required): Time in 24-hour format (HH:MM)
- `days_of_week` (required): Array of integers 1-7 (1=Monday, 7=Sunday). Use empty array `[]` for every day
- `title` (optional): Custom notification title (max 255 chars)
- `message` (optional): Custom notification message (max 1000 chars)
- `is_enabled` (optional): Boolean to enable/disable schedule (default: false)

**Response:**
```json
{
  "success": true,
  "message": "Fee reminder schedule created successfully.",
  "data": {
    "type": "scheduled",
    "id": 1,
    "notification_time": "09:00",
    "days_of_week": [1, 2, 3, 4, 5],
    "days_names": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
    "title": "Fee Payment Due",
    "message": "Please pay your outstanding fees before the due date.",
    "is_enabled": true,
    "last_sent_at": null
  }
}
```

---

### 2. Get Fee Reminder Schedule
**Endpoint:** `GET /api/v1/principal/fee-notifications/schedule`

**Access:** Principal, School Admin

**Response:**
```json
{
  "success": true,
  "message": "Fee reminder schedule retrieved successfully.",
  "data": {
    "has_schedule": true,
    "schedule": {
      "id": 1,
      "notification_time": "09:00",
      "days_of_week": [1, 2, 3, 4, 5],
      "days_names": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
      "title": "Tuition Fee Payment Reminder",
      "message": "This is a reminder to pay your outstanding tuition fees...",
      "is_enabled": true,
      "last_sent_at": "2026-06-06 09:00:15",
      "created_at": "2026-06-05 10:30:00",
      "updated_at": "2026-06-06 08:00:00"
    }
  }
}
```

---

## Usage Examples

### Example 1: Send Instant Notification
```bash
curl -X POST http://localhost:8000/api/v1/principal/fee-notifications/notify \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "instant",
    "title": "Urgent: Fee Payment Required",
    "message": "Please pay your outstanding tuition fees immediately."
  }'
```

### Example 2: Create Daily Schedule (Every Day at 9 AM)
```bash
curl -X POST http://localhost:8000/api/v1/principal/fee-notifications/notify \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "scheduled",
    "notification_time": "09:00",
    "days_of_week": [],
    "title": "Daily Fee Reminder",
    "message": "Please pay your outstanding fees.",
    "is_enabled": true
  }'
```

### Example 3: Create Weekly Schedule (Mon, Wed, Fri at 10 AM)
```bash
curl -X POST http://localhost:8000/api/v1/principal/fee-notifications/notify \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "scheduled",
    "notification_time": "10:00",
    "days_of_week": [1, 3, 5],
    "title": "Weekly Fee Reminder",
    "message": "Reminder: Tuition fees are due.",
    "is_enabled": true
  }'
```

### Example 4: Update Existing Schedule
```bash
curl -X POST http://localhost:8000/api/v1/principal/fee-notifications/notify \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "scheduled",
    "notification_time": "14:00",
    "days_of_week": [1, 2, 3, 4, 5],
    "title": "Afternoon Fee Reminder",
    "message": "Please complete your fee payment.",
    "is_enabled": false
  }'
```

---

## Scheduled Command

### Check and Send Scheduled Reminders
**Command:** `php artisan fee-reminders:check-scheduled`

**Description:** This command should be run daily (via cron job) to check if it's time to send scheduled fee reminders.

**Setup Cron Job:**
Add to crontab (`crontab -e`):
```bash
# Run every minute to check if scheduled time has arrived
* * * * * cd /path/to/project && php artisan fee-reminders:check-scheduled >> /dev/null 2>&1
```

**How It Works:**
1. Fetches all enabled schedules from database
2. Checks if current time matches scheduled time (within 5-minute window)
3. Verifies notifications haven't been sent today already
4. Sends notifications to parents with unpaid invoices
5. Updates `last_sent_at` timestamp to prevent duplicates

**Example Output:**
```
Checking scheduled fee reminders...
Sending fee reminders for institution 1...
✓ Sent to 15 parents (0 failed) - Total due: 45000
Completed! Processed: 1, Skipped: 0
```

---

## Database Schema

### fee_reminder_schedules Table
```sql
CREATE TABLE fee_reminder_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_time TIME NOT NULL,
    days_of_week JSON NULL,
    title VARCHAR(255) DEFAULT 'Tuition Fee Payment Reminder',
    message TEXT NULL,
    is_enabled BOOLEAN DEFAULT FALSE,
    last_sent_at TIMESTAMP NULL,
    institution_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    INDEX idx_institution_enabled (institution_id, is_enabled)
);
```

---

## How It Works

### Instant Notification Flow:
1. Principal calls `/notify` endpoint with `type: "instant"`
2. System queries all `StudentInvoice` records with `status IN ('unpaid', 'partial')` AND `due_amount > 0`
3. Extracts unique parent IDs from `student.guardian_id`
4. For each parent with FCM token:
   - Creates `NotificationLog` entry with type `fee_payment_reminder`
   - Broadcasts via `NewNotificationEvent` (WebSocket)
   - Sends Firebase push notification
5. Returns detailed statistics

### Scheduled Notification Flow:
1. Principal calls `/notify` endpoint with `type: "scheduled"`
2. System creates or updates `FeeReminderSchedule` record
3. Cron runs `fee-reminders:check-scheduled` every minute
4. Command fetches all enabled schedules
5. For each schedule:
   - Checks if current day is in `days_of_week` (or any day if empty)
   - Checks if current time is within 5 minutes of `notification_time`
   - Checks if `last_sent_at` is not today
   - If all conditions met, triggers notification
   - Updates `last_sent_at` timestamp

---

## Notification Types

### In-App Notification (WebSocket)
- **Channel:** `notifications.{parent_id}`
- **Event:** `NewNotificationEvent`
- **Type:** `fee_payment_reminder`
- **Data:** Includes invoice details, amounts, student names

### Push Notification (Firebase)
- **Title:** Custom or default "Tuition Fee Payment Reminder"
- **Body:** Custom or default reminder message
- **Data Payload:** 
  ```json
  {
    "type": "fee_payment_reminder",
    "total_due": "3000",
    "invoice_count": "2"
  }
  ```

---

## Error Handling

The system handles various error scenarios:
- No unpaid invoices found → Returns success with 0 notified
- Parent has no FCM token → Still sends in-app notification
- Firebase fails → Logs error, continues with other parents
- Database errors → Caught and returned as error response
- Schedule conflicts → Prevents duplicate daily notifications
- Invalid request data → Returns validation errors

---

## Security & Permissions

- All endpoints require authentication via Sanctum
- Protected by `otp.verified` middleware
- Restricted to `principal` and `school-admin` roles
- Institution-scoped: Users can only access their own institution's data

---

## Testing

### Test Instant Notification:
```bash
curl -X POST http://localhost:8000/api/v1/principal/fee-notifications/notify \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "instant",
    "title": "Fee Reminder",
    "message": "Please pay your outstanding fees."
  }'
```

### Test Schedule Creation:
```bash
curl -X POST http://localhost:8000/api/v1/principal/fee-notifications/notify \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "scheduled",
    "notification_time": "09:00",
    "days_of_week": [1, 3, 5],
    "is_enabled": true
  }'
```

### Test Scheduled Command:
```bash
php artisan fee-reminders:check-scheduled
```

---

## Files Created/Modified

### New Files:
1. `app/Http/Controllers/Api/Principal/FeeNotificationController.php` - Main controller
2. `app/Actions/Parent/NotifyUnpaidFeeParentsAction.php` - Business logic for notifications
3. `app/Models/FeeReminderSchedule.php` - Schedule model
4. `app/Console/Commands/CheckScheduledFeeReminders.php` - Scheduled command
5. `database/migrations/2026_06_06_120927_create_fee_reminder_schedules_table.php` - Migration

### Modified Files:
1. `routes/api.php` - Added fee notification routes (2 endpoints only)

---

## Best Practices

1. **Schedule Timing**: Set notification time during business hours (9 AM - 6 PM)
2. **Frequency**: Avoid daily notifications; use 2-3 times per week
3. **Message Tone**: Keep messages professional and polite
4. **Testing**: Test with small groups before mass notifications
5. **Monitoring**: Check logs regularly for failed notifications
6. **FCM Tokens**: Ensure parents have valid FCM tokens for push notifications

---

## Troubleshooting

### Notifications Not Sending:
- Check if schedule is enabled (`is_enabled = true`)
- Verify `notification_time` matches current time
- Ensure `days_of_week` includes current day
- Check `last_sent_at` is not today
- Verify parents have FCM tokens

### Duplicate Notifications:
- System prevents duplicates via `last_sent_at` check
- If issue persists, check timezone settings in `config/app.php`

### Firebase Errors:
- Check Firebase credentials in `.env`
- Verify FCM tokens are valid
- Check Firebase quota limits

---

## Support

For issues or questions, check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

Or search for fee reminder related logs:
```bash
grep "fee reminder" storage/logs/laravel.log
```
