# Classnames API — Principal access

Base URL: `v1/classnames`
Auth: `Authorization: Bearer <token>` (Sanctum). All endpoints require an authenticated, active user (`auth:sanctum` + `active.user`). No OTP-verification step is required for these endpoints.

A classname belongs to exactly one institution (`institution_id`). A principal may only create, update, or delete classnames for **their own** institution — the one on their own user record (`principal.institution_id`). Attempting to target any other institution returns `403 Unauthorized`.

Listing/viewing (`GET`) is open to any authenticated, active user regardless of role — not principal-specific.

---

## GET `v1/classnames`

List classnames, optionally filtered. Paginated, 20 per page.

**Query params**

| Param | Type | Required | Notes |
|---|---|---|---|
| `institution_id` | integer | **required** | Must exist in `institutions`. |
| `name` | string | optional | Partial, case-sensitive `LIKE %value%` match. |

**Example**

```
GET v1/classnames?institution_id=1&name=grade
```

**Response `200`**

```json
{
  "success": true,
  "message": "Class names fetched successfully",
  "data": [
    {
      "id": 10,
      "institution_id": 1,
      "name": "Grade 1",
      "created_at": "2026-09-30 10:00:00",
      "updated_at": "2026-09-30 10:00:00"
    }
  ],
  "meta": {
    "pagination": {
      "total": 1,
      "per_page": 20,
      "current_page": 1,
      "last_page": 1,
      "from": 1,
      "to": 1
    }
  }
}
```

---

## GET `v1/classnames/{classname}`

Fetch a single classname by id. No ownership check — any authenticated user can view any classname by id.

**Response `200`**

```json
{
  "success": true,
  "message": "Class name fetched successfully",
  "data": {
    "id": 10,
    "institution_id": 1,
    "name": "Grade 1",
    "created_at": "2026-09-30 10:00:00",
    "updated_at": "2026-09-30 10:00:00"
  }
}
```

**Response `404`** — id doesn't exist: `{"success": false, "message": "Record not found", "errors": null}`

---

## POST `v1/classnames`

Create a classname for the principal's own institution.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `institution_id` | integer | **required** | Must exist in `institutions`. Must equal the principal's own `institution_id`, or the request is rejected with 403. |
| `name` | string | **required** | Max 100 chars. Must be unique within that `institution_id` (two schools may reuse the same name). |

**Example**

```json
POST v1/classnames
{
  "institution_id": 1,
  "name": "Grade 2"
}
```

**Response `201`**

```json
{
  "success": true,
  "message": "Class name created successfully",
  "data": {
    "id": 11,
    "institution_id": 1,
    "name": "Grade 2",
    "created_at": "2026-09-30 10:05:00",
    "updated_at": "2026-09-30 10:05:00"
  }
}
```

**Error responses**

- `403` — `institution_id` is not the principal's own school: `{"success": false, "message": "You do not manage this institution.", "errors": null}`
- `422` — validation failure (missing field, duplicate name for that institution, non-existent institution): `{"success": false, "message": "<first validation error>", "errors": null}`

---

## PUT / PATCH `v1/classnames/{classname}`

Rename a classname. `institution_id` cannot be changed through this endpoint (it is derived from the existing record).

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | optional (`sometimes`) | Max 100 chars. Must stay unique within the record's existing `institution_id` (ignoring itself). |

**Example**

```json
PUT v1/classnames/11
{
  "name": "Grade 2A"
}
```

**Response `200`**

```json
{
  "success": true,
  "message": "Class name updated successfully",
  "data": {
    "id": 11,
    "institution_id": 1,
    "name": "Grade 2A",
    "created_at": "2026-09-30 10:05:00",
    "updated_at": "2026-09-30 10:07:00"
  }
}
```

**Error responses**

- `403` — the classname belongs to a different institution than the principal's own: `{"success": false, "message": "You do not manage this institution.", "errors": null}`
- `404` — classname id doesn't exist.
- `422` — validation failure (duplicate name within that institution).

---

## DELETE `v1/classnames/{classname}`

Delete a classname belonging to the principal's own institution.

**No request body or query params.**

**Response `200`**

```json
{
  "success": true,
  "message": "Class name deleted successfully",
  "data": null
}
```

**Error responses**

- `403` — the classname belongs to a different institution than the principal's own.
- `404` — classname id doesn't exist.

---

## Notes

- Roles that can `POST`/`PUT`/`PATCH`/`DELETE`: `admin`, `sub_admin`, `manager`, `principal`. Teacher, SchoolAdmin, and Parent cannot write — only read via the `GET` endpoints.
- A manager is restricted to institutions it owns (`Institution.manager_id`); a sub-admin is restricted to its assigned schools (or unrestricted, if none are assigned); a principal is restricted to its own single institution. These checks are independent of each other — a principal's permission is not affected by manager/sub-admin scoping and vice versa.
