# Firebase Push Notification Troubleshooting Guide

## Issue: Proxy Teacher Not Receiving Push Notifications

When assigning a proxy teacher, the system creates a notification log and attempts to send a Firebase push notification, but the teacher doesn't receive it.

---

## 🔍 Diagnostic Steps

### 1. Check Laravel Logs

Run this command to see recent Firebase-related logs:
```bash
tail -100 storage/logs/laravel.log | grep -i "firebase\|proxy\|push\|fcm"
```

Look for these log messages:
- ✅ `"Attempting to send push notification to proxy teacher"` - Code is executing
- ✅ `"Firebase credentials file found"` - Credentials exist
- ❌ `"Firebase credentials file not found"` - **PROBLEM**: Missing credentials
- ✅ `"Push notification sent successfully"` - Firebase accepted the message
- ❌ `"Push notification returned false"` - **PROBLEM**: Firebase not initialized
- ❌ `"Failed to send push notification"` - **PROBLEM**: Error occurred (check error message)
- ⚠️ `"Teacher has no FCM token"` - **PROBLEM**: Teacher's FCM token is null/empty

---

## 🛠️ Common Issues & Solutions

### Issue 1: Firebase Credentials Not Configured

**Symptoms:**
- Log shows: `"Firebase credentials file not found"`
- Log shows: `"Firebase messaging is not initialized. Skipping push notification."`

**Solution:**

1. **Check if .env has FIREBASE_CREDENTIALS:**
   ```bash
   grep FIREBASE_CREDENTIALS .env
   ```

2. **If missing, add to .env:**
   ```env
   FIREBASE_CREDENTIALS=/path/to/your/firebase-creds.json
   ```
   
   Or use default location:
   ```env
   FIREBASE_CREDENTIALS=storage/app/firebase/firebase-creds.json
   ```

3. **Verify credentials file exists:**
   ```bash
   ls -la storage/app/firebase/firebase-creds.json
   ```

4. **If file doesn't exist:**
   - Download service account JSON from Firebase Console
   - Place it in `storage/app/firebase/` directory
   - Set proper permissions:
     ```bash
     chmod 644 storage/app/firebase/firebase-creds.json
     ```

5. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

---

### Issue 2: Teacher Has No FCM Token

**Symptoms:**
- Log shows: `"Teacher has no FCM token, skipping push notification"`

**Solution:**

1. **Check teacher's FCM token in database:**
   ```sql
   SELECT id, first_name, sur_name, fcm_token 
   FROM users 
   WHERE id = <teacher_id>;
   ```

2. **If fcm_token is NULL or empty:**
   - Teacher needs to login/register from mobile app
   - App should send FCM token to backend via `/api/update-fcm-token` endpoint
   - Verify mobile app is properly configured with Firebase

3. **Test FCM token update:**
   ```bash
   curl -X PUT http://localhost:8000/api/update-fcm-token \
     -H "Authorization: Bearer TEACHER_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
       "fcm_token": "YOUR_FCM_TOKEN_HERE"
     }'
   ```

---

### Issue 3: Invalid FCM Token

**Symptoms:**
- Log shows: `"Firebase Notification Failed (Token): Invalid registration token"`
- Teacher previously received notifications but stopped

**Solution:**

1. **FCM tokens can expire or become invalid when:**
   - User uninstalls/reinstalls app
   - User clears app data
   - Token refreshes (happens periodically)

2. **Update the token:**
   - Teacher should logout and login again from mobile app
   - App should automatically send new FCM token

3. **Clear old token from database:**
   ```sql
   UPDATE users 
   SET fcm_token = NULL 
   WHERE id = <teacher_id>;
   ```
   Then have teacher login again to get fresh token.

---

### Issue 4: Firebase Project Configuration

**Symptoms:**
- Credentials file exists
- FCM token is valid
- Still getting errors

**Solution:**

1. **Verify Firebase project settings:**
   - Go to Firebase Console → Project Settings → Cloud Messaging
   - Ensure "Cloud Messaging API" is enabled
   - Check that you're using the correct project

2. **Check service account permissions:**
   - Service account should have "Firebase Admin SDK Administrator" role
   - Verify in Google Cloud Console → IAM & Admin

3. **Test with Firebase CLI:**
   ```bash
   # Install Firebase CLI if not installed
   npm install -g firebase-tools
   
   # Login
   firebase login
   
   # Test messaging
   firebase messaging:send --token=<FCM_TOKEN> --title="Test" --body="Test message"
   ```

---

### Issue 5: Android/iOS App Configuration

**Symptoms:**
- Backend sends notification successfully
- Teacher doesn't receive it on device

**Solution:**

#### For Android:
1. **Check google-services.json:**
   - File should be in `android/app/` directory
   - Package name must match Firebase project

2. **Verify Firebase initialization in app:**
   ```java
   // In Application class or MainActivity
   FirebaseApp.initializeApp(this);
   ```

3. **Check notification channel:**
   ```java
   // Android 8.0+ requires notification channels
   NotificationChannel channel = new NotificationChannel(
       "default_channel",
       "Default Channel",
       NotificationManager.IMPORTANCE_HIGH
   );
   ```

#### For iOS:
1. **Check GoogleService-Info.plist:**
   - File should be in `ios/Runner/` directory
   - Bundle ID must match Firebase project

2. **Enable Push Notifications capability:**
   - Xcode → Signing & Capabilities → Add "Push Notifications"

3. **Request permission in app:**
   ```swift
   UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, error in
       // Handle permission
   }
   ```

---

## 🧪 Testing Push Notifications

### Test 1: Manual Firebase Test

Use Firebase Console to send test notification:
1. Go to Firebase Console → Cloud Messaging
2. Click "Send your first message"
3. Enter title and message
4. Select "Send test message"
5. Enter the teacher's FCM token
6. Click "Test"

If this works → Firebase is configured correctly  
If this fails → Problem with Firebase setup or token

---

### Test 2: Backend API Test

Assign a proxy teacher and check logs:
```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i "proxy\|firebase"

# In another terminal, call the API
curl -X POST http://localhost:8000/api/v1/principal/free-period-teachers/notify-teacher \
  -H "Authorization: Bearer PRINCIPAL_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lecture_id": 103,
    "teacher_id": 2,
    "message": "Test proxy assignment"
  }'
```

Check logs for:
- ✅ Success messages
- ❌ Error messages
- ⚠️ Warning messages

---

### Test 3: Database Verification

After assigning proxy teacher, verify notification was created:
```sql
SELECT * 
FROM notification_logs 
WHERE user_id = <teacher_id> 
  AND type = 'proxy_class_assignment' 
ORDER BY created_at DESC 
LIMIT 1;
```

Should show:
- `is_read = 0` (unread)
- `type = 'proxy_class_assignment'`
- Proper metadata in JSON

---

## 📊 Debug Checklist

Use this checklist to systematically debug:

- [ ] Firebase credentials file exists at configured path
- [ ] `.env` has `FIREBASE_CREDENTIALS` variable set
- [ ] Laravel config cache cleared (`php artisan config:clear`)
- [ ] Teacher has valid FCM token in database
- [ ] FCM token is not expired/invalid
- [ ] Mobile app is properly configured with Firebase
- [ ] Mobile app has notification permissions granted
- [ ] Firebase project has Cloud Messaging enabled
- [ ] Service account has proper permissions
- [ ] Laravel logs show successful send attempt
- [ ] No errors in Laravel logs
- [ ] Test notification from Firebase Console works

---

## 🔧 Quick Fix Commands

```bash
# 1. Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 2. Check Firebase credentials
ls -la storage/app/firebase/firebase-creds.json

# 3. Check teacher's FCM token
mysql -u root -p -e "SELECT id, first_name, fcm_token FROM users WHERE id = <teacher_id>;"

# 4. Monitor logs while testing
tail -f storage/logs/laravel.log

# 5. Restart Laravel server (if using artisan serve)
# Ctrl+C then:
php artisan serve
```

---

## 📞 If Nothing Works

If you've tried everything and push notifications still don't work:

1. **Enable Firebase debug mode:**
   Add to `FirebaseNotificationService.php`:
   ```php
   $factory = (new Factory)
       ->withServiceAccount($credentialsPath)
       ->withDatabaseUri('your-database-url');
   ```

2. **Test with a simple PHP script:**
   ```php
   <?php
   require 'vendor/autoload.php';
   
   use Kreait\Firebase\Factory;
   
   $factory = (new Factory)->withServiceAccount('path/to/creds.json');
   $messaging = $factory->createMessaging();
   
   $message = CloudMessage::withTarget('token', 'FCM_TOKEN')
       ->withNotification(Notification::create('Test', 'Test message'));
   
   try {
       $result = $messaging->send($message);
       echo "Success: " . $result;
   } catch (Exception $e) {
       echo "Error: " . $e->getMessage();
   }
   ```

3. **Contact Firebase Support:**
   - Provide error messages from logs
   - Share Firebase project configuration
   - Include test results from above steps

---

## 📝 Notes

- **NotificationLog vs Push Notification**: The system ALWAYS creates a NotificationLog entry (for in-app viewing). Push notification is separate and requires Firebase.
- **Dual Notifications**: Teachers may receive TWO notifications when assigned as proxy:
  1. From `NotifyFreeTeachersAction` (type: `extra_class_assignment`)
  2. From our custom code (type: `proxy_class_assignment`)
- **Silent Failures**: Firebase errors are caught and logged but don't break the API response. Always check logs!
- **Token Refresh**: FCM tokens can change. Apps should update tokens on every login.

---

## ✅ Expected Behavior

When everything works correctly:

1. Principal assigns proxy teacher via API
2. System creates NotificationLog entry ✅
3. System sends Firebase push notification ✅
4. Teacher receives push notification on device ✅
5. Teacher can view notification in app's notification list ✅
6. Notification shows as unread until opened ✅
