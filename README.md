# E-Notice and Communication Compliance System

A comprehensive web-based platform for managing educational notices, communications, and compliance across multiple user roles in an academic institution.

## Overview

This system enables efficient notification distribution and communication tracking across students, teachers, community supervisors, and administrators. It enforces compliance requirements while maintaining a streamlined user experience for each role.

**Key Features:**
- Multi-role authentication (students, teachers, supervisors, administrators)
- Scope-based notice distribution (all, department, specific individuals)
- Email notification system with compliance tracking
- Community engagement through discussion boards
- User settings and profile management
- Database-driven architecture with role-based access control

## System Architecture

The application follows a role-based access model where users authenticate and access role-specific dashboards. Each role has dedicated pages and permissions.

**Core Components:**

1. **Authentication Layer** — Central login page (`index.php`) validates credentials against the user database. Passwords use Argon2id hashing for security. Role determination happens at login and session establishment.

2. **Role-Specific Modules** — Four main user paths:
   - **Student** — View assigned notices, join communities, reply to teachers
   - **Teacher** — Post notices to departments/classes, manage community discussions
   - **Community Supervisor** — Post campus-wide notices, supervise student engagement
   - **Super Admin** — System-wide administration, user enrollment, compliance oversight

3. **Notice System** — Posts created by authorized users (teachers, supervisors, admins) with scope control (all users, specific department, or targeted list). Pending approval before publication.

4. **Email Integration** — Uses PHPMailer library for sending notifications. Stores message records in the database for compliance audit trails.

5. **Access Guards** — Each role-specific directory includes a guard file (`*_guard.php`) that validates session identity before allowing page access. Prevents unauthorized cross-role navigation.

## Database Schema

The system uses a MySQL database (`project`) with the following main tables:

**Users Table:**
- Core authentication and role assignment
- Stores hashed passwords (Argon2id)
- Tracks login times

**Student Table:**
- Roll number, name, department, session
- Foreign key to user table

**Teacher Table:**
- Name, department assignment
- Foreign key to user table

**Community Supervisor Table:**
- Name, department responsibility

**Posts Table:**
- Notice content, images, creation timestamp
- Expiration date for automatic removal
- Scope: 'all', 'department', or targeted
- Status: 'pending', 'approved', 'rejected'

**Messages Table:**
- Sender/recipient IDs, email addresses
- Subject and body content
- Sent timestamp and delivery status

**Notifications Table:**
- Sender/recipient IDs and message content
- Timestamp and read status
- For in-app notification tracking

**Queries Table:**
- Student-teacher communication requests
- Status tracking (pending, resolved)

## Configuration

### Database Setup

1. Create a MySQL database named `project`
2. Import the SQL schema from `DB/project (1).sql`
3. Update credentials in `config.php`:
   ```
   $host = 'localhost';
   $dbname = 'project';
   $username = 'root';
   $password = '';
   ```

### Email Configuration

Edit `email_config.php` with your SMTP settings:
- Server address and port
- Sender email and credentials
- Authentication method

### Dependencies

Install required packages via Composer:
```bash
composer install
```

Packages:
- **PHPMailer** — Email delivery with SMTP/POP3 support
- **OpenSpout** — Spreadsheet reading/writing (for data exports)

## User Roles and Workflows

### Student
**Entry Point:** `student/dashboard.php`

Students log in with roll number and password. The dashboard displays:
- All active notices (department-wide and campus-wide)
- Assigned community discussions
- Reply interface for contacting teachers
- Profile settings and preferences

**Workflow:**
1. Login via main page
2. View incoming notices
3. Respond to teacher messages via dashboard
4. Join community discussions
5. Update personal settings

### Teacher
**Entry Point:** `teacher/dashboard.php`

Teachers can create and manage department-specific notices and community posts.

**Workflow:**
1. Authenticate with credentials
2. Create new notice (content, expiration, scope: department or all)
3. Participate in community discussions
4. Receive student replies via email
5. Manage settings and profile

### Community Supervisor
**Entry Point:** `community_supervisor/dashboard.php`

Supervisors manage campus-wide communications and approve content.

**Workflow:**
1. Login to supervisor dashboard
2. Post campus-wide notices
3. Oversee community engagement
4. Approve or reject pending posts
5. Track compliance metrics

### Super Admin
**Entry Point:** `super_admin/dashboard.php`

Full system control including user enrollment, compliance oversight, and system settings.

**Workflow:**
1. Access admin dashboard
2. Enroll new students/teachers/supervisors
3. Review system-wide compliance reports
4. Manage expiring notices
5. Configure system settings

## File Structure

```
project/
├── config.php                  # Database and password hashing functions
├── email_config.php           # SMTP configuration
├── index.php                  # Main login page
├── send_email.php             # Email sending utilities
├── logout.php                 # Session cleanup
│
├── student/
│   ├── dashboard.php          # Student notice view and reply interface
│   ├── community.php          # Community discussion board
│   ├── settings.php           # Profile and preferences
│   └── student_guard.php      # Session validation
│
├── teacher/
│   ├── login.php              # Teacher authentication
│   ├── dashboard.php          # Notice creation and management
│   ├── community.php          # Community discussion moderation
│   ├── settings.php           # Profile settings
│   └── teacher_guard.php      # Session validation
│
├── community_supervisor/
│   ├── login.php              # Supervisor authentication
│   ├── dashboard.php          # Supervisor oversight
│   ├── settings.php           # Supervisor profile
│   └── supervisor_guard.php   # Session validation
│
├── super_admin/
│   ├── login.php              # Admin authentication
│   ├── dashboard.php          # System administration
│   ├── re_enroll.php          # User enrollment
│   ├── settings.php           # System settings
│   └── admin_guard.php        # (implicit, admin_dashboard validates role)
│
├── DB/
│   └── project (1).sql        # Complete database schema
│
├── composer.json              # PHP dependencies
├── vendor/                    # Installed packages (PHPMailer, OpenSpout)
```

## Key Workflows

### Notice Creation and Distribution

1. Authorized user (teacher/supervisor/admin) accesses dashboard
2. Clicks "Create Notice"
3. Enters content, selects scope (all/department/targeted)
4. Optionally uploads image attachment
5. Sets expiration date
6. Submits for approval

**For Pending Approval:**
- Super admin reviews post in moderation queue
- Approves or rejects with optional feedback
- Approved posts become visible to target audience
- Email notification sent to creator

### Email Reply Flow

1. Student receives email notification about a notice
2. Can reply directly via dashboard
3. System sends email to teacher/original sender
4. Conversation logged in messages table for compliance

### Session Management

- Login sets session variables: user_id, roll_number (student), student_id, student_name
- Guard files on each role page validate session before rendering
- Automatic timeout handled by PHP session settings
- Logout (`logout.php`) clears session and redirects to login

## Security Features

**Password Security:**
- Argon2id hashing with verification function `verifyPasswordArgon2id()`
- One-way hash — password never stored in plaintext
- Constant-time comparison prevents timing attacks

**SQL Injection Prevention:**
- Prepared statements with parameter binding throughout
- `prepareAndExecute()` utility for safe queries

**Session Validation:**
- Guard files verify user identity and role before page access
- Session hijacking prevention via secure cookie settings

**Data Access Control:**
- Role-based restrictions on notice scopes
- Students can only see notices meant for their department or all users
- Teachers cannot modify other teachers' posts
- Admins have full visibility

## Development Notes

### Adding a New Notice

Edit the relevant dashboard file (e.g., `teacher/dashboard.php`):
1. Create a form to collect content, scope, expiration
2. Validate input server-side
3. Prepare a statement to insert into `posts` table
4. Set status to 'pending' if approval required
5. Trigger email notification to admin if needed

### Modifying Email Templates

Edit `send_email.php`. All emails use HTML format with consistent styling. Example structure:
```php
$emailBody = "
    <div style='font-family: Arial, sans-serif; padding: 20px;'>
        <h3>" . htmlspecialchars($subject) . "</h3>
        <p>" . nl2br(htmlspecialchars($message)) . "</p>
    </div>
";
```

### Extending Roles

To add a new user role:
1. Add role value to `user` table (e.g., 'moderator')
2. Create new directory with login, dashboard, guard, and settings pages
3. Update `index.php` to route to new role's dashboard after login
4. Implement guard file with `require*Identity()` function matching pattern in existing guards

## Troubleshooting

**"Connected successfully" message on every page:**
Remove or comment out the debug echo in `config.php` line 18:
```php
// echo "Connected successfully";
```

**Emails not sending:**
- Verify SMTP credentials in `email_config.php`
- Check server firewall allows outbound SMTP (port 587 or 465)
- Review PHPMailer exception messages for detailed errors

**Posts not appearing after creation:**
- Verify status is 'approved' (not 'pending') in posts table
- Check scope matches user's department
- Confirm expiration date is in future

**Session timeout issues:**
- Adjust `session.gc_maxlifetime` in php.ini (default 1440 seconds = 24 minutes)
- Implement JavaScript timer to warn user before logout

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
