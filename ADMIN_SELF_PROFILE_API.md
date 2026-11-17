# Admin / Sub-admin / Manager — Update Own Profile

**Audience:** Frontend team (Admin panel).
**Date:** 2026-11-17

| Method | Endpoint | Purpose |
| ------ | -------- | ------- |
| `GET` | `/api/v1/admin-settings` | Current profile |
| `POST` / `PATCH` / `PUT` | `/api/v1/admin-settings` | Update own profile |

**Auth:** Bearer token of an admin, sub-admin or manager. Any other account gets `403`.
**Content type:** `multipart/form-data` (needed for the picture). Files are only read on `POST`/`PATCH`; if you use `PUT`, send `POST` with `_method=PUT`.
Send only the fields that changed.

## Who can change what

| Field | Admin | Sub-admin | Manager |
| ----- | :---: | :-------: | :-----: |
| `profile_image` (jpeg/png/jpg/webp, max 2 MB) | yes | yes | yes |
| `first_name`, `sure_name`, `last_name` | yes | no | no |
| `phone_number` | yes | yes | yes |
| `emergency_contact_name`, `emergency_contact_phone` | no | no | yes |
| Password | yes | yes | yes |

Sending a field the role cannot change returns `422` listing the rejected and allowed fields, so hide those inputs in the UI.

## Password change

Send all three together: `current_password`, `password` (min 6), `password_confirmation`.
Wrong `current_password` returns `403` "Current password is incorrect." A missing `current_password` returns `422`.

## Response `200`

```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "user": {
      "id": 2, "name": "Robert Williams",
      "first_name": "Robert", "sure_name": "Williams", "last_name": null,
      "email": "...", "phone_number": "+220...",
      "role": "manager",
      "emergency_contact_name": "Em", "emergency_contact_phone": "123",
      "profile_image": "admin_profiles/abc.png",
      "password_changed_at": null
    }
  }
}
```

- `emergency_contact_name` / `emergency_contact_phone` are now also returned by `/me`, login and other admin responses.
- `profile_image` is a storage path (same as the existing manager/sub-admin responses); build the URL the same way you already do.

## Errors

| Case | Status |
| ---- | ------ |
| Field not allowed for the role | 422 |
| `phone_number` already used by another admin account | 422 |
| Invalid image | 422 |
| Wrong current password | 403 |
| Non-admin account | 403 |

The older `PUT /api/v1/update-profile` is unchanged, but the admin panel should use `admin-settings`.
