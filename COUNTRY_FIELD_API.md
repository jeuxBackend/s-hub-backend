# `country` field — what to send and who can set it

Written for: frontend developers integrating the `country` field across schools, managers, sub-admins, and self-service profile updates.

## Format

`country` is always a **2-letter ISO country code** (e.g. `"US"`, `"GB"`, `"PK"`), sent as a plain string. It's optional/nullable everywhere it appears — omit it if you don't want to set/change it.

## Why it exists

- A school's `country` determines which country it belongs to.
- A manager's and a sub-admin's own `country` determines which country's data they can see when they have no explicit school assignments. This part is backend-internal logic — nothing you need to send differently for it, just know that a `country` value on a sub-admin/manager has real effect on what they're scoped to see.

## Important: some writes are silently ignored, not rejected

In a few places below, if the person making the request isn't allowed to set `country`, the API does **not** return a validation error. It returns a normal `200`/`201` success response, processes every other field in the request normally, and just drops `country` from what gets saved. If you send `country` and the response comes back successful but the field didn't change, that's expected — check who's performing the action against the table below rather than looking for an error message.

---

## 1. Schools

### `POST /admin/schools` and `PUT/PATCH /admin/schools/{school}`
Caller: Admin or Sub-Admin (same as every other field on this endpoint).

| Field | Type | Required |
|---|---|---|
| `country` | string, 2 letters | optional |

### `POST /manager/schools` and `PUT/PATCH /manager/schools/{school}`
Caller: Manager (their own schools only, same as every other field on this endpoint).

| Field | Type | Required |
|---|---|---|
| `country` | string, 2 letters | optional |

No restrictions here beyond the existing ones on these endpoints — whoever can already create/edit a school can set its `country`.

---

## 2. Managers — `POST /admin/managers` and `PUT/PATCH /admin/managers/{manager}`

Caller: **Admin or Sub-Admin**, both allowed to set a manager's `country`.

| Field | Type | Required |
|---|---|---|
| `country` | string, 2 letters | optional |

No special restriction — this is a normal field alongside the manager's other profile fields on this endpoint.

---

## 3. Sub-admins — `POST /admin/sub-admins` and `PUT/PATCH /admin/sub-admins/{sub_admin}`

Caller: Admin or Sub-Admin can both call this endpoint, **but only an Admin's request actually changes `country`.**

| Field | Type | Required |
|---|---|---|
| `country` | string, 2 letters | optional |

| Who sends `country` | Result |
|---|---|
| Admin | Saved normally. |
| Sub-Admin (creating or editing any sub-admin, including another sub-admin) | **Silently dropped.** Every other field in the same request (name, email, permissions, assigned schools, etc.) still saves normally — only `country` is ignored. |

So: don't let a Sub-Admin-logged-in user edit the `country` field for a sub-admin in your UI — it won't take effect, with no error to show them. Only show/enable that field when the logged-in user is an Admin.

---

## 4. Self-service profile — `PUT /update-profile`

This is the shared "edit my own profile" endpoint used by Admins, Sub-Admins, Managers, Teachers, Principals, and Parents. **Only Admin's own request for `country` actually takes effect.**

| Field | Type | Required |
|---|---|---|
| `country` | string, 2 letters | optional |

| Who is editing their own profile | Result |
|---|---|
| Admin | `country` saved normally. |
| Sub-Admin | **Silently dropped.** The rest of their profile update (name, email, phone, password, etc.) still saves. |
| Manager | **Silently dropped.** Same — rest of the update still saves. |
| Teacher / Principal / Parent | `country` is a different, unrelated free-text field on their own account (not the 2-letter admin/school code) — unaffected by any of the rules above, no restriction. |

So in your UI: only show an editable "country" field on the self-service profile screen when the logged-in user is an Admin. For Sub-Admin and Manager, their own `country` can only be set for them by an Admin, via the endpoints in sections 2 and 3 — not through their own profile screen.

---

## Quick reference — who can set whose `country`

| Target | Who can set it |
|---|---|
| A school | Admin, Sub-Admin (admin-side), or the owning Manager (manager-side) |
| A manager | Admin or Sub-Admin, via `/admin/managers` |
| A sub-admin | **Admin only**, via `/admin/sub-admins` — not even another sub-admin |
| Admin's own `country` | The Admin themself, via `/update-profile` |
| Manager's own `country` | Not by themself — only an Admin/Sub-Admin, via `/admin/managers` |
| Sub-Admin's own `country` | Not by themself — only an Admin, via `/admin/sub-admins` |
