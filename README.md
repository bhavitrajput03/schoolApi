# ET Teacher PHP API

Secure PHP 8 API for the restored tmpgolden SQL Server database. Legacy tables remain the source for classes, students, exams, marks and attendance. API credentials and tokens use isolated modern tables.

## Setup

1. Enable Microsoft pdo_sqlsrv and sqlsrv extensions for the PHP version selected in WAMP.
2. Copy .env.example to .env. Add SQL credentials if Windows Authentication is unavailable to Apache.
3. Run database/001_api_security.sql in SSMS against tmpgolden.
4. Create a teacher login:

    php bin/create-user.php B anita "Anita Sharma" "StrongPassword@123" teacher 218

5. Assign only authorized class/section/subjects. Current academic session is 13:

    php bin/assign-teacher.php anita 13 155 61 40
    php bin/assign-teacher.php anita 13 155 61 41

6. Enable Apache mod_rewrite and test POST http://localhost/school/public/api/auth/login.

All request examples are in docs/curl-examples.md.

## Security

- Argon2id password hashes; legacy UserName.UserPwd is intentionally not used.
- Random opaque bearer tokens; the database stores only SHA-256 token hashes.
- Expiring/revocable sessions; password changes revoke other sessions.
- Login throttling, prepared SQL, validation and teacher-assignment authorization.
- Transaction-safe batch marks and attendance.
- Allowlisted CORS, no-store responses, hidden server errors and disabled directory listing.
- Production must use HTTPS and a least-privilege SQL login.

