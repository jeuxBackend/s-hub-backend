# State, Province & City on Users, Admin Accounts and Students

**Audience:** Frontend team (all apps).
**Date:** 2026-11-17

Three new **optional** text fields: `state`, `province`, `city` (each max 100 characters, can be `null`).

## Where they apply

| Who | Create | Update | Returned in |
| --- | ------ | ------ | ----------- |
| Manager | `POST /admin/managers` | `PUT /admin/managers/{id}` | manager list, show, create/update responses |
| Sub-admin | `POST /admin/sub-admins` | `PUT /admin/sub-admins/{id}` | sub-admin list, show, create/update responses |
| Principal | `POST /manager/principals` | `PUT /manager/principals/{id}`; self: `PUT /update-profile` | principal responses, login, `/me` |
| Teacher | `POST /principal/teachers`, `POST /manager/teachers`, `POST /admin/teachers` | matching `PUT .../teachers/{id}`; self: `PUT /update-profile` | teacher responses |
| Parent / Guardian | `POST /principal/parents` | `PUT /principal/parents/{id}`; self: `PUT /update-profile` | parent responses, `/me` |
| Student | `POST /principal/students`, `POST /manager/students` | `PUT/POST .../students/{id}` (principal, manager, admin) | student list, show, with-invoices |
| Self signup | `POST /signup` (accepts the same three fields) | | |

On every update, send only the fields that changed. Send `""` or `null` to clear a value.

## Response

The fields are always present, `null` when not set:

```json
{ "state": "Western", "province": "Kombo", "city": "Serrekunda" }
```

They appear on the user object (`UserResource`), the admin object (`AdminResource`, so `/me` and login for admin accounts) and the student object.

## Not included

- The admin-side self-service endpoint `PUT /admin-settings` is **unchanged**. It still accepts only the fields agreed per role, so managers, sub-admins and admins cannot edit their own state/province/city there. Tell us if you want that changed.

## Backend note

Migration `2026_11_17_100000_add_state_province_city_to_users_admins_students.php` adds the three nullable columns to `users`, `admins` and `students`. Run `php artisan migrate`.
