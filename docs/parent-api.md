# ET Parent API

Base URL: `https://api.eyetab.in`. Parent-protected routes require `Authorization: Bearer <accessToken>`. The login `schoolCode` selects the tenant through `admineyetab.dbo.SchoolConnection`; clients never send a database name or `OwnerSessionID`.

## Installation

Run `database/003_teacher_subject_max_marks.sql` first if the tenant does not already contain `SchoolTeacher`, then run `database/004_parent_app.sql` once in every tenant database as `sa`/dbo. Finally run the read-only `database/005_parent_legacy_preflight.sql`; every required item should show `OK`. Existing ERP tables remain the source for students, sessions, attendance, fees, receipts, teachers, subjects, and school information. The migration adds only app-specific authentication, linking, class-teacher mapping, homework, notices, notification state, devices, payments, and receipt-PDF metadata.

Create a parent and link a child:

```bash
php8.2 bin/create-parent.php B PARENT007 "Rajneesh Shahi" "Strong@Password123" 9758780185
php8.2 bin/link-parent-student.php B PARENT007 14687 Father 1
php8.2 bin/assign-class-teacher.php B anita 13 155 61 class_teacher
```

## Authentication flow

- `POST /api/auth/login`: parent body uses `schoolCode`, `parentCode` (or `userId`), and `password`. Teacher login remains compatible through its existing `username` body.
- `POST /api/auth/refresh-token`: body contains `refreshToken`; refresh tokens rotate on every use.
- `POST /api/auth/logout`: revokes the current teacher or parent token automatically.

Example parent login:

```json
{"schoolCode":"B","parentCode":"PARENT007","password":"Strong@Password123"}
```

The response includes `parent`, the date-selected `academicSession`, and `auth.accessToken`/`auth.refreshToken`.

School code, teacher username, parent code/user ID, and login passwords are matched case-insensitively. New and changed passwords are stored as hashes of their normalized lowercase form. An existing mixed-case password hash must be entered with its original case once; that successful login upgrades it for future case-insensitive use.

## Parent and student

- `GET /api/parent/profile`
- `GET /api/parent/students`
- `GET /api/students/{studentId}`
- `GET /api/students/{studentId}/dashboard`

Every student endpoint verifies `AppParentStudent` ownership and current `StudentSession` membership.

## Attendance

- `GET /api/students/{studentId}/attendance/summary?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/students/{studentId}/attendance?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/students/{studentId}/attendance/calendar?month=YYYY-MM`

Attendance reads existing `AttItem.AttType` (`P`, `A`, `H/2`, `Holiday`).

## Homework and notices

- `GET /api/students/{studentId}/homework?subjectId={id}`
- `GET /api/homework/{homeworkId}`
- `GET /api/students/{studentId}/homework/subjects`
- `GET /api/students/{studentId}/notices`
- `GET /api/notices/{noticeId}`
- `POST /api/notices/{noticeId}/read`

## Teachers

- `GET /api/students/{studentId}/class-teachers`
- `GET /api/students/{studentId}/subject-teachers`
- `GET /api/teachers/{teacherId}`

`{teacherId}` accepts `SchoolTeacher.ApiUserID` whether that tenant stores it as an integer/bigint or UUID. Responses include `teacherId`, `apiUserId`, and `employeeId`.

Subject-teacher visibility comes from current `ApiTeacherAssignment` rows. Class/co-class teacher mapping comes from `AppClassTeacher`; both use `SchoolTeacher` identities.

## Fees, payments, and receipts

- `GET /api/students/{studentId}/fees/summary`
- `GET /api/students/{studentId}/fees`
- `GET /api/students/{studentId}/fees/{feeId}`
- `GET /api/students/{studentId}/fee-account`
- `POST /api/payments/create-order`
- `POST /api/payments/verify`
- `POST /api/payments/webhook` (requires `schoolCode` and provider signature)
- `GET /api/payments/{paymentId}/status`
- `GET /api/students/{studentId}/payments`
- `GET /api/students/{studentId}/receipts`
- `GET /api/receipts/{receiptId}`
- `GET /api/receipts/{receiptId}/download`

Fee reads use legacy `StudentFees`, `StudentFeesItem`, and `FeeReceipt`. `create-order` records a local order only after `PAYMENT_PROVIDER` is configured. A real gateway order call and provider-specific signature format must be connected using the chosen provider credentials before collecting live money. Receipt downloads are restricted to files beneath `storage/receipts`.

## Notifications and devices

- `GET /api/notifications`
- `GET /api/notifications/unread-count`
- `POST /api/notifications/{notificationId}/read`
- `POST /api/notifications/read-all`
- `POST /api/devices/register`
- `DELETE /api/devices/{deviceId}`

Device registration body:

```json
{"deviceId":"android-device-key","fcmToken":"token","platform":"android","appVersion":"1.0.0"}
```

## School and settings

- `GET /api/school`
- `GET /api/school/academic-session`
- `GET /api/app/config` (public)
- `GET /api/settings`
- `PUT /api/settings`

Current academic session is selected automatically where today is between `OwnerSession.StartDate` and `OwnerSession.EndDate`.
