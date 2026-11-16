# Manager Profile Picture (`profile_image`)

**Audience:** Frontend engineering team (Admin / Sub-Admin dashboard and Manager app).
**Date:** 2026-11-16

**Endpoints impacted:**

- `POST   /api/v1/admin/managers` *(accepts `profile_image`)*
- `PUT|PATCH /api/v1/admin/managers/{id}` *(accepts `profile_image`)*
- `GET    /api/v1/admin/managers` and `GET /api/v1/admin/managers/{id}` *(return `profile_image`)*
- `DELETE /api/v1/admin/managers/{id}` *(picture file is removed)*
- `PUT    /api/v1/update-profile` *(manager changes their own picture)*

---

## 1. Overview

Managers can now have a profile picture. It works **exactly like the sub-admin picture**: same field name (`profile_image`), same storage folder, same response format.

- Admins / sub-admins can upload a picture when **creating** a manager and replace it when **editing** one.
- A manager can change their **own** picture from their profile.
- The picture is **optional**.

---

## 2. Response field

Every manager object contains `profile_image`:

| Field           | Type          | Description                                                        |
| --------------- | ------------- | ------------------------------------------------------------------ |
| `profile_image` | string / null | The stored path, e.g. `admin_profiles/abc.png`. `null` if no picture. |

```json
{
  "id": 12,
  "first_name": "Jane",
  "sure_name": "Doe",
  "role": "manager",
  "profile_image": "admin_profiles/TOQEp7v4iP4FaldBTYkaINitfAEebJ08YnJ4akST.png"
}
```

**This is a path, not a full URL** (same as sub-admins). To display it, build the URL on the frontend:

```
{API_BASE_URL}/storage/{profile_image}
e.g. https://api.example.com/storage/admin_profiles/TOQEp7v4iP4FaldBTYkaINitfAEebJ08YnJ4akST.png
```

When `profile_image` is `null`, show your own default avatar.

Where it appears: the managers list (`GET admin/managers`), a single manager (`GET admin/managers/{id}`), and the create and update responses.

---

## 3. Create a manager with a picture

### `POST /api/v1/admin/managers`

**Auth:** Admin, or Sub-Admin with the `Managers` permission.
**Content type:** `multipart/form-data` (required when sending a file).

| Field                     | Type   | Required | Notes                                  |
| ------------------------- | ------ | -------- | -------------------------------------- |
| `first_name`              | string | yes      | Existing.                              |
| `sure_name`               | string | yes      | Existing.                              |
| `last_name`               | string | no       | Existing.                              |
| `email`                   | email  | yes      | Existing.                              |
| `phone_number`            | string | yes      | Existing.                              |
| `password`                | string | yes      | Existing, min 8.                       |
| `emergency_contact_name`  | string | no       | Existing.                              |
| `emergency_contact_phone` | string | no       | Existing.                              |
| `status`                  | string | no       | `active` / `inactive`. Existing.       |
| `country`                 | string | no       | 2-letter code. Existing.               |
| **`profile_image`**       | image  | **no**   | jpeg, png, jpg or webp. **Max 2 MB.**  |

**Response `201`:** the created manager, including `profile_image`.

---

## 4. Update a manager's picture (admin side)

### `PUT` or `PATCH /api/v1/admin/managers/{id}`

Same fields as create, all optional. Send **only** what changed.

- Sending a new `profile_image` **replaces** the old one, and the old file is deleted from the server.
- Not sending `profile_image` leaves the current picture untouched.
- There is **no "remove picture" option** yet; an image can only be replaced.

> **Important for file uploads:** PHP does not read files sent with a real `PUT` request.
> To upload a picture while updating, send a **`POST`** to the same URL with `multipart/form-data` and add a form field **`_method` = `PUT`**.
> Updates without a file can use a normal JSON `PUT`/`PATCH`.

```
POST /api/v1/admin/managers/12
Content-Type: multipart/form-data

_method=PUT
first_name=Jane
profile_image=<file>
```

---

## 5. Manager changes their own picture

### `PUT /api/v1/update-profile`

Existing endpoint shared by all roles. For a manager (or sub-admin / admin) it already saves the picture into `profile_image`.

> **Note:** on this endpoint the **request** field is named **`profile_picture`** (it is the shared profile form for every role). The saved value comes back as **`profile_image`** in the response, like everywhere else for managers.

| Request field     | Type  | Notes                               |
| ----------------- | ----- | ----------------------------------- |
| `profile_picture` | image | Max 2 MB. Replaces the current one. |

- The same `_method=PUT` + `POST` rule from section 4 applies when sending a file.
- The old picture file is now deleted when a new one is saved.
- This endpoint still only lets a manager change their name, email and phone. A manager cannot change their own country.

---

## 6. Delete a manager

`DELETE /api/v1/admin/managers/{id}`: unchanged behaviour (still blocked while the manager has schools or invoices). When a manager is deleted, their picture file is deleted too.

---

## 7. Validation

| Case                                            | Status | Notes                             |
| ----------------------------------------------- | ------ | --------------------------------- |
| File is not an image / not jpeg, png, jpg, webp | 422    | Error on `profile_image`.         |
| File larger than 2 MB                           | 422    | Error on `profile_image`.         |

---

## 8. Frontend checklist

- [ ] Admin dashboard: add an optional **picture upload** (`profile_image`) to the "Add manager" form, sent as `multipart/form-data`.
- [ ] Admin dashboard: let admins **replace** the picture on the edit-manager form (use `POST` + `_method=PUT` when a file is chosen).
- [ ] Display the picture from `profile_image` using `{API_BASE_URL}/storage/{profile_image}`. Show a default avatar when it is `null`. This is the same helper already used for sub-admin pictures.
- [ ] Manager app profile screen: allow changing the own picture through `update-profile`, sending the file as `profile_picture`.
- [ ] Validate size (2 MB) and type client-side to avoid a round trip.
- [ ] Do not build any "remove picture" button yet; it isn't supported.

---

## 9. Backend notes

- No migration: the existing `admins.profile_image` column is used (shared with sub-admins).
- Files are stored on the `public` disk in `admin_profiles/`.
- Make sure `php artisan storage:link` has been run on the server so `/storage/...` URLs resolve.
