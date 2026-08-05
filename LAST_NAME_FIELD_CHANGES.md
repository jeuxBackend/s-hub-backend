# `last_name` field rollout — endpoint changes

A new **optional** `last_name` string field was added alongside the existing `first_name` and `sur_name` (`sure_name` for admins) fields, across `admins`, `users`, `students`, and `family_members`. This document lists every affected endpoint and exactly what changed: request body, query params, or response.

**Global rules that apply everywhere below:**
- `last_name` is **nullable** on every table — no existing client breaks by omitting it.
- Full-name display order is now **first_name → last_name → sur_name/sure_name** (previously first_name → sur_name).
- Wherever a resource used to return a single computed **`full_name`** (or a `name` key that was really just `first_name + sur_name`), it has been **removed** and replaced with the three raw fields (`first_name`, `last_name`, `sur_name`) returned separately. Consumers that read `full_name`/`name` from those specific resources must be updated to concatenate client-side if they still want a single display string.
- Name-search filters (`?name=`, `?student_name=`, etc.) now also match against `last_name`.

---

## 1. Auth / Signup

| Endpoint | Change |
|---|---|
| `POST /signup` (⚠️ **currently commented out in `routes/api.php` — not a live route**) | **Request body**: `last_name` accepted (nullable) for `principal`, `teacher`, `parent`, `school_admin` roles, alongside `first_name`/`sur_name`. No response change (route inactive). |

## 2. Admin — Managers (`Api/Admin/ManagerController`)

Response is the raw `Admin` model (no API Resource), so `last_name` appears automatically once set.

| Method & URI | Change |
|---|---|
| `POST /api/v1/admin/managers` | **Request body**: `last_name` accepted (nullable). **Response**: manager object now includes `last_name`. |
| `PUT\|PATCH /api/v1/admin/managers/{manager}` | **Request body**: `last_name` accepted (nullable). **Response**: includes `last_name`. |
| `GET /api/v1/admin/managers` | **Response**: each manager object includes `last_name`. |
| `GET /api/v1/admin/managers/{manager}` | **Response**: includes `last_name`. |
| `GET /api/v1/admin/managers/{id}/schools` | No field change (unrelated payload). |
| `DELETE /api/v1/admin/managers/{manager}` | No change. |

## 3. Admin — Sub-Admins (`Api/Admin/SubAdminController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/admin/sub-admins` | **Request body**: `last_name` accepted (nullable). **Response**: sub-admin object includes `last_name`. |
| `PUT\|PATCH /api/v1/admin/sub-admins/{sub_admin}` | **Request body**: `last_name` accepted (nullable). **Response**: includes `last_name`. |
| `GET /api/v1/admin/sub-admins` | **Response**: includes `last_name` (search also matches `last_name` now). |
| `GET /api/v1/admin/sub-admins/{sub_admin}` | **Response**: includes `last_name`. |

## 4. Admin — Schools / Teachers / Students (read-only, list uses of `first_name`/`sur_name`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/admin/schools` | **Response**: nested `manager` object (`id, first_name, sure_name, email`) now also includes `last_name`. |
| `GET /api/v1/admin/schools/{school}` | Same nested manager change. |
| `GET /api/v1/admin/teachers` | **Response**: search/list now also matches/returns `last_name` for each teacher. |
| `GET /api/v1/admin/teachers/{teacher}` | **Response** includes `last_name`. |
| `GET /api/v1/admin/students` | **Response**: search now also matches `last_name`; uses `StudentWithInvoicesResource` (see §11 below) — `full_name` removed, `last_name` added, guardian block bug fixed (see §11). |
| `GET /api/v1/admin/students/{student}` | Same `StudentWithInvoicesResource` change. |

## 5. Manager — Principals, Schools, Teachers, Guardians, Students

| Method & URI | Change |
|---|---|
| `POST /api/v1/manager/principals` | **Request body**: `last_name` accepted (nullable). **Response**: principal (User) object includes `last_name`. |
| `PUT\|PATCH /api/v1/manager/principals/{principal}` | Same as above. |
| `GET /api/v1/manager/principals` / `GET .../{principal}` | **Response** includes `last_name`. |
| `GET /api/v1/manager/schools` / `GET .../{school}` | **Response**: nested `manager` (Admin) select now includes `last_name` (`id, first_name, sure_name, last_name, email`). |
| `GET /api/v1/manager/teachers` / `GET .../{teacher}` | **Response**: list/search now includes and matches `last_name`. |
| `PATCH /api/v1/manager/teachers/{id}/toggle-block` | No field change. |
| `GET /api/v1/manager/parents` | **Response**: list/search now includes and matches `last_name`. |
| `PATCH /api/v1/manager/parents/{id}/toggle-block` | No field change. |
| `GET /api/v1/manager/students` / `GET .../{student}` | **Response**: list/search now includes and matches `last_name`. |
| `PATCH /api/v1/manager/students/{id}/toggle-block` | No field change. |

## 6. Principal — Teachers (`Api/Principal/TeacherController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/principal/teachers` | **Request body**: `last_name` accepted (nullable). **Response**: `UserResource` — `full_name` removed, `last_name` added. |
| `PUT\|PATCH /api/v1/principal/teachers/{teacher}` | Same as above. |
| `GET /api/v1/principal/teachers` / `GET .../{teacher}` | **Response**: `UserResource` — `full_name` removed, `last_name` added. |
| `DELETE /api/v1/principal/teachers/{teacher}` | No field change. |
| `GET /api/v1/principal/teachers/teaching-assignments` | **Response**: teacher eager-load selects (`teacher:id,first_name,sur_name,last_name,...`) and the flattened teacher-listing array now include `last_name`. |

## 7. Principal — Guardians / Parents (`Api/Principal/GuardianController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/principal/parents` | **Request body**: `last_name` accepted (nullable). **Response**: `UserResource` — `full_name` removed, `last_name` added. |
| `PUT\|PATCH /api/v1/principal/parents/{parent}` | Same as above. |
| `GET /api/v1/principal/parents` / `GET .../{parent}` | **Response**: `UserResource` — `full_name` removed, `last_name` added; list search also matches `last_name`. |
| `DELETE /api/v1/principal/parents/{parent}` | No field change. |

## 8. Principal — School Admins (`Api/Principal/SchoolAdminController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/principal/school-admins` | **Request body**: `last_name` accepted implicitly via `sur_name`/general User rules — **Response**: `UserResource` — `full_name` removed, `last_name` added. |
| `PUT\|PATCH /api/v1/principal/school-admins/{school_admin}` | Same. |
| `GET /api/v1/principal/school-admins` / `GET .../{school_admin}` | **Response**: `UserResource` list — `full_name` removed, `last_name` added; search also matches `last_name`. |
| `GET /api/v1/principal/teachers-list` | **Response**: `get(['id','first_name','last_name','sur_name'])` — now includes `last_name`. |
| `DELETE /api/v1/principal/school-admins/{school_admin}` | No change. |
| `POST /api/v1/principal/change-role`, `GET /api/v1/principal/remove-admin/{id}`, `POST /api/v1/principal/update-permissions` | No field change. |

## 9. Principal — Free-period / proxy teachers (`Api/Principal/FreePeriodTeacherController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/principal/free-period-teachers/notify-teacher` | **Response/notification payload**: `teacher_name` now built as `first_name + last_name + sur_name` (was `first_name + sur_name`). |
| `POST /api/v1/principal/free-period-teachers/notify` | No direct field change (delegates to notification action — see Actions section). |
| `POST /api/v1/teacher/proxy-attendance/mark` | **Response payload**: `teacher_name` for both the actual and proxy teacher now includes `last_name`. |

## 10. Principal — Teacher attendance (`Api/Principal/TeacherAttendanceController`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/principal/teacher-attendance/{teacherId}` | No field change. |
| `POST /api/v1/principal/teachers/free-during-time` | **Response**: each free teacher entry now includes `'last_name' => $teacher->last_name` alongside `first_name`/`sur_name`. |

## 11. Principal — Timetable (`Api/Principal/TimetableConfigController`, `PrincipalTimetableController`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/principal/timetable/teacher-availabilities` | **Response**: nested `teacher` select now includes `last_name`. |
| `POST /api/v1/principal/timetable/teacher-availabilities` | Same nested teacher select change. |
| `PATCH /api/v1/principal/timetable/teacher-availabilities/{id}` | Same. |
| `GET /api/v1/principal/timetable/class-subject-requirements` | Same nested teacher select change. |
| `POST /api/v1/principal/timetable/class-subject-requirements` | Same. |
| `PATCH /api/v1/principal/timetable/class-subject-requirements/{id}` | Same. |
| `GET /api/v1/principal/teachers/{id}/timetable` | **Query behavior**: internal teacher name search (used for the CSV filename/lookup) now also matches on `last_name`. |
| `GET /api/v1/principal/school-timetable`, `GET /api/v1/principal/subjects/{id}/timetable`, `GET /api/v1/principal/classrooms/{id}/timetable` | No direct change (no first_name/sur_name usage found). |

## 12. Classroom endpoints (`Api/Classroom/ClassroomController`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/principal/classrooms-with-subjects` | **Response**: nested `classSubjectRequirement.teacher` select now includes `last_name`. |
| `GET /api/v1/principal/classrooms/{id}/average-attendance` | **Response**: `student_name` now `first_name + last_name + sur_name` (was `first_name + sur_name`). |
| `GET /api/v1/principal/classrooms/{id}/average-performance` | Same `student_name` change. |
| `GET /api/v1/principal/classrooms/{id}/tuition-paid-owed` | Same `student_name` change. |
| `GET /api/v1/classrooms`, `GET /api/v1/principal/classrooms`, `GET .../{classroom}`, `POST/PUT/DELETE .../classrooms...` | No field change. |

## 13. Grades (`Api/Grade/GradeController`)

| Method & URI | Change |
|---|---|
| `PATCH /api/v1/classrooms/{classroom}/grades/{grade}` | **Response**: `student_name` now `first_name + last_name + sur_name`. |
| `GET /api/v1/classrooms/{classroom}/grades`, `POST .../grades` | No field change. |

## 14. Parent self-service (`Api/Parent/ParentController`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/parent/attendances/by-month` | **Response**: `student_name` now includes `last_name`. |
| `GET /api/v1/parent/students` (children/classrooms) | **Response**: `student_name` now includes `last_name`. |
| `GET /api/v1/parent/grades` | **Response**: `student_name` now includes `last_name`. |
| `GET /api/v1/parent/attendances/by-date`, `POST .../reason`, `POST /api/v1/parent/child/picture/{id}` | No field change. |

## 15. Family Members (`Api/Parent/FamilyMemberController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/parent/family-members` | **Request body**: `last_name` accepted (nullable). **Response**: `FamilyMemberResource` — `full_name` removed, `last_name` added. |
| `POST /api/v1/parent/family-members/{familyMember}` (update) | Same as above. |
| `GET /api/v1/parent/family-members` | **Response**: `FamilyMemberResource` list — `full_name` removed, `last_name` added. |
| `DELETE /api/v1/parent/family-members/{familyMember}` | No field change. |

## 16. Students (`Api/Student/StudentController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/principal/students` | **Request body**: `last_name` accepted (nullable). **Response**: `StudentResource` — `full_name` removed, `last_name` added. |
| `PUT\|PATCH /api/v1/principal/students/{student}` | Same as above. |
| `GET /api/v1/principal/students` | **Response**: `StudentResource` collection — `full_name` removed, `last_name` added; `?student_name=` search now also matches `last_name`. |
| `GET /api/v1/principal/students/{student}` | Same `StudentResource` change. |
| `GET /api/v1/principal/students/{id}/with-invoices` | **Response**: `StudentWithInvoicesResource` — see §16a below (bug fix). |
| `GET /api/v1/principal/students/year-marks`, `GET /api/v1/parent/students/year-marks` | **Response**: `full_name` field now uses the model accessor directly (still a single string, but now correctly includes `last_name`); `?student_name=` search also matches `last_name`; guardian-name fallback concatenation includes `last_name`. |
| `GET /api/v1/principal/students/{id}/year-marks`, `GET /api/v1/parent/students/{id}/year-marks` | **Response**: `student_name` switched to the `full_name` accessor (includes `last_name`). |
| `DELETE /api/v1/principal/students/{student}` | No field change. |

### 16a. `StudentWithInvoicesResource` — also used by `GET /api/v1/admin/students`, `GET /api/v1/admin/students/{student}` (§4)

- Main student block: `full_name` removed, `last_name` added.
- **Guardian block bug fix**: previously returned `'last_name' => $guardian->sur_name` (i.e. it silently mislabeled the guardian's surname as `last_name`, and never exposed the real `sur_name` separately, and included a `full_name`). It now returns all three correctly: `first_name`, `last_name` (the real field), and `sur_name`, with `full_name` removed. **This is a behavior change for any consumer reading `last_name` from the guardian block of this endpoint** — it previously contained the surname, now it contains the actual last name.

## 17. Generic Users (`Api/User/UserController`)

All of these return `UserResource` — `full_name` removed, `last_name` added to every response below.

| Method & URI | Change |
|---|---|
| `GET /api/v1/me` | **Response**: `full_name` removed, `last_name` added. |
| `GET /api/v1/users` | **Response**: list — `full_name` removed, `last_name` added; `?name=` search now matches `CONCAT(first_name, ' ', last_name, ' ', sur_name)` (was `CONCAT(first_name, ' ', sur_name)`). |
| `GET /api/v1/users/{user}` | Same response change. |
| `PUT\|PATCH /api/v1/users/{user}` | **Request body**: `last_name` accepted (nullable, max:100). **Response**: same change. |
| `PUT/POST /api/v1/teacher/profile`, `PUT /api/v1/update-profile` | **Request body**: `last_name` accepted (nullable, max:100). **Response**: same change. |
| `PUT /api/v1/update-contact` | No field change (contact-only fields). |
| `PATCH /api/v1/users/{user}/notifications/toggle`, `PATCH /api/v1/users/allow-alert/toggle`, `PATCH /api/v1/users/remote/toggle` | **Response**: same `UserResource` change (no request body change). |
| `DELETE /api/v1/users/{user}` | No change. |
| `PUT /api/v1/change-password` | No change. |

Also, `UserResource`'s nested `children` block (returned for Parent-role users) had the same treatment: `full_name` removed, `last_name` added for each child.

## 18. Chat Conversations (`Api/Chat/ConversationController`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/chat/conversations` | **Response**: `ConversationResource` — the other participant's `full_name` key replaced with separate `first_name`, `last_name`, `sur_name`. |
| `POST /api/v1/chat/conversations` | Same response change. |
| `GET /api/v1/chat/conversations/{conversation}` | Same response change. |

## 19. Student Fees (`Api/StudentFee/StudentFeeController`)

| Method & URI | Change |
|---|---|
| `POST /api/v1/student-fees` (list/filter, uses POST) | **Response**: `StudentFeeResource` — the student's `name` key (was `first_name + sur_name`) replaced with separate `first_name`, `last_name`, `sur_name`. |
| `PATCH /api/v1/student-fees/{student_fee}` | Same response change. |

## 20. Student Reports (`Api/StudentReportController`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/student-reports` | **Response**: `StudentReportResource` — student `name` key replaced with separate `first_name`, `last_name`, `sur_name`. |
| `POST /api/v1/student-reports` | Same response change. |
| `PATCH /api/v1/principal/student-reports/{id}/status` | Same response change. |
| `DELETE /api/v1/student-reports/{student_report}` | No change. |

## 21. Attendance (`Api/Attendance/AttendanceController`, `MarkAttendanceAction`)

| Method & URI | Change |
|---|---|
| `PATCH /api/v1/attendances/mark` | **Notification/log payload only** (not the HTTP response body): low-attendance alert message text and `meta.student_name` sent to the principal now include `last_name`; the parent-absence notification already used the `full_name` accessor (now automatically includes `last_name`). |
| `GET /api/v1/attendances/by-date`, `GET /api/v1/attendances/by-month` | No field change. |

## 22. Dashboard analytics (`Api/Principal/PrincipalDashboardController`)

| Method & URI | Change |
|---|---|
| `GET /api/v1/principal/dashboard/academic-analytics` | **Response**: nested student eager-load select and `student_name` concatenations now include `last_name`. |
| `GET /api/v1/dashboard-stats` | No field change. |

## 23. Not exposed over HTTP but worth knowing about

- **PDF/HTML templates**: `resources/views/invoices/student_invoice_receipt.blade.php` now prints `first_name last_name sur_name` on the printed receipt. `resources/views/results/student_result_sheet.blade.php` already used the `full_name` accessor, so it picks up `last_name` automatically with no template edit needed.
- **School alert actor name** (`SchoolAlertBroadcastedEvent`, `SchoolAlertService`): the fallback name-concatenation used when broadcasting/logging alerts now includes `last_name`.

---

## Quick reference — which resources dropped `full_name`/`name`

| Resource | Endpoints affected | Old key | New keys |
|---|---|---|---|
| `UserResource` | §6, §7, §8, §17 (and nested `children`) | `full_name` | `first_name`, `last_name`, `sur_name` |
| `StudentResource` | §16 | `full_name` | `first_name`, `last_name`, `sur_name` |
| `StudentWithInvoicesResource` | §4, §16a | `full_name` (student + guardian) | `first_name`, `last_name`, `sur_name` (both blocks; guardian block bug also fixed) |
| `FamilyMemberResource` | §15 | `full_name` | `first_name`, `last_name`, `sur_name` |
| `ConversationResource` | §18 | `full_name` | `first_name`, `last_name`, `sur_name` |
| `StudentFeeResource` | §19 | `name` | `first_name`, `last_name`, `sur_name` |
| `StudentReportResource` | §20 | `name` | `first_name`, `last_name`, `sur_name` |

Any frontend/consumer currently reading `full_name` or `name` from these seven resources needs to switch to reading the three separate fields.
