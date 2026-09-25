# E-Notice and Communication Compliance System

A PHP-based academic communication platform designed for universities and institutions that need role-based notice distribution, approval workflows, email communication, and auditing.

## Overview

The system supports four main roles:
- Student
- Teacher
- Community Supervisor
- Super Admin

Each role has a distinct login flow and dashboard, with protected pages enforced by session guards. The project centralizes communication, compliance tracking, and academic record management in one web application.

## Core Features

- Student login by roll number and staff login by email
- Role-based access control through session checks and guard files
- Course notification delivery to enrolled students
- Community post creation with department or all-user visibility
- Supervisor review and moderation workflow for pending posts
- Email reply flow between students and teachers
- Bulk student import from Excel, CSV, or ODS files
- Admin tools for course, offering, and enrollment management
- Audit trail for login and system activity logging
- Argon2id password hashing and prepared-statement database queries

## Technology Stack

- PHP 8+
- MySQL / MariaDB via MySQLi
- Tailwind CSS for the UI
- PHPMailer for SMTP delivery
- OpenSpout for spreadsheet imports
- Composer for package management

## Project Structure

```text
project/
├── .gitignore
├── .htaccess
├── audit_log.php
├── composer.json
├── composer.lock
├── config.php
├── email_config.php
├── email_config.local.example.php
├── email_config.local.php
├── index.php
├── logout.php
├── send_email.php
├── js-functions-overview.txt
├── assets/
│   └── images/
│       └── must_logo.png
├── community_supervisor/
│   ├── dashboard.php
│   ├── login.php
│   ├── settings.php
│   └── supervisor_guard.php
├── student/
│   ├── community.php
│   ├── dashboard.php
│   ├── settings.php
│   └── student_guard.php
├── super_admin/
│   ├── audit_logs.php
│   ├── dashboard.php
│   ├── login.php
│   ├── re_enroll.php
│   ├── settings.php
│   └── super_admin_guard.php
├── teacher/
│   ├── community.php
│   ├── dashboard.php
│   ├── login.php
│   ├── settings.php
│   └── teacher_guard.php
├── vendor/
│   └── ...
├── Diagram/
└── README.md
```

The brag-related folders are intentionally not included in the project documentation or source listing.

## User Roles and Workflows

### Student
Entry point: index.php -> student/dashboard.php

Students can:
- log in with their roll number
- view notifications sent by teachers
- filter notifications by offering, date, or text
- read approved community posts
- reply by email to a teacher
- manage personal settings

### Teacher
Entry point: teacher/login.php -> teacher/dashboard.php

Teachers can:
- log in with their email address
- send notifications to students in assigned offerings
- create community posts with optional images
- review existing communication activity
- manage profile settings

### Community Supervisor
Entry point: community_supervisor/login.php -> community_supervisor/dashboard.php

Supervisors can:
- review pending posts for their department
- approve or reject submissions
- maintain moderation records for compliance
- manage personal settings

### Super Admin
Entry point: super_admin/login.php -> super_admin/dashboard.php

Admins can:
- import students through spreadsheet files
- manage courses, sections, and offerings
- assign teachers to course offerings
- enroll and re-enroll students
- review audit logs and system activity

## Authentication and Security

The application uses MySQLi prepared statements throughout the project to reduce SQL injection risk. Passwords are stored and validated using Argon2id hashing through helper functions in config.php.

Key security measures include:
- session-based role checks
- guard files for protected pages
- server-side validation for inputs and files
- email format validation for communication workflows
- audit logging for user activity and administrative actions

## Email and Communication Flow

The system supports a direct student-to-teacher email reply flow:
1. A student selects a teacher and enters a message.
2. The app validates the teacher email and sender data.
3. PHPMailer sends the message through the configured SMTP settings.
4. The message is logged in the database for tracking and audit review.

Email configuration is managed through:
- email_config.php
- email_config.local.php
- email_config.local.example.php

## Student Import System

The admin dashboard includes a spreadsheet import workflow for bulk student registration. Supported file types are:
- .xlsx
- .csv
- .ods

The import process validates:
- email format
- required fields
- duplicate email and roll number values
- department scope restrictions
- semester number range
- section format

## Audit Logging

Activity is written through audit_log.php and stored in the database activity log tables. The admin dashboard exposes the log review screens for actions such as:
- logins
- notifications
- community moderation
- student imports
- administrative changes

## Configuration

### Database

Update the connection settings in config.php before running the app:

```php
$host = 'localhost';
$dbname = 'project';
$username = 'root';
$password = '';
```

The application expects a MySQL database named project with the required academic and communication tables.

### Email

Copy email_config.local.example.php to email_config.local.php and configure your SMTP credentials.

### Dependencies

Install composer packages with:

```bash
composer install
```

Required packages:
- phpmailer/phpmailer
- openspout/openspout

## Usage Notes

- Student login is handled from the main index.php page.
- Staff portals use separate login pages under their role folders.
- Access is restricted by role-specific guards and session checks.
- Global functions for hashing, database execution, and auditing are centralized in config.php and audit_log.php.

## Development Notes

This project is a custom PHP web system rather than a framework-based app. Most logic is organized by role folder, with shared utilities kept at the project root. For design changes, the main front-end styling is handled with Tailwind utility classes embedded in each page.

## Future Maintenance

When updating the system, keep these areas aligned:
- role/session logic in the guard files
- database queries and schema assumptions in config.php and dashboard pages
- SMTP settings in the email configuration files
- import validation rules in super_admin/dashboard.php
- audit log behavior in audit_log.php

This project is intended for academic administration workflows and compliance tracking, with the current implementation focused on notice delivery, moderation, enrollment management, and activity logging.

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
