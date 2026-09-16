<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response};
use App\Service\{LegacyMapper,ParentService,SchoolService};

final class ParentController {
    public function profile(Request $r,array $p):never{unset($p['_tokenHash']);Response::success(['parent'=>$p]);}
    public function students(Request $r,array $p):never{
        $session=SchoolService::currentSessionId();$sql="SELECT s.StudentID studentId,s.StudentName name,s.AdmissionNo admissionNo,s.FatherName fatherName,
          ss.RollNo rollNumber,ss.ClassID classId,c.Classmaster className,ss.SectionID sectionId,se.SectionName sectionName,link.Relationship relationship,link.IsPrimary isPrimary
          FROM AppParentStudent link JOIN Student s ON s.StudentID=link.StudentID
          JOIN StudentSession ss ON ss.StudentID=s.StudentID AND ss.OwnerSessionID=? AND ss.IsLeave=0
          JOIN ClassMaster c ON c.ClassmasterID=ss.ClassID JOIN SectionMaster se ON se.SectionMasterID=ss.SectionID
          WHERE link.AppParentID=? ORDER BY link.IsPrimary DESC,s.StudentName";
        $st=Database::connection()->prepare($sql);$st->execute([$session,$p['AppParentID']]);Response::success(['students'=>$st->fetchAll()]);
    }
    public function student(Request $r,array $p,int $student):never{
        $ctx=ParentService::student($p,$student);$db=Database::connection();$st=$db->prepare('SELECT TOP 1 * FROM Student WHERE StudentID=?');$st->execute([$student]);$s=$st->fetch();
        $class=$db->prepare('SELECT c.Classmaster className,se.SectionName sectionName,ss.RollNo rollNumber FROM StudentSession ss JOIN ClassMaster c ON c.ClassmasterID=ss.ClassID JOIN SectionMaster se ON se.SectionMasterID=ss.SectionID WHERE ss.StudentID=? AND ss.OwnerSessionID=?');$class->execute([$student,$ctx['OwnerSessionID']]);$enrol=$class->fetch()?:[];
        Response::success(['student'=>[
          'studentId'=>$student,'name'=>LegacyMapper::value($s,['StudentName','Name']),'admissionNo'=>LegacyMapper::value($s,['AdmissionNo','AdmissionNumber']),
          'rollNumber'=>$enrol['rollNumber']??null,'classId'=>(int)$ctx['ClassID'],'className'=>$enrol['className']??null,'sectionId'=>(int)$ctx['SectionID'],'sectionName'=>$enrol['sectionName']??null,
          'fatherName'=>LegacyMapper::value($s,['FatherName']),'motherName'=>LegacyMapper::value($s,['MotherName']),
          'fatherMobile'=>LegacyMapper::value($s,['FatherMobile','FatherMobileNo','MobileNo']),'motherMobile'=>LegacyMapper::value($s,['MotherMobile','MotherMobileNo']),
          'dateOfBirth'=>LegacyMapper::date(LegacyMapper::value($s,['DOB','DateOfBirth','BirthDate'])),'address'=>LegacyMapper::value($s,['Address','PermanentAddress']),
          'city'=>LegacyMapper::value($s,['CityName','City']),'category'=>LegacyMapper::value($s,['CategoryName','Category'])
        ]]);
    }
    public function dashboard(Request $r,array $p,int $student):never{
        ParentService::student($p,$student);$db=Database::connection();$session=SchoolService::currentSession();$today=(new \DateTimeImmutable())->format('Y-m-d');
        $att=$db->prepare("SELECT TOP 1 COALESCE(AttType,'P') FROM AttItem WHERE StudentID=? AND CAST(Dated AS date)=?");$att->execute([$student,$today]);$status=$att->fetchColumn();
        $hw=$db->prepare('SELECT TOP 5 AppHomeworkID id,Title title,HomeworkDate homeworkDate,DueDate dueDate FROM AppHomework h JOIN StudentSession ss ON ss.ClassID=h.ClassID AND (h.SectionID IS NULL OR h.SectionID=ss.SectionID) WHERE ss.StudentID=? AND ss.OwnerSessionID=h.OwnerSessionID AND h.IsActive=1 ORDER BY h.HomeworkDate DESC,h.AppHomeworkID DESC');$hw->execute([$student]);
        $notice=$db->prepare('SELECT TOP 1 n.AppNoticeID id,n.Title title,n.PublishedAt publishedAt FROM AppNotice n JOIN StudentSession ss ON ss.StudentID=? AND ss.OwnerSessionID=? WHERE n.IsActive=1 AND (n.OwnerSessionID IS NULL OR n.OwnerSessionID=ss.OwnerSessionID) AND (n.ClassID IS NULL OR n.ClassID=ss.ClassID) AND (n.SectionID IS NULL OR n.SectionID=ss.SectionID) AND (n.StudentID IS NULL OR n.StudentID=ss.StudentID) ORDER BY n.PublishedAt DESC');$notice->execute([$student,$session['id']]);
        $unread=$db->prepare('SELECT COUNT(*) FROM AppNotification n WHERE (n.AppParentID=? OR n.StudentID=? OR (n.AppParentID IS NULL AND n.StudentID IS NULL)) AND NOT EXISTS(SELECT 1 FROM AppNotificationRead r WHERE r.AppNotificationID=n.AppNotificationID AND r.AppParentID=?)');$unread->execute([$p['AppParentID'],$student,$p['AppParentID']]);
        Response::success(['studentId'=>$student,'academicSession'=>$session,'today'=>['date'=>$today,'attendanceStatus'=>$status?:null],'homework'=>$hw->fetchAll(),'recentNotice'=>$notice->fetch()?:null,'unreadNotifications'=>(int)$unread->fetchColumn()]);
    }
}
