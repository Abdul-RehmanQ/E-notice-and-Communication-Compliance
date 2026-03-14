# University Department Community & Notification Portal

A role-based university portal built with core PHP + MySQL for department-level communication, course-driven notifications, and moderated community posts.

## Project Goal

### Current Goal (Web-Based)
Build and run a reliable department-level web application for students, teachers, community supervisors, and super admins.

### Future Goal (Mobile + Scale)
Extend the same platform to mobile apps (Android/iOS) while supporting **500–1000 users** (students + teachers + admins) with stable performance and secure access control.

---

## What We Built (Completed Work)

### 1) Multi-Role Authentication & Session Flows
- Student login from `index.php`
- Teacher login from `teacher/login.php`
- Community Supervisor login from `community_supervisor/login.php`
- Super Admin login from `super_admin/login.php`
- Role-specific session identities are enforced via guard files:
  - `student/student_guard.php`
  - `teacher/teacher_guard.php`
  - `community_supervisor/supervisor_guard.php`
- Secure logout with login/logout timestamps in `logout.php`

### 2) Community Module (Student + Teacher)
- Students and teachers can:
  - Create text/image posts
  - Choose post scope (`all` university or `department`)
  - Set expiration window
  - Delete their own posts
- Implemented in:
  - `student/community.php`
  - `teacher/community.php`

### 3) Community Supervisor Moderation
- Supervisor dashboard for pending posts review:
  - Approve post
  - Reject and delete post
  - View moderation stats and recent actions
- Implemented in `community_supervisor/dashboard.php`

### 4) Department-Scoped Moderation (Important Security Logic)
We implemented and hardened department scope rules:
- Supervisor department is loaded during login and guard resolution.
- Supervisor top bar now displays department.
- Supervisor can only see pending posts from their own department.
- Supervisor can only approve/reject posts from their own department.
- Department comparison is normalized (`trim + lowercase`) to avoid mismatches caused by spacing/case differences.
- Teacher and student community feeds also use normalized department matching.

### 5) Teacher Notifications
- Teachers send class notifications only for offerings assigned to them.
- Notifications are delivered to enrolled students.
- Students view teacher notifications in `student/dashboard.php`.
- Teachers can delete sent notification batches.
- Implemented in `teacher/dashboard.php`.

### 6) Email Reply System
- Student reply-to-teacher email flow via PHPMailer (`send_email.php`).
- SMTP settings are separated in:
  - `email_config.php`
  - `email_config.local.php` (local/private)
- Email domain policy checks are included.
- Email send attempts are logged in `messages` table.

### 7) Super Admin Academic Operations
Implemented in `super_admin/dashboard.php` and `super_admin/re_enroll.php`:
- Import students from Excel/CSV/ODS
- Import teachers from Excel/CSV/ODS
- Import courses from Excel/CSV/ODS
- Assign courses to teachers by session/semester/section (class offering model)
- Individual and group enrollment with scope validation
- Re-enroll workflow for special cases
- Department-scoped controls for admin operations

### 8) Account Settings
- Password update (Argon2id verification + hashing)
- Email update with uniqueness checks
- Role-specific settings pages under each module folder

---

## Technical Approach Followed

This project follows a **modular role-based PHP approach** (without a framework) using:
- Server-rendered pages (PHP + Bootstrap)
- Prepared statements (`mysqli`) for DB safety
- Session-based authentication per role
- PRG-style flash messages in many handlers (Post/Redirect/Get)
- Department-level authorization rules in query conditions
- Utility-style common config for DB and password hashing in `config.php`

Why this approach was chosen:
- Fast to implement for academic delivery
- Easy to host on XAMPP/shared PHP hosting
- Clear role separation by folder and page

---

## Frontend ↔ Backend Connection (How It Works)

### UI Rendering
- Pages are rendered by PHP directly (HTML + Bootstrap + FontAwesome).
- Each role has its own pages inside dedicated folders.

### Request Handling Pattern
Most pages use a same-file request cycle:
1. Browser loads `*.php` page (GET)
2. User submits form (POST)
3. Top-of-file PHP handler validates input and executes SQL
4. Success/error set using local variables or flash session
5. Page reloads to reflect updated data

### Example Flow: Teacher Creates Community Post
1. Teacher opens `teacher/community.php`
2. Fills form + optional image upload
3. POST request reaches same file
4. Backend validates content/image and inserts into `posts`
5. Status remains `pending`
6. Supervisor later reviews in `community_supervisor/dashboard.php`

### Example Flow: Supervisor Approves/Rejects
1. Supervisor submits approve/reject action in `community_supervisor/dashboard.php`
2. Backend checks post status + expiration + department scope
3. On approve: updates `posts.status = approved`
4. On reject: logs review and deletes post
5. Stats and pending counts update after redirect

### Example Flow: Teacher Notifications
1. Teacher chooses assigned class offering in `teacher/dashboard.php`
2. Backend verifies teacher-offering assignment
3. Pulls enrolled students from `student_course_enrollments`
4. Inserts notification rows into `notifications`
5. Students read notifications on `student/dashboard.php`

---

## Project Structure

```text
fyp/
├─ index.php
├─ config.php
├─ logout.php
├─ send_email.php
├─ DB/project.sql
├─ student/
│  ├─ dashboard.php
│  ├─ community.php
│  ├─ settings.php
│  └─ student_guard.php
├─ teacher/
│  ├─ login.php
│  ├─ dashboard.php
│  ├─ community.php
│  ├─ settings.php
│  └─ teacher_guard.php
├─ community_supervisor/
│  ├─ login.php
│  ├─ dashboard.php
│  ├─ settings.php
│  └─ supervisor_guard.php
├─ super_admin/
│  ├─ login.php
│  ├─ dashboard.php
│  ├─ re_enroll.php
│  └─ settings.php
└─ vendor/
```

---

## Database Design (Core Tables)

From `DB/project.sql`, major tables include:
- Identity and roles: `user`, `student`, `teacher`, `community_supervisor`, `super_admin`
- Community: `posts`, `post_reviews`
- Notifications & messaging: `notifications`, `messages`
- Academics: `courses`, `course_offerings`, `teacher_course_assignments`, `student_course_enrollments`

This schema allows:
- Single user identity with role-linked profile tables
- Moderation workflow for posts
- Course-offering based teacher-student communication
- Enrollment and assignment traceability

---

## Security & Validation Practices Used

- Argon2id password hashing and verification (`config.php`)
- Prepared statements on DB operations
- Role checks before protected routes
- Session lifecycle handling on logout
- Input validation for:
  - emails
  - required fields
  - section/semester constraints
  - image size/type
- Department-scope enforcement in queries

---

## Setup Instructions (Local)

### Prerequisites
- PHP 8.2+ (Argon2id support required)
- MySQL / MariaDB
- Composer
- XAMPP (recommended for local)

### Steps
1. Clone/copy project to XAMPP htdocs:
   - `c:\xampp\htdocs\fyp`
2. Create DB and import schema:
   - import `DB/project.sql`
3. Install dependencies:
   - `composer install`
4. Configure DB in `config.php`
5. Configure SMTP in `email_config.local.php`
6. Start Apache + MySQL
7. Open:
   - `http://localhost/fyp/`

---

## Deployment Notes

You **do not need Laravel** to deploy this project.

Current stack can be deployed on:
- Apache/Nginx + PHP-FPM
- MySQL/MariaDB

For 500–1000 users, recommended production hardening:
- Enable OPcache
- Add/verify DB indexes on frequent filters (`user_id`, `status`, `department`, `offering_id`, timestamps)
- Use HTTPS
- Move secrets to environment variables
- Enable application + server logs
- Regular DB backups

---

## Scalability Roadmap (500–1000 Users)

### Phase 1 (Now)
- Stabilize current web app
- Improve indexes and query profiling
- Add audit logs and better monitoring

### Phase 2 (Next)
- API-first endpoints for mobile readiness
- Token/session strategy for app clients
- Notification queueing for bulk operations

### Phase 3 (Future Mobile)
- Build Android/iOS app (or Flutter/React Native)
- Reuse same backend rules for role and department access

---

## Suggested Next Evolution

For long-term maintainability and team collaboration, migrate gradually to Laravel:
- Keep current app operational
- Move module-by-module (Auth → Community → Moderation → Notifications → Admin tools)
- Add automated tests during migration

---

## Credits / Context

This is an academic FYP-oriented system designed for departmental communication and governance, with a roadmap toward institution-level reliability and future mobile adoption.
