<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Database, Request, Response, Validator};
use App\Service\SchoolService;

final class MarksController
{
    public function maxMarks(Request $request, array $user): never
    {
        $class = Validator::id($request->query('classId'), 'classId');
        $section = Validator::id($request->query('sectionId'), 'sectionId');
        $term = Validator::id($request->query('termOptionId'), 'termOptionId');
        $session = SchoolService::currentSessionId();
        SchoolService::assertAssignment($user['ApiUserID'], $class, $section);
        $this->assertTermOption($term);

        $sql = "SELECT DISTINCT
                    subject.CBSEExamSubjectID subjectId,
                    subject.CBSEExamSubject subjectName,
                    CAST(config.MaxMarks AS float) maxMarks
                FROM ApiTeacherAssignment assignment
                JOIN CBSEExamSubject subject
                  ON subject.CBSEExamSubjectID = assignment.SubjectID
                CROSS APPLY(
                    SELECT TOP 1 sub.CBSEExamSubSubjectID
                    FROM CBSEExamSubSubject sub
                    WHERE sub.CBSEExamSubjectID = subject.CBSEExamSubjectID
                    ORDER BY sub.CBSEExamSubSubjectID
                ) mapping
                JOIN AppSubjectMaxMark config
                  ON config.OwnerSessionID=assignment.OwnerSessionID
                 AND config.ClassID=assignment.ClassID
                 AND config.TermOptionID=?
                 AND config.SubSubjectID=mapping.CBSEExamSubSubjectID
                WHERE assignment.ApiUserID = ?
                  AND assignment.OwnerSessionID = ?
                  AND assignment.ClassID = ?
                  AND assignment.SectionID = ?
                  AND assignment.IsActive = 1
                ORDER BY subject.CBSEExamSubject";

        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            $term, $user['ApiUserID'], $session, $class, $section,
        ]);
        Response::success(['subjects' => $statement->fetchAll()]);
    }

    public function saveMaxMarks(Request $request, array $user): never
    {
        $data = $request->json();
        Validator::required($data, ['classId', 'sectionId', 'termOptionId', 'subjects']);
        $class = Validator::id($data['classId'], 'classId');
        $section = Validator::id($data['sectionId'], 'sectionId');
        $term = Validator::id($data['termOptionId'], 'termOptionId');
        if (!is_array($data['subjects']) || !$data['subjects']) {
            Response::error('subjects must be a non-empty array.', 422, 'VALIDATION_ERROR');
        }

        $session = SchoolService::currentSessionId();
        SchoolService::assertAssignment($user['ApiUserID'], $class, $section);
        $this->assertTermOption($term);
        $database = Database::connection();
        $database->beginTransaction();
        $updated = [];
        try {
            foreach ($data['subjects'] as $index => $row) {
                if (!is_array($row)) {
                    Response::error('Each subjects item must be an object.', 422, 'VALIDATION_ERROR');
                }
                Validator::required($row, ['subjectId', 'maxMarks']);
                $subject = Validator::id($row['subjectId'], 'subjectId');
                $rawMax = $row['maxMarks'];
                if (!is_numeric($rawMax)
                    || (float)$rawMax !== (float)(int)$rawMax
                    || (int)$rawMax < 1
                    || (int)$rawMax > 1000) {
                    Response::error(
                        'maxMarks must be an integer between 1 and 1000.',
                        422,
                        'VALIDATION_ERROR',
                        ['subjectId' => $subject]
                    );
                }

                $max = (int)$rawMax;
                $serial = array_key_exists('serial', $row)
                    ? Validator::id($row['serial'], 'serial')
                    : $index + 1;
                SchoolService::assertAssignment($user['ApiUserID'], $class, $section, $subject);
                $subSubject = SchoolService::subSubjectId($subject);

                $tooHigh = $database->prepare(
                    'SELECT TOP 1 marks.StudentID,marks.ObtainMarks
                     FROM CBSEExamMarksEntry marks
                     JOIN StudentSession roster
                       ON roster.StudentID=marks.StudentID
                      AND roster.OwnerSessionID=marks.OwnerSessionID
                     WHERE marks.OwnerSessionID=? AND marks.TermOptionID=?
                       AND marks.SubSubjectID=? AND roster.ClassID=?
                       AND roster.IsLeave=0
                       AND marks.ObtainMarks>?'
                );
                $tooHigh->execute([$session, $term, $subSubject, $class, $max]);
                $invalid = $tooHigh->fetch();
                if ($invalid) {
                    Response::error(
                        'maxMarks cannot be lower than marks already obtained by a student.',
                        422,
                        'MAX_MARKS_BELOW_OBTAINED',
                        [
                            'subjectId' => $subject,
                            'studentId' => (int)$invalid['StudentID'],
                            'obtainedMarks' => (float)$invalid['ObtainMarks'],
                        ]
                    );
                }

                $upsert = $database->prepare(
                    'UPDATE AppSubjectMaxMark WITH (UPDLOCK,HOLDLOCK)
                     SET MaxMarks=?,Serial=?
                     WHERE OwnerSessionID=? AND ClassID=?
                       AND TermOptionID=? AND SubSubjectID=?;
                     IF @@ROWCOUNT=0
                     INSERT INTO AppSubjectMaxMark
                       (OwnerSessionID,ClassID,TermOptionID,SubSubjectID,MaxMarks,Serial)
                     VALUES(?,?,?,?,?,?)'
                );
                $upsert->execute([
                    $max, $serial, $session, $class, $term, $subSubject,
                    $session, $class, $term, $subSubject, $max, $serial,
                ]);

                $update = $database->prepare(
                    'UPDATE marks
                     SET marks.MaxMarks=?,
                         marks.Percentage=CASE WHEN marks.ObtainMarks IS NULL THEN NULL
                           ELSE ROUND(CAST(marks.ObtainMarks AS float)*100.0/?,2) END
                     FROM CBSEExamMarksEntry marks
                     JOIN StudentSession roster
                       ON roster.StudentID=marks.StudentID
                      AND roster.OwnerSessionID=marks.OwnerSessionID
                     WHERE marks.OwnerSessionID=? AND marks.TermOptionID=?
                       AND marks.SubSubjectID=? AND roster.ClassID=?
                       AND roster.IsLeave=0'
                );
                $update->execute([$max, $max, $session, $term, $subSubject, $class]);
                $updated[$subject] = true;
            }
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }

        Response::success(['updated' => count($updated)]);
    }

    public function subjectStudents(Request $request, array $user): never
    {
        [$class, $section, $term, $subject] = $this->context($request);
        SchoolService::assertAssignment($user['ApiUserID'], $class, $section, $subject);
        $session = SchoolService::currentSessionId();
        $subSubject = SchoolService::subSubjectId($subject);

        $sql = "SELECT
                    student.StudentID id,student.StudentName name,
                    student.AdmissionNo admissionNo,roster.RollNo rollNo,
                    student.FatherName fatherName,
                    marks.CBSEExamMarksEntryID marksEntryId,
                    marks.TermOptionID termOptionId,
                    marks.SubSubjectID subSubjectId,
                    marks.StudentID marksStudentId,
                    CAST(COALESCE(config.MaxMarks,marks.MaxMarks) AS float) maxMarks,
                    CAST(marks.MinMarks AS float) minMarks,
                    CAST(marks.ObtainMarks AS float) obtainedMarks,
                    CAST(marks.Percentage AS float) percentage,
                    marks.IsAbsent isAbsent,marks.IsMedical isMedical,
                    marks.Serial serial,marks.OwnerSessionID ownerSessionId,
                    marks.IsDef isDefault
                FROM StudentSession roster
                JOIN Student student ON student.StudentID=roster.StudentID
                LEFT JOIN CBSEExamMarksEntry marks
                  ON marks.StudentID=student.StudentID
                 AND marks.OwnerSessionID=roster.OwnerSessionID
                 AND marks.TermOptionID=? AND marks.SubSubjectID=?
                LEFT JOIN AppSubjectMaxMark config
                  ON config.OwnerSessionID=roster.OwnerSessionID
                 AND config.ClassID=roster.ClassID
                 AND config.TermOptionID=?
                 AND config.SubSubjectID=?
                WHERE roster.OwnerSessionID=? AND roster.ClassID=?
                  AND roster.SectionID=? AND roster.IsLeave=0
                ORDER BY CASE WHEN roster.RollNo<>'' AND LEN(roster.RollNo)<=9
                  AND roster.RollNo NOT LIKE '%[^0-9]%'
                  THEN CONVERT(int,roster.RollNo) ELSE 2147483647 END,
                  roster.RollNo,student.StudentName";

        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            $term, $subSubject, $term, $subSubject,
            $session, $class, $section,
        ]);
        Response::success($statement->fetchAll());
    }

    public function saveSubjectWise(Request $request, array $user): never
    {
        $data = $request->json();
        Validator::required($data, ['classId', 'sectionId', 'termOptionId', 'subjectId', 'maxMarks', 'marks']);
        $class = Validator::id($data['classId'], 'classId');
        $section = Validator::id($data['sectionId'], 'sectionId');
        $term = Validator::id($data['termOptionId'], 'termOptionId');
        $subject = Validator::id($data['subjectId'], 'subjectId');
        SchoolService::assertAssignment($user['ApiUserID'], $class, $section, $subject);
        $this->write(
            $user,
            $class,
            $section,
            $term,
            (float)$data['maxMarks'],
            $data['marks'],
            static fn(array $row): int => $subject
        );
    }

    public function studentWise(Request $request, array $user, int $student): never
    {
        $term = Validator::id($request->query('termOptionId'), 'termOptionId');
        $session = SchoolService::currentSessionId();
        $database = Database::connection();
        $statement = $database->prepare('SELECT TOP 1 ClassID,SectionID FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND IsLeave=0');
        $statement->execute([$student, $session]);
        $context = $statement->fetch();
        if (!$context) {
            Response::error('Student not found in current session.', 404, 'STUDENT_NOT_FOUND');
        }
        $class = (int)$context['ClassID'];
        $section = (int)$context['SectionID'];
        SchoolService::assertAssignment($user['ApiUserID'], $class, $section);

        $sql = "SELECT
                    subject.CBSEExamSubjectID subjectId,
                    subject.CBSEExamSubject subjectName,
                    marks.CBSEExamMarksEntryID marksEntryId,
                    marks.TermOptionID termOptionId,
                    mapping.CBSEExamSubSubjectID subSubjectId,
                    marks.StudentID studentId,
                    CAST(COALESCE(config.MaxMarks,marks.MaxMarks) AS float) maxMarks,
                    CAST(marks.MinMarks AS float) minMarks,
                    CAST(marks.ObtainMarks AS float) obtainedMarks,
                    CAST(marks.Percentage AS float) percentage,
                    marks.IsAbsent isAbsent,marks.IsMedical isMedical,
                    marks.Serial serial,marks.OwnerSessionID ownerSessionId,
                    marks.IsDef isDefault
                FROM ApiTeacherAssignment assignment
                JOIN CBSEExamSubject subject
                  ON subject.CBSEExamSubjectID=assignment.SubjectID
                CROSS APPLY(
                    SELECT TOP 1 sub.CBSEExamSubSubjectID
                    FROM CBSEExamSubSubject sub
                    WHERE sub.CBSEExamSubjectID=subject.CBSEExamSubjectID
                    ORDER BY sub.CBSEExamSubSubjectID
                ) mapping
                LEFT JOIN CBSEExamMarksEntry marks
                  ON marks.SubSubjectID=mapping.CBSEExamSubSubjectID
                 AND marks.StudentID=? AND marks.TermOptionID=?
                 AND marks.OwnerSessionID=?
                LEFT JOIN AppSubjectMaxMark config
                  ON config.OwnerSessionID=assignment.OwnerSessionID
                 AND config.ClassID=assignment.ClassID
                 AND config.TermOptionID=?
                 AND config.SubSubjectID=mapping.CBSEExamSubSubjectID
                WHERE assignment.ApiUserID=? AND assignment.ClassID=?
                  AND assignment.SectionID=? AND assignment.OwnerSessionID=?
                  AND assignment.IsActive=1
                ORDER BY subject.CBSEExamSubject";

        $statement = $database->prepare($sql);
        $statement->execute([
            $student, $term, $session, $term,
            $user['ApiUserID'], $class, $section, $session,
        ]);
        Response::success([
            'studentId' => $student,
            'classId' => $class,
            'sectionId' => $section,
            'marks' => $statement->fetchAll(),
        ]);
    }

    public function saveStudentWise(Request $request, array $user): never
    {
        $data = $request->json();
        Validator::required($data, ['studentId', 'termOptionId', 'marks']);
        $student = Validator::id($data['studentId'], 'studentId');
        $term = Validator::id($data['termOptionId'], 'termOptionId');
        $session = SchoolService::currentSessionId();
        $database = Database::connection();
        $statement = $database->prepare('SELECT TOP 1 ClassID,SectionID FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND IsLeave=0');
        $statement->execute([$student, $session]);
        $context = $statement->fetch();
        if (!$context) {
            Response::error('Student not found.', 404, 'STUDENT_NOT_FOUND');
        }
        $rows = [];
        foreach ($data['marks'] as $mark) {
            $mark['studentId'] = $student;
            $rows[] = $mark;
        }
        $this->write(
            $user,
            (int)$context['ClassID'],
            (int)$context['SectionID'],
            $term,
            0,
            $rows,
            static fn(array $row): int => Validator::id($row['subjectId'] ?? null, 'subjectId')
        );
    }

    private function write(
        array $user,
        int $class,
        int $section,
        int $term,
        float $commonMax,
        array $rows,
        callable $subjectOf
    ): never {
        if (!$rows) {
            Response::error('marks must be a non-empty array.', 422, 'VALIDATION_ERROR');
        }
        $session = SchoolService::currentSessionId();
        $database = Database::connection();
        $database->beginTransaction();
        $savedRows = [];
        try {
            foreach ($rows as $row) {
                Validator::required($row, ['studentId']);
                $student = Validator::id($row['studentId'], 'studentId');
                $subject = $subjectOf($row);
                SchoolService::assertAssignment($user['ApiUserID'], $class, $section, $subject);
                $subSubject = SchoolService::subSubjectId($subject);
                $configured = $this->configuredMaxMarks(
                    $session,
                    $class,
                    $section,
                    $term,
                    $subSubject
                );
                $max = $configured ?? (float)($row['maxMarks'] ?? $commonMax);
                $absent = (bool)($row['isAbsent'] ?? false);
                $medical = (bool)($row['isMedical'] ?? false);
                if ($max <= 0 || ($absent && $medical)) {
                    Response::error('Invalid maxMarks/absence flags.', 422, 'VALIDATION_ERROR');
                }
                $rawObtained = $row['obtainedMarks'] ?? null;
                $hasObtained = array_key_exists('obtainedMarks', $row)
                    && $rawObtained !== null
                    && !(is_string($rawObtained) && trim($rawObtained) === '');
                if (!$hasObtained && !$absent && !$medical) {
                    continue;
                }
                if ($hasObtained && !is_numeric($rawObtained)) {
                    Response::error('obtainedMarks must be a number.', 422, 'VALIDATION_ERROR', [
                        'studentId' => $student,
                        'subjectId' => $subject,
                    ]);
                }
                $obtained = ($absent || $medical)
                    ? null
                    : (float)$rawObtained;
                if (!$absent && !$medical
                    && ($obtained === null || $obtained < 0 || $obtained > $max)) {
                    Response::error('obtainedMarks must be between 0 and maxMarks.', 422, 'VALIDATION_ERROR');
                }

                $membership = $database->prepare('SELECT COUNT(*) FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND ClassID=? AND SectionID=? AND IsLeave=0');
                $membership->execute([$student, $session, $class, $section]);
                if (!(int)$membership->fetchColumn()) {
                    Response::error('Student does not belong to selected class/section.', 422, 'INVALID_STUDENT');
                }

                $percentage = $obtained === null ? null : round($obtained * 100 / $max, 2);
                $exists = $database->prepare('SELECT COUNT(*) FROM CBSEExamMarksEntry WITH (UPDLOCK,HOLDLOCK) WHERE StudentID=? AND OwnerSessionID=? AND TermOptionID=? AND SubSubjectID=?');
                $exists->execute([$student, $session, $term, $subSubject]);
                if ((int)$exists->fetchColumn() > 0) {
                    $update = $database->prepare('UPDATE CBSEExamMarksEntry SET MaxMarks=?,ObtainMarks=?,Percentage=?,IsAbsent=?,IsMedical=? WHERE StudentID=? AND OwnerSessionID=? AND TermOptionID=? AND SubSubjectID=?');
                    $update->execute([
                        $max, $obtained, $percentage, (int)$absent, (int)$medical,
                        $student, $session, $term, $subSubject,
                    ]);
                } else {
                    $insert = $database->prepare('INSERT INTO CBSEExamMarksEntry(TermOptionID,SubSubjectID,StudentID,MaxMarks,MinMarks,ObtainMarks,Percentage,IsAbsent,IsMedical,OwnerSessionID,IsDef) VALUES(?,?,?,?,0,?,?,?,?,?,0)');
                    $insert->execute([
                        $term, $subSubject, $student, $max, $obtained,
                        $percentage, (int)$absent, (int)$medical, $session,
                    ]);
                }
                $savedRows[$student . ':' . $subject] = [
                    'studentId' => $student,
                    'subjectId' => $subject,
                    'maxMarks' => $max,
                    'obtainedMarks' => $obtained,
                    'percentage' => $percentage,
                    'isAbsent' => $absent,
                    'isMedical' => $medical,
                ];
            }
            if (!$savedRows) {
                $database->rollBack();
                Response::error('Enter marks or select Absent/Medical for at least one student.', 422, 'NO_MARKS_ENTERED');
            }
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        Response::success([
            'saved' => count($savedRows),
            'classId' => $class,
            'sectionId' => $section,
            'termOptionId' => $term,
            'marks' => array_values($savedRows),
        ]);
    }

    private function configuredMaxMarks(
        int $session,
        int $class,
        int $section,
        int $term,
        int $subSubject
    ): ?float {
        $sql = 'SELECT MaxMarks
                FROM AppSubjectMaxMark
                WHERE OwnerSessionID=? AND ClassID=?
                  AND TermOptionID=? AND SubSubjectID=?';
        $statement = Database::connection()->prepare($sql);
        $statement->execute([$session, $class, $term, $subSubject]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (float)$value;
    }

    private function assertTermOption(int $term): void
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM CBSEExamTermOption WHERE CBSEExamTermOptionID=?');
        $statement->execute([$term]);
        if (!(int)$statement->fetchColumn()) {
            Response::error('Term option not found.', 404, 'TERM_OPTION_NOT_FOUND');
        }
    }

    private function context(Request $request): array
    {
        return [
            Validator::id($request->query('classId'), 'classId'),
            Validator::id($request->query('sectionId'), 'sectionId'),
            Validator::id($request->query('termOptionId'), 'termOptionId'),
            Validator::id($request->query('subjectId'), 'subjectId'),
        ];
    }
}
