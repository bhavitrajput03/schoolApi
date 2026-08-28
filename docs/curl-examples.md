# cURL examples

Base URL: http://localhost/school/public

Set TOKEN to the accessToken returned by login.

## Authentication

    curl.exe -X POST "http://localhost/school/public/api/auth/login" -H "Content-Type: application/json" -d "{\"schoolCode\":\"B\",\"username\":\"anita\",\"password\":\"StrongPassword@123\"}"
    curl.exe "http://localhost/school/public/api/auth/me" -H "Authorization: Bearer TOKEN"
    curl.exe -X POST "http://localhost/school/public/api/auth/logout" -H "Authorization: Bearer TOKEN"
    curl.exe -X POST "http://localhost/school/public/api/auth/change-password" -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" -d "{\"currentPassword\":\"StrongPassword@123\",\"newPassword\":\"NewStrongPassword@456\"}"

## Teacher and exams

    curl.exe "http://localhost/school/public/api/teacher/dashboard" -H "Authorization: Bearer TOKEN"
    curl.exe "http://localhost/school/public/api/teacher/classes" -H "Authorization: Bearer TOKEN"
    curl.exe "http://localhost/school/public/api/teacher/classes/155/sections" -H "Authorization: Bearer TOKEN"
    curl.exe "http://localhost/school/public/api/teacher/classes/155/sections/61/subjects" -H "Authorization: Bearer TOKEN"
    curl.exe "http://localhost/school/public/api/teacher/classes/155/sections/61/students" -H "Authorization: Bearer TOKEN"
    curl.exe "http://localhost/school/public/api/exams/terms" -H "Authorization: Bearer TOKEN"

## Subject-wise marks

    curl.exe "http://localhost/school/public/api/marks/subject-wise/students?classId=155&sectionId=61&termOptionId=39&subjectId=40" -H "Authorization: Bearer TOKEN"
    curl.exe -X POST "http://localhost/school/public/api/marks/subject-wise" -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" -d "{\"classId\":155,\"sectionId\":61,\"termOptionId\":39,\"subjectId\":40,\"maxMarks\":80,\"marks\":[{\"studentId\":14687,\"obtainedMarks\":72,\"isAbsent\":false,\"isMedical\":false}]}"

## Student-wise marks

    curl.exe "http://localhost/school/public/api/marks/student-wise/14687?termOptionId=39" -H "Authorization: Bearer TOKEN"
    curl.exe -X POST "http://localhost/school/public/api/marks/student-wise" -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" -d "{\"studentId\":14687,\"termOptionId\":39,\"marks\":[{\"subjectId\":40,\"maxMarks\":80,\"obtainedMarks\":72},{\"subjectId\":41,\"maxMarks\":80,\"obtainedMarks\":68}]}"

## Attendance

    curl.exe "http://localhost/school/public/api/attendance/students?classId=155&sectionId=61&date=2026-08-24" -H "Authorization: Bearer TOKEN"
    curl.exe -X POST "http://localhost/school/public/api/attendance" -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" -d "{\"classId\":155,\"sectionId\":61,\"date\":\"2026-08-24\",\"attendance\":[{\"studentId\":14687,\"status\":\"P\"}]}"
