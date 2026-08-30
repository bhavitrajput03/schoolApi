# ET Teacher Flutter App — Development Prompt

Build a complete, production-ready Flutter mobile application named **ET Teacher** for Android and iOS using the REST API described below.

## Technical requirements

- Use current stable Flutter and Dart with null safety.
- Use a clean feature-first architecture with presentation, domain, and data layers.
- Use `dio` for HTTP, `flutter_riverpod` for state management, `go_router` for navigation, `freezed`/`json_serializable` for immutable API models, and `flutter_secure_storage` for the access token.
- Base URL: `https://api.eyetab.in`
- Do not append `/public` to the production URL.
- Send `Accept: application/json` on every request.
- Send `Content-Type: application/json` on POST requests.
- For protected endpoints send `Authorization: Bearer <accessToken>`.
- Never hardcode a username, password, or access token.
- Add a Dio interceptor that attaches the stored token and handles `401 UNAUTHENTICATED` and `401 INVALID_TOKEN` by clearing the session and returning to Login.
- Parse every response through the standard envelope below. Show safe user-facing messages and keep technical details in debug logs only.
- Add loading, empty, validation, offline, retry, and API-error states to every screen.
- Prevent duplicate submissions and ask for confirmation before saving marks or attendance.
- Write unit tests for JSON models and repositories, plus widget tests for Login, Marks Entry, and Attendance.

## Standard response envelope

Successful response:

```json
{
  "success": true,
  "data": {}
}
```

Error response:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed.",
    "details": {
      "missing": ["password"]
    }
  }
}
```

Support HTTP statuses `200`, `204`, `401`, `403`, `404`, `422`, `429`, and `500`.

## Authentication APIs

### Login

`POST /api/auth/login`

Request:

```json
{
  "schoolCode": "B",
  "username": "anita",
  "password": "USER_ENTERED_PASSWORD"
}
```

Example response:

```json
{
  "success": true,
  "data": {
    "user": {
      "ApiUserID": "uuid",
      "EmployeeID": 218,
      "SchoolBranchID": 21,
      "DisplayName": "Anita Sharma",
      "Username": "anita",
      "Role": "teacher"
    },
    "auth": {
      "accessToken": "opaque-token",
      "tokenType": "Bearer",
      "expiresIn": 604800
    }
  }
}
```

Store only `accessToken` in secure storage. Do not log it.

### Current user

`GET /api/auth/me`

Example `data`:

```json
{
  "ApiUserID": "uuid",
  "EmployeeID": 218,
  "SchoolBranchID": 21,
  "DisplayName": "Anita Sharma",
  "Username": "anita",
  "Role": "teacher"
}
```

### Logout

`POST /api/auth/logout`

Example `data`:

```json
{
  "message": "Logged out."
}
```

Always clear secure storage after a successful logout.

### Change password

`POST /api/auth/change-password`

Request:

```json
{
  "currentPassword": "CURRENT_PASSWORD",
  "newPassword": "NEW_STRONG_PASSWORD"
}
```

The new password must contain at least 12 characters with uppercase, lowercase, number, and symbol.

Example `data`:

```json
{
  "message": "Password changed. Other sessions were signed out."
}
```

## Dashboard and teacher assignment APIs

### Dashboard

`GET /api/teacher/dashboard`

Example `data`:

```json
{
  "teacher": {
    "id": "uuid",
    "name": "Anita Sharma",
    "role": "teacher"
  },
  "academicSessionId": 13,
  "assignedClassSections": 1
}
```

### Assigned classes

`GET /api/teacher/classes`

Example `data`:

```json
[
  {
    "id": 155,
    "name": "VII",
    "sortOrder": 7
  }
]
```

### Assigned sections

`GET /api/teacher/classes/{classId}/sections`

Example `data`:

```json
[
  {
    "id": 61,
    "name": "A"
  }
]
```

### Assigned subjects

`GET /api/teacher/classes/{classId}/sections/{sectionId}/subjects`

Example `data`:

```json
[
  {
    "id": 40,
    "name": "Mathematics",
    "abbreviation": "MATH"
  },
  {
    "id": 41,
    "name": "Science",
    "abbreviation": "SCI"
  }
]
```

Treat names above as examples; display the actual API values.

### Class/section student roster

`GET /api/teacher/classes/{classId}/sections/{sectionId}/students`

Example `data`:

```json
{
  "students": [
    {
      "studentId": 14687,
      "name": "Aarav Kumar",
      "admissionNo": "1021",
      "rollNumber": "1",
      "fatherName": "Rajesh Kumar"
    }
  ]
}
```

Use this endpoint for student pickers. Do not use the attendance endpoint as a roster workaround.

## Exam terms API

`GET /api/exams/terms`

Example `data`:

```json
[
  {
    "id": 1,
    "name": "Unit Test",
    "optionId": 39,
    "optionName": "Unit Test 1",
    "weightage": 10
  }
]
```

Use `optionId` as `termOptionId` in marks APIs.

## Subject-wise marks APIs

### Load configured maximum marks

`GET /api/marks/max-marks?classId={classId}&sectionId={sectionId}&termOptionId={termOptionId}`

Example `data`:

```json
{
  "subjects": [
    {"subjectId": 40, "subjectName": "Mathematics", "maxMarks": 80},
    {"subjectId": 41, "subjectName": "Science", "maxMarks": 80}
  ]
}
```

Subjects without configuration are omitted; the client may display 80 as its unsaved default.

### Save configured maximum marks

`POST /api/marks/max-marks`

```json
{
  "classId": 155,
  "sectionId": 61,
  "termOptionId": 38,
  "subjects": [
    {"subjectId": 40, "maxMarks": 80},
    {"subjectId": 41, "maxMarks": 80}
  ]
}
```

Example `data`:

```json
{"updated": 2}
```

Each `maxMarks` must be an integer from 1 through 1000. Once configured, this value is authoritative in subject-wise and student-wise marks GET/save flows.

### Load students and existing marks

`GET /api/marks/subject-wise/students?classId={classId}&sectionId={sectionId}&termOptionId={termOptionId}&subjectId={subjectId}`

Example `data`:

```json
[
  {
    "id": 14687,
    "name": "Aarav Kumar",
    "admissionNo": "1021",
    "rollNo": "1",
    "fatherName": "Rajesh Kumar",
    "marksEntryId": 1050,
    "termOptionId": 31,
    "subSubjectId": 102,
    "marksStudentId": 14687,
    "maxMarks": 80,
    "minMarks": 0,
    "obtainedMarks": 72,
    "percentage": 90,
    "isAbsent": false,
    "isMedical": false,
    "serial": null,
    "ownerSessionId": 13,
    "isDefault": false
  }
]
```

Marks fields may be `null` when marks have not been entered. When `isAbsent` or `isMedical` is true, disable obtained marks input and send `obtainedMarks: null` or omit it.

### Save subject-wise marks

`POST /api/marks/subject-wise`

Request:

```json
{
  "classId": 155,
  "sectionId": 61,
  "termOptionId": 39,
  "subjectId": 40,
  "maxMarks": 80,
  "marks": [
    {
      "studentId": 14687,
      "obtainedMarks": 72,
      "isAbsent": false,
      "isMedical": false
    }
  ]
}
```

Example `data`:

```json
{
  "saved": 1,
  "classId": 155,
  "sectionId": 61,
  "termOptionId": 39,
  "marks": [
    {
      "studentId": 14687,
      "subjectId": 40,
      "maxMarks": 80,
      "obtainedMarks": 72,
      "percentage": 90,
      "isAbsent": false,
      "isMedical": false
    }
  ]
}
```

Validate obtained marks between `0` and `maxMarks`. Absent and medical cannot both be true.

## Student-wise marks APIs

### Load one student's subjects and marks

`GET /api/marks/student-wise/{studentId}?termOptionId={termOptionId}`

Example `data`:

```json
{
  "studentId": 14687,
  "classId": 155,
  "sectionId": 61,
  "marks": [
    {
      "subjectId": 40,
      "subjectName": "Mathematics",
      "marksEntryId": 1050,
      "termOptionId": 31,
      "subSubjectId": 102,
      "studentId": 14687,
      "maxMarks": 80,
      "minMarks": 0,
      "obtainedMarks": 72,
      "percentage": 90,
      "isAbsent": false,
      "isMedical": false,
      "serial": null,
      "ownerSessionId": 13,
      "isDefault": false
    }
  ]
}
```

### Save student-wise marks

`POST /api/marks/student-wise`

Request:

```json
{
  "studentId": 14687,
  "termOptionId": 39,
  "marks": [
    {
      "subjectId": 40,
      "maxMarks": 80,
      "obtainedMarks": 72,
      "isAbsent": false,
      "isMedical": false
    },
    {
      "subjectId": 41,
      "maxMarks": 80,
      "obtainedMarks": 68,
      "isAbsent": false,
      "isMedical": false
    }
  ]
}
```

Example `data`:

```json
{
  "saved": 2,
  "classId": 155,
  "sectionId": 61,
  "termOptionId": 39,
  "marks": [
    {
      "studentId": 14687,
      "subjectId": 40,
      "maxMarks": 80,
      "obtainedMarks": 72,
      "percentage": 90,
      "isAbsent": false,
      "isMedical": false
    },
    {
      "studentId": 14687,
      "subjectId": 41,
      "maxMarks": 80,
      "obtainedMarks": 68,
      "percentage": 85,
      "isAbsent": false,
      "isMedical": false
    }
  ]
}
```

## Attendance APIs

### Load attendance students

`GET /api/attendance/students?classId={classId}&sectionId={sectionId}&date=YYYY-MM-DD`

Example `data`:

```json
{
  "date": "2026-08-28",
  "students": [
    {
      "id": 14687,
      "name": "Aarav Kumar",
      "admissionNo": "1021",
      "rollNo": "1",
      "fatherName": "Rajesh Kumar",
      "status": "P"
    }
  ]
}
```

Supported statuses are `P`, `A`, `H/2`, and `Holiday`.

### Save attendance

`POST /api/attendance`

Request:

```json
{
  "classId": 155,
  "sectionId": 61,
  "date": "2026-08-28",
  "attendance": [
    {
      "studentId": 14687,
      "status": "P"
    }
  ]
}
```

Example `data`:

```json
{
  "saved": 1,
  "date": "2026-08-28"
}
```

## Required screens and flow

1. Splash/session-check screen.
2. Login screen with school code, username, password, validation, password visibility toggle, and secure-school-access copy.
3. Teacher dashboard showing teacher name, current session, assigned class/section count, Marks Entry, Attendance, and Account actions.
4. Marks Entry filters: class -> section -> term -> subject. Reset downstream selections whenever a parent selection changes.
5. Subject-wise marks screen: student list, father/admission/roll information, max marks, obtained marks, absent, medical, save confirmation, and saved count.
6. Student-wise marks screen: select student and term, list assigned subjects, edit/save marks.
7. Attendance screen: class, section, date, totals for present/absent/half-day/holiday, per-student status control, bulk mark-present action, and submit confirmation.
8. Account screen: user profile, change password, logout confirmation.

## UX and implementation rules

- Match a clean school app design using navy, white, light grey, and orange accents.
- Preserve selections while navigating within a feature, but refresh server data after a successful save.
- Use API IDs internally and names only for display.
- Disable save until the form is valid and changed.
- Show a clear warning that marks and attendance saves modify live school records.
- Do not invent school name, class-teacher title, student names, or marks. Render only values returned by the API.
- The API currently returns teacher identity and assigned data, but it does not return a school display name or a `Class Teacher` label. Omit those values unless a future API supplies them.
- Generate all source files, model classes, repositories, providers, routes, theme, reusable widgets, tests, and a README containing setup/build instructions.
