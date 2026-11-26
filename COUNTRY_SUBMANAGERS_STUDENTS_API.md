# Country on Sub-Managers, Managers & Sub-Admins — and Students

**Audience:** Frontend team (Manager app, Admin panel, Principal app).
**Date:** 2026-11-26
Follows USER_COUNTRY_API.md (teachers, principals, parents). Same rule: a **2-letter code** (`GM`), shown as a name in the picker.

## 1. Sub-managers

| Action | Endpoint | `country` |
| ------ | -------- | --------- |
| Create | `POST /manager/sub-managers` | optional |
| Update | `PUT` / `PATCH /manager/sub-managers/{id}` | optional; omit to keep the current value |
| List filter | `GET /manager/sub-managers?country=GM` | matches the code, ignoring case |

- Format: exactly 2 letters. `"Gambia"` returns `422` ("must be 2 characters"); `"G1"` returns `422` ("must only contain letters").
- Saved uppercase (`gm` is saved as `GM`). The response returns `"country": "GM"` or `null`.
- A sub-manager's country is only profile information. It does **not** change which schools they see (that comes from the schools the manager assigns).

## 2. Managers and sub-admins (small tightening)

`country` on `POST` / `PUT /admin/managers` and `POST` / `PUT /admin/sub-admins` was already limited to 2 characters. It now also **must be letters only** and is **saved uppercase**. Send the code from your Country picker, as before.

For sub-admins this value matters: when a sub-admin has no assigned schools, they see the schools in their own country. Only the platform admin can set a sub-admin's country.

## 3. Students: no change

Students have **no country of their own**. A student's country is always their **school's country**.

- **Student forms:** show the school's country as read-only (use the school's `country`).
- **Filter:** `?country=GM` on student lists (`/admin/students`, `/manager/students`, `/principal/students`, ...) already matches the **school's** country.
- For a student's personal details keep using `nationality` and `country_of_birth`, which already exist.
- Do not send `country` when creating or editing a student. It is not a student field.

## Frontend checklist

- [ ] Sub-manager create and edit forms: Country picker (shows the name, sends the 2-letter code); handle `null`.
- [ ] Sub-managers list: optional Country filter using the code.
- [ ] Student forms: show the school's country as read-only, no input.
