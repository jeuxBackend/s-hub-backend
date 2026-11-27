# Web App: Principal Chat Replies & Report-Book Remarks (Teacher / Principal)

**Audience:** React (web) developers: Principal app and Teacher app.
**Date:** 2026-11-27
**How to use this doc:** two behaviors the backend now enforces. If your screens already handle them, use the checklists at the end to confirm. If not, this is what to build. All endpoints are under `/api/v1` with `Authorization: Bearer <token>`.

---

## Part 1: Principal can reply in chat (but not start one)

### What the server does

| Situation | Result |
| --------- | ------ |
| Parent / teacher / school admin opens their chat list | The principal is always listed (first), even with no messages yet. The server creates an empty conversation for them. |
| Parent / teacher writes to the principal | The principal sees the conversation in their inbox. |
| **Principal replies** in a conversation they are part of, **after the other person has written** | Works: `POST /chat/conversations/{id}/messages` → `201`. |
| Principal sends in a chat where **nobody else has written yet** | `403` "Principals can only reply to messages sent to them." |
| Principal sends in a conversation they are not part of | `403` |
| Principal starts a new conversation: `POST /chat/conversations` | `403` "Principals cannot start conversations." |
| Principal marks a chat as read: `PATCH /chat/conversations/{id}/read` | `200` with `{ "marked_read": 0 }`. **Nothing is changed.** (Before it returned `403`; it now succeeds quietly.) |

### Endpoints (same for every role)

| Method | Endpoint | Purpose |
| ------ | -------- | ------- |
| `GET` | `/chat/conversations` | Inbox. For the principal: only conversations that contain at least one message. |
| `GET` | `/chat/conversations/{id}` | Open a chat (paginated messages) |
| `POST` | `/chat/conversations/{id}/messages` | Send. Body: `body` (text, max 5000) and/or `attachment` (file, max 20 MB). At least one is required. |
| `PATCH` | `/chat/conversations/{id}/read` | Mark as read |
| `GET` | `/chat/users` | Contacts. Returns nothing for the principal (they cannot start chats). Each contact has `id`, `full_name`, `role`, `position`, `profile_picture`. |

### What the Principal web app must do

- Show a **message box** in an open chat.
- **Enable it only when the other person has already sent at least one message** in that conversation (check the loaded messages for any with `sender_id` different from the principal's id). Otherwise show it disabled with a hint such as "You can reply once they message you."
- If the server still refuses (`403`), show the server's `message` text.
- Do **not** offer a "New chat" or contact picker for the principal.
- Opening a chat may call mark-as-read. It now returns `200`, so no special error handling is needed.

### What the Teacher web app must do

- Show the **principal first** in the contact list, and show an **empty conversation** as normal (empty state, message box enabled). The principal appears in `GET /chat/conversations` even before any message.

---

## Part 2: Report-book remarks: a teacher edits only their own students

Details printed on the result PDF for each student and term (conduct, attendance, remarks, next term date).

### Endpoints

- `GET /student-result-remarks?student_id=7&term=second`
- `PUT /student-result-remarks`

`term` is `first`, `second`, `third` or `final`. Allowed roles: principal, school admin, teacher.

### Who can edit what

| Field | Type | Teacher | Principal / school admin |
| ----- | ---- | :-----: | :----------------------: |
| `conduct` | text, max 255 | yes | yes |
| `possible_attendance`, `times_absent`, `times_late` | integer 0 to 65535 | yes | yes |
| `teacher_remarks` | text, max 2000 | yes | yes |
| `head_teacher_remarks` | text, max 2000 | **no** | yes |
| `next_term_begins` | date | **no** | yes |

### Rules the server enforces

- **Own students only (teacher):** a teacher can read and save details for a student only if the teacher is **assigned to that student's class**: either allocated as the **class teacher**, or set as the **teacher of one of its subjects**, or placed on its **timetable**. Any other student returns `403` "Unauthorized access to this student." (Subject teachers are included, so every student the Results screen lists for a teacher works.)
- **Principal / school admin:** can edit any student of their school.
- **Principal-only fields:** if a teacher sends `head_teacher_remarks` or `next_term_begins`, the server returns `403` "Only the principal can set: …". The `GET` response contains these two fields for everyone, so the **Teacher app must hide them**; do not rely on the server to hide them.
- **Partial saves are safe:** only the fields you send are changed, and a teacher's save never clears the principal's fields. Send `null` or `""` to clear one field.
- A student with no classroom returns `422` "This student is not assigned to a classroom."
- `student_id` and `term` are required on `PUT`; everything else is optional.
- Attendance numbers are entered by hand; they are not calculated from attendance records.

### `GET` response

```json
{
  "success": true,
  "data": {
    "student_id": 7, "classroom_id": 3, "term": "second",
    "conduct": "Fair", "possible_attendance": 108, "times_absent": 7, "times_late": null,
    "teacher_remarks": "Encourage her on writing at home.",
    "head_teacher_remarks": "Help her to improve on her writing.",
    "next_term_begins": "2026-05-04"
  }
}
```

All detail fields are `null` when nothing has been saved yet.

### What each web app must do

- **Teacher app:** a form per student and term with conduct, possible attendance, times absent, times late and teacher remarks. **Do not show or send** `head_teacher_remarks` or `next_term_begins`. Only list students from classes the teacher is assigned to (class teacher, subject teacher or timetable).
- **Principal app:** the same form plus head teacher's remarks and next term begins. Include a term selector, because details are saved per term.
- Remind users to save the details **before** parents download the result PDF; the PDF reads whatever is saved at download time.

---

## Checklist: confirm whether each point is already done

**Chat: Principal app**
- [ ] Message box exists in an open chat.
- [ ] It is disabled until the other person has written.
- [ ] Server `403` messages are shown to the user.
- [ ] No "new chat" option for the principal.

**Chat: Teacher app**
- [ ] The principal is shown first and an empty conversation opens normally.

**Report-book: Teacher app**
- [ ] Form per student and term, with only the five teacher fields.
- [ ] `head_teacher_remarks` and `next_term_begins` are neither shown nor sent.
- [ ] Only the teacher's own students can be selected; `403` is handled.

**Report-book: Principal app**
- [ ] All seven fields are available, with a term selector.
- [ ] `PUT` sends only the fields that changed.

## Backend note

No migration is needed for the chat change. The report-book remarks use the `student_result_remarks` table (migration `2026_10_30_110100_create_student_result_remarks_table.php`); run `php artisan migrate` if it has not run yet.
