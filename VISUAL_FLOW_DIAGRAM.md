# 📊 Visual Guide - New Endpoint Flow

## Complete Flow Diagram

```
PRINCIPAL
    │
    ├─ Request:
    │  {
    │    "lecture_id": 5,
    │    "teacher_id": 8,
    │    "message": "..."
    │  }
    │
    ▼
POST /v1/principal/free-period-teachers/notify-teacher
    │
    ├─ AUTH CHECK
    │  ├─ Bearer Token? ────────── NO ──→ 401 Unauthorized
    │  ├─ OTP Verified? ────────── NO ──→ 403 Forbidden
    │  └─ Principal Role? ─────── NO ──→ 403 Forbidden
    │
    ├─ LECTURE VALIDATION
    │  ├─ Lecture exists? ─────── NO ──→ 404 Not Found
    │  ├─ Same institution? ────── NO ──→ 404 Not Found
    │  └─ Has times? ──────────── NO ──→ 400 Bad Request
    │
    ├─ TEACHER VALIDATION
    │  ├─ Teacher exists? ─────── NO ──→ 404 Not Found
    │  ├─ Same institution? ────── NO ──→ 404 Not Found
    │  └─ Teacher role? ───────── NO ──→ 404 Not Found
    │
    ├─ AVAILABILITY CHECK
    │  ├─ Find all free teachers
    │  └─ Is teacher in free list? ─ NO ──→ 400 Not Available
    │
    ├─ NOTIFICATION SEND
    │  ├─ Record attendance (type='extra')
    │  ├─ Send Firebase push notification
    │  └─ Log result
    │
    └─ SUCCESS RESPONSE
       {
         "success": true,
         "message": "Teacher notified successfully",
         "data": {
           "lecture_id": 5,
           "teacher_id": 8,
           "teacher_name": "Jane Doe",
           "notified": 1,
           "status": "success"
         }
       }
```

---

## Step-by-Step Execution

```
REQUEST ARRIVES
    │
    └─→ Controller: notifyTeacher()
           │
           ├─ Parse parameters
           │  ├─ lecture_id ✓
           │  ├─ teacher_id ✓
           │  └─ message (optional)
           │
           ├─ Get authenticated principal
           │
           ├─ Find lecture by ID + Institution
           │  └─ Query: Subject::where('id', 5)->where('institution_id', X)
           │
           ├─ Validate lecture
           │  ├─ Exists? ✓
           │  ├─ Has start_time? ✓
           │  └─ Has end_time? ✓
           │
           ├─ Find teacher by ID + Institution
           │  └─ Query: User::where('id', 8)->where('institution_id', X)->whereIn('role', ...)
           │
           ├─ Validate teacher
           │  ├─ Exists? ✓
           │  ├─ In same institution? ✓
           │  └─ Has teacher role? ✓
           │
           ├─ Check availability using FindFreeTeachersAction
           │  └─ Returns: [8, 12, 15, ...] (free teachers)
           │     Check: Is 8 in the list? ✓
           │
           ├─ Send notification using NotifyFreeTeachersAction
           │  ├─ Create attendance record (type='extra')
           │  ├─ Send Firebase notification
           │  └─ Collect results
           │
           └─ Return response
              {
                "success": true,
                "data": {...}
              }
```

---

## Error Scenarios

```
ERROR 1: Not Available
Principal: lecture_id=5, teacher_id=8
System: Teacher 8 HAS CLASS during lecture time
Result: 400 "Teacher is not available"

ERROR 2: Different Institution
Principal: Institution A, lecture_id=5
Teacher: Institution B
Result: 404 "Teacher not found in your institution"

ERROR 3: Teacher Not Found
Principal: lecture_id=5, teacher_id=999
System: No teacher with ID 999
Result: 404 "Teacher not found"

ERROR 4: No Scheduled Time
Principal: lecture_id=5
Lecture: No start_time or end_time
Result: 400 "Lecture does not have a scheduled time"

ERROR 5: Unauthorized
Request: No bearer token
Result: 401 "Unauthorized"

ERROR 6: Not Verified
Principal: Valid token but not OTP verified
Result: 403 "Forbidden"
```

---

## Availability Check Detail

```
LECTURE TIME: 2:00 PM - 3:00 PM

Teacher Schedule:
  1:00 - 2:00 PM ──────────────────────── NOT overlapping (FREE) ✓
  
  2:00 - 3:00 PM ──────────────────────── EXACTLY overlapping (BUSY) ✗
  
  1:30 - 2:30 PM ──────────────────────── PARTIAL overlap (BUSY) ✗
  
  3:00 - 4:00 PM ──────────────────────── NOT overlapping (FREE) ✓
  
  All day free ─────────────────────────── NO classes (FREE) ✓

RESULT: Teacher is available during 2-3 PM? 
  If YES → Send notification ✓
  If NO → Return error 400 ✗
```

---

## Request/Response Comparison

### Broadcast Endpoint

```
REQUEST:
POST /v1/principal/free-period-teachers/notify
{
  "lecture_id": 5,
  "message": "..."
}

RESPONSE:
{
  "data": {
    "free_teachers_count": 3,
    "notified": 3,
    "results": [
      {teacher_id: 8, teacher_name: "Jane", status: "success"},
      {teacher_id: 12, teacher_name: "John", status: "success"},
      {teacher_id: 15, teacher_name: "Sarah", status: "success"}
    ]
  }
}
```

### Specific Teacher Endpoint (NEW)

```
REQUEST:
POST /v1/principal/free-period-teachers/notify-teacher
{
  "lecture_id": 5,
  "teacher_id": 8,
  "message": "..."
}

RESPONSE:
{
  "data": {
    "lecture_id": 5,
    "lecture_name": "Math",
    "teacher_id": 8,
    "teacher_name": "Jane Doe",
    "notified": 1,
    "status": "success"
  }
}
```

---

## Decision Tree

```
                        Principal wants to notify
                                │
                    ┌───────────┴───────────┐
                    │                       │
            Know who to assign?        Don't know who
                    │                       │
              YES → │                       │ ← NO
                    │                       │
            ┌───────▼─────┐        ┌───────▼─────┐
            │ Use endpoint│        │ Use endpoint│
            │  /notify-   │        │   /notify   │
            │ teacher     │        │             │
            └───────┬─────┘        └───────┬─────┘
                    │                      │
         Assign specific teacher  Find all free teachers
         Check if available       Send to all
         One response             Multiple responses
```

---

## Data Transformation

```
INPUT (From Principal):
{
  "lecture_id": 5,
  "teacher_id": 8
}
        │
        ▼
VALIDATION LAYER:
├─ Lecture found ✓
├─ Teacher found ✓
├─ Same institution ✓
├─ Teacher available ✓
        │
        ▼
PROCESSING LAYER:
├─ Create attendance record
│  └─ teacher_id: 8
│     subject_id: 5
│     type: 'extra'
│     status: 'present'
│     date: TODAY
│
├─ Send Firebase notification
│  └─ Token: teacher.fcm_token
│     Title: "Extra Class Assignment"
│     Body: "You have been assigned..."
│     Data: {subject_id, subject_name, classroom_id}
        │
        ▼
OUTPUT (To Principal):
{
  "success": true,
  "teacher_id": 8,
  "teacher_name": "Jane Doe",
  "notified": 1,
  "status": "success"
}
```

---

## Integration Points

```
FreePeriodTeacherController
    │
    ├─→ FindFreeTeachersAction
    │   ├─ Query: All subjects in institution
    │   ├─ Logic: Check time overlaps
    │   └─ Return: Array of free teacher IDs
    │
    ├─→ NotifyFreeTeachersAction
    │   ├─ Create: TeacherAttendance record
    │   ├─ Send: Firebase notification
    │   └─ Return: Notification results
    │
    └─→ Database
        ├─ subjects (lecture info)
        ├─ users (teacher info)
        ├─ institutions (validation)
        └─ teacher_attendances (record)
```

---

## Database Interactions

```
READ QUERIES:
1. SELECT * FROM users WHERE id = 8 AND institution_id = X
2. SELECT * FROM subjects WHERE id = 5 AND institution_id = X
3. SELECT * FROM subjects WHERE teacher_id IN (...) AND institution_id = X
   (To find free teachers)

WRITE QUERIES:
1. INSERT/UPDATE INTO teacher_attendances
   (teacher_id, subject_id, date, type, status)
   = (8, 5, TODAY, 'extra', 'present')

NOTIFICATION:
1. Send Firebase message to teacher.fcm_token
```

---

## Complete Timeline

```
T+0ms:    Request received by controller
          │
T+10ms:   Parameters validated
          │
T+20ms:   Lecture loaded from database
          │
T+30ms:   Teacher loaded from database
          │
T+50ms:   All free teachers fetched
          │
T+60ms:   Check if teacher is in free list
          │
T+65ms:   Attendance record created
          │
T+100ms:  Firebase notification sent
          │
T+110ms:  Response prepared
          │
T+115ms:  Response sent to principal
```

---

## Success Path

```
Principal sends request
        │
        ├─ All validations PASS ✓
        │
        ├─ Teacher IS available ✓
        │
        ├─ Notification sent ✓
        │
        ├─ Attendance recorded ✓
        │
        └─ Return 200 OK with success data ✓
```

---

## Failure Paths

```
Path 1: Validation Failure
├─ Parameter missing → 422 Validation Error

Path 2: Not Found
├─ Lecture not found → 404
├─ Teacher not found → 404

Path 3: Not Authorized
├─ No token → 401
├─ OTP not verified → 403
├─ Wrong role → 403

Path 4: Business Logic Failure
├─ Wrong institution → 404
├─ Teacher not available → 400
├─ Lecture no time → 400
```

---

## Summary

**Single Endpoint:** `/v1/principal/free-period-teachers/notify-teacher`

**Flow:**
1. Receive request with lecture_id and teacher_id
2. Validate all inputs and relationships
3. Check if teacher is available
4. Send notification if available
5. Return result

**Result:** One teacher assigned and notified (or error if not available)

---

**Visual Implementation:** ✅ Complete
