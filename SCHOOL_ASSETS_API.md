# School Assets API (Stamp & Watermark)

Base URL: `v1/principal/school-assets`
Auth: `Authorization: Bearer <token>` (Sanctum). Available to **Principal** and **SchoolAdmin** roles (`role:principal,school-admin`).

Each school has at most **one `stamp`** and **one `watermark`**. Each asset is either `text` (a literal text value) or `image` (an uploaded file) — chosen per asset by the Principal/SchoolAdmin. All endpoints are scoped to the caller's own institution; accessing or modifying another school's asset returns `404`.

---

## GET `v1/principal/school-assets`

List this school's assets (0–2 items — only what's been created so far).

**No request body or query params.**

**Response `200`**

```json
{
  "success": true,
  "message": "School assets retrieved successfully",
  "data": [
    {
      "id": 1,
      "institution_id": 5,
      "type": "stamp",
      "asset_format": "text",
      "text_value": "APPROVED",
      "file_url": null,
      "created_by": 12,
      "created_at": "2026-10-05 10:00:00",
      "updated_at": "2026-10-05 10:00:00"
    },
    {
      "id": 2,
      "institution_id": 5,
      "type": "watermark",
      "asset_format": "image",
      "text_value": null,
      "file_url": "https://s-hub-backend.jeuxvps.com/storage/school_assets/abc123.png",
      "created_by": 12,
      "created_at": "2026-10-05 10:05:00",
      "updated_at": "2026-10-05 10:05:00"
    }
  ]
}
```

---

## GET `v1/principal/school-assets/{id}`

Get a single asset by id.

**Response `200`**

```json
{
  "success": true,
  "message": "School asset retrieved successfully",
  "data": {
    "id": 1,
    "institution_id": 5,
    "type": "stamp",
    "asset_format": "text",
    "text_value": "APPROVED",
    "file_url": null,
    "created_by": 12,
    "created_at": "2026-10-05 10:00:00",
    "updated_at": "2026-10-05 10:00:00"
  }
}
```

**Response `404`** — id doesn't exist, or belongs to another school:
```json
{ "success": false, "message": "Record not found", "errors": null }
```

---

## POST `v1/principal/school-assets`

Create the school's stamp or watermark. Content-Type `multipart/form-data` when uploading a file.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `type` | string | **required** | `stamp` or `watermark`. |
| `asset_format` | string | **required** | `text` or `image`. |
| `text_value` | string | required if `asset_format=text` | Max 500 chars. |
| `file` | file | required if `asset_format=image` | Image, max 2MB (`jpeg`, `png`, `jpg`, `gif`, `webp`). |

**Example — text stamp**

```json
POST v1/principal/school-assets
{
  "type": "stamp",
  "asset_format": "text",
  "text_value": "APPROVED"
}
```

**Example — image watermark**

```
POST v1/principal/school-assets
Content-Type: multipart/form-data

type: watermark
asset_format: image
file: <binary>
```

**Response `201`**

```json
{
  "success": true,
  "message": "Stamp created successfully",
  "data": {
    "id": 1,
    "institution_id": 5,
    "type": "stamp",
    "asset_format": "text",
    "text_value": "APPROVED",
    "file_url": null,
    "created_by": 12,
    "created_at": "2026-10-05 10:00:00",
    "updated_at": "2026-10-05 10:00:00"
  }
}
```

**Error responses**

- `422` — that `type` already exists for this school:
  ```json
  { "success": false, "message": "This school already has a stamp. Use update instead.", "errors": null }
  ```
- `422` — validation failure (e.g. `asset_format=image` with no `file`, or `asset_format=text` with no `text_value`):
  ```json
  { "message": "The file field is required when asset format is image.", "errors": { "file": ["The file field is required when asset format is image."] } }
  ```

---

## PUT / PATCH `v1/principal/school-assets/{id}`

Update an existing asset — change its text, replace its image, or switch its format entirely (e.g. image → text). `type` cannot be changed (it's fixed by the record itself).

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `asset_format` | string | optional | `text` or `image`. Omit to keep the current format. |
| `text_value` | string | required if `asset_format=text` | Max 500 chars. |
| `file` | file | required if `asset_format=image` | Same constraints as store. |

Switching **to** `image` with a new `file` deletes the previous image from storage. Switching **to** `text` clears any existing image (file deleted from storage, `file_url` becomes `null`).

**Example — switch watermark from image to text**

```json
PATCH v1/principal/school-assets/2
{
  "asset_format": "text",
  "text_value": "CONFIDENTIAL"
}
```

**Response `200`**

```json
{
  "success": true,
  "message": "School asset updated successfully",
  "data": {
    "id": 2,
    "institution_id": 5,
    "type": "watermark",
    "asset_format": "text",
    "text_value": "CONFIDENTIAL",
    "file_url": null,
    "created_by": 12,
    "created_at": "2026-10-05 10:05:00",
    "updated_at": "2026-10-05 10:12:00"
  }
}
```

**Error responses**

- `404` — asset doesn't exist or belongs to another school.
- `422` — validation failure.

---

## DELETE `v1/principal/school-assets/{id}`

Delete an asset (and its stored file, if it has one).

**No request body.**

**Response `200`**

```json
{
  "success": true,
  "message": "School asset deleted successfully",
  "data": null
}
```

**Response `404`** — asset doesn't exist or belongs to another school.
