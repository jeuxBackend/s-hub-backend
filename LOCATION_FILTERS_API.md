# Location Filters on List Endpoints (country / state / province / city)

**Audience:** Frontend team (all apps).
**Date:** 2026-11-20

Every list below now accepts **optional** query params. Send any combination; they combine with the existing filters (AND). A missing or empty param changes nothing, so existing calls behave exactly as before. Pagination and scoping (a principal sees only their school, a manager only theirs, and so on) are unchanged.

| Param | Matches |
| ----- | ------- |
| `country` | see per-list table below |
| `state` | `state` column |
| `province` | `province` column |
| `city` | `city` column |
| `region` | schools only (class-toppers and the manager's academic students list also accept it) |

**Matching rules:** case-insensitive, exact, surrounding spaces ignored (`city=serrekunda` matches `Serrekunda`). Use dropdown values rather than free text. Schools store `country` as a 2-letter code (e.g. `GM`).

## Where each filter applies

| Who | Endpoint | Filters | What they match |
| --- | -------- | ------- | --------------- |
| Admin / sub-admin / sub-sub-admin | `GET /admin/managers` | `country`, `state`, `province`, `city` | the manager's own location |
| | `GET /admin/sub-admins` | same | the sub-admin's own location |
| | `GET /admin/schools`, `GET /admin/schools/pending` | `country`, `region` | the school |
| | `GET /admin/teachers` | `country`, `state`, `province`, `city` | the teacher's own location |
| | `GET /admin/students`, `GET /admin/students/search` | same | `state`/`province`/`city` = student's; `country` = the student's school's country |
| | `GET /admin/class-toppers?limit=3` | `country`, `region`, `state`, `province`, `city` | `country`/`region` = school; `state`/`province`/`city` = student |
| Manager / sub-manager | `GET /manager/schools` | `country`, `region` | the school |
| | `GET /manager/principals`, `/manager/teachers`, `/manager/parents` | `country`, `state`, `province`, `city` | the person's own location |
| | `GET /manager/students`, `/manager/tuition`, `/manager/academic-performance/students` | same | as for admin students |
| | `GET /manager/sub-managers` | `country`, `state`, `province`, `city` | the sub-manager's own location |
| Principal / school admin | `GET /principal/students` | same | as for admin students (counts such as `gender_counts` follow the filter) |
| | `GET /principal/teachers`, `GET /principal/parents`, `GET /principal/school-admins` | `country`, `state`, `province`, `city` | the person's own location |
| Sub-admin | `GET /admin/sub-sub-admins` | same | the sub-sub-admin's own location |

### Example

```
GET /api/v1/admin/class-toppers?limit=3&country=GM&city=Serrekunda
GET /api/v1/principal/students?state=Western&per_page=50
GET /api/v1/admin/managers?country=GM&city=Banjul
```

## Good to know

- **Schools have no `state` / `province` / `city`.** Only `country` and `region` exist on schools, so schools filter by those two. Adding the other three to schools would be a separate change.
- A student's `country` filter means the country of their **school**, because students have no country of their own.
- The values only exist if they were saved (create/update accept `state`, `province`, `city`; see the admin roles guide). Records without a value never match a filter on that field.
- Not filtered (no person/school location to match): manager invoices, reports and notifications.
