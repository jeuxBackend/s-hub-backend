# Country on Teachers, Principals & Parents

**Audience:** Frontend team (Admin panel, Manager app, Principal app, signup).
**Date:** 2026-11-26

Users can now have a **country**, saved as a **2-letter code** (for example `GM` for Gambia). The list filters (`?country=GM`) match this code, the same way schools, managers and admin accounts already work.

## The rule

| Item | Value |
| ---- | ----- |
| Field name | `country` |
| Format | exactly **2 letters** (`gm` and `GM` both work) |
| Required? | no, optional everywhere |
| Stored as | uppercase (`gm` is saved as `GM`) |
| Invalid values | `422`, for example `"Gambia"` ("must be 2 characters") or `"12"` ("must only contain letters") |

**Send the code, show the name.** Use a Country picker that displays "Gambia" and sends `GM`. Do not send free text.

## Where you can send it

| Who | Endpoint | Create | Update |
| --- | -------- | :----: | :----: |
| Admin / sub-admin | `POST` / `PUT /admin/teachers` | yes | yes |
| Manager / sub-manager | `POST` / `PUT /manager/teachers` | yes | yes |
| Manager | `POST` / `PUT /manager/principals` | yes | yes |
| Principal | `POST` / `PUT /principal/teachers` | yes | yes |
| Principal | `POST` / `PUT /principal/parents` | yes | yes |
| Any user, own profile | `PUT /update-profile` | | yes |
| Self signup | `POST /signup` | yes | |

- On **update**, send `country` only when it changes. Leaving it out keeps the current value.
- To keep it empty, omit it or send `null`.

## Responses

Every user object already returns `country` as stored, either a code like `"GM"` or `null`. Show the country name by looking the code up in your country list.

## Filtering by country

Use the same code in the list filters. Matching ignores case.

```
GET /api/v1/principal/teachers?country=GM
GET /api/v1/principal/parents?country=GM
GET /api/v1/manager/principals?country=GM
GET /api/v1/manager/teachers?country=GM
```

See LOCATION_FILTERS_API.md for the full list of filterable endpoints.

## Things to know

- **Old records:** Users saved earlier with a full name such as "Gambia" keep that text until someone edits them. A `?country=GM` filter will not find them until the value is changed to `GM`.
- **Principal's parents list:** It shows only registered parents (those who have set a password) by default. Add `registration_status=all` to include the rest.
- **Errors:** A bad value returns `422` with the message under the `country` field. Show it next to the picker.

## Frontend checklist

- [ ] Add a Country picker (shows the name, sends the 2-letter code) to the add and edit forms for teachers, principals and parents.
- [ ] Add the same picker to the signup form and the profile edit screen.
- [ ] Show the country name from the returned code, and handle `null`.
- [ ] Use the picker's code for the `country` list filter.
