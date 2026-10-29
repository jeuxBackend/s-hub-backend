# Admin & Sub-Admin Dashboard Stats Cards & API Updates

**Audience:** Frontend engineering team integrating the Admin & Sub-Admin Dashboards and management tables.  
**Endpoints Impacted:**

- `GET v1/admin/dashboard`
- `GET v1/admin/sub-admins`
- `GET v1/admin/managers`
- `GET v1/admin/class-toppers` *(new)*

---

## 1. Dashboard Stats Cards (`GET v1/admin/dashboard`)

The dashboard statistics response now includes number cards for **Managers** and **Sub-Admins**, matching the existing structure of `teachers`, `principals`, `students`, and `parents`.

### Auth & Role

- **Header:** `Authorization: Bearer <token>` (Sanctum)
- **Roles:** `admin`, `sub_admin` (requires `subadmin.permission:Dashboard`)

### New Response Fields

The endpoint returns the following new metrics in the response JSON:

```json
{
  "success": true,
  "message": "Admin dashboard data",
  "data": {
    "total_managers": 15,
    "total_managers_change_percent": 7.1,
    "active_managers": 14,
    "active_managers_change_percent": 0.0,
    "blocked_managers": 1,
    "blocked_managers_change_percent": 0.0,

    "total_subadmins": 5,
    "total_subadmins_change_percent": 0.0,
    "active_subadmins": 5,
    "active_subadmins_change_percent": 0.0,
    "blocked_subadmins": 0,
    "blocked_subadmins_change_percent": 0.0,

    "...": "existing fields (total_users, total_institutions, total_teachers, etc.)"
  }
}
```

### Metric Definitions

| Key                                | Type      | Description                                                      |
| ---------------------------------- | --------- | ---------------------------------------------------------------- |
| `total_managers`                   | `integer` | Total number of managers in scope (`active + blocked`).          |
| `total_managers_change_percent`    | `float`   | Percentage change in total managers vs. last month.              |
| `active_managers`                  | `integer` | Managers with status `"active"`.                                 |
| `active_managers_change_percent`   | `float`   | Percentage change in active managers vs. last month.             |
| `blocked_managers`                 | `integer` | Managers with status `"inactive"`.                               |
| `blocked_managers_change_percent`  | `float`   | Percentage change in inactive/blocked managers vs. last month.   |
| `total_subadmins`                  | `integer` | Total number of sub-admins in scope (`active + blocked`).        |
| `total_subadmins_change_percent`   | `float`   | Percentage change in total sub-admins vs. last month.            |
| `active_subadmins`                 | `integer` | Sub-admins with status `"active"`.                               |
| `active_subadmins_change_percent`  | `float`   | Percentage change in active sub-admins vs. last month.           |
| `blocked_subadmins`                | `integer` | Sub-admins with status `"inactive"`.                             |
| `blocked_subadmins_change_percent` | `float`   | Percentage change in inactive/blocked sub-admins vs. last month. |

### Backend Scoping Behavior

- **Platform Super Admin (`admin`):** Returns platform-wide counts across all countries and schools.
- **Sub-Admin (`sub_admin`):**
  - **Managers:** Automatically filtered to managers who oversee at least one school within the acting sub-admin's assigned scope.
  - **Sub-Admins:** Filtered to sub-admins in the same country or sharing assigned schools.

### Suggested Frontend UI Implementation

1. Render two new stats card groups/components on the dashboard matching the `Teachers` and `Principals` card styles:
   - **Managers Card:**
     - Primary counter: `total_managers` (with `total_managers_change_percent`)
     - Sub-badges: Active (`active_managers`) and Blocked (`blocked_managers`)
   - **Sub-Admins Card:**
     - Primary counter: `total_subadmins` (with `total_subadmins_change_percent`)
     - Sub-badges: Active (`active_subadmins`) and Blocked (`blocked_subadmins`)
2. **Role Visibility Check (Optional):** If your UX design only permits the Super Admin to see Sub-Admin management cards, check `currentUser.role === 'admin'` before rendering the Sub-Admin card.

---

## 2. Sub-Admin & Manager Listings: `country` Field Access

Both `GET v1/admin/sub-admins` and `GET v1/admin/managers` now return the `country` column on each record.

### A. Sub-Admins List (`GET v1/admin/sub-admins`)

Each item in the `data` array now includes the `country` property:

```json
{
  "id": 4,
  "first_name": "John",
  "last_name": "Doe",
  "sure_name": "Smith",
  "email": "subadmin@shub.com",
  "phone_number": "+1234567890",
  "role": "sub_admin",
  "status": "active",
  "region": ["East"],
  "country": "US",
  "permissions": ["Dashboard", "Schools"],
  "profile_image": null,
  "assigned_schools": []
}
```

### B. Managers List (`GET v1/admin/managers`)

Each manager object in the paginated `data` array now includes `country`:

```json
{
  "id": 12,
  "first_name": "Jane",
  "last_name": "Manager",
  "sure_name": "Doe",
  "email": "manager@shub.com",
  "phone_number": "+1987654321",
  "role": "manager",
  "status": "active",
  "country": "GB",
  "profile_image": null,
  "total_schools": 3,
  "total_student": 120,
  "total_teachers": 15,
  "total_school_sub_admin": 2
}
```

### Format Notes

- `country` is a **2-letter ISO country code** (e.g. `"US"`, `"GB"`, `"PK"`), or `null` if unassigned.
- Use this value to display country flags, badges, or filter options in your manager/sub-admin tables.

---

## 3. Class Toppers API *(New — 2026-10-29)*

### `GET /api/v1/admin/class-toppers`

Returns the **top 3 performing students per classroom per school**, based on `years_marks` grade type.  
Ties are fully included — if two students share rank 1, both appear as rank 1.

**Auth:** Admin or Sub-Admin token (Sanctum).  
**Permission middleware:** `subadmin.permission:Students,Dashboard`

**Scoping:**
| Role | Sees |
|---|---|
| `admin` | All schools |
| `sub_admin` | Only schools in their assigned country |

**Optional query parameters:**

| Param | Type | Description |
|---|---|---|
| `school_id` | integer | Filter to a single school (alias: `institution_id`) |
| `classroom_id` | integer | Filter to a single classroom |
| `term` | string | Filter by term (`first`, `second`, `third`, `final`) |
| `limit` | integer | Number of rank positions to return per class. Default: `3`. Pass `?limit=1` to get only the #1 topper(s) of each class. |

---

### Success Response `200`

```json
{
  "success": true,
  "message": "Class toppers fetched successfully",
  "data": [
    {
      "school": {
        "id": 1,
        "name": "Sunrise Academy",
        "country": "PK",
        "region": "Punjab"
      },
      "classrooms": [
        {
          "classroom": {
            "id": 3,
            "name": "Class 12",
            "code": "CL12"
          },
          "toppers": [
            {
              "rank": 1,
              "performance_percentage": 95.5,
              "obtained_marks": 286.5,
              "total_marks": 300,
              "student": {
                "id": 7,
                "first_name": "Ayesha",
                "last_name": "Khan",
                "sur_name": null,
                "profile_picture": "https://...",
                "registration_number": "REG-001",
                "gender": "female"
              }
            },
            {
              "rank": 1,
              "performance_percentage": 95.5,
              "obtained_marks": 286.5,
              "total_marks": 300,
              "student": {
                "id": 9,
                "first_name": "Sara",
                "last_name": "Ali",
                "sur_name": null,
                "profile_picture": null,
                "registration_number": "REG-002",
                "gender": "female"
              }
            },
            {
              "rank": 2,
              "performance_percentage": 88.0,
              "obtained_marks": 264.0,
              "total_marks": 300,
              "student": {
                "id": 12,
                "first_name": "Bilal",
                "last_name": "Ahmed",
                "sur_name": null,
                "profile_picture": null,
                "registration_number": "REG-003",
                "gender": "male"
              }
            }
          ]
        }
      ]
    }
  ]
}
```

> **Note on ranking & tie logic:**  
> - Performance % is calculated as `SUM(score) / SUM(total) * 100` from `years_marks` grades.
> - **Ties are fully included**: If two or more students have identical scores (e.g. 95.5%), all of them receive the same rank (`rank: 1`) and all appear as toppers.
> - Default returns top 3 ranks (`limit=3`). Pass `?limit=1` to view only the 1st position topper(s) for each class.

---

### Empty Response (no grades recorded yet)

```json
{
  "success": true,
  "message": "Class toppers fetched successfully",
  "data": []
}
```

---

### Error — Forbidden `403`

Returned if the token belongs to a role other than `admin` or `sub_admin`.

