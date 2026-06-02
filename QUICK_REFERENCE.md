# Quick Reference Guide - Free Period Teacher Feature

## 📁 File Locations

| File | Purpose |
|------|---------|
| `/database/migrations/2026_06_02_000001_add_type_to_teacher_attendances_table.php` | Add type column to DB |
| `/app/Models/TeacherAttendance.php` | Model - Added 'type' to fillable |
| `/app/Actions/Teacher/FindFreeTeachersAction.php` | Find free teachers logic |
| `/app/Actions/Teacher/NotifyFreeTeachersAction.php` | Send notifications logic |
| `/app/Http/Controllers/Api/Principal/FreePeriodTeacherController.php` | API Controller |
| `/routes/api.php` | Route definition |

## 🚀 One-Line Commands

### Run Migration
```bash
php artisan migrate
```

### Test the API
```bash
curl -X POST http://localhost:8000/api/v1/principal/free-period-teachers/notify \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"lecture_id":1,"message":"Extra class needed"}'
```

### Check Database
```sql
SELECT * FROM teacher_attendances WHERE type = 'extra';
```

### Rollback
```bash
php artisan migrate:rollback --step=1
```

## 📊 API Endpoint

**URL:** `POST /v1/principal/free-period-teachers/notify`

**Headers:**
```
Authorization: Bearer {sanctum_token}
Content-Type: application/json
```

**Body:**
```json
{
  "lecture_id": 5,
  "message": "Optional: Custom notification message"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Free teachers notified successfully",
  "data": {
    "lecture_id": 5,
    "lecture_name": "Mathematics",
    "free_teachers_count": 3,
    "notified": 3,
    "failed": 0,
    "results": [
      {"teacher_id": 8, "teacher_name": "Jane Doe", "status": "success"},
      {"teacher_id": 12, "teacher_name": "John Smith", "status": "success"},
      {"teacher_id": 15, "teacher_name": "Sarah Wilson", "status": "success"}
    ]
  }
}
```

## 🔧 Key Classes & Methods

### FindFreeTeachersAction
```php
$action = new FindFreeTeachersAction();
$freeTeachers = $action->handle(
    lectureId: 5,
    institutionId: 1
); // Returns: [8, 12, 15]
```

### NotifyFreeTeachersAction
```php
$action = new NotifyFreeTeachersAction($notificationService);
$result = $action->handle(
    lectureId: 5,
    freeTeacherIds: [8, 12, 15],
    message: "Optional message"
);

// Returns:
// {
//   'success': true,
//   'notified': 3,
//   'failed': 0,
//   'results': [...]
// }
```

## ⚙️ Configuration Points

### Time Overlap Logic
File: `/app/Actions/Teacher/FindFreeTeachersAction.php` (lines 40-50)

Currently uses simple overlap detection:
```php
!($lectureEnd->lessThanOrEqualTo($subjectStart) || 
  $lectureStart->greaterThanOrEqualTo($subjectEnd))
```

To add buffer time (e.g., 15 min):
```php
$buffer = 15; // minutes
!($lectureEnd->addMinutes($buffer)->lessThanOrEqualTo($subjectStart) || 
  $lectureStart->subMinutes($buffer)->greaterThanOrEqualTo($subjectEnd))
```

### Notification Title & Body
File: `/app/Actions/Teacher/NotifyFreeTeachersAction.php` (lines 49-50)

Currently:
```php
$notificationTitle = 'Extra Class Assignment';
$notificationBody = $message ?? "You have been assigned to an extra class for {$lecture->name}";
```

## 📋 Database Schema

### Attendance Type Values
| Type | Meaning |
|------|---------|
| `regular` | Normal scheduled class |
| `extra` | Extra/unscheduled assignment |

### Sample Query
```sql
-- All extra assignments today
SELECT ta.*, u.first_name, u.sur_name, s.name as subject_name
FROM teacher_attendances ta
JOIN users u ON ta.teacher_id = u.id
JOIN subjects s ON ta.subject_id = s.id
WHERE ta.type = 'extra' AND ta.date = CURDATE();

-- Count by type
SELECT type, COUNT(*) as count 
FROM teacher_attendances 
WHERE date = CURDATE() 
GROUP BY type;
```

## 🔐 Security Requirements

- ✅ Must be authenticated (Bearer token)
- ✅ Must verify OTP
- ✅ Must be principal or school-admin role
- ✅ Lecture must belong to same institution
- ✅ Teachers must belong to same institution

## ❌ Error Codes & Messages

| Code | Message | Reason |
|------|---------|--------|
| 401 | Unauthorized | Missing or invalid token |
| 403 | Forbidden | Not OTP verified or wrong role |
| 404 | Lecture not found | Lecture doesn't exist or wrong institution |
| 400 | Lecture does not have scheduled time | Missing start_time or end_time |
| 422 | Validation failed | Missing required fields |
| 500 | Server error | Unexpected exception |

## 🧪 Testing Checklist

- [ ] Migration runs without errors
- [ ] Type column added to teacher_attendances table
- [ ] API endpoint accessible with valid token
- [ ] Returns 404 for non-existent lecture
- [ ] Returns error for lecture without schedule
- [ ] Correctly identifies free teachers
- [ ] Attendance records created with type='extra'
- [ ] Firebase notifications sent successfully
- [ ] Response includes correct teacher information
- [ ] Works with custom message
- [ ] Works with default message

## 🎯 Use Cases

### Use Case 1: Cover for Absent Teacher
```json
{
  "lecture_id": 42,
  "message": "Teacher Smith is absent. Can anyone cover Grade 10 English at 2 PM?"
}
```

### Use Case 2: Extra Support Session
```json
{
  "lecture_id": 23,
  "message": "Extra remedial class for struggling students - Mathematics"
}
```

### Use Case 3: Exam Invigilation
```json
{
  "lecture_id": 15,
  "message": "Exam invigilation needed for Grade 12 Final Exam"
}
```

## 📞 Troubleshooting

### Problem: "No free teachers found" but teachers should be free
**Solution:** 
1. Verify teachers have classes during that time
2. Check if subject times overlap correctly
3. Verify institution_id is correct

### Problem: Notifications not sent
**Solution:**
1. Verify teachers have FCM tokens registered
2. Check Firebase credentials are correct
3. Verify Firebase service account has messaging permission

### Problem: Type column not showing
**Solution:**
1. Run migration: `php artisan migrate`
2. Clear cache: `php artisan cache:clear`
3. Verify column in database: `DESC teacher_attendances;`

### Problem: 403 Forbidden error
**Solution:**
1. Verify user is principal (not school-admin without permissions)
2. Verify OTP is verified
3. Check authentication token is valid

## 📈 Performance Tips

For institutions with 500+ teachers:
1. Add index: `CREATE INDEX idx_teacher_subject_institution ON subjects(teacher_id, institution_id);`
2. Cache free teachers list for 5-10 minutes
3. Implement job queue for notification sending
4. Use pagination for large result sets

## 🔄 Integration Points

### With Other Systems
- **Firebase Cloud Messaging**: Sends push notifications
- **User Model**: Retrieves teacher info and FCM tokens
- **Subject Model**: Gets lecture details and schedules
- **TeacherAttendance Model**: Records attendance
- **Institution Model**: Validates same institution

### Data Dependencies
- `users` table - Teacher data
- `subjects` table - Lecture schedules
- `institutions` table - Institution info
- `teacher_attendances` table - Attendance records

## 📚 Related Documentation

- See `FREE_PERIOD_TEACHER_FEATURE_SUMMARY.md` for detailed feature info
- See `SYSTEM_ARCHITECTURE.md` for flow diagrams
- See `TESTING_AND_SETUP_GUIDE.md` for comprehensive testing guide
