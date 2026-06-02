# Free Period Teacher Feature - System Flow Diagram

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     PRINCIPAL DASHBOARD                         │
│              (Sends Notification Request)                       │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ POST /v1/principal/free-period-teachers/notify
                         │ {lecture_id: 5, message: "..."}
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│         FreePeriodTeacherController::notifyFreeTeachers         │
│                    (API Endpoint)                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ 1. Validate request
                         │ 2. Verify lecture exists & belongs to institution
                         │ 3. Check if lecture has scheduled times
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│          FindFreeTeachersAction::handle()                       │
│                  (Business Logic)                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                 ┌───────┴────────┐
                 │                │
         ┌───────▼────────┐  ┌────▼────────────────┐
         │ Get All        │  │ Find Busy Teachers  │
         │ Teachers in    │  │ with overlapping    │
         │ Institution    │  │ class schedules     │
         │                │  │                     │
         │ Query: All     │  │ Query: All subjects │
         │ users where    │  │ that have time      │
         │ role IN        │  │ overlap with target │
         │ (teacher,      │  │ lecture             │
         │ school-admin)  │  │                     │
         └───────┬────────┘  └────┬────────────────┘
                 │                │
                 └────────┬────────┘
                          │
                          ▼
              ┌──────────────────────────┐
              │ Calculate Free Teachers  │
              │ = All - Busy             │
              │ (Array difference)       │
              └────────────┬─────────────┘
                           │
                           ▼ [Array of Teacher IDs]
┌─────────────────────────────────────────────────────────────────┐
│         NotifyFreeTeachersAction::handle()                      │
│              (Notification & Attendance)                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
         ┌───────────────┴────────────────┐
         │                                │
         ▼                                ▼
┌─────────────────────┐          ┌──────────────────────┐
│ For Each Teacher:   │          │ For Each Teacher:    │
│                     │          │                      │
│ 1. Create/Update    │          │ 1. Send FCM          │
│    Attendance       │          │    Notification      │
│    Record           │          │                      │
│                     │          │ 2. Include Payload   │
│    Fields:          │          │    - subject_id      │
│    - type='extra'   │          │    - subject_name    │
│    - status=present │          │    - classroom_id    │
│    - date=today     │          │    - type=extra_...  │
│                     │          │                      │
│ 2. Store in DB      │          │ 3. Log Result        │
│    (Persist)        │          │                      │
└─────────────────────┘          └──────────────────────┘
                │                        │
                └───────────┬────────────┘
                            │
                            ▼ [Results Array]
┌─────────────────────────────────────────────────────────────────┐
│            Response Back to Principal                           │
│                                                                 │
│ {                                                               │
│   "success": true,                                              │
│   "message": "Free teachers notified successfully",             │
│   "data": {                                                     │
│     "lecture_id": 5,                                            │
│     "lecture_name": "Mathematics",                              │
│     "free_teachers_count": 3,                                   │
│     "notified": 3,                                              │
│     "failed": 0,                                                │
│     "results": [                                                │
│       {                                                         │
│         "teacher_id": 8,                                        │
│         "teacher_name": "Jane Doe",                             │
│         "status": "success"                                     │
│       },                                                        │
│       ...                                                       │
│     ]                                                           │
│   }                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘


```

## Data Flow - Time Overlap Detection

```
Target Lecture (Requested):
┌─────────────────────────────┐
│   09:00 ──────── 10:00      │ ← Need free teachers during this time
└─────────────────────────────┘

Teacher A - HAS CLASS (BUSY):
┌────────────┐
│ 08:00 - 09:00 │ ← Ends exactly when lecture starts (FREE)
└────────────┘

Teacher B - HAS CLASS (BUSY):
              ┌──────────────┐
              │ 09:30 - 11:00│ ← Overlaps with lecture (BUSY)
              └──────────────┘

Teacher C - HAS CLASS (BUSY):
                                 ┌────────────┐
                                 │10:00 - 11:00│ ← Starts when lecture ends (FREE)
                                 └────────────┘

Teacher D - NO CONFLICT (FREE):
   All day free ✓

Teacher E - NO CONFLICT (FREE):
   All day free ✓

═════════════════════════════════════════════════════════════════

RESULT: Free Teachers = [D, E]
   Teachers B has overlapping schedule (overlap detected)
   Teachers A & C have adjacent schedules (no overlap)
   Teachers D & E have no classes (completely free)
```

## Database Schema Changes

```
OLD teacher_attendances TABLE:
┌──────────────────────────────────────┐
│ id                                   │
│ teacher_id          → FK: users      │
│ subject_id          → FK: subjects   │
│ institution_id      → FK: institutions
│ date                                 │
│ status (present, absent, etc)        │
│ is_remote (boolean)                  │
│ created_at                           │
│ updated_at                           │
└──────────────────────────────────────┘

NEW teacher_attendances TABLE:
┌──────────────────────────────────────┐
│ id                                   │
│ teacher_id          → FK: users      │
│ subject_id          → FK: subjects   │
│ institution_id      → FK: institutions
│ date                                 │
│ status (present, absent, etc)        │
│ type (regular, extra) ← NEW!         │
│ is_remote (boolean)                  │
│ created_at                           │
│ updated_at                           │
└──────────────────────────────────────┘
```

## Middleware & Security Layers

```
REQUEST
  │
  ├─ auth:sanctum ..................... API Token Validation
  │  └─ FAIL: 401 Unauthorized
  │
  ├─ otp.verified ..................... OTP Verification Check
  │  └─ FAIL: 403 Forbidden
  │
  ├─ role:principal,school-admin ...... Role-based Access Control
  │  └─ FAIL: 403 Unauthorized
  │
  ├─ Lecture Validation
  │  ├─ Exists? 
  │  └─ Belongs to Institution?
  │     └─ FAIL: 404 Not Found
  │
  ├─ Schedule Validation
  │  ├─ Has start_time?
  │  ├─ Has end_time?
  │  └─ FAIL: 400 Bad Request
  │
  └─ SUCCESS: Process continues
      └─ Find & Notify Teachers
```

## Exception Handling Flow

```
Request Comes In
    │
    ├─ Try
    │  ├─ Auth User
    │  ├─ Get Lecture
    │  ├─ Find Free Teachers
    │  ├─ Send Notifications
    │  └─ Build Response
    │
    └─ Catch Throwable
       │
       ├─ ModelNotFoundException? (404)
       ├─ ValidationException? (422)
       ├─ Generic Exception? (500)
       │
       └─ Return Error Response
          {
            "success": false,
            "message": "...",
            "errors": {...}
          }
```

## Notification Lifecycle

```
1. DATABASE
   ┌──────────────────────────────┐
   │ INSERT/UPDATE                │
   │ teacher_attendances          │
   │                              │
   │ teacher_id: 8                │
   │ subject_id: 5                │
   │ date: 2026-06-02             │
   │ status: 'present'            │
   │ type: 'extra' ← MARKED HERE  │
   │ is_remote: false             │
   └──────────────────────────────┘
                  │
                  ▼

2. FIREBASE MESSAGING
   ┌──────────────────────────────┐
   │ Send FCM Notification        │
   │                              │
   │ Token: fcm_token_xxx         │
   │ Title: "Extra Class..."      │
   │ Body: "You have been..."     │
   │ Data: {subject_id, ...}      │
   └──────────────────────────────┘
                  │
                  ▼

3. TEACHER DEVICE
   ┌──────────────────────────────┐
   │ Notification Received        │
   │                              │
   │ ✓ Visual Notification        │
   │ ✓ Sound Alert                │
   │ ✓ Data Payload Stored        │
   │ ✓ App can handle event       │
   └──────────────────────────────┘
```

## Key Design Decisions

1. **Immediate Attendance Recording**: Teachers are marked present immediately upon notification
   - Pro: Simple tracking
   - Con: No confirmation needed
   
2. **Same Institution Only**: Validates lecture belongs to principal's institution
   - Pro: Data isolation
   - Con: Can't coordinate across institutions
   
3. **Time Range Overlap**: Uses >= and <= for boundary checking
   - Pro: Catches adjacent schedules correctly
   - Con: May need adjustment for buffer times
   
4. **Firebase FCM Only**: No fallback to email/SMS
   - Pro: Real-time, instant delivery
   - Con: Requires active FCM tokens
   
5. **Optional Custom Message**: Supports principal customization
   - Pro: Flexible communication
   - Con: May inconsistent messaging if not managed
