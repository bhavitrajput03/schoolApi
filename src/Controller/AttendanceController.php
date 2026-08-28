<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response,Validator};
use App\Service\SchoolService;
final class AttendanceController {
    public function students(Request $r,array $u):never{
        $class=Validator::id($r->query('classId'),'classId');$section=Validator::id($r->query('sectionId'),'sectionId');$date=Validator::date((string)$r->query('date'));SchoolService::assertAssignment($u['ApiUserID'],$class,$section);$session=SchoolService::currentSessionId();
        $sql="SELECT s.StudentID id,s.StudentName name,s.AdmissionNo admissionNo,ss.RollNo rollNo,s.FatherName fatherName,COALESCE(a.AttType,'P') status
              FROM StudentSession ss JOIN Student s ON s.StudentID=ss.StudentID LEFT JOIN AttItem a ON a.StudentID=s.StudentID AND CAST(a.Dated AS date)=?
              WHERE ss.OwnerSessionID=? AND ss.ClassID=? AND ss.SectionID=? AND ss.IsLeave=0
              ORDER BY CASE WHEN ss.RollNo<>'' AND LEN(ss.RollNo)<=9 AND ss.RollNo NOT LIKE '%[^0-9]%'
                            THEN CONVERT(int,ss.RollNo) ELSE 2147483647 END,ss.RollNo,s.StudentName";
        $st=Database::connection()->prepare($sql);$st->execute([$date,$session,$class,$section]);Response::success(['date'=>$date,'students'=>$st->fetchAll()]);
    }
    public function save(Request $r,array $u):never{
        $d=$r->json();Validator::required($d,['classId','sectionId','date','attendance']);$class=Validator::id($d['classId'],'classId');$section=Validator::id($d['sectionId'],'sectionId');$date=Validator::date((string)$d['date']);SchoolService::assertAssignment($u['ApiUserID'],$class,$section);
        if(!is_array($d['attendance'])||!$d['attendance'])Response::error('attendance must be a non-empty array.',422,'VALIDATION_ERROR');$session=SchoolService::currentSessionId();$db=Database::connection();$db->beginTransaction();$saved=0;
        try{foreach($d['attendance'] as $a){Validator::required($a,['studentId','status']);$student=Validator::id($a['studentId'],'studentId');$status=(string)$a['status'];if(!in_array($status,['P','A','H/2','Holiday'],true))Response::error('status must be P, A, H/2 or Holiday.',422,'VALIDATION_ERROR');
            $st=$db->prepare('SELECT COUNT(*) FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND ClassID=? AND SectionID=? AND IsLeave=0');$st->execute([$student,$session,$class,$section]);if(!(int)$st->fetchColumn())Response::error('Student is outside selected class/section.',422,'INVALID_STUDENT');
            $value=['P'=>1,'A'=>0,'H/2'=>.5,'Holiday'=>1][$status];$up=$db->prepare('UPDATE AttItem SET AttType=?,AttValue=? WHERE StudentID=? AND CAST(Dated AS date)=?');$up->execute([$status,$value,$student,$date]);if($up->rowCount()===0)$db->prepare('INSERT INTO AttItem(Dated,StudentID,AttType,AttValue) VALUES(?,?,?,?)')->execute([$date,$student,$status,$value]);$saved++;
        }$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}Response::success(['saved'=>$saved,'date'=>$date]);
    }
}
