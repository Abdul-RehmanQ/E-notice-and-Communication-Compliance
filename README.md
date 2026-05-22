# E-Notice and Communication Compliance System

A comprehensive web-based platform for managing academic notices, class notifications, and compliance across multiple user roles in an academic institution.

## Overview

This system enables efficient notification distribution and communication tracking across students, teachers, community supervisors, and administrators. It enforces compliance requirements while maintaining a streamlined user experience for each role.

**Key Features:**
- Role-specific authentication pages (students via roll number, staff via email)
- Community posts with department/all scopes, optional images, and expiry dates
- Supervisor moderation workflow for posts (pending/approved/rejected)
- Course-based notifications sent to enrolled students
- Email reply flow with message logging for compliance
- Audit logging with searchable admin reports
- Bulk student import plus course/offering/assignment management
- Database-driven architecture with role-based access control

## System Architecture

The application follows a role-based access model with dedicated login pages and dashboards. Students authenticate via roll number, while teachers, supervisors, and admins authenticate via email. Sessions store role-specific identity data and guard files protect access to role directories.

**Core Components:**

1. **Authentication & Sessions** — `index.php` handles student login by roll number. `teacher/login.php`, `community_supervisor/login.php`, and `super_admin/login.php` handle staff logins by email. Passwords use Argon2id hashing and login events are written to the audit log.

2. **Role-Specific Modules** — Four main user paths:
   - **Student** — View course notifications, browse community posts, reply to teachers via email
   - **Teacher** — Send course notifications to assigned offerings, create community posts
   - **Community Supervisor** — Approve or reject pending community posts for their department
   - **Super Admin** — Import students, manage courses/offerings/assignments, review audit logs

3. **Community Posts** — Students and teachers create posts with scope (`all` or `department`), optional images, and expiration dates. Posts default to `pending` status and require supervisor approval. Review decisions are stored in `post_reviews`.

4. **Course Notifications** — Teachers send notifications to students enrolled in assigned course offerings. Students can filter notifications by course, date range, and message text.

5. **Email Integration** — `send_email.php` uses PHPMailer with SMTP settings from `email_config.php`/`email_config.local.php`. Student replies are delivered to teachers and stored in the `messages` table.

6. **Audit Logging** — `audit_log.php` captures actions into `activity_logs`. Super admins review logs in `super_admin/audit_logs.php` with filters for role, action, date range, and search.

## Database Schema

The system uses a MySQL/MariaDB database (`project`) with these main tables:

**Identity & Roles**
- **user** — Authentication, role, email, login timestamps
- **student**, **teacher**, **community_supervisor**, **super_admin** — Profile and department details

**Academic Structure**
- **courses** — Course catalog
- **course_offerings** — Session/semester/section-specific offerings
- **teacher_course_assignments** — Teacher-to-offering assignments
- **student_course_enrollments** — Student enrollments per offering

**Communications**
- **posts** — Community posts with scope, status, and expiry
- **post_reviews** — Supervisor approvals/rejections
- **notifications** — Teacher-to-student class notifications
- **messages** — Logged reply emails

**Compliance**
- **activity_logs** — Audit trail for logins, notifications, posts, and admin actions

## Configuration

### Database Setup

1. Create a MySQL database named `project`
2. Import the SQL schema from `DB/project.sql`
3. Update credentials in `config.php`:
   ```php
   $host = 'localhost';
   $dbname = 'project';
   $username = 'root';
   $password = '';
   ```

### Email Configuration

1. Copy `email_config.local.example.php` to `email_config.local.php`
2. Set SMTP credentials and sender details, or define environment variables used in `email_config.php`
3. Optionally configure:
   - `EMAIL_VALIDATE_STRICT` to enforce domain policy
   - `EMAIL_BLOCKED_DOMAINS` to block test domains

### Dependencies

Install required packages via Composer:
```bash
composer install
```

Packages:
- **PHPMailer** — SMTP email delivery
- **OpenSpout** — Excel/CSV/ODS import for student uploads

## User Roles and Workflows

### Student
**Entry Point:** `index.php` → `student/dashboard.php`

Students log in with roll number and password. The dashboard provides:
- Course notifications sent by assigned teachers
- Filters by course, date range, and message search
- Community posts approved for their department or all users
- Reply-to-teacher email workflow
- Profile and preference settings

### Teacher
**Entry Point:** `teacher/login.php` → `teacher/dashboard.php`

Teachers can:
- Send notifications to students enrolled in assigned course offerings
- Review and delete previously sent notification batches
- Create community posts with optional image attachments
- Manage profile settings

### Community Supervisor
**Entry Point:** `community_supervisor/login.php` → `community_supervisor/dashboard.php`

Supervisors:
- Review pending posts scoped to their department
- Approve or reject posts with an audit trail
- Track daily approval/rejection counts
- Manage profile settings

### Super Admin
**Entry Point:** `super_admin/login.php` → `super_admin/dashboard.php`

Admins handle:
- Bulk student import (Excel/CSV/ODS)
- Course catalog and offering management
- Teacher course assignments
- Student enrollments and re-enrollments (`super_admin/re_enroll.php`)
- Audit log reporting (`super_admin/audit_logs.php`)

## File Structure

```
project/
├── config.php                  # Database and password hashing utilities
├── audit_log.php               # Activity log helpers
├── email_config.php            # SMTP configuration loader
├── email_config.local.example.php
├── index.php                   # Student login
├── send_email.php              # Email sending utilities
├── logout.php                  # Session cleanup
├── assets/
│   └── images/must_logo.png
├── student/
│   ├── dashboard.php
│   ├── community.php
│   ├── settings.php
│   └── student_guard.php
├── teacher/
│   ├── login.php
│   ├── dashboard.php
│   ├── community.php
│   ├── settings.php
│   └── teacher_guard.php
├── community_supervisor/
│   ├── login.php
│   ├── dashboard.php
│   ├── settings.php
│   └── supervisor_guard.php
├── super_admin/
│   ├── login.php
│   ├── dashboard.php
│   ├── audit_logs.php
│   ├── re_enroll.php
│   └── settings.php
├── DB/
│   └── project.sql             # Database schema
├── composer.json
├── js-functions-overview.txt
├── test_db_debug.php
└── test_logging.php
```

## Key Workflows

### Community Post Moderation

1. Student/teacher submits a post with scope and expiration
2. Post enters `pending` status
3. Community supervisor reviews and approves/rejects
4. Approved posts appear in community feeds; expired posts are removed

### Course Notification Broadcast

1. Admin assigns teachers to course offerings and enrolls students
2. Teacher selects an assigned offering and sends a notification
3. Notifications are stored per student and surfaced in the student dashboard
4. Students can filter and reply to teachers via email

### Email Reply Flow

1. Student composes a reply from the dashboard
2. `sendReplyEmail()` sends mail via SMTP and logs to `messages`
3. Teachers receive the email and compliance records are retained

### Audit Log Review

1. `logActivity()` writes actions to `activity_logs`
2. Super admin filters logs by role, action, user, and date range

## Session Management

- **Student sessions:** user_id, role, student_id, roll_number, student_name
- **Teacher sessions:** user_id, role, teacher_id, teacher_name, teacher_department
- **Supervisor sessions:** user_id, role, supervisor_id, supervisor_name, supervisor_department
- **Admin sessions:** user_id, role, super_admin_email
- Guard files enforce role access for student/teacher/supervisor pages; admin pages validate the `super_admin` role directly.

## Security Features

**Password Security:**
- Argon2id hashing via `hashPasswordArgon2id()` / `verifyPasswordArgon2id()`

**SQL Injection Prevention:**
- Prepared statements with parameter binding throughout
- `prepareAndExecute()` utility for safe queries

**Session Validation:**
- Guard files verify user identity and role before page access

**Email Validation:**
- Strict validation with optional blocked domain list

**File Upload Validation:**
- Community post images are limited to JPG/PNG/GIF under 2MB

**Audit Trail:**
- Activity logs stored in `activity_logs` for compliance reporting

## Development Notes

### Creating Community Posts

Community posts are stored in `posts` with `scope`, `status`, and `expires_at`. New posts default to `pending` and are approved by community supervisors.

### Sending Course Notifications

Notifications are created in `notifications` with an `offering_id`. Ensure teachers are assigned to offerings and students are enrolled before sending.

### Modifying Email Templates

Edit the HTML layout in `student/dashboard.php` (reply template) or adjust `send_email.php` for shared formatting.

### Extending Roles

To add a new role:
1. Add a role value to the `user` table
2. Create a new directory with login, dashboard, guard, and settings pages
3. Add navigation links from `index.php` or the role switcher
4. Implement guard checks similar to existing role guards

## Troubleshooting

**"Connected successfully" message on every page:**
Remove or comment out the debug echo in `config.php` line 18:
```php
// echo "Connected successfully";
```

**Emails not sending:**
- Ensure `email_config.local.php` or SMTP environment variables are set
- Verify SMTP credentials and ports (587/465)
- Check `EMAIL_BLOCKED_DOMAINS` and `EMAIL_VALIDATE_STRICT`

**Posts not appearing:**
- Confirm the post is approved by a community supervisor
- Check `expires_at` is in the future
- Confirm scope matches the user’s department

**Notifications not appearing:**
- Verify teacher course assignments and student enrollments
- Check `offering_id` filters on the student dashboard

## Future Enhancements

- Real-time notifications via WebSocket
- Bulk notice import from CSV
- Advanced analytics dashboard for compliance reporting
- Two-factor authentication
- Mobile app
- Notice template library
- Automated compliance validation rules

## Support and Maintenance

For issues or feature requests, contact the development team. Ensure regular database backups and monitor server logs for errors.

## License

Internal use only.
