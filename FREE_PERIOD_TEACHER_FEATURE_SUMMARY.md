# Free Period Teacher Notification Feature - Implementation Summary

## Overview
This feature allows principals to identify free period teachers during a specific lecture and send them notifications for extra class assignments. The system automatically marks their attendance as "extra" type.

## Changes Made

### 1. Database Migration
**File:** `/database/migrations/2026_06_02_000001_add_type_to_teacher_attendances_table.php`

Added a new `type` column to the `teacher_attendances` table:
- Column: `type` (string)
- Default value: `'regular'`
- Possible values: `'regular'` or `'extra'`
- Position: After `status` column

```php
$table->string('type')->default('regular')->after('status');
```

### 2. TeacherAttendance Model Update
**File:** `/app/Models/TeacherAttendance.php`

Added `'type'` to the fillable array to allow mass assignment:
```php
protected $fillable = [
    'teacher_id',
    'subject_id',
    'institution_id',
    'date',
    'status',
    'type',
    'is_remote',
];
```

### 3. Actions Created

#### A. FindFreeTeachersAction
**File:** `/app/Actions/Teacher/FindFreeTeachersAction.php`

- **Purpose:** Identifies all teachers who don't have classes scheduled during a specific lecture time
- **Logic:**
  - Gets all teachers in the same institution
  - Finds teachers with overlapping class schedules
  - Returns the difference (free teachers)
  - Supports time range overlap detection

- **Method:** `handle(int $lectureId, int $institutionId): array`
  - Input: Lecture/Subject ID and Institution ID
  - Output: Array of free teacher IDs

#### B. NotifyFreeTeachersAction
**File:** `/app/Actions/Teacher/NotifyFreeTeachersAction.php`

- **Purpose:** Sends Firebase notifications to free teachers and marks their attendance as extra
- **Features:**
  - Marks attendance record with `type='extra'` and `status='present'` for today
  - Sends Firebase Cloud Messaging (FCM) notification to each teacher's device
  - Returns detailed results with success/failure status for each teacher

- **Method:** `handle(int $lectureId, array $freeTeacherIds, string $message = null): array`
  - Input: Lecture ID, array of teacher IDs, optional custom message
  - Output: Array with notification results including count and individual results
  - Response includes:
    - `success`: Overall operation success
    - `notified`: Number of successfully notified teachers
    - `failed`: Number of failed notifications
    - `results`: Detailed array of each teacher's notification status

### 4. API Controller
**File:** `/app/Http/Controllers/Api/Principal/FreePeriodTeacherController.php`

- **Class:** `FreePeriodTeacherController`
- **Method:** `notifyFreeTeachers(Request $request, FindFreeTeachersAction, NotifyFreeTeachersAction)`

#### Request Validation:
```php
{
    'lecture_id' => 'required|exists:subjects,id',
    'message' => 'nullable|string|max:500'
}
```

#### Response Structure:
```json
{
    "success": true,
    "message": "Free teachers notified successfully",
    "data": {
        "lecture_id": 1,
        "lecture_name": "Mathematics 101",
        "free_teachers_count": 5,
        "notified": 5,
        "failed": 0,
        "results": [
            {
                "teacher_id": 2,
                "teacher_name": "John Smith",
                "status": "success"
            }
        ]
    }
}
```

### 5. API Route
**File:** `/routes/api.php`

Added new endpoint under principal routes:

```php
Route::post('principal/free-period-teachers/notify', 
    [FreePeriodTeacherController::class, 'notifyFreeTeachers']);
```

**Route Details:**
- Path: `POST /v1/principal/free-period-teachers/notify`
- Middleware: `auth:sanctum`, `otp.verified`, `role:principal,school-admin`
- No additional permission checks needed for principals

## Usage Example

### API Request:
```bash
POST /v1/principal/free-period-teachers/notify
Content-Type: application/json
Authorization: Bearer {token}

{
    "lecture_id": 5,
    "message": "Please cover extra class for Senior 1 Mathematics"
}
```

### API Response:
```json
{
    "success": true,
    "message": "Free teachers notified successfully",
    "data": {
        "lecture_id": 5,
        "lecture_name": "Mathematics - Senior 1",
        "free_teachers_count": 3,
        "notified": 3,
        "failed": 0,
        "results": [
            {
                "teacher_id": 8,
                "teacher_name": "Jane Doe",
                "status": "success"
            },
            {
                "teacher_id": 12,
                "teacher_name": "Michael Johnson",
                "status": "success"
            },
            {
                "teacher_id": 15,
                "teacher_name": "Sarah Williams",
                "status": "success"
            }
        ]
    }
}
```

## Business Logic

### Free Period Identification:
1. Fetches the target lecture with start and end times
2. Gets all teachers in the same institution
3. Identifies teachers who have overlapping class schedules
4. Returns teachers without any overlapping schedules (free periods)

### Notification Process:
1. For each free teacher:
   - Creates/Updates attendance record with `type='extra'` for today
   - Sends FCM notification with lecture details
   - Includes data payload with subject_id, subject_name, classroom_id, and type
2. Returns comprehensive results showing which teachers were notified successfully

### Response Behavior:
- If no free teachers are available: Returns success with `notified: 0` and message indicating no free teachers
- If FCM tokens are missing: Teachers without tokens are skipped
- Each teacher's status is tracked individually for transparency

## Notes

1. **Same Institution Requirement:** The system ensures only teachers from the same institution as the principal are notified
2. **FCM Token Requirement:** Teachers must have FCM tokens registered to receive notifications
3. **Attendance Recording:** Attendance is marked for today's date only
4. **Type Column:** Helps distinguish between regular attendance and extra assignments
5. **No Response Modification:** API response format maintains consistency with existing endpoints

## Running the Migration

```bash
php artisan migrate
```

This will add the `type` column to the `teacher_attendances` table.
