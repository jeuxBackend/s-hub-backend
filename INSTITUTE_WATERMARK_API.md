# Institute Watermark (Text or Image) for Result PDFs

**Audience:** Frontend engineering team (Principal web app, "Institute Profile" → "Result PDF Images" screen).
**Date:** 2026-11-16

**Endpoint:** `POST` or `PATCH /api/v1/principal/institution/slogan` *(the same endpoint that already uploads the logo, signature and stamp)*

---

## 1. What this is

A school can have an optional **watermark** for its result PDFs, as **text** (e.g. `SUNRISE ACADEMY`) or an **image**. A school has **one** watermark. The server applies it when it generates the PDF, so the frontend only has to let the principal **set, replace and remove** it.

**What the frontend must do:** add a **Watermark** card next to School Logo, Principal Signature and Institute Stamp on the existing "Result PDF Images" screen. No new endpoint is needed.

---

## 2. Request

**Auth:** Principal, or School Admin with the `Settings` permission.
**Content type:** `multipart/form-data`.

Send the watermark fields in the same request as, or separately from, the other images. Only send what changed.

| Field              | Type    | Required | Notes                                                                         |
| ------------------ | ------- | -------- | ----------------------------------------------------------------------------- |
| `watermark_text`   | string  | no       | Up to 500 characters. Creates or switches to a **text** watermark.            |
| `watermark_image`  | image   | no       | jpeg, png, jpg, gif or webp, **max 2 MB**. Creates or switches to an **image** watermark. |
| `remove_watermark` | boolean | no       | `true` deletes the watermark.                                                 |

Existing fields on this endpoint are unchanged: `logo`, `principal_signature`, `stamp` (images, max 2 MB each) and `slogan` (text).

### Rules

- Send **either** `watermark_text` **or** `watermark_image`, not both. If both are sent the API returns `422` with the message *"Send either watermark_text or watermark_image, not both."*
- Sending a new watermark **replaces** the current one. Switching between text and image uses the same fields. The old image file is deleted automatically.
- If **no** watermark field is sent, the current watermark is left untouched (for example when only the slogan or logo is changed).
- An **empty** `watermark_text` is ignored. To clear the watermark, send `remove_watermark=true`.

### Examples

Set a text watermark:

```
POST /api/v1/principal/institution/slogan
Content-Type: multipart/form-data

watermark_text=SUNRISE ACADEMY
```

Set an image watermark:

```
POST /api/v1/principal/institution/slogan
Content-Type: multipart/form-data

watermark_image=<file>
```

Remove the watermark:

```
POST /api/v1/principal/institution/slogan
Content-Type: multipart/form-data

remove_watermark=true
```

---

## 3. Response

`200` with the same body as before. The institution object now contains a `watermark` field:

```json
{
  "success": true,
  "message": "Institution updated successfully",
  "data": {
    "user": {
      "institution": {
        "id": 3,
        "name": "Sunrise Academy",
        "logo": "https://.../storage/logos/abc.png",
        "principal_signature": "https://.../storage/signatures/def.png",
        "stamp": "https://.../storage/stamps/ghi.png",
        "watermark": {
          "id": 2,
          "asset_format": "text",
          "text_value": "SUNRISE ACADEMY",
          "file_url": null
        }
      }
    }
  }
}
```

An image watermark looks like this:

```json
"watermark": {
  "id": 2,
  "asset_format": "image",
  "text_value": null,
  "file_url": "https://.../storage/school_assets/Y6Wut0pTbE7f1gTGD9gCVW5ZDQTvg5h0YuTOVSvW.png"
}
```

| Field          | Type          | Description                                                  |
| -------------- | ------------- | ------------------------------------------------------------ |
| `id`           | integer       | Watermark record id.                                         |
| `asset_format` | string        | `"text"` or `"image"`.                                       |
| `text_value`   | string / null | The text, when `asset_format` is `"text"`.                   |
| `file_url`     | string / null | Full image URL, when `asset_format` is `"image"`.            |

- When the school has **no** watermark, `watermark` is `null`.
- The `watermark` field also appears on the institution object in **login**, **`GET /api/v1/me`** and the principal's profile response, so the screen can show the current value when it opens.

---

## 4. Errors

| Case                                         | Status | Message / field                                           |
| -------------------------------------------- | ------ | --------------------------------------------------------- |
| Both `watermark_text` and `watermark_image`  | 422    | Send either watermark_text or watermark_image, not both.  |
| `watermark_image` not an image / too large   | 422    | Error on `watermark_image` (jpeg, png, jpg, gif, webp, max 2 MB). |
| `watermark_text` longer than 500 characters  | 422    | Error on `watermark_text`.                                |

---

## 5. Suggested UI

A fourth card on "Result PDF Images", matching the existing cards:

- Title **Watermark**, with the hint *"Shown on the result PDFs parents download. Choose text or an image."*
- A **Text / Image** toggle.
  - **Text:** a text input (max 500 characters) and a **Save** button.
  - **Image:** a file picker and a **Replace / Upload** button.
- Show the current watermark from `institution.watermark`: the text, or a preview of `file_url`.
- A **Remove** button, visible only when a watermark exists. It sends `remove_watermark=true`.
- Tip for the hint text: short text or a light/transparent PNG works best.

---

## 6. Frontend checklist

- [ ] Add the Watermark card to the "Result PDF Images" screen.
- [ ] Read the current value from `institution.watermark` (handle `null`).
- [ ] Send `watermark_text` **or** `watermark_image`, never both.
- [ ] Send `remove_watermark=true` for the Remove button.
- [ ] Validate size (2 MB) and type client-side before uploading.
- [ ] Refresh the institution data from the response after saving.

---

## 7. Notes

- No migration is needed; the existing `school_assets` table is used.
- Make sure `php artisan storage:link` has been run on the server so `file_url` image links resolve.
