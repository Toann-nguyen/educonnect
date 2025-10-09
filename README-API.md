## EduConnect API Reference

This document lists API endpoints with Method, URL, and sample Body where applicable. All endpoints are prefixed with `/api`.

Authentication: Use `Authorization: Bearer {token}` for protected routes (Sanctum).

### Public (no auth)

-   POST /api/auth/register
    -   Body:
    ```json
    {
        "name": "John Doe",
        "email": "john@example.com",
        "password": "secret",
        "password_confirmation": "secret"
    }
    ```
-   POST /api/auth/login
    -   Body:
    ```json
    { "email": "john@example.com", "password": "secret" }
    ```
-   POST /api/auth/forgot-password
    -   Body: `{ "email": "john@example.com" }`
-   POST /api/auth/reset-password
    -   Body:
    ```json
    {
        "email": "john@example.com",
        "token": "RESET_TOKEN",
        "password": "newpass",
        "password_confirmation": "newpass"
    }
    ```
-   POST /api/qrcode
    -   Body: varies (QR test)

### Auth & Profile (auth required)

-   POST /api/auth/logout
-   GET /api/auth/user
-   GET /api/profile
-   PUT /api/profile
    -   Body: profile fields (e.g., `{ "full_name": "John Doe", ... }`)

### Dashboard

-   GET /api/dashboard

### Admin (role: admin)

-   Users: /api/admin/users
    -   GET /api/admin/users
    -   POST /api/admin/users
        -   Body: user fields
    -   GET /api/admin/users/{user}
    -   PUT/PATCH /api/admin/users/{user}
        -   Body: user fields
    -   DELETE /api/admin/users/{user}
    -   POST /api/admin/users/{id}/restore
-   Academic Years: /api/admin/academic-years (CRUD similar to above)
-   Classes: /api/admin/classes (CRUD similar)
-   Subjects: /api/admin/subjects (CRUD similar)

### Schedules

-   GET /api/schedules/class/{class}
-   GET /api/schedules/class/{class}/week
-   GET /api/schedules/my (roles: teacher|student)
-   GET /api/schedules/my-classes (role: teacher)
-   [Admin|Principal|Teacher] CRUD at `http://127.0.0.1:8000/api/schedules`
    -   GET /api/schedules
        -   Returns all classes with students and schedules
        -   Headers: `Authorization: Bearer {token}`
        -   Body: none
    -   POST /api/schedules
        -   Headers: `Content-Type: application/json`
        -   Body (required fields):
        ```json
        {
            "class_id": 1,
            "subject_id": 2,
            "teacher_id": 5,
            "day_of_week": 2,
            "period": 3,
            "room": "A102"
        }
        ```
        -   Notes:
            -   `day_of_week`: 1-7
            -   `period`: positive integer
    -   GET /api/schedules/{schedule}
        -   Headers: `Authorization: Bearer {token}`
        -   Body: none
    -   PUT /api/schedules/{schedule}
        -   Headers: `Content-Type: application/json`
        -   Body (full replace of values you want to set):
        ```json
        {
            "class_id": 1,
            "subject_id": 3,
            "teacher_id": 8,
            "day_of_week": 3,
            "period": 2,
            "room": "B201"
        }
        ```
    -   PATCH /api/schedules/{schedule}
        -   Headers: `Content-Type: application/json`
        -   Body (partial update, send only fields to change):
        ```json
        {
            "day_of_week": 5,
            "period": 4,
            "room": "Lab-01"
        }
        ```
    -   DELETE /api/schedules/{schedule}
        -   Headers: `Authorization: Bearer {token}`
        -   Body: none
    -   POST /api/schedules/{id}/restore
        -   Headers: `Authorization: Bearer {token}`
        -   Body: none

### Grades

-   GET /api/my-grades (roles: student|parent)
-   [role_or_permission: teacher|admin]
    -   Standard resource: /api/grades (index, store, show, update, destroy)
    -   Bodies depend on Grade fields

### Fee Types

-   GET /api/fee-types
-   GET /api/fee-types/{feeType}
-   [roles: admin|principal|accountant]
    -   POST /api/fee-types
        -   Body:
        ```json
        { "name": "Tuition", "amount": 1000000, "is_active": true }
        ```
    -   PUT/PATCH /api/fee-types/{feeType}
        -   Body: same fields as POST
    -   DELETE /api/fee-types/{feeType}
    -   PATCH /api/fee-types/{feeType}/toggle-active
    -   POST /api/fee-types/{id}/restore

### Invoices

-   GET /api/my-invoices (roles: student|parent)
-   GET /api/invoices/overdue (roles: admin|principal|accountant)
-   GET /api/invoices/statistics (roles: admin|principal|accountant)
-   POST /api/invoices/bulk-create (roles: admin|principal|accountant)
    -   Body (example):
    ```json
    {
        "class_id": 1,
        "fee_type_id": 2,
        "amount": 500000,
        "due_date": "2025-10-31"
    }
    ```
-   POST /api/invoices/update-overdue (roles: admin|principal|accountant)
-   GET /api/invoices/class/{classId} (roles: admin|principal|accountant|teacher)
-   GET /api/invoices
-   GET /api/invoices/{invoice}
-   [roles: admin|principal|accountant]
    -   POST /api/invoices
        -   Headers: `Content-Type: application/json`
        -   Body (example - simple invoice without items):
        ```json
        {
            "student_id": 12,
            "title": "Semester tuition",
            "notes": "Due end of month",
            "total_amount": 1500000,
            "paid_amount": 0,
            "due_date": "2025-10-31",
            "status": "unpaid"
        }
        ```
        -   Body (example - with items array):
        ```json
        {
            "student_id": 12,
            "title": "October fees",
            "notes": "Include uniforms",
            "due_date": "2025-10-31",
            "items": [
                {
                    "fee_type_id": 2,
                    "description": "Tuition",
                    "unit_price": 1000000,
                    "quantity": 1,
                    "total_amount": 1000000
                },
                {
                    "fee_type_id": 5,
                    "description": "Uniform",
                    "unit_price": 250000,
                    "quantity": 2,
                    "total_amount": 500000
                }
            ],
            "total_amount": 1500000,
            "paid_amount": 0,
            "status": "unpaid"
        }
        ```
    -   PUT/PATCH /api/invoices/{id}
        -   Headers: `Content-Type: application/json`
        -   Body (full update - PUT example):
        ```json
        {
            "title": "Semester tuition (updated)",
            "notes": "Pay before 25th",
            "due_date": "2025-11-25",
            "total_amount": 1600000,
            "paid_amount": 200000,
            "status": "partially_paid"
        }
        ```
        -   Body (partial update - PATCH example):
        ```json
        {
            "paid_amount": 1600000,
            "status": "paid"
        }
        ```
    -   DELETE /api/invoices/{id}
        -   Body: none
-   GET /api/invoices/{invoiceId}/payments

### Payments

-   GET /api/payments/statistics (roles: admin|principal|accountant)
-   GET /api/payments (roles: admin|principal|accountant)
-   GET /api/payments/{payment}
-   POST /api/payments (roles: admin|principal|accountant|parent)
    -   Body (example):
    ```json
    { "invoice_id": 10, "amount": 300000, "method": "cash" }
    ```
-   DELETE /api/payments/{id} (roles: admin|accountant)

### Discipline (Kỷ luật)

-   GET /api/disciplines
-   GET /api/disciplines/my (roles: student|parent)
-   GET /api/disciplines/class/{classId} (roles: admin|principal|teacher)
-   GET /api/disciplines/student/{studentId} (roles: admin|principal|teacher)
-   GET /api/disciplines/{discipline}
-   GET /api/disciplines/statistics (roles: admin|principal)
-   GET /api/disciplines/export (roles: admin|principal)
-   POST /api/disciplines (permission: record discipline)
    -   Body (example):
    ```json
    {
        "student_id": 5,
        "type_id": 2,
        "incident_date": "2025-10-08",
        "penalty_points": 3,
        "description": "Late to class"
    }
    ```
-   PUT /api/disciplines/{discipline}
    -   Body: discipline fields
-   DELETE /api/disciplines/{discipline} (roles: admin|principal)
-   POST /api/disciplines/{discipline}/approve (roles: admin|principal)
-   POST /api/disciplines/{discipline}/reject (roles: admin|principal)
-   POST /api/disciplines/{discipline}/appeal (roles: student|parent)
    -   Body: `{ "reason": "...", "evidence": ["url1", "url2"] }`

#### Discipline API Flows (Step-by-step)

-   GET /api/disciplines

    -   Who: Any authenticated user
    -   Flow:
        1. Auth via Sanctum
        2. Controller calls service `getAllDisciplines(filters)`
        3. Service applies role-based scope (admin/principal see all; teacher sees related; student/parent see relevant)
        4. Returns paginated list with basic relations
    -   Responses: 200 with list; 500 on errors

-   GET /api/disciplines/my (roles: student|parent)

    -   Who: student, parent
    -   Flow:
        1. Role middleware checks student|parent
        2. Service `getMyDisciplines(user, filters)`
            - student: by `user->student->id`
            - parent: by children ids
        3. Returns paginated list
    -   Responses: 200; 403 if role invalid; 500 on errors

-   GET /api/disciplines/class/{classId} (roles: admin|principal|teacher)

    -   Who: admin, principal, teacher
    -   Flow:
        1. Role middleware validates
        2. Service `getDisciplinesByClass(classId, filters)`
            - Teacher: ensure homeroom or permitted class
        3. Returns list
    -   Responses: 200; 403 if unauthorized; 404 if class missing; 500 on errors

-   GET /api/disciplines/student/{studentId} (roles: admin|principal|teacher)

    -   Who: admin, principal, teacher
    -   Flow:
        1. Role middleware validates
        2. Service `getDisciplinesByStudent(studentId, filters)` with policy-like checks for teacher
        3. Returns list
    -   Responses: 200; 403/404/500 as applicable

-   GET /api/disciplines/{discipline}

    -   Who: Any authenticated user; Policy enforces visibility
    -   Flow:
        1. Route-model binding loads discipline
        2. Policy `view` checks (admin/principal allow; teacher only if related; student/parent if owner)
        3. Load relations and return
    -   Responses: 200; 403 unauthorized; 404 not found; 500 errors

-   GET /api/disciplines/statistics (roles: admin|principal)

    -   Who: admin, principal
    -   Flow:
        1. Role middleware validates
        2. Service aggregates counts by status/type/date ranges
        3. Return KPIs
    -   Responses: 200; 403/500

-   POST /api/disciplines (permission: record discipline)

    -   Who: Users with permission `record discipline` (e.g., admin/principal/teacher if granted)
    -   Body (example):
        ```json
        {
            "student_id": 5,
            "type_id": 2,
            "incident_date": "2025-10-08",
            "penalty_points": 3,
            "description": "Late to class"
        }
        ```
    -   Flow:
        1. Permission middleware validates
        2. Validate body (student exists, type valid, date format, points >= 0)
        3. Service creates discipline record; may log activity and notify
        4. Optionally recalculate conduct score asynchronously/synchronously
    -   Responses: 201 with created resource; 422 validation; 403 permission; 500 errors

-   PUT /api/disciplines/{discipline}

    -   Who: Authenticated; Policy controls who can update (reporter/admin/principal, etc.)
    -   Body: any updatable fields (description, status, etc.)
    -   Flow:
        1. Load discipline by id
        2. Policy `update` checks complex role/state rules
        3. Validate body; Service updates record
        4. If status changed to confirmed/rejected, trigger side-effects (e.g., conduct score recompute)
    -   Responses: 200 updated; 403 unauthorized; 422 validation; 404 not found; 500 errors

-   DELETE /api/disciplines/{discipline} (roles: admin|principal)

    -   Who: admin, principal
    -   Flow:
        1. Role middleware validates
        2. Service deletes (soft delete if enabled)
    -   Responses: 200; 403; 404; 500

-   POST /api/disciplines/{discipline}/approve (roles: admin|principal)

    -   Who: admin, principal
    -   Flow:
        1. Role middleware validates
        2. Load record; Service marks approved (approved_by, approved_at), updates status
        3. Trigger conduct score recalculation for the student/timeframe
    -   Responses: 200; 403; 404; 500

-   POST /api/disciplines/{discipline}/reject (roles: admin|principal)

    -   Flow similar to approve; set status rejected with note if provided

-   POST /api/disciplines/{discipline}/appeal (roles: student|parent)
    -   Who: student/parent owning the student
    -   Body:
        ```json
        { "reason": "...", "evidence": ["url1", "url2"] }
        ```
    -   Flow:
        1. Role middleware validates
        2. Ownership check (student or their parent)
        3. Service creates appeal linked to discipline; may notify reviewers
    -   Responses: 200/201; 403; 404; 422; 500

### Discipline Types

-   GET /api/discipline-types
-   GET /api/discipline-types/{disciplineType}
-   [roles: admin|principal]
    -   POST /api/discipline-types
        -   Body: `{ "name": "Late", "penalty_points": 1, "active": true }`
    -   PUT /api/discipline-types/{disciplineType}
        -   Body: same fields
    -   DELETE /api/discipline-types/{disciplineType}

### Conduct Scores (Hạnh kiểm)

-   GET /api/conduct-scores/my (roles: student|parent)
    -   Query: `semester?`, `academic_year_id?`
-   GET /api/conduct-scores/class/{classId} (roles: admin|principal|teacher)
    -   Query: `semester?`, `academic_year_id?`
-   GET /api/conduct-scores/student/{studentId} (roles: admin|principal|teacher)
    -   Query: `semester?`, `academic_year_id?`
-   PUT /api/conduct-scores/{conductScore} (roles: teacher|admin|principal)
    -   Body (any subset):
    ```json
    { "teacher_comment": "Nhận xét...", "total_penalty_points": 5 }
    ```
-   POST /api/conduct-scores/{conductScore}/approve (roles: admin|principal)
    -   Body: `{}`
-   POST /api/conduct-scores/recalculate (roles: admin|principal)
    -   Body:
    ```json
    { "semester": 1, "academic_year_id": 1, "class_id": 2, "student_id": 5 }
    ```

### Students & Parents

-   GET /api/my-children (role: parent)

### Financial Reports

-   GET /api/financial-reports (role_or_permission: admin|manage finances)

Notes

-   Bodies above are examples; exact validation may be defined in Form Request classes under `app/Http/Requests`.
-   Use `Accept: application/json` and `Content-Type: application/json` headers for requests with bodies.
