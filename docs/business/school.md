# School — Business Domain

## Overview

The School domain is the core educational management system of EduConnect. It handles student enrollment, academic tracking, classroom management, discipline, attendance, library operations, and events.

**Database connection**: `school`

## Business Capabilities

### 1. Academic Year Management

**Purpose**: Define academic periods (e.g., "2025-2026 Academic Year").

| Action | Who | UI Notes |
|--------|-----|----------|
| Create academic year | Admin | Form with name, start_date, end_date |
| List academic years | Admin | Table with active/inactive status |
| Update academic year | Admin | Edit form |
| Delete academic year | Admin | Soft delete (restoreable) |

**UI/UX Notes**:
- Only one academic year can be active at a time
- Academic year determines the default period for grades, attendance, and discipline
- Deleting an academic year should be soft delete with restore capability

### 2. Class Management

**Purpose**: Manage school classes (e.g., "Grade 10-A", "Grade 11-B").

| Action | Who | UI Notes |
|--------|-----|----------|
| Create class | Admin | Form with name, academic_year, homeroom_teacher |
| List classes | Admin | Table with class name, academic year, teacher |
| Update class | Admin | Edit form |
| Delete class | Admin | Soft delete |

**UI/UX Notes**:
- Classes are linked to academic years
- Homeroom teacher is assigned to each class
- Class list should be filterable by academic year

### 3. Subject Management

**Purpose**: Manage subjects/courses offered by the school.

| Action | Who | UI Notes |
|--------|-----|----------|
| Create subject | Admin | Form with name, subject_code |
| List subjects | Admin | Table with subject name and code |
| Update subject | Admin | Edit form |
| Delete subject | Admin | Soft delete |

**UI/UX Notes**:
- Subjects are used in schedules and grades
- Subject code should be unique

### 4. Schedule Management

**Purpose**: Define the weekly class schedule (which subject is taught in which class, by which teacher, at which period on which day).

| Action | Who | UI Notes |
|--------|-----|----------|
| Create schedule | Admin/Principal/Teacher | Form with class, subject, teacher, day, period, room |
| View schedule by class | Admin/Principal/Teacher | Weekly timetable view |
| View my schedule | Teacher/Student | Personal schedule view |
| Update schedule | Admin/Principal/Teacher | Edit form |
| Delete schedule | Admin/Principal/Teacher | Confirmation dialog |
| Restore schedule | Admin/Principal/Teacher | From trash |

**UI/UX Notes**:
- Schedule view should be a weekly timetable (grid: days × periods)
- Teachers see their own teaching schedule
- Students see their class schedule
- Drag-and-drop schedule editing would be ideal for UX
- Schedule conflicts should be detected and prevented

### 5. Grade Management

**Purpose**: Record and manage student grades/scores for subjects.

| Action | Who | UI Notes |
|--------|-----|----------|
| Create grade | Admin/Principal/Teacher | Form with student, subject, score, type, semester |
| View my grades | Student/Parent | Personal grade report |
| View grades by class | Admin/Principal/Teacher | Table with student grades |
| Update grade | Admin/Principal/Teacher | Edit form |
| Delete grade | Admin/Principal/Teacher | Confirmation dialog |
| Get student stats | Admin/Principal/Teacher | Statistics view |

**UI/UX Notes**:
- Grade types might include: midterm, final, homework, quiz
- Grades should be filterable by semester and subject
- Student/parent view shows grades in a readable report format
- Teacher view shows class average and individual student grades
- Grade input should have validation (score range, required fields)

### 6. Attendance Management

**Purpose**: Track student attendance for each class session.

| Action | Who | UI Notes |
|--------|-----|----------|
| Record attendance | Teacher | Quick attendance form (present/absent/late/early-leave) |
| View attendance | Admin/Principal/Teacher/Student/Parent | Attendance list with filters |
| Update attendance | Teacher | Edit attendance status |
| Delete attendance | Teacher | Confirmation dialog |

**UI/UX Notes**:
- Attendance should be recorded per schedule session
- Quick attendance UI (checkboxes or dropdown per student)
- Attendance statuses: present, absent, late, early_leave, excused
- Attendance reports should be exportable
- Parent view shows their child's attendance record

### 7. Discipline Management

**Purpose**: Record student disciplinary incidents, approvals, appeals, and statistics.

| Action | Who | UI Notes |
|--------|-----|----------|
| Record discipline | Teacher (permission: record discipline) | Form with student, type, description, incident date |
| View all disciplines | Admin/Principal/Teacher | List with filters |
| View my discipline | Student/Parent | Personal discipline records |
| View by class | Admin/Principal/Teacher | Class-level discipline list |
| View by student | Admin/Principal/Teacher | Student-specific discipline history |
| Approve discipline | Admin/Principal | Review and approve pending records |
| Reject discipline | Admin/Principal | Reject with reason |
| Appeal discipline | Student/Parent | Appeal form with reason |
| Statistics | Admin/Principal | Dashboard with discipline stats |
| Export | Admin/Principal | Download discipline report |

**UI/UX Notes**:
- Discipline workflow: Record → Pending → Approved/Rejected
- Student/parent can appeal approved discipline records
- Appeal workflow: Appeal → Pending → Approved/Rejected
- Statistics view should show charts (bar, pie) for discipline types and trends
- Export to CSV/Excel for admin reporting
- Discipline types are configurable (e.g., tardiness, fighting, cheating)

### 8. Discipline Types

**Purpose**: Configurable types of disciplinary actions with default penalty points.

| Action | Who | UI Notes |
|--------|-----|----------|
| Create discipline type | Admin/Principal | Form with name, default_penalty_points |
| List discipline types | Admin/Principal | Table with types |
| Update discipline type | Admin/Principal | Edit form |
| Delete discipline type | Admin/Principal | Soft delete |

**UI/UX Notes**:
- Discipline types are master data — rarely changed
- Default penalty points used when recording discipline incidents
- Should have validation to prevent deletion if in use

### 9. Conduct Score Management

**Purpose**: Track student conduct/behavior scores with penalty points.

| Action | Who | UI Notes |
|--------|-----|----------|
| Record conduct score | Admin/Principal/Teacher | Form with student, semester, academic_year, penalty_points |
| View my conduct score | Student/Parent | Personal conduct report |
| View by class | Admin/Principal/Teacher | Class-level conduct scores |
| View by student | Admin/Principal/Teacher | Student-specific conduct history |
| Approve conduct score | Admin/Principal | Review and approve |
| Recalculate | Admin/Principal | Recalculate all scores for a period |

**UI/UX Notes**:
- Conduct scores are per semester and academic year
- Penalty points accumulate over time
- Recalculation should be a background job for large datasets
- Student/parent view shows a summary with color coding (green=good, yellow=warning, red=bad)

### 10. Dashboard

**Purpose**: Aggregated statistics and overview for the school dashboard.

| Action | Who | UI Notes |
|--------|-----|----------|
| View dashboard | All authenticated users | Summary statistics |

**UI/UX Notes**:
- Dashboard should show key metrics: total students, today's attendance, upcoming events, recent grades
- Role-based dashboard content (admin sees different metrics than student/parent)
- Charts and visual indicators for quick overview

### 11. Event Management

**Purpose**: Manage school events and student registrations.

| Action | Who | UI Notes |
|--------|-----|----------|
| Create event | Admin/Principal/Teacher | Form with title, description, dates, location |
| List events | All | Event calendar/list view |
| View event detail | All | Event details page |
| Update event | Admin/Principal/Teacher | Edit form |
| Delete event | Admin/Principal/Teacher | Confirmation dialog |
| Register for event | Student | Registration button |
| View my registrations | Student/Parent | List of registered events |

**UI/UX Notes**:
- Events should have a calendar view option
- Registration status: pending, confirmed, cancelled
- Event list should show upcoming events prominently

### 12. Library Management

**Purpose**: Manage library books and borrowing/returning transactions.

| Action | Who | UI Notes |
|--------|-----|----------|
| Create book | Admin | Form with title, author, ISBN, quantity |
| List books | All | Book catalog with search |
| Update book | Admin | Edit form |
| Delete book | Admin | Soft delete |
| Borrow book | Student | Borrow button, due date auto-set |
| Return book | Student | Return button |
| View transactions | Admin/Teacher | Transaction history |

**UI/UX Notes**:
- Book availability should be shown (available copies count)
- Borrowing should auto-set a due date (e.g., 14 days)
- Overdue books should be highlighted
- Transaction history shows borrow/return dates and status

### 13. Student-Guardian Linkage

**Purpose**: Link students to their guardians (parents) for communication and access.

| Action | Who | UI Notes |
|--------|-----|----------|
| Link guardian | Admin | Form with student, guardian user, relationship |
| View linkages | Admin | Table with student-guardian pairs |
| Update linkage | Admin | Edit form |
| Delete linkage | Admin | Confirmation dialog |

**UI/UX Notes**:
- Guardian must be a registered user in the system
- Relationship types: father, mother, other
- Parent/guardian sees their linked children's data

## Models Summary

| Model | Key Fields |
|-------|-----------|
| Student | user_id, class_id, student_code, status |
| SchoolClass | name, academic_year_id, homeroom_teacher_id |
| Subject | name, subject_code |
| Schedule | class_id, subject_id, teacher_id, day_of_week, period, room |
| Grade | student_id, subject_id, teacher_id, score, type, semester |
| Attendance | student_id, schedule_id, date, status, note |
| Discipline | student_id, discipline_type_id, incident_date, description, status, penalty_points |
| DisciplineType | name, default_penalty_points |
| DisciplineAction | discipline_id, action_type, note |
| DisciplineAppeal | discipline_id, appellant_user_id, appellant_type, appeal_reason, status |
| StudentConductScore | student_id, academic_year_id, semester, total_penalty_points |
| AcademicYear | name, start_date, end_date, is_active |
| Event | title, description, start_date, end_date, location |
| EventRegistration | event_id, student_id, status |
| LibraryBook | title, author, isbn, quantity |
| LibraryTransaction | book_id, student_id, borrowed_at, returned_at |
| StudentGuardian | student_id, guardian_user_id, relationship |

## Related Microservices

- **notify**: Sends notifications for events, attendance alerts, discipline alerts
- **realtime**: WebSocket connections for real-time schedule updates, attendance changes