# Admin panel: backend gaps & issues

**For:** the S-Hub backend developer.
**From:** the React admin and sub-admin panel integration (repo `s-hub`, `src/Admin/*`, `src/SubAdmin/*`).
**Last updated:** 2026-09-28.

The admin web panel is now wired to the existing `v1/admin/*` and `web/login` endpoints. **No backend code was changed.**

This file lists two kinds of items:
- endpoints or fields the panel's screens need that the backend does not provide, and
- backend behaviour that looks like a bug.

For each item the panel currently hides the feature, disables it, or shows "N/A", until the backend supports it.

References are to `routes/api.php` and the controllers and actions in `app/`. Suggested shapes are only suggestions; any equivalent works.

## Status after the 2026-09-28 backend update

**Second update (2026-09-28, evening):**
- **Fixed and used by the panel:**
  - **C1:** the `sub-admins` routes are admin-only.
  - **C3 (partly):** `v1/me` returns the `AdminResource`, so the sub-admin portal refreshes permissions on load. `update-profile` works for first name, last name and picture.
  - **C4–C7:** the dashboard, managers, invoices, categories, classnames and reports are scoped to assigned schools. School create forces `subadmin_id`, and teacher/student updates are checked against the assigned schools.
  - **C8:** a blocked account gets 403 "Your account has been blocked." on its next request; the panel logs it out with that message.
  - **C11 (partly):** validation errors now return their real message.
  - **C13:** `exclude_status=pending` is used by the Schools page.
  - **C14:** `schools/names` and `schools/{id}/classrooms` also accept `Teachers` or `Students`.
- **New and used:** manual invoice payments. The panel lists a manager's invoices, links the payment proof and confirms payments (`PATCH v1/admin/manager-invoices/{id}/confirm`).
- **Still open:** C2, C9, C10, C12, the new C15–C16, and B30 / B31 (unchanged).

**Fix first (2026-09-28):**
- ~~**C1:** security: any sub-admin can create, edit and delete sub-admins.~~ Fixed.
- ~~**C8:** blocked accounts keep working until their token expires.~~ Fixed.
- **C15:** an admin's surname is not saved by `update-profile`.
- **C2:** a sub-admin with no assigned schools sees every school. Confirm this is intended.
- ~~**C3:** a sub-admin cannot load or edit its own profile.~~ Mostly fixed (see C15).
- ~~**C14:** the Teachers and Students pages need `Schools` permission just for the school list.~~ Fixed.
- ~~**C4 / C5:** scoping to assigned schools.~~ Fixed.


The panel now uses every fixed item below. The status comes from reading the current backend code, since the fixes were not marked in this file.

| Status | Items |
|---|---|
| **✅ Fixed** (used by the panel) | A1, A3–A6, A8–A12, B1–B6, B9, B11–B15, B17–B20, B23, B24, B26–B29, B32, C1, C4–C8, C13, C14 |
| **🟡 Partly fixed** (details below) | A2, B16, B21, B22, B30, B31, C3, C11 |
| **❌ Open** | A7 (school `timezone`, no column), B7 (school level), B8 (approve/reject notification and reject reason), B10 (price per school), C2, C9, C10, C12, C15, C16 |
| **➖ Not needed** | B25 |

What is still missing from the partly fixed items:

| # | Still missing |
|---|---|
| A2 | The `sub-admins` routes are now admin-only (C1 fixed). `general-reports` store, update and destroy still have no permission check. |
| B16 | `profile_image` is now in the managers list, but managers have no upload field on store/update. |
| B21 | `attendance_rate` and `performance_percentage` are on the student detail. The drill-down data (attendance by month, grades by subject) is still missing, so those two cards are not clickable. |
| B22 | `GET v1/admin/students/search` is paginated and is used by the Students page. `GET v1/admin/students` (with `total_paid`, `total_due`, `performance_percentage`) is still unpaginated. |
| B30 | Role `admin` can delete any report. A sub-admin still gets 403, so the panel hides "Remove Report" for sub-admins. |
| B31 | `read_at`, `is_read` and `PATCH …/{id}/read` exist. There is no `is_read` filter on the list, so the Unread/Read split only works on the current page. `read_at` is global, not per viewer. |

---

## C. New issues found while building the Sub-Admin portal

| # | Status | Where | Problem | Effect / suggestion |
|---|---|---|---|---|
| **C1** | ✅ Fixed | `routes/api.php`, `Route::apiResource('sub-admins', …)` | **Security.** No `role:admin` or permission middleware. Any sub-admin can list, create, edit and delete sub-admins, including giving itself more `permissions` or `school_ids`. Only `sub-admins/permissions` is admin-only. | Put the sub-admins resource under `role:admin`. The panel never shows Country Admins to sub-admins, but the API is open. |
| **C2** | ❌ Open | `Admin::assignedInstitutionIds()` | A sub-admin with **no** assigned schools is unrestricted: it sees every school, teacher and student. | If "no schools" should mean "no access", return `[]` instead of `null`. Confirm the intended rule. |
| **C3** | 🟡 Partly fixed | `GET v1/me`, `PUT v1/update-profile` | Neither works for Admin-model accounts: `/me` returns an incomplete `UserResource`, and `update-profile` calls `User::findOrFail(adminId)`. | A sub-admin cannot refresh or edit its own profile. The panel shows the profile read-only (from the login response) and only allows changing the password. Suggest: return `AdminResource` from `/me` for Admin models, and add an own-profile update for admins. |
| **C4** | ✅ Fixed | `AdminDashboardAction` | The dashboard is global: not scoped to the sub-admin's assigned schools, and cached under one key for 5 minutes. | A restricted sub-admin sees platform-wide totals. Scope it (and the cache key) per sub-admin if it should only show its own schools. |
| **C5** | ✅ Fixed | managers, `managers/{id}/schools`, manager-invoices, categories, general-reports, classnames | Not scoped to the sub-admin's assigned schools. | A restricted sub-admin with `Managers` or `Reports` sees all managers, invoices and reports. |
| **C6** | ✅ Fixed | `POST v1/admin/schools` | `subadmin_id` is taken from the request and not forced for sub-admins. A restricted sub-admin creating a school cannot see it afterwards, and can set any admin id. | Force `subadmin_id = auth id` when the creator is a sub-admin. |
| **C7** | ✅ Fixed | `PUT v1/admin/teachers/{id}` (`institution_id`), `PUT v1/admin/students/{id}` (`classroom_id`) | The new value is not checked against the sub-admin's assigned schools (store does check `institution_id`). | A restricted sub-admin can move a teacher or student into a school it does not manage. |
| **C8** | ✅ Fixed | `EnsureActiveUser` middleware | It is a no-op. Blocking a manager or sub-admin only stops the **next** login; existing tokens keep working. | Check `status` on each request, or revoke tokens when an account is set to inactive. |
| **C9** | ❌ Open | Notifications (`get-noticeboard`, `notifications/unread-count`, …) | They filter `notification_logs.user_id` with the admin id, which belongs to the users table, so the counts are wrong or collide. | The admin and sub-admin panels do not use notifications. |
| **C10** | ❌ Open | `GET v1/admin/schools/{id}` | `teachers_count` includes school-admins. | The panel shows teachers as `teachers_count - school_admins_count`. A separate count would be clearer. |
| **C11** | 🟡 Partly fixed | Error responses | A 403 from `subadmin.permission` has only `{message}`, with no `success`. With `APP_DEBUG` off, validation errors thrown inside actions come back as 422 "Something went wrong" (for example a wrong login password, or deleting a school that still has users). | The panel shows the message it gets. Specific messages would help users. |
| **C12** | ❌ Open | `POST v1/general-reports` from admin / sub-admin | Only `reported_to_role: "manager"` is allowed, with no target manager, so every manager sees the report. | Add an optional `manager_id` / `institution_id` target if reports should go to one manager. |
| **C13** | ✅ Fixed | `GET v1/admin/schools` | There is no way to exclude pending schools in one call (no `status[]` or `status!=pending`). | The Schools page "All" filter drops pending schools from the loaded page on the client, so a page can show fewer than 20 rows and the total includes pending schools. Suggest `exclude_pending=1` or `status[]=approved&status[]=rejected`. |
| **C14** | ✅ Fixed | `schools/names`, `schools/{id}/classrooms`, `managers*` behind other pages | The Teachers, Students and School Requests pages need lookups guarded by other permissions: the school list and classrooms need `Schools`, and manager stats and invoices need `Managers`. | A sub-admin with only `Teachers` cannot choose a school, so cannot add a teacher or filter by school. A sub-admin with only `Students` cannot change a student's class. Suggest allowing `schools/names` and `schools/{id}/classrooms` for `Teachers` and `Students` too, limited to the assigned schools. |
| **C15** | ❌ Open | `PUT v1/update-profile` for Admin models (`UserController::updateAdminOwnProfile`) | The request validates `sur_name`, but the admin branch only copies `sure_name`, so an admin's surname is never saved. Email and phone cannot be changed either (`UpdateUserRequest` checks uniqueness on the `users` table). | The sub-admin Settings page edits first name, last name and picture; surname, email and phone are read-only ("managed by the admin"). Map `sur_name` → `sure_name` for admins. |
| **C16** | ❌ Open | `AdminLoginAction` (blocked account) | With `APP_DEBUG` off, the `AuthorizationException('Your account has been blocked.')` comes back as "You are not authorized to perform this action". | A blocked user logging in does not see why. `EnsureActiveUser` already returns the clear message on other requests. |

---

## Original list (2026-09-25)

Every original item is kept, with its current status in the **Status** column. What is still missing on the partly fixed items is in the table above.

### Priority summary (2026-09-25, mostly fixed now)

**Fix first:** security and correctness.
- **A3:** inactive admin accounts can still log in.
- **A1:** the `School` vs `Schools` permission mismatch blocks sub-admins from every school route.
- **A2:** most sub-admin permissions are never enforced.
- **A5:** `v1/change-password` returns 500 for admin accounts.
- **A6:** `v1/me` returns 500 for admin accounts.

**Needed to switch on features the panel currently disables:**

| # | Feature |
|---|---|
| B13 / B27 | Block or unblock managers and sub-admins |
| B15 | Update, block or delete students |
| B21 | Student attendance and performance |
| B1, B2 | Dashboard countries card and gender charts |
| B26 / A8 | Sub-admin assigned schools |
| B12, B18 | Manager emergency contact; teacher staff number and emergency contact |
| B29 | Report school and role filters |
| B30 | Admin removing a report |

**Performance** (the panel works around these for now):
- Pagination for schools (B6), students (B22) and pending schools (B11).
- Per-school counts on `GET v1/admin/schools/{id}` (B5).
- Phone and permissions in the sub-admin list (B24).
- A `manager_id` filter on students (B14).

---

## A. Bugs found while reading the code

| # | Status | Where | Problem | Effect on the panel |
|---|---|---|---|---|
| A1 | ✅ Fixed | `routes/api.php` (schools/categories routes) vs `App\Enums\SubAdminPermission` | Routes check `subadmin.permission:School`, but the enum (and `GET v1/admin/sub-admins/permissions`) offers **`Schools`**. | A sub-admin given the "Schools" permission from the list is refused on every school/category route. |
| A2 | 🟡 Partly fixed | `routes/api.php`, admin group | The permissions `Dashboard`, `Managers`, `School_Requests` and `Reports` are defined but never enforced by any middleware. | Every sub-admin can use managers, invoices, sub-admins, school requests and reports. |
| A3 | ✅ Fixed | `AdminLoginAction` | Status check `if (!$admin->status)` with a string status `'active'`/`'inactive'` is always truthy. | Inactive admins, sub-admins and managers can still log in. |
| A4 | ✅ Fixed | `AdminResource` (login response) | `permissions`, `first_name`, `sure_name`, `last_name` and `region` are not returned. | After login the panel cannot know a sub-admin's permissions without an extra `GET v1/admin/sub-admins/{id}`. |
| A5 | ✅ Fixed | `UserController@changePassword` → `ChangePasswordAction::handle(array, User $user)` | Type-hinted to `User`; an `Admin` model causes a TypeError, returned as 500. | Admins cannot change their own password. |
| A6 | ✅ Fixed | `GET v1/me` → `GetOngoingClassAction::handle(User $teacher)` | Same TypeError for `Admin`, so 500. | The panel cannot refresh the admin profile. |
| A7 | ❌ Open | School create/update (`timezone` rule) | `timezone` is validated and fillable, but `institutions` has no `timezone` column, so a SQL error returns 500. | The panel never sends `timezone`. |
| A8 | ✅ Fixed | `SubAdmin` store/update | `school_ids` is validated but never saved. | The "Manually Assigned School" field in the Country Admin form cannot be saved. |
| A9 | ✅ Fixed | `GetManagerAction` (`status` filter) | Compares the enum string column with `true`/`false`, so `status=inactive` matches nothing. | The panel filters manager status on the client side instead. |
| A10 | ✅ Fixed | `GetGlobalTeachersAction` (`status` filter) | Uses `!empty($status)`, so `status=0` (blocked) is ignored. | The panel filters blocked teachers on the client side (current page only). |
| A11 | ✅ Fixed | `GeneralReportController@store` | For `admin`/`sub_admin` the allowed `reported_to_role` targets are `[]`, so every create fails with 422. | Admins cannot create reports (the panel does not offer it). |
| A12 | ✅ Fixed | `GeneralReportResource` | `reporter.full_name` / `resolved_by.full_name` are `null` for Admin-model users (the Admin model has `name`, not `full_name`). | The panel builds the name from first/sur/last name. |

---

## B. Missing endpoints or fields

_Filled in phase by phase below._

### Dashboard (`GET v1/admin/dashboard`)

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B1 | ✅ Fixed | Dashboard, "Countries" card (Total Countries + % vs last month) | No count of countries/regions. | Add `total_countries` and `total_countries_change_percent`, for example as distinct `institutions.region` or sub-admin `region` values. |
| B2 | ✅ Fixed | Dashboard, "Gender Demographics" (students and teachers pie charts) | No gender breakdown. | Add `students_by_gender: {male, female, other}` and `teachers_by_gender: {male, female, other}` (`students.gender`, `users.gender`). |

The panel currently hides the Countries card and the Gender Demographics section.

### Schools (`v1/admin/schools*`)

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B3 | ✅ Fixed | School → Total Students table: "Tuition Paid", "Tuition Owing", "Performance" | `GET v1/admin/students` returns raw students with no tuition totals and no performance. | Add `total_paid`, `total_due` and `performance_percentage` to each student (the principal endpoint `v1/principal/students` already has them). |
| B4 | ✅ Fixed | School → Classes cards (subjects per class) | `GET v1/admin/schools/{id}/classrooms` returns only `{id, name}`. | Include `subjects: [{id, name}]` for each classroom. |
| B5 | ✅ Fixed | School detail stat boxes (students, teachers, school admins, classes) | `GET v1/admin/schools/{id}` has no counts, so the panel currently makes 3 extra list calls per school. | Add `students_count`, `teachers_count`, `school_admins_count` and `classrooms_count` (for example with `withCount`). |
| B6 | ✅ Fixed | Schools list filter "Active / Blocked" | `GET v1/admin/schools` has no `is_blocked` filter and no pagination. | Add an `is_blocked` query param and `per_page`/`page` (the panel filters on the client side for now, which will not scale). |
| B7 | ❌ Open | School detail "School Level" | The `institutions` table has no level field. | Add one if still needed. The panel shows the category instead and replaced this row with "Country" (`region`). |

### School requests (`v1/admin/schools/pending`, `approve`, `reject`) and invoices

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B8 | ❌ Open | Approve / Reject | Approve and reject only change `status`. The manager gets no email or notification, and reject takes no reason. | Notify the manager (email or push), and accept an optional `reason` on reject. |
| B9 | ✅ Fixed | "Generate Invoice" from a school request | Manager invoices are not linked to a school or request, so the panel cannot show whether a request has been invoiced. | Optionally store `institution_id` on `manager_invoices` and return it. |
| B10 | ❌ Open | Request detail "Charges Per School" | There is no per-manager or per-school price setting. The panel shows the price of the manager's latest invoice instead. | Add a configured price per school (manager setting), if one exists in the business process. |
| B11 | ✅ Fixed | Pending list | `GET v1/admin/schools/pending` is not paginated (the code comment says it is). | Add pagination (`per_page`, `page`, `meta.pagination`). |

### Managers (`v1/admin/managers*`)

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B12 | ✅ Fixed | Add/Edit Manager: "Emergency Contact Name" and "Emergency Contact Phone Number" | The `admins` table and the manager store/update rules have no emergency contact fields. | Add `emergency_contact_name` and `emergency_contact_phone` (nullable). The panel has removed these two fields for now. |
| B13 | ✅ Fixed | Managers list status pill + detail "Block" button | There is no way to block or unblock a manager: the update has no `status` field, and there is no toggle route (see also A3: inactive admins can still log in). | Add `status` (`active`/`inactive`) to `PUT v1/admin/managers/{id}` or a `PATCH …/{id}/toggle-status`, and enforce it at login. The panel shows the status read-only. |
| B14 | ✅ Fixed | Manager → Total Students page | `GET v1/admin/students` has no `manager_id` filter, so the panel makes one call per school of the manager. | Add a `manager_id` filter (as `GET v1/admin/teachers` already has). |
| B15 | ✅ Fixed | Manager → Total Students (actions) and the Students page | Admins cannot update, block or delete students: only index/show exist. | Add `PUT`/`PATCH` (at least `status`) and `DELETE` for `v1/admin/students/{id}` if admins should manage students. |
| B16 | 🟡 Partly fixed | Managers list avatar | `profile_image` is not selected in the managers list, and managers have no upload field. | Return `profile_image` (full URL) in the list. The panel shows initials. |
| B17 | ✅ Fixed | Managers list search | Search is split into `name` and `email` params (no combined search). | Optional: a single `search` param over name, email and phone. |

### Teachers (`v1/admin/teachers*`)

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B18 | ✅ Fixed | Edit Teacher: "Teacher Number", "Emergency Contact Person/Phone" | `PUT v1/admin/teachers/{id}` only accepts name, email, phone, password, institution and status. `staff_number`, `emergency_contact_name` and `emergency_number` cannot be changed. | Add them to the admin update rules (nullable). The panel shows them read-only. |
| B19 | ✅ Fixed | Teachers list | There is no `role` filter (teacher vs school-admin), and `status=0` is ignored (A10). | Add a `role` filter, and use `$request->has('status')` instead of `!empty()`. |
| B20 | ✅ Fixed | Teachers list | Admins cannot add teachers (no `store` route). | If the admin should create teachers, add `POST v1/admin/teachers`. The panel has no add button, as before. |

### Students (`v1/admin/students*`)

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B21 | 🟡 Partly fixed | Student detail cards "Attendance Rate" and "Academic Performance" (and their detail modals) | `GET v1/admin/students/{id}` returns no attendance or grades. | Add `attendance_rate` (%) and `performance_percentage` / grade, and optionally the endpoints behind the two drill-down modals (attendance by month, grades by subject). The panel shows N/A. |
| B22 | 🟡 Partly fixed | Students list | Not paginated: every student on the platform is returned at once. There is no status filter and no free-text search over phone or guardian. | Paginate (`per_page`, `page`, `meta.pagination`), and add `status` and `search` params. The panel renders the list in chunks of 50 for now. |
| B23 | ✅ Fixed | Student detail "Email Address", "Alternative Phone/Email", "Subjects" | Students have no email or alternative contacts, and subjects are not included. | Add them if the design still needs them. The panel shows other existing fields instead (nationality, DOB, language, authorized pickup). |

(Edit, block and delete for students are covered by B15.)

### Country admins / sub-admins (`v1/admin/sub-admins*`)

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B24 | ✅ Fixed | Country admin cards ("Phone Number", "Key Capabilities") | `GET v1/admin/sub-admins` selects no `phone_number` or `permissions`, so the panel calls `GET …/sub-admins/{id}` once per card. | Add `phone_number` and `permissions` to the list select. |
| B25 | ➖ Not needed | Card "Log In Password" | Passwords are hashed and cannot be shown (correctly). | None needed. The panel shows the account status there instead. |
| B26 | ✅ Fixed | Card / form "Manually Assigned Schools" | `school_ids` is validated but not persisted (A8), and there is no relation to read it back. | Add a pivot table (sub_admin_institution) or use `institutions.subadmin_id`, save `school_ids`, and return `schools: [{id, name}]` on show and list. The field is disabled in the panel. |
| B27 | ✅ Fixed | Sub-admin status | There is no way to deactivate a sub-admin (no `status` in store/update), and inactive accounts can log in anyway (A3). | Same as B13. |
| B28 | ✅ Fixed | Sub-admin portal | Sub-admins log in with the same `web/login`, but their permissions are not in the login response (A4) and permission names do not match the routes (A1). | Fix A1 and A4 first. The React sub-admin portal can then be wired to the same `v1/admin/*` endpoints. |

### Reports (`v1/general-reports*`)

| # | Status | Needed by | Missing | Suggested |
|---|---|---|---|---|
| B29 | ✅ Fixed | Reports filters "Select School" and "User Role" | `GET v1/general-reports` only filters by `status`, `manager_id` and `month`. | Add `institution_id` and `reporter_role` filters. The panel offers Reporter (managers) and Month instead. |
| B30 | 🟡 Partly fixed | Report card menu "Remove Report" | Only the original reporter can delete a report (`403` for admins). | If admins should remove reports, allow `admin` in `destroy`. The panel removed the menu item. |
| B31 | 🟡 Partly fixed | "Unread / Read" tabs | Reports have no read/unread flag, only a status (pending/resolved/rejected/closed). | Optional: add `read_at` for the viewer. The panel uses status tabs from `GET v1/general-reports/statuses`. |
| B32 | ✅ Fixed | Report card "School" | Reports only carry `institution_id`, not the school name. | Include `institution: {id, name}`. The panel resolves the name through `GET v1/admin/schools/names`. |
