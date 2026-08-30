# ET Teacher API — Complete SQL Query Reference

The central SQL Server database `admineyetab` first resolves `schoolCode` through `dbo.SchoolConnection`; the API then uses the returned full connection string to run these parameterized queries against that school's configured SQL Server database (for example `SchoolManagement`). Placeholders below are named only for readability.

## Shared response format

```json
{ "success": true, "data": {} }
```

```json
{ "success": false, "error": { "code": "ERROR_CODE", "message": "Message", "details": {} } }
```

## Queries shared by protected endpoints

Bearer-token authentication:

```sql
SELECT u.ApiUserID,u.EmployeeID,u.SchoolBranchID,u.DisplayName,u.Username,u.Role
FROM dbo.ApiAuthToken t
JOIN dbo.SchoolTeacher u ON u.ApiUserID=t.ApiUserID
WHERE t.TokenHash=@TokenHash
  AND t.RevokedAt IS NULL
  AND t.ExpiresAt>SYSUTCDATETIME()
  AND u.IsActive=1;

UPDATE dbo.ApiAuthToken
SET LastUsedAt=SYSUTCDATETIME()
WHERE TokenHash=@TokenHash;
```

Current academic session:

```sql
SELECT TOP 1 OwnerSessionID,SessionName,StartDate,EndDate,SessionID
FROM dbo.OwnerSession
WHERE CAST(GETDATE() AS date)
      BETWEEN CAST(StartDate AS date) AND CAST(EndDate AS date)
ORDER BY StartDate DESC,OwnerSessionID DESC;
```

There is deliberately no fallback to an expired or future session. Login returns this current academic-session object, and all subsequent classes, marks, and attendance endpoints resolve and use the same current `OwnerSessionID` automatically. Clients do not send `ownerSessionId`.

Teacher assignment authorization:

```sql
SELECT COUNT(*)
FROM dbo.ApiTeacherAssignment
WHERE ApiUserID=@ApiUserID
  AND ClassID=@ClassID
  AND SectionID=@SectionID
  AND OwnerSessionID=@OwnerSessionID
  AND IsActive=1
  AND SubjectID=@SubjectID; -- included only for subject-specific APIs
```

## Authentication APIs

### POST `/api/auth/login`

```sql
SELECT COUNT(*) FROM dbo.ApiLoginAttempt
WHERE AttemptKey=@AttemptKey
  AND AttemptedAt>DATEADD(MINUTE,-15,SYSUTCDATETIME());

SELECT u.ApiUserID,u.PasswordHash,u.DisplayName,u.Username,u.Role,
       u.EmployeeID,u.SchoolBranchID
FROM dbo.SchoolTeacher u
JOIN dbo.ApiSchool s ON s.SchoolBranchID=u.SchoolBranchID
WHERE s.SchoolCode=@SchoolCode
  AND LOWER(u.Username)=@Username
  AND u.IsActive=1;
```

Invalid login:

```sql
INSERT INTO dbo.ApiLoginAttempt(AttemptKey,AttemptedAt)
VALUES(@AttemptKey,SYSUTCDATETIME());
```

Successful login:

```sql
DELETE FROM dbo.ApiLoginAttempt WHERE AttemptKey=@AttemptKey;
UPDATE dbo.SchoolTeacher SET LastLoginAt=SYSUTCDATETIME() WHERE ApiUserID=@ApiUserID;

INSERT INTO dbo.ApiAuthToken(ApiUserID,TokenHash,ExpiresAt,IpAddress,UserAgent)
VALUES(@ApiUserID,@TokenHash,
       DATEADD(MINUTE,CAST(@TtlMinutes AS int),SYSUTCDATETIME()),
       @IpAddress,@UserAgent);
```

### GET `/api/auth/me`

Uses the shared token query. Returned fields are `ApiUserID`, `EmployeeID`, `SchoolBranchID`, `DisplayName`, `Username`, and `Role`.

### POST `/api/auth/logout`

```sql
UPDATE dbo.ApiAuthToken
SET RevokedAt=SYSUTCDATETIME()
WHERE TokenHash=@CurrentTokenHash;
```

### POST `/api/auth/change-password`

```sql
SELECT PasswordHash FROM dbo.SchoolTeacher WHERE ApiUserID=@ApiUserID;

UPDATE dbo.SchoolTeacher
SET PasswordHash=@NewPasswordHash,PasswordChangedAt=SYSUTCDATETIME()
WHERE ApiUserID=@ApiUserID;

UPDATE dbo.ApiAuthToken
SET RevokedAt=SYSUTCDATETIME()
WHERE ApiUserID=@ApiUserID
  AND TokenHash<>@CurrentTokenHash
  AND RevokedAt IS NULL;
```

## Teacher APIs

### GET `/api/teacher/dashboard`

```sql
SELECT COUNT(DISTINCT CONCAT(ClassID,':',SectionID))
FROM dbo.ApiTeacherAssignment
WHERE ApiUserID=@ApiUserID AND OwnerSessionID=@OwnerSessionID AND IsActive=1;
```

### GET `/api/teacher/classes`

```sql
SELECT DISTINCT c.ClassmasterID id,c.Classmaster name,c.SNo sortOrder
FROM dbo.ApiTeacherAssignment a
JOIN dbo.ClassMaster c ON c.ClassmasterID=a.ClassID
WHERE a.ApiUserID=@ApiUserID AND a.OwnerSessionID=@OwnerSessionID AND a.IsActive=1
ORDER BY c.SNo,c.Classmaster;
```

### GET `/api/teacher/classes/{classId}/sections`

```sql
SELECT DISTINCT s.SectionMasterID id,s.SectionName name
FROM dbo.ApiTeacherAssignment a
JOIN dbo.SectionMaster s ON s.SectionMasterID=a.SectionID
WHERE a.ApiUserID=@ApiUserID AND a.OwnerSessionID=@OwnerSessionID
  AND a.ClassID=@ClassID AND a.IsActive=1
ORDER BY s.SectionName;
```

### GET `/api/teacher/classes/{classId}/sections/{sectionId}/subjects`

```sql
SELECT DISTINCT s.CBSEExamSubjectID id,s.CBSEExamSubject name,
       s.SubjectAbbreviation abbreviation
FROM dbo.ApiTeacherAssignment a
JOIN dbo.CBSEExamSubject s ON s.CBSEExamSubjectID=a.SubjectID
WHERE a.ApiUserID=@ApiUserID AND a.OwnerSessionID=@OwnerSessionID
  AND a.ClassID=@ClassID AND a.SectionID=@SectionID AND a.IsActive=1
ORDER BY s.CBSEExamSubject;
```

### GET `/api/teacher/classes/{classId}/sections/{sectionId}/students`

```sql
SELECT s.StudentID studentId,s.StudentName name,s.AdmissionNo admissionNo,
       ss.RollNo rollNumber,s.FatherName fatherName
FROM dbo.StudentSession ss
JOIN dbo.Student s ON s.StudentID=ss.StudentID
WHERE ss.OwnerSessionID=@OwnerSessionID AND ss.ClassID=@ClassID
  AND ss.SectionID=@SectionID AND ss.IsLeave=0
ORDER BY CASE WHEN ss.RollNo<>'' AND LEN(ss.RollNo)<=9
    AND ss.RollNo NOT LIKE '%[^0-9]%' THEN CONVERT(int,ss.RollNo)
    ELSE 2147483647 END,ss.RollNo,s.StudentName;
```

## Exam API

### GET `/api/exams/terms`

```sql
SELECT t.CbseExamTermID id,t.CbseExamTerm name,
       o.CBSEExamTermOptionID optionId,o.CBSEExamTermOption optionName,
       o.Wt weightage
FROM dbo.CBSEExamTerm t
JOIN dbo.CBSEExamTermOption o ON o.CBSEExamTermID=t.CbseExamTermID
ORDER BY t.CbseExamTermID,o.CBSEExamTermOptionID;
```

The app must send `optionId`, not `id`, as `termOptionId`.

## Subject-wise marks APIs

### GET `/api/marks/max-marks`

Parameters: `classId`, `sectionId`, `termOptionId`. Only configured assigned subjects are returned.

The endpoint maps each assigned main subject to its first `CBSEExamSubSubject`, then reads the class-level configuration from `AppSubjectMaxMark`. Since this table intentionally has no `SectionID`, the value applies to every section of that class. Unconfigured subjects are omitted.

```sql
SELECT DISTINCT
       subject.CBSEExamSubjectID subjectId,
       subject.CBSEExamSubject subjectName,
       CAST(config.MaxMarks AS float) maxMarks
FROM dbo.ApiTeacherAssignment assignment
JOIN dbo.CBSEExamSubject subject
  ON subject.CBSEExamSubjectID=assignment.SubjectID
CROSS APPLY(
  SELECT TOP 1 sub.CBSEExamSubSubjectID
  FROM dbo.CBSEExamSubSubject sub
  WHERE sub.CBSEExamSubjectID=subject.CBSEExamSubjectID
  ORDER BY sub.CBSEExamSubSubjectID
) mapping
JOIN dbo.AppSubjectMaxMark config
  ON config.OwnerSessionID=assignment.OwnerSessionID
 AND config.ClassID=assignment.ClassID
 AND config.TermOptionID=@TermOptionID
 AND config.SubSubjectID=mapping.CBSEExamSubSubjectID
WHERE assignment.ApiUserID=@ApiUserID
  AND assignment.OwnerSessionID=@OwnerSessionID
  AND assignment.ClassID=@ClassID AND assignment.SectionID=@SectionID
  AND assignment.IsActive=1
ORDER BY subject.CBSEExamSubject;
```

### POST `/api/marks/max-marks`

Validates each max value as an integer from 1 to 1000 and upserts one configuration row. It updates existing student marks and percentages for the whole class, but does not create empty marks rows.

```sql
UPDATE dbo.AppSubjectMaxMark
SET MaxMarks=@MaxMarks,Serial=@Serial
WHERE OwnerSessionID=@OwnerSessionID AND ClassID=@ClassID
  AND TermOptionID=@TermOptionID AND SubSubjectID=@SubSubjectID;

IF @@ROWCOUNT=0
INSERT INTO dbo.AppSubjectMaxMark
 (OwnerSessionID,ClassID,TermOptionID,SubSubjectID,MaxMarks,Serial)
VALUES
 (@OwnerSessionID,@ClassID,@TermOptionID,@SubSubjectID,@MaxMarks,@Serial);
```

Existing marks synchronization:

```sql
UPDATE m
SET m.MaxMarks=@MaxMarks,
    m.Percentage=CASE WHEN m.ObtainMarks IS NULL THEN NULL
      ELSE ROUND(CAST(m.ObtainMarks AS float)*100.0/@MaxMarks,2) END
FROM dbo.CBSEExamMarksEntry m
JOIN dbo.StudentSession ss
  ON ss.StudentID=m.StudentID AND ss.OwnerSessionID=m.OwnerSessionID
WHERE m.OwnerSessionID=@OwnerSessionID AND m.TermOptionID=@TermOptionID
  AND m.SubSubjectID=@SubSubjectID AND ss.ClassID=@ClassID
  AND ss.IsLeave=0;
```

### GET `/api/marks/subject-wise/students`

Parameters: `classId`, `sectionId`, `termOptionId`, `subjectId`.

Subject mapping:

```sql
SELECT TOP 1 CBSEExamSubSubjectID
FROM dbo.CBSEExamSubSubject
WHERE CBSEExamSubjectID=@SubjectID
ORDER BY CBSEExamSubSubjectID;
```

Student/marks query:

```sql
SELECT s.StudentID id,s.StudentName name,s.AdmissionNo admissionNo,
       ss.RollNo rollNo,s.FatherName fatherName,
       m.CBSEExamMarksEntryID marksEntryId,m.TermOptionID termOptionId,
       m.SubSubjectID subSubjectId,m.StudentID marksStudentId,
       CAST(COALESCE(m.MaxMarks,config.MaxMarks) AS float) maxMarks,
       CAST(m.MinMarks AS float) minMarks,
       CAST(m.ObtainMarks AS float) obtainedMarks,
       CAST(m.Percentage AS float) percentage,
       m.IsAbsent isAbsent,m.IsMedical isMedical,m.Serial serial,
       m.OwnerSessionID ownerSessionId,m.IsDef isDefault
FROM dbo.StudentSession ss
JOIN dbo.Student s ON s.StudentID=ss.StudentID
LEFT JOIN dbo.CBSEExamMarksEntry m
  ON m.StudentID=s.StudentID
 AND m.OwnerSessionID=ss.OwnerSessionID
 AND m.TermOptionID=@TermOptionID
 AND m.SubSubjectID=@SubSubjectID
OUTER APPLY(
  SELECT TOP 1 existing.MaxMarks
  FROM dbo.CBSEExamMarksEntry existing
  JOIN dbo.StudentSession existingRoster
    ON existingRoster.StudentID=existing.StudentID
   AND existingRoster.OwnerSessionID=existing.OwnerSessionID
  WHERE existing.TermOptionID=@TermOptionID
    AND existing.SubSubjectID=@SubSubjectID
    AND existing.OwnerSessionID=@OwnerSessionID
    AND existingRoster.ClassID=@ClassID
    AND existingRoster.SectionID=@SectionID
    AND existingRoster.IsLeave=0
    AND existing.MaxMarks IS NOT NULL
  ORDER BY existing.CBSEExamMarksEntryID DESC
) config
WHERE ss.OwnerSessionID=@OwnerSessionID AND ss.ClassID=@ClassID
  AND ss.SectionID=@SectionID AND ss.IsLeave=0
ORDER BY CASE WHEN ss.RollNo<>'' AND LEN(ss.RollNo)<=9
    AND ss.RollNo NOT LIKE '%[^0-9]%' THEN CONVERT(int,ss.RollNo)
    ELSE 2147483647 END,ss.RollNo,s.StudentName;
```

Because this is a `LEFT JOIN`, the student is returned with null marks when no row matches the requested session + term option + mapped sub-subject.

### POST `/api/marks/subject-wise`

Student membership:

```sql
SELECT COUNT(*) FROM dbo.StudentSession
WHERE StudentID=@StudentID AND OwnerSessionID=@OwnerSessionID
  AND ClassID=@ClassID AND SectionID=@SectionID AND IsLeave=0;
```

Safe upsert lookup inside a transaction:

```sql
SELECT COUNT(*)
FROM dbo.CBSEExamMarksEntry WITH (UPDLOCK,HOLDLOCK)
WHERE StudentID=@StudentID AND OwnerSessionID=@OwnerSessionID
  AND TermOptionID=@TermOptionID AND SubSubjectID=@SubSubjectID;
```

Update existing row:

```sql
UPDATE dbo.CBSEExamMarksEntry
SET MaxMarks=@MaxMarks,ObtainMarks=@ObtainedMarks,Percentage=@Percentage,
    IsAbsent=@IsAbsent,IsMedical=@IsMedical
WHERE StudentID=@StudentID AND OwnerSessionID=@OwnerSessionID
  AND TermOptionID=@TermOptionID AND SubSubjectID=@SubSubjectID;
```

Insert only when no row exists:

```sql
INSERT INTO dbo.CBSEExamMarksEntry
 (TermOptionID,SubSubjectID,StudentID,MaxMarks,MinMarks,ObtainMarks,
  Percentage,IsAbsent,IsMedical,OwnerSessionID,IsDef)
VALUES
 (@TermOptionID,@SubSubjectID,@StudentID,@MaxMarks,0,@ObtainedMarks,
  @Percentage,@IsAbsent,@IsMedical,@OwnerSessionID,0);
```

Marks uniqueness key: `StudentID + OwnerSessionID + TermOptionID + SubSubjectID`.

## Student-wise marks APIs

### GET `/api/marks/student-wise/{studentId}?termOptionId={optionId}`

Student context:

```sql
SELECT TOP 1 ClassID,SectionID
FROM dbo.StudentSession
WHERE StudentID=@StudentID AND OwnerSessionID=@OwnerSessionID AND IsLeave=0;
```

Assigned subjects and marks (same first sub-subject mapping used by save):

```sql
SELECT s.CBSEExamSubjectID subjectId,s.CBSEExamSubject subjectName,
       m.CBSEExamMarksEntryID marksEntryId,m.TermOptionID termOptionId,
       map.CBSEExamSubSubjectID subSubjectId,m.StudentID studentId,
       CAST(COALESCE(m.MaxMarks,config.MaxMarks) AS float) maxMarks,
       CAST(m.MinMarks AS float) minMarks,
       CAST(m.ObtainMarks AS float) obtainedMarks,
       CAST(m.Percentage AS float) percentage,
       m.IsAbsent isAbsent,m.IsMedical isMedical,m.Serial serial,
       m.OwnerSessionID ownerSessionId,m.IsDef isDefault
FROM dbo.ApiTeacherAssignment a
JOIN dbo.CBSEExamSubject s ON s.CBSEExamSubjectID=a.SubjectID
CROSS APPLY(
  SELECT TOP 1 ss.CBSEExamSubSubjectID
  FROM dbo.CBSEExamSubSubject ss
  WHERE ss.CBSEExamSubjectID=s.CBSEExamSubjectID
  ORDER BY ss.CBSEExamSubSubjectID
) map
LEFT JOIN dbo.CBSEExamMarksEntry m
  ON m.SubSubjectID=map.CBSEExamSubSubjectID
 AND m.StudentID=@StudentID AND m.TermOptionID=@TermOptionID
 AND m.OwnerSessionID=@OwnerSessionID
OUTER APPLY(
  SELECT TOP 1 existing.MaxMarks
  FROM dbo.CBSEExamMarksEntry existing
  JOIN dbo.StudentSession existingRoster
    ON existingRoster.StudentID=existing.StudentID
   AND existingRoster.OwnerSessionID=existing.OwnerSessionID
  WHERE existing.TermOptionID=@TermOptionID
    AND existing.SubSubjectID=map.CBSEExamSubSubjectID
    AND existing.OwnerSessionID=a.OwnerSessionID
    AND existingRoster.ClassID=a.ClassID
    AND existingRoster.SectionID=a.SectionID
    AND existingRoster.IsLeave=0
    AND existing.MaxMarks IS NOT NULL
  ORDER BY existing.CBSEExamMarksEntryID DESC
) config
WHERE a.ApiUserID=@ApiUserID AND a.ClassID=@ClassID
  AND a.SectionID=@SectionID AND a.OwnerSessionID=@OwnerSessionID
  AND a.IsActive=1
ORDER BY s.CBSEExamSubject;
```

### POST `/api/marks/student-wise`

Finds the student's current class/section and then uses the same mapping, validation, and transaction-safe update/insert queries as subject-wise save.

Both marks save APIs return `saved`, `classId`, `sectionId`, `termOptionId`, and the normalized `marks` array that was stored.

## Attendance APIs

### GET `/api/attendance/students`

Parameters: `classId`, `sectionId`, `date` (`YYYY-MM-DD`).

```sql
SELECT s.StudentID id,s.StudentName name,s.AdmissionNo admissionNo,
       ss.RollNo rollNo,s.FatherName fatherName,
       COALESCE(a.AttType,'P') status
FROM dbo.StudentSession ss
JOIN dbo.Student s ON s.StudentID=ss.StudentID
LEFT JOIN dbo.AttItem a
  ON a.StudentID=s.StudentID AND CAST(a.Dated AS date)=@AttendanceDate
WHERE ss.OwnerSessionID=@OwnerSessionID AND ss.ClassID=@ClassID
  AND ss.SectionID=@SectionID AND ss.IsLeave=0
ORDER BY CASE WHEN ss.RollNo<>'' AND LEN(ss.RollNo)<=9
    AND ss.RollNo NOT LIKE '%[^0-9]%' THEN CONVERT(int,ss.RollNo)
    ELSE 2147483647 END,ss.RollNo,s.StudentName;
```

No attendance row currently defaults to `P` in the GET response.

### POST `/api/attendance`

Allowed statuses: `P`, `A`, `H/2`, `Holiday`.

```sql
UPDATE dbo.AttItem
SET AttType=@Status,AttValue=@StatusValue
WHERE StudentID=@StudentID AND CAST(Dated AS date)=@AttendanceDate;

-- Only when UPDATE affects no row:
INSERT INTO dbo.AttItem(Dated,StudentID,AttType,AttValue)
VALUES(@AttendanceDate,@StudentID,@Status,@StatusValue);
```

Status values: `P=1`, `A=0`, `H/2=0.5`, `Holiday=1`.

## Table map

| Feature | SQL tables |
|---|---|
| Schools/users | `ApiSchool`, `SchoolTeacher` |
| Login/tokens | `ApiLoginAttempt`, `ApiAuthToken` |
| Teacher access | `ApiTeacherAssignment` |
| Academic session | `OwnerSession` |
| Class/section | `ClassMaster`, `SectionMaster` |
| Students | `Student`, `StudentSession` |
| Terms | `CBSEExamTerm`, `CBSEExamTermOption` |
| Subjects | `CBSEExamSubject`, `CBSEExamSubSubject` |
| Marks | `CBSEExamMarksEntry` |
| Max-marks configuration and student marks | `CBSEExamMarksEntry` |
| Attendance | `AttItem` |

## Critical app rules

1. Send exam `optionId` as `termOptionId`.
2. Send assigned `subjectId`; never send `SubSubjectID` from Flutter.
3. Null marks mean no matching marks row for the selected term/subject/session.
4. Class, section, and subjects are restricted by `ApiTeacherAssignment`.
5. Use the class/section roster endpoint for student pickers.
6. Marks and attendance POST endpoints update live legacy tables.


