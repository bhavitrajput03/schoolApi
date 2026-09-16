# ET Teacher PHP API

Secure PHP 8 multi-school API. A central SQL Server database named `admineyetab` contains the `SchoolConnection` routing table; each school's users, classes, students, exams, marks and attendance remain in its own SQL Server database.

## Setup

1. Enable `pdo_sqlsrv` and `sqlsrv` for the selected PHP version.
2. Run `database/admin/001_admin_school_registry.sql` to create the central `admineyetab` registry.
3. Copy `.env.example` to `.env`; configure `ADMIN_DB_CONNECTION_STRING` for `admineyetab`.
4. Register each school SQL Server connection using `bin/register-school.php`.
5. For a fresh tenant run `database/001_api_security.sql` and `database/002_marks_unique.sql`. For an existing tenant run `database/003_teacher_subject_max_marks.sql` once; it safely renames only `ApiUser` to `SchoolTeacher`, creates `AppSubjectMaxMark`, and copies existing max-mark values. An unrelated `Teacher` table is never changed.
6. Create a teacher login:

    php bin/create-user.php B anita "Anita Sharma" "StrongPassword@123" teacher 218

7. Assign only authorized class/section/subjects. Current academic session is 13:

    php bin/assign-teacher.php B anita 13 155 61 40
    php bin/assign-teacher.php B anita 13 155 61 41

8. Enable Apache mod_rewrite and test POST http://localhost/school/public/api/auth/login.

## Parent API

Run `database/004_parent_app.sql` in each tenant database, verify it with `database/005_parent_legacy_preflight.sql`, then create/link a parent with `bin/create-parent.php` and `bin/link-parent-student.php`. Class teachers can be mapped with `bin/assign-class-teacher.php`. The same `/api/auth/login` endpoint automatically selects teacher login for a `username` body and parent login for a `parentCode`/`userId` body. All 42 parent routes and deployment notes are documented in `docs/parent-api.md`; an importable collection is in `postman/ET-Parent-API.postman_collection.json`.

All request examples are in docs/curl-examples.md.

## Security

- Argon2id password hashes. Legacy plain-text values found in `SchoolTeacher.PasswordHash` can authenticate once and are immediately upgraded to a secure hash; plain text is never written by the API.
- Random tenant-routed opaque bearer tokens; tenant SQL databases store only SHA-256 token hashes.
- Each `admineyetab.dbo.SchoolConnection` row stores one school's full SQL connection string. Restrict this table and its SQL login because the connection string contains credentials.
- Expiring/revocable sessions; password changes revoke other sessions.
- Login throttling, prepared SQL, validation and teacher-assignment authorization.
- Transaction-safe batch marks and attendance.
- Allowlisted CORS, no-store responses, hidden server errors and disabled directory listing.
- Production must use HTTPS and a least-privilege SQL login.
