<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response};
use App\Service\ParentService;

final class ParentTeacherController {
    public function classTeachers(Request $r,array $p,int $student):never{
        $ctx=ParentService::student($p,$student);$sql="SELECT DISTINCT t.ApiUserID teacherId,t.ApiUserID apiUserId,t.EmployeeID employeeId,t.DisplayName name,m.TeacherRole role
          FROM AppClassTeacher m JOIN SchoolTeacher t ON CONVERT(varchar(64),t.ApiUserID)=CONVERT(varchar(64),m.ApiUserID)
          WHERE m.OwnerSessionID=? AND m.ClassID=? AND m.SectionID=? AND m.IsActive=1 AND t.IsActive=1 ORDER BY CASE m.TeacherRole WHEN 'class_teacher' THEN 1 ELSE 2 END,t.DisplayName";
        $st=Database::connection()->prepare($sql);$st->execute([$ctx['OwnerSessionID'],$ctx['ClassID'],$ctx['SectionID']]);Response::success(['teachers'=>$st->fetchAll()]);
    }
    public function subjectTeachers(Request $r,array $p,int $student):never{
        $ctx=ParentService::student($p,$student);$sql="SELECT DISTINCT t.ApiUserID teacherId,t.ApiUserID apiUserId,t.EmployeeID employeeId,t.DisplayName name,s.CBSEExamSubjectID subjectId,s.CBSEExamSubject subjectName,s.SubjectAbbreviation abbreviation
          FROM ApiTeacherAssignment a JOIN SchoolTeacher t ON t.ApiUserID=a.ApiUserID JOIN CBSEExamSubject s ON s.CBSEExamSubjectID=a.SubjectID
          WHERE a.OwnerSessionID=? AND a.ClassID=? AND a.SectionID=? AND a.IsActive=1 AND t.IsActive=1 ORDER BY s.CBSEExamSubject,t.DisplayName";
        $st=Database::connection()->prepare($sql);$st->execute([$ctx['OwnerSessionID'],$ctx['ClassID'],$ctx['SectionID']]);Response::success(['teachers'=>$st->fetchAll()]);
    }
    public function teacher(Request $r,array $p,string|int $teacher):never{
        $identifier=(string)$teacher;$sql='SELECT TOP 1 t.ApiUserID teacherId,t.ApiUserID apiUserId,t.EmployeeID employeeId,t.DisplayName name,t.Role role FROM SchoolTeacher t WHERE CONVERT(varchar(64),t.ApiUserID)=? AND t.IsActive=1 AND (EXISTS(SELECT 1 FROM ApiTeacherAssignment a JOIN AppParentStudent l ON l.AppParentID=? JOIN StudentSession ss ON ss.StudentID=l.StudentID AND ss.OwnerSessionID=a.OwnerSessionID AND ss.ClassID=a.ClassID AND ss.SectionID=a.SectionID WHERE a.ApiUserID=t.ApiUserID AND a.IsActive=1) OR EXISTS(SELECT 1 FROM AppClassTeacher a JOIN AppParentStudent l ON l.AppParentID=? JOIN StudentSession ss ON ss.StudentID=l.StudentID AND ss.OwnerSessionID=a.OwnerSessionID AND ss.ClassID=a.ClassID AND ss.SectionID=a.SectionID WHERE CONVERT(varchar(64),a.ApiUserID)=CONVERT(varchar(64),t.ApiUserID) AND a.IsActive=1))';
        $st=Database::connection()->prepare($sql);$st->execute([$identifier,$p['AppParentID'],$p['AppParentID']]);$row=$st->fetch();if(!$row)Response::error('Teacher not found.',404,'TEACHER_NOT_FOUND');Response::success(['teacher'=>$row]);
    }
}
