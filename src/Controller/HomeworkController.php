<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response};
use App\Service\{ParentService,SchoolService};

final class HomeworkController {
    public function list(Request $r,array $p,int $student):never{
        $ctx=ParentService::student($p,$student);$subject=$r->query('subjectId');$sql="SELECT h.AppHomeworkID id,h.Title title,h.Description description,h.HomeworkDate homeworkDate,h.DueDate dueDate,h.AttachmentUrl attachmentUrl,h.SubjectID subjectId,s.CBSEExamSubject subjectName,h.CreatedAt createdAt
          FROM AppHomework h LEFT JOIN CBSEExamSubject s ON s.CBSEExamSubjectID=h.SubjectID
          WHERE h.OwnerSessionID=? AND h.ClassID=? AND (h.SectionID IS NULL OR h.SectionID=?) AND h.IsActive=1";$args=[$ctx['OwnerSessionID'],$ctx['ClassID'],$ctx['SectionID']];
        if($subject!==null){$sql.=' AND h.SubjectID=?';$args[]=(int)$subject;}$sql.=' ORDER BY h.HomeworkDate DESC,h.AppHomeworkID DESC';$st=Database::connection()->prepare($sql);$st->execute($args);Response::success(['homework'=>$st->fetchAll()]);
    }
    public function detail(Request $r,array $p,int $homework):never{
        $sql="SELECT TOP 1 h.AppHomeworkID id,h.Title title,h.Description description,h.HomeworkDate homeworkDate,h.DueDate dueDate,h.AttachmentUrl attachmentUrl,h.SubjectID subjectId,s.CBSEExamSubject subjectName,h.TeacherEmployeeID teacherId,e.DisplayName teacherName
          FROM AppHomework h LEFT JOIN CBSEExamSubject s ON s.CBSEExamSubjectID=h.SubjectID LEFT JOIN SchoolTeacher e ON e.EmployeeID=h.TeacherEmployeeID
          WHERE h.AppHomeworkID=? AND h.IsActive=1 AND EXISTS(SELECT 1 FROM AppParentStudent l JOIN StudentSession ss ON ss.StudentID=l.StudentID WHERE l.AppParentID=? AND ss.OwnerSessionID=h.OwnerSessionID AND ss.ClassID=h.ClassID AND (h.SectionID IS NULL OR h.SectionID=ss.SectionID) AND ss.IsLeave=0)";
        $st=Database::connection()->prepare($sql);$st->execute([$homework,$p['AppParentID']]);$row=$st->fetch();if(!$row)Response::error('Homework not found.',404,'HOMEWORK_NOT_FOUND');Response::success(['homework'=>$row]);
    }
    public function subjects(Request $r,array $p,int $student):never{
        $ctx=ParentService::student($p,$student);$st=Database::connection()->prepare("SELECT DISTINCT s.CBSEExamSubjectID id,s.CBSEExamSubject name,s.SubjectAbbreviation abbreviation FROM AppHomework h JOIN CBSEExamSubject s ON s.CBSEExamSubjectID=h.SubjectID WHERE h.OwnerSessionID=? AND h.ClassID=? AND (h.SectionID IS NULL OR h.SectionID=?) AND h.IsActive=1 ORDER BY s.CBSEExamSubject");$st->execute([$ctx['OwnerSessionID'],$ctx['ClassID'],$ctx['SectionID']]);Response::success(['subjects'=>$st->fetchAll()]);
    }
}
