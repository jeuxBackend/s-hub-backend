# Manager portal: backend gaps & issues

**For:** the S-Hub backend developer.
**From:** the React Manager portal integration (repo `s-hub`, `src/ManagerSide/*`, API client `src/api/manager.js`).
**Last updated:** 2026-09-30.

The Manager portal is wired to the `v1/manager/*` endpoints (plan: `docs/MANAGER_PLAN.md`). **No backend code was changed.** Where the backend falls short, the panel filters the loaded page, shows "N/A", hides the feature or makes extra requests, until the backend supports it.

Admin and sub-admin gaps are in `docs/ADMIN_BACKEND_GAPS.md`.

## Status summary

| Status | Items |
|---|---|
| **✅ Fixed** | – |
| **🟡 Partly fixed** | – |
| **❌ Open** | D1–D20 |

**Fix first:**
- **D2:** the manager parents list is always empty.
- **D4:** a school can be given several principals.
- **D16:** a manager can change the status of a report from a school it does not manage (by id).
- **D10:** categories are global, so a manager's create / delete affects every school.
- **D13:** region filters list regions of all schools, not the manager's.

## Items

| # | Status | Where | Problem | Effect / suggestion |
|---|---|---|---|---|
| **D1** | ❌ Open | `GET v1/manager/my-invoices` | No `status` filter, and no totals (outstanding / awaiting confirmation / paid). | The Invoices page tabs and summary only cover the loaded page. Add `status` and a totals block in `meta`. |
| **D2** | ❌ Open | `GET v1/manager/parents` (`ListGuardiansAction`) | Filters by the requester's `institution_id`, and a manager has none, so the list is empty (the controller has a "might need a fix for managers" comment). | Scope to the manager's schools instead. The panel has no Parents page yet. |
| **D3** | ❌ Open | `GET v1/manager/principals` | No search or filters (name, email, school, status). | The search box only filters the loaded page. Add `name` / `email` / `institution_id`. |
| **D4** | ❌ Open | Principal store / update | A school can be given several principals: there is no check that it already has one. | Refuse, or replace the existing principal. |
| **D5** | ❌ Open | Principals | No block / unblock endpoint (teachers and students have `toggle-block`). | Principal status is read-only in the panel. |
| **D6** | ❌ Open | `GET v1/manager/schools/names` | Returns only `{id, name}`, not `status`, so pending / rejected schools cannot be hidden in the principal form. The 403 for a pending school uses `{status: "error"}` instead of `{success: false}`. | Add `status` to the names list; use the standard envelope. |
| **D7** | ❌ Open | `GET v1/manager/schools`, `GET …/schools/{id}` | No counts (students, teachers, school admins, classes) and no principal. | The detail view makes 4 extra calls, plus one to the principals list. Add counts with `withCount` and the principal, like the admin show. |
| **D8** | ❌ Open | `POST v1/manager/schools` | Store only accepts `category_id, name, email, phone_number, physical_address, school_logo`. | The panel saves the other fields (slogan, academic year, region, …) with a second PUT, so a failed PUT leaves a school with partial data. Accept the optional fields on store. |
| **D9** | ❌ Open | `UpdateSchoolAction` | Returns the school without its `category`. The old logo is deleted using the URL accessor, not the stored path, so old logos are probably never removed. | Load `category`; delete by the raw path. |
| **D10** | ❌ Open | Categories (`v1/manager/categories`) | Categories are global: a manager's create / delete changes them for every school, and the list is not scoped. | The panel only lists and creates categories for managers; delete is not offered. Confirm the intended rule. |
| **D11** | ❌ Open | `GET v1/manager/schools/{id}/classrooms` | Only `{id, name}`: no class teacher or teacher count. | Hidden from the class cards. |
| **D12** | ❌ Open | `GET v1/manager/students` | No gender counts in `meta`. | The school's students modal makes two extra `per_page=1` calls. |
| **D13** | ❌ Open | `GET v1/manager/dashboard/regions` (`GetDistinctRegionsAction`) | Returns regions of **all** institutions, not the manager's schools. The academic filters endpoint returns no regions. | The region filters can list regions the manager has no schools in. Scope to the manager. |
| **D14** | ❌ Open | Manager tuition | No endpoint for a student's payment history or fee breakdown, and a student has only one `guardian` (father and mother are not separate). | The tuition detail shows a summary and one guardian. |
| **D15** | ❌ Open | Academic performance | No school logo on `AcademicPerformanceStudentResource.institution` or on the filters' schools. | Initials are shown instead. |
| **D16** | ❌ Open | `GeneralReportController` (manager) | `status` / `read` updates check the role only, so a manager could update a manager-addressed report from a school it does not manage (by id). There is no `is_read` filter. A manager's own reports have `institution_id` null, so a school filter hides them. The resource does not expose the reporter type. | The list itself is scoped correctly. |
| **D17** | ❌ Open | `PUT v1/update-profile` (managers) | Email and phone cannot be changed; there is no "password last changed" date. | Read-only in the panel. |
| **D18** | ❌ Open | Teacher / principal update and toggle-block responses | They do not include the `institution`. Teacher `show` returns no classrooms. There is no profile-picture field on manager teacher / principal store or update. | The panel fills the school name from the names list; Classes and photo upload are not offered. |
| **D19** | ❌ Open | `GET v1/manager/students` (`ListStudentsAction`) | No `status` filter, and search is by name only (`student_name`), not email / phone / registration number. | Active / Blocked only filters the loaded page. |
| **D20** | ❌ Open | `GET v1/manager/students/{id}` | Returns the raw model (institution, classroom, guardian), not a `StudentResource`: no attendance, performance, invoices, subjects or per-subject grades, no guardian relation, and no invoice / payment list. `update` takes no profile picture; `update` / `toggle-block` return the model without relations. | The detail uses the list row for stats; Academic Performance and Paid Tuition are not clickable. |
