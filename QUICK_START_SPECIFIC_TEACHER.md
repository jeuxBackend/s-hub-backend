# 🚀 Quick Start - New Specific Teacher Endpoint

## New Endpoint

**POST** `/v1/principal/free-period-teachers/notify-teacher`

---

## Request

```json
{
  "lecture_id": 5,
  "teacher_id": 8,
  "message": "Optional custom message"
}
```

**Required:**
- `lecture_id` - The lecture/subject ID
- `teacher_id` - The teacher to notify

**Optional:**
- `message` - Custom notification message (max 500 chars)

---

## What It Does

```
1. ✅ Verify lecture exists in same institution
2. ✅ Verify lecture has scheduled times
3. ✅ Verify teacher exists in same institution
4. ✅ Check if teacher is FREE during lecture time
5. ✅ If free → Send notification + Mark attendance as 'extra'
6. ❌ If busy → Return error "not available"
```

---

## Success Response

```json
{
  "success": true,
  "message": "Teacher notified successfully",
  "data": {
    "lecture_id": 5,
    "lecture_name": "Mathematics",
    "teacher_id": 8,
    "teacher_name": "Jane Doe",
    "notified": 1,
    "failed": 0,
    "status": "success",
    "result": {
      "teacher_id": 8,
      "teacher_name": "Jane Doe",
      "status": "success"
    }
  }
}
```

---

## Error Response 1: Teacher Not Available

```json
{
  "success": false,
  "message": "Teacher is not available during this lecture time.",
  "errors": null
}
```

---

## Error Response 2: Teacher Not Found

```json
{
  "success": false,
  "message": "Teacher not found in your institution.",
  "errors": null
}
```

---

## Test with cURL

```bash
curl -X POST http://localhost:8000/api/v1/principal/free-period-teachers/notify-teacher \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lecture_id": 5,
    "teacher_id": 8,
    "message": "Can you cover extra class at 2 PM?"
  }'
```

---

## Flow Diagram

```
Principal Request
      │
      ├─ lecture_id ✓
      ├─ teacher_id ✓
      └─ message (optional)
            │
            ▼
    Validate all inputs
            │
            ├─ Lecture exists in same institution? ✓
            ├─ Lecture has times? ✓
            ├─ Teacher exists in same institution? ✓
            └─ Teacher is available (free)? 
                 │
                 ├─ YES → Continue
                 └─ NO → Return error 400
                      │
                      ▼
              Send notification
              Mark attendance as 'extra'
                      │
                      ▼
              Return success response
```

---

## 2 Endpoints Available

| Endpoint | Use Case | Returns |
|----------|----------|---------|
| `/notify` | Auto-find all free teachers | List of all notified |
| `/notify-teacher` | Assign specific teacher | Single teacher result |

---

## Key Features

✅ **Same Institution** - Only teachers from your institution  
✅ **Availability Check** - Verifies teacher is free before notifying  
✅ **Auto Attendance** - Marks attendance as type='extra' automatically  
✅ **Firebase Notification** - Sends push notification to teacher  
✅ **Custom Message** - Optional custom notification text  
✅ **Detailed Result** - Shows exact status (success/failed)  

---

## That's It! 🎉

Both endpoints now available:
- ✅ `/notify` - Broadcast to all free teachers
- ✅ `/notify-teacher` - Assign to specific teacher

Both:
- Check teacher availability
- Verify same institution
- Send Firebase notifications
- Mark attendance as 'extra'
