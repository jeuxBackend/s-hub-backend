# Free Period Teacher Notification - UPDATED Feature Guide

## Changes Made

### New Endpoint Added: Notify Specific Teacher

**Endpoint:** `POST /v1/principal/free-period-teachers/notify-teacher`

This new endpoint allows principals to send a notification to a **specific teacher** if they are:
1. ✅ From the same institution
2. ✅ Available (free/no conflicting classes) during the lecture time
3. ✅ Registered as teacher or school-admin role

---

## 🎯 API Endpoints (Now 2 Available)

### Endpoint 1: Notify All Free Teachers (Original)
**POST** `/v1/principal/free-period-teachers/notify`

**Request:**
```json
{
  "lecture_id": 5,
  "message": "Optional message"
}
```

**Response:**
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
    "results": [...]
  }
}
```

---

### Endpoint 2: Notify Specific Teacher (NEW ✨)
**POST** `/v1/principal/free-period-teachers/notify-teacher`

**Request:**
```json
{
  "lecture_id": 5,
  "teacher_id": 8,
  "message": "Optional message"
}
```

**Response (Success):**
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

**Response (Teacher Not Available):**
```json
{
  "success": false,
  "message": "Teacher is not available during this lecture time.",
  "errors": null
}
```

---

## ✅ Validation Flow for New Endpoint

```
1. Validate request parameters
   ├─ lecture_id: required, must exist in subjects table
   ├─ teacher_id: required, must exist in users table
   └─ message: optional, max 500 characters

2. Verify lecture exists in same institution
   └─ FAIL → 404: "Lecture not found in your institution"

3. Verify lecture has scheduled times
   └─ FAIL → 400: "Lecture does not have a scheduled time"

4. Verify teacher exists in same institution
   └─ FAIL → 404: "Teacher not found in your institution"

5. Verify teacher is available (free during lecture)
   ├─ Uses FindFreeTeachersAction to check availability
   └─ FAIL → 400: "Teacher is not available during this lecture time"

6. Send notification & mark attendance
   └─ SUCCESS → Records type='extra' in teacher_attendances
   └─ SUCCESS → Sends Firebase notification
   └─ SUCCESS → Returns result
```

---

## 🔍 Key Differences

| Feature | Notify All Free | Notify Specific |
|---------|-----------------|-----------------|
| Endpoint | `/notify` | `/notify-teacher` |
| Method | Auto-finds free teachers | Principal specifies teacher |
| Request Params | lecture_id, message | lecture_id, teacher_id, message |
| Availability Check | Automatic | Validated before notification |
| Use Case | Broadcast to all free teachers | Assign specific teacher |
| Response | List of all notified | Single teacher result |

---

## 📋 Implementation Details

### Updated Controller File:
**Location:** `app/Http/Controllers/Api/Principal/FreePeriodTeacherController.php`

**New Method:** `notifyTeacher(Request $request, ...)`

**What it does:**
1. Validates all request parameters
2. Checks if lecture exists and belongs to principal's institution
3. Checks if teacher exists and belongs to principal's institution
4. Verifies teacher has proper role (teacher or school-admin)
5. Checks if teacher is free during lecture time using `FindFreeTeachersAction`
6. Sends notification and marks attendance using `NotifyFreeTeachersAction`
7. Returns detailed result

---

## 🚀 Usage Examples

### Example 1: Notify Specific Teacher for Coverage
```bash
curl -X POST http://localhost:8000/api/v1/principal/free-period-teachers/notify-teacher \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lecture_id": 42,
    "teacher_id": 8,
    "message": "Can you cover Grade 10 English at 2 PM? Teacher Smith is absent."
  }'
```

### Example 2: Notify Teacher for Extra Session (Default Message)
```bash
curl -X POST http://localhost:8000/api/v1/principal/free-period-teachers/notify-teacher \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lecture_id": 23,
    "teacher_id": 12
  }'
```

### Example 3: Notify All Free Teachers (Original Endpoint)
```bash
curl -X POST http://localhost:8000/api/v1/principal/free-period-teachers/notify \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lecture_id": 5,
    "message": "Extra help needed for struggling students"
  }'
```

---

## 🔐 Security

All validations same as before:
- ✅ Sanctum authentication
- ✅ OTP verification
- ✅ role:principal,school-admin middleware
- ✅ Institution data isolation
- ✅ Teacher role validation
- ✅ Availability verification

**Additional Validation (New):**
- ✅ Specific teacher must exist in same institution
- ✅ Specific teacher must be available (free) during lecture
- ✅ Specific teacher must have teacher or school-admin role

---

## 📊 Database Impact

**No new database changes** - uses existing tables:
- `teacher_attendances` - Records attendance with type='extra'
- `users` - Retrieves teacher information
- `subjects` - Gets lecture details
- `institutions` - Validates institution match

---

## 🎯 When to Use Each Endpoint

### Use `/notify` when:
- ✅ You want to broadcast to all available teachers
- ✅ You need help from any qualified teacher
- ✅ Multiple teachers could handle the assignment
- ✅ You want to see who responds first

### Use `/notify-teacher` when:
- ✅ You have a specific teacher in mind
- ✅ You want to assign directly to one person
- ✅ You know that teacher is free (verified manually)
- ✅ You want one-on-one assignment

---

## ✨ Benefits of New Endpoint

1. **Direct Assignment** - Assign specific teacher without broadcasting
2. **Targeted Communication** - Personalized notification to one teacher
3. **Privacy** - Other teachers don't see the assignment
4. **Efficiency** - Faster for known assignments
5. **Verification** - System verifies teacher availability before notifying

---

## 📝 Sample Flow Diagram

```
PRINCIPAL
    │
    ├─ Scenario 1: Need coverage from specific teacher
    │     │
    │     └─→ POST /free-period-teachers/notify-teacher
    │         ├─ lecture_id: 42
    │         ├─ teacher_id: 8 (Jane Doe)
    │         └─ message: "Can you cover?"
    │               │
    │               ├─ Verify Jane is free ✓
    │               ├─ Send notification to Jane
    │               ├─ Mark attendance as extra
    │               └─ Return success
    │
    └─ Scenario 2: Find any available teacher
          │
          └─→ POST /free-period-teachers/notify
              ├─ lecture_id: 5
              └─ message: "Extra class needed"
                    │
                    ├─ Find all free teachers
                    ├─ Send to all: John, Sarah, Mike
                    ├─ Mark all as extra attendance
                    └─ Return all results
```

---

## 🔄 Complete Request/Response Examples

### Request 1: Specific Teacher (Success)
```json
POST /v1/principal/free-period-teachers/notify-teacher
{
  "lecture_id": 15,
  "teacher_id": 8,
  "message": "Exam invigilation needed for Senior 1"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Teacher notified successfully",
  "data": {
    "lecture_id": 15,
    "lecture_name": "Exam Invigilation",
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

### Request 2: Specific Teacher (Not Available)
```json
POST /v1/principal/free-period-teachers/notify-teacher
{
  "lecture_id": 20,
  "teacher_id": 12
}
```

**Response:**
```json
{
  "success": false,
  "message": "Teacher is not available during this lecture time.",
  "errors": null
}
```

---

### Request 3: Teacher Not Found
```json
POST /v1/principal/free-period-teachers/notify-teacher
{
  "lecture_id": 5,
  "teacher_id": 999
}
```

**Response:**
```json
{
  "success": false,
  "message": "Teacher not found in your institution.",
  "errors": null
}
```

---

## 📚 Testing Checklist

- [ ] Test notify specific teacher (success case)
- [ ] Test with teacher not in same institution (404)
- [ ] Test with teacher who is busy (400)
- [ ] Test with invalid lecture_id (404)
- [ ] Test with missing teacher_id parameter (422)
- [ ] Verify attendance record created with type='extra'
- [ ] Verify Firebase notification sent
- [ ] Test with custom message
- [ ] Test with default message (no message provided)
- [ ] Compare with broadcast endpoint behavior

---

## 🎉 Summary

The new endpoint provides a more **targeted approach** to teacher notifications while the original endpoint remains for **broadcast notifications**. Both endpoints:
- ✅ Check teacher availability
- ✅ Verify same institution
- ✅ Send Firebase notifications
- ✅ Mark attendance as type='extra'
- ✅ Maintain all security validations
